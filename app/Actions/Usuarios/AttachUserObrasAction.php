<?php

namespace App\Actions\Usuarios;

use App\Actions\Obras\Concerns\GuardsObraAdministration;
use App\Actions\Usuarios\Concerns\GuardsObraAssociationTarget;
use App\Enums\UserAdminAction;
use App\Models\Obra;
use App\Models\User;
use App\Services\UserAdminAuditRecorder;
use App\Support\ObraGoneViolation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Adds one or several obras to a user in a single operation (RF-09, CT-06).
 *
 * Only a `manage-obras` actor (Gestão or Suprimentos) may call it — not
 * `manage-users` (RF-07) — and only an `obra` or `suprimentos` target may
 * hold associations (RF-11, NC-03). A duplicate inside the input or an obra
 * already associated rejects the whole operation with a PT-BR 422 naming
 * the obra (RF-10). Only active obras (`Obra::isActive()`, whatever their
 * Status) accept new associations: an inactive one rejects the whole
 * operation with a 422 naming it (`obras-ativacao-exclusao` RF-12, which
 * narrows NC-07). Existing associations to an inactive obra are kept and
 * stay removable through `DetachUserObraAction`.
 *
 * Each id is written with `attach()` — deliberately never
 * `syncWithoutDetaching()` — so a concurrent duplicate reaches the composite
 * primary key `(obra_id, user_id)`. That violation surfaces as
 * `UniqueConstraintViolationException`, caught **outside** the transaction
 * closure (PostgreSQL has already aborted it) and rethrown as the same 422,
 * never an HTTP 500. An obra deleted concurrently surfaces as an FK
 * violation on `obra_profile_obra_id_foreign` (or a deadlock) and becomes
 * the 422 "A obra informada não foi encontrada." (RNF-01). The inserts and
 * the `obra_access_changed` audit (RF-12) commit or roll back together.
 *
 * Self-association is intended (RF-11 v1.3, F-13): a Suprimentos actor may
 * attach obras to its own account or to any other Suprimentos user. That is
 * exactly what grants Suprimentos creation eligibility in slice 2
 * (`solicitacao-historico-finalizacao` RF-02), so `actor == target` is never
 * blocked here.
 */
class AttachUserObrasAction
{
    use GuardsObraAdministration, GuardsObraAssociationTarget;

    public function __construct(private readonly UserAdminAuditRecorder $recorder) {}

    /**
     * @param  array{obra_ids?: mixed}  $data
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function execute(User $actor, User $target, array $data): User
    {
        $this->ensureActorManagesObras($actor);
        $this->ensureTargetAcceptsObras($target);

        $validated = Validator::make($data, [
            'obra_ids' => ['required', 'array', 'min:1'],
            'obra_ids.*' => ['integer', 'exists:obras,id'],
        ], self::messages())->validate();

        $obraIds = array_map('intval', array_values($validated['obra_ids']));

        $this->ensureNoDuplicateInInput($obraIds);
        $this->ensureNoneAlreadyAssociated($target, $obraIds);
        self::ensureObrasAcceptNewAssociations($obraIds);

        try {
            DB::transaction(function () use ($actor, $target, $obraIds): void {
                $before = $this->recorder->snapshot($target)['obra_ids'];

                foreach ($obraIds as $obraId) {
                    $target->obras()->attach($obraId);
                }

                $after = $this->recorder->snapshot($target)['obra_ids'];

                $this->recorder->record(
                    $actor,
                    $target,
                    UserAdminAction::ObraAccessChanged,
                    ['obra_ids' => $before],
                    ['obra_ids' => $after],
                );
            });
        } catch (UniqueConstraintViolationException) {
            $this->ensureNoneAlreadyAssociated($target, $obraIds);

            throw ValidationException::withMessages([
                'obra_ids' => 'Uma das obras informadas já está associada a este usuário.',
            ]);
        } catch (QueryException $exception) {
            throw self::translateObraGone($exception);
        }

        return $target->fresh();
    }

    /**
     * Rejects, with a 422 on `obra_ids` naming it, the first inactive obra
     * (by name) among those about to be newly associated (RF-12). Shared
     * with `CreateUserAction` and `UpdateUserAction`.
     *
     * @param  list<int>  $obraIds
     *
     * @throws ValidationException
     */
    public static function ensureObrasAcceptNewAssociations(array $obraIds): void
    {
        if ($obraIds === []) {
            return;
        }

        $inactive = Obra::query()
            ->whereKey($obraIds)
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->first(fn (Obra $obra): bool => ! $obra->isActive());

        if ($inactive !== null) {
            throw ValidationException::withMessages([
                'obra_ids' => sprintf('A obra «%s» está inativa e não aceita novas associações.', $inactive->name),
            ]);
        }
    }

    /**
     * The 422 on `obra_ids` for an `obra_profile` write that raced an obra
     * deletion (RNF-01); any other database error is returned unchanged, to
     * be rethrown. Shared with `CreateUserAction` and `UpdateUserAction`.
     */
    public static function translateObraGone(QueryException $exception): Throwable
    {
        return ObraGoneViolation::matches($exception, ['obra_profile_obra_id_foreign'])
            ? ObraGoneViolation::exception('obra_ids')
            : $exception;
    }

    /**
     * @param  list<int>  $obraIds
     *
     * @throws ValidationException
     */
    private function ensureNoDuplicateInInput(array $obraIds): void
    {
        $counts = array_count_values($obraIds);
        $duplicated = array_key_first(array_filter($counts, fn (int $count): bool => $count > 1));

        if ($duplicated !== null) {
            throw ValidationException::withMessages([
                'obra_ids' => sprintf('A obra «%s» foi informada mais de uma vez.', Obra::query()->whereKey($duplicated)->value('name')),
            ]);
        }
    }

    /**
     * @param  list<int>  $obraIds
     *
     * @throws ValidationException
     */
    private function ensureNoneAlreadyAssociated(User $target, array $obraIds): void
    {
        $alreadyAssociated = $target->obras()
            ->whereIn('obras.id', $obraIds)
            ->orderBy('obras.name')
            ->value('obras.name');

        if ($alreadyAssociated !== null) {
            throw ValidationException::withMessages([
                'obra_ids' => sprintf('A obra «%s» já está associada a este usuário.', $alreadyAssociated),
            ]);
        }
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'obra_ids.required' => 'Selecione pelo menos uma obra.',
            'obra_ids.array' => 'Obras inválidas.',
            'obra_ids.min' => 'Selecione pelo menos uma obra.',
            'obra_ids.*.integer' => 'Obra inválida.',
            'obra_ids.*.exists' => 'Obra inválida.',
        ];
    }
}
