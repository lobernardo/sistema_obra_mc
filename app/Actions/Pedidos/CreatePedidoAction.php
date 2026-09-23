<?php

namespace App\Actions\Pedidos;

use App\Enums\EventTypeSlug;
use App\Enums\PedidoAttachmentKind;
use App\Enums\RoleSlug;
use App\Enums\StatusSlug;
use App\Models\EventType;
use App\Models\Pedido;
use App\Models\PedidoAttachment;
use App\Models\Status;
use App\Models\User;
use App\Services\PedidoAttachmentStorage;
use App\Services\PedidoCodeGenerator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Creates a pedido on behalf of an `obra` or `suprimentos` requester
 * (RF-01, CT-01).
 *
 * Input keys (CT-01): `obra_selection` (an obra id or the literal `outra`),
 * `obra_reference` (only kept with `outra`), `descricao` and `needed_at`
 * (Preciso para), plus the optional `anexos` list of files. Every other key is ignored, so a forged `requested_at`,
 * `data_prevista`, `code`, `status_id` or `requester_id` has no effect
 * (RF-09): `requested_at` is the server clock and the `Pedido` creating hook
 * derives `data_prevista` from it.
 *
 * The actor guard, the validation and every obra check run before the
 * transaction and before `PedidoCodeGenerator::generate()` (`nextval` is
 * not rolled back), so a refusal never consumes a code (RF-03, RF-07):
 * - zero associated active obras → 422 on `obra_id`, also for `outra`;
 * - obra not associated (or nonexistent) → 422 on `obra_id`;
 * - associated obra Concluído (not `Obra::active()`) → 422 on `obra_id`.
 *
 * `outra` creates the pedido without obra and with the trimmed reference
 * (null when blank); a real obra always drops the reference (RF-04, RF-06).
 * It never creates an obra nor an `obra_profile` row (RF-05).
 *
 * Every file in `anexos` (at most 10) is inspected by
 * `PedidoAttachmentStorage` after the obra checks and still before the
 * transaction: one invalid file refuses the whole submission, naming it on
 * `anexos.<i>` (RF-14, RF-15). This is the only path that writes
 * `PedidoAttachmentKind::Anexo` rows: general attachments exist only at
 * creation.
 *
 * The pedido, its files, their `anexo` rows and its `criacao_pedido` event,
 * whose `new_value` is the `obraLabel()` snapshot at creation (RF-08,
 * CT-07), are written in a single transaction. When anything fails, the
 * files already written are removed (best effort), so no committed row
 * lacks its file and no stored file outlives a rolled-back row (RNF-02).
 */
class CreatePedidoAction
{
    public const string OUTRA_SELECTION = 'outra';

    public function __construct(
        private readonly PedidoCodeGenerator $codeGenerator,
        private readonly PedidoAttachmentStorage $attachmentStorage,
    ) {}

    /**
     * Papel-aware message for a requester without any associated active
     * obra (RF-07, F-17): the single source of the Nova Solicitação empty
     * state and of the backend refusal.
     */
    public static function noActiveObraMessage(User $user): string
    {
        if ($user->role?->slug === RoleSlug::Suprimentos->value) {
            return 'Nenhuma obra ativa está associada ao seu usuário. Fale com a Gestão ou associe-se em Associações.';
        }

        return 'Nenhuma obra ativa está associada ao seu usuário. Fale com a Gestão ou com Suprimentos.';
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function execute(User $requester, array $data): Pedido
    {
        if (Gate::forUser($requester)->denies('create-pedido')) {
            throw new AuthorizationException('Apenas os perfis Obra e Suprimentos podem criar solicitações.');
        }

        $validated = $this->validate($data);

        if ($requester->obras()->active()->doesntExist()) {
            throw ValidationException::withMessages([
                'obra_id' => self::noActiveObraMessage($requester),
            ]);
        }

        $isOutra = $validated['obra_selection'] === self::OUTRA_SELECTION;
        $obraId = $isOutra ? null : (int) $validated['obra_selection'];

        if ($obraId !== null) {
            $this->ensureObraAcceptsSolicitacao($requester, $obraId);
        }

        $obraReference = $isOutra && $validated['obra_reference'] !== '' ? $validated['obra_reference'] : null;

        $anexos = [];

        foreach ($validated['anexos'] as $index => $file) {
            $anexos[] = [
                'file' => $file,
                ...$this->attachmentStorage->inspect($file, PedidoAttachmentKind::Anexo, "anexos.{$index}"),
            ];
        }

        $storedPaths = [];

        try {
            return DB::transaction(function () use ($requester, $validated, $obraId, $obraReference, $anexos, &$storedPaths) {
                $pedido = $this->insertPedido($requester, $validated, $obraId, $obraReference);

                foreach ($anexos as $anexo) {
                    $path = $this->attachmentStorage->store($pedido, $anexo['file'], $anexo['extension']);
                    $storedPaths[] = $path;

                    PedidoAttachment::query()->create([
                        'pedido_id' => $pedido->id,
                        'kind' => PedidoAttachmentKind::Anexo,
                        'path' => $path,
                        'original_name' => $anexo['display_name'],
                        'mime_type' => $anexo['mime'],
                        'size_bytes' => $anexo['size'],
                        'uploaded_by' => $requester->id,
                    ]);
                }

                $pedido->events()->create([
                    'event_type_id' => EventType::query()->where('slug', EventTypeSlug::CriacaoPedido->value)->value('id'),
                    'new_value' => $pedido->obraLabel(),
                    'actor_id' => $requester->id,
                ]);

                return $pedido;
            });
        } catch (Throwable $exception) {
            $this->attachmentStorage->deleteQuietly($storedPaths);

            throw $exception;
        }
    }

    /**
     * @param  array{descricao: string, needed_at: string}  $validated
     */
    private function insertPedido(User $requester, array $validated, ?int $obraId, ?string $obraReference): Pedido
    {
        $status = Status::query()
            ->whereIn('slug', array_map(fn (StatusSlug $slug): string => $slug->value, StatusSlug::activeNonFinal()))
            ->ordered()
            ->firstOrFail();

        return Pedido::query()->create([
            'code' => $this->codeGenerator->generate(),
            'obra_id' => $obraId,
            'obra_reference' => $obraReference,
            'requester_id' => $requester->id,
            'requested_at' => now(),
            'needed_at' => $validated['needed_at'],
            'items_description' => $validated['descricao'],
            'status_id' => $status->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{obra_selection: string, obra_reference: string, descricao: string, needed_at: string, anexos: list<UploadedFile>}
     *
     * @throws ValidationException
     */
    private function validate(array $data): array
    {
        $input = Arr::only($data, ['obra_selection', 'obra_reference', 'descricao', 'needed_at', 'anexos']);

        if (is_int($input['obra_selection'] ?? null)) {
            $input['obra_selection'] = (string) $input['obra_selection'];
        }

        foreach (['obra_selection', 'obra_reference', 'descricao'] as $key) {
            if (is_string($input[$key] ?? null)) {
                $input[$key] = trim($input[$key]);
            }
        }

        $validator = Validator::make($input, [
            'obra_selection' => ['required', 'string'],
            'obra_reference' => ['nullable', 'string', 'max:255'],
            'descricao' => ['required', 'string'],
            'needed_at' => ['required', 'date'],
            'anexos' => ['nullable', 'array', 'list', 'max:'.PedidoAttachmentStorage::MAX_ANEXOS_POR_PEDIDO],
            'anexos.*' => ['file'],
        ], [
            'obra_selection.required' => 'Selecione a obra.',
            'obra_selection.string' => 'Obra inválida.',
            'obra_reference.string' => 'Referência inválida.',
            'obra_reference.max' => 'A referência deve ter no máximo 255 caracteres.',
            'descricao.required' => 'Informe a descrição.',
            'descricao.string' => 'Informe a descrição.',
            'needed_at.required' => 'Informe a data em Preciso para.',
            'needed_at.date' => 'Informe uma data válida em Preciso para.',
            'anexos.array' => 'Anexos inválidos.',
            'anexos.list' => 'Anexos inválidos.',
            'anexos.max' => 'Envie no máximo '.PedidoAttachmentStorage::MAX_ANEXOS_POR_PEDIDO.' anexos.',
            'anexos.*.file' => 'Anexo inválido.',
        ]);

        $validator->after(function ($validator) use ($input): void {
            $selection = $input['obra_selection'] ?? null;

            if (is_string($selection) && $selection !== ''
                && $selection !== self::OUTRA_SELECTION
                && ! ctype_digit($selection)) {
                $validator->errors()->add('obra_selection', 'Obra inválida.');
            }
        });

        if ($validator->fails()) {
            $errors = [];

            foreach ($validator->errors()->messages() as $key => $messages) {
                $errors[$key === 'obra_selection' ? 'obra_id' : $key] = $messages;
            }

            throw ValidationException::withMessages($errors);
        }

        return [
            'obra_selection' => (string) $input['obra_selection'],
            'obra_reference' => (string) ($input['obra_reference'] ?? ''),
            'descricao' => (string) $input['descricao'],
            'needed_at' => (string) $input['needed_at'],
            'anexos' => array_values($input['anexos'] ?? []),
        ];
    }

    /**
     * @throws ValidationException
     */
    private function ensureObraAcceptsSolicitacao(User $requester, int $obraId): void
    {
        if (! $requester->obras()->whereKey($obraId)->exists()) {
            throw ValidationException::withMessages([
                'obra_id' => 'A obra informada não está associada ao solicitante.',
            ]);
        }

        if (! $requester->obras()->active()->whereKey($obraId)->exists()) {
            throw ValidationException::withMessages([
                'obra_id' => 'A obra informada está inativa e não recebe novas solicitações.',
            ]);
        }
    }
}
