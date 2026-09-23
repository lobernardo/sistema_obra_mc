<?php

namespace App\Actions\Obras;

use App\Actions\Obras\Concerns\GuardsObraAdministration;
use App\Enums\ObraAdminAction;
use App\Enums\ObraStatus;
use App\Models\Obra;
use App\Models\User;
use App\Services\ObraAdminAuditRecorder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Creates an obra on behalf of a `manage-obras` actor (RF-01, CT-01).
 *
 * `name` is trimmed and must be unique on `lower(btrim(name))`: the explicit
 * check rejects the common case with a PT-BR 422, and the functional index
 * `obras_name_normalized_unique` backstops concurrent creates. That race
 * surfaces as `UniqueConstraintViolationException`, caught **outside** the
 * transaction closure (PostgreSQL has already aborted it) and rethrown as
 * the same 422 — never an HTTP 500. `responsavel` is optional free text,
 * with an empty value stored as `null` (NC-01). `is_demo` is never taken
 * from input.
 *
 * Audit (CT-07 b): one `obra_created` record (`before = null`, `after` =
 * whitelisted snapshot) is written in the same transaction as the insert.
 * No Action deletes an obra (RF-06).
 */
class CreateObraAction
{
    use GuardsObraAdministration;

    public const DUPLICATE_NAME_MESSAGE = 'Já existe uma obra com este nome.';

    public function __construct(private readonly ObraAdminAuditRecorder $recorder) {}

    /**
     * @param  array{name?: mixed, responsavel?: mixed, status?: mixed}  $data
     *
     * @throws ValidationException
     */
    public function execute(User $actor, array $data): Obra
    {
        $this->ensureActorManagesObras($actor);

        $validated = self::validate($data);

        self::ensureNameIsUnique($validated['name']);

        try {
            return DB::transaction(function () use ($actor, $validated): Obra {
                $obra = Obra::query()->create([
                    'name' => $validated['name'],
                    'responsavel' => $validated['responsavel'],
                    'status' => $validated['status'],
                    'is_demo' => false,
                ]);

                $this->recorder->record($actor, $obra, ObraAdminAction::ObraCreated, null, $this->recorder->snapshot($obra));

                return $obra;
            });
        } catch (UniqueConstraintViolationException) {
            throw self::duplicateNameException();
        }
    }

    /**
     * Normalizes (trimmed `name`; trimmed `responsavel`, empty → `null`) and
     * validates the obra payload with PT-BR messages.
     *
     * @param  array{name?: mixed, responsavel?: mixed, status?: mixed}  $data
     * @return array{name: string, responsavel: string|null, status: ObraStatus}
     *
     * @throws ValidationException
     */
    public static function validate(array $data): array
    {
        $payload = [
            'name' => is_string($data['name'] ?? null) ? trim($data['name']) : ($data['name'] ?? null),
            'responsavel' => is_string($data['responsavel'] ?? null) ? trim($data['responsavel']) : ($data['responsavel'] ?? null),
            'status' => ($data['status'] ?? null) instanceof ObraStatus ? $data['status']->value : ($data['status'] ?? null),
        ];

        if ($payload['responsavel'] === '') {
            $payload['responsavel'] = null;
        }

        $validated = Validator::make($payload, [
            'name' => ['required', 'string', 'max:255'],
            'responsavel' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'string', Rule::enum(ObraStatus::class)],
        ], self::messages())->validate();

        return [
            'name' => $validated['name'],
            'responsavel' => $validated['responsavel'] ?? null,
            'status' => ObraStatus::from($validated['status']),
        ];
    }

    /**
     * Case- and whitespace-insensitive uniqueness on `lower(btrim(name))`,
     * the same expression as the backstop index (Q-03). On edit, the obra
     * itself is excluded.
     *
     * @throws ValidationException
     */
    public static function ensureNameIsUnique(string $name, ?Obra $ignore = null): void
    {
        $exists = Obra::query()
            ->whereRaw('lower(btrim(name)) = lower(btrim(?))', [$name])
            ->when($ignore !== null, fn ($query) => $query->whereKeyNot($ignore->getKey()))
            ->exists();

        if ($exists) {
            throw self::duplicateNameException();
        }
    }

    public static function duplicateNameException(): ValidationException
    {
        return ValidationException::withMessages(['name' => self::DUPLICATE_NAME_MESSAGE]);
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'name.required' => 'Informe o nome da obra.',
            'name.string' => 'Nome inválido.',
            'name.max' => 'O nome deve ter no máximo 255 caracteres.',
            'responsavel.string' => 'Responsável inválido.',
            'responsavel.max' => 'O responsável deve ter no máximo 255 caracteres.',
            'status.required' => 'Selecione o status da obra.',
            'status.string' => 'Status inválido.',
            'status.enum' => 'Status inválido.',
        ];
    }
}
