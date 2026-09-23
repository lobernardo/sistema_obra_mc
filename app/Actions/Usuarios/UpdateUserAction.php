<?php

namespace App\Actions\Usuarios;

use App\Actions\Usuarios\Concerns\GuardsGestaoLockout;
use App\Actions\Usuarios\Concerns\GuardsUserAdministration;
use App\Enums\UserAdminAction;
use App\Models\User;
use App\Services\UserAdminAuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Updates nome, e-mail, papel and obra associations of a user (RF-08,
 * RF-09). A papel change is subject to the RF-30 lockout guards. Moving to
 * `gestao` detaches every `obra_profile` row (RF-11b a); for `obra` and
 * `suprimentos` the associations are synced only when `obra_ids` is present
 * in the payload — an absent key keeps them intact, so a change between
 * `obra` and `suprimentos` preserves them (RF-11b b). Both papéis accept
 * 0..N obras (RF-11, RF-13b). An e-mail change only updates the
 * column — no invite is sent and no `password_reset_tokens` row of the old
 * address is deleted (Q-10.3).
 *
 * Audit (RF-19, RF-20): inside the same transaction as the update, one
 * record per changed aspect — `user_updated` (only the changed keys among
 * `name`/`email`), `role_changed` (`{role}` slug) and `obra_access_changed`
 * (`{obra_ids}` sorted, including the detach on moving to `gestao`). Identical
 * data emits no record. Mutation and audit commit or roll back together
 * (RNF-10).
 */
class UpdateUserAction
{
    use GuardsGestaoLockout, GuardsUserAdministration;

    public function __construct(private readonly UserAdminAuditRecorder $recorder) {}

    /**
     * @param  array{name?: mixed, email?: mixed, role_id?: mixed, obra_ids?: mixed}  $data
     */
    public function execute(User $actor, User $target, array $data): User
    {
        $this->ensureActorManagesUsers($actor);

        $data = CreateUserAction::withNormalizedEmail($data);

        $validated = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($target->getKey())],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            ...CreateUserAction::obraIdsRules($data['role_id'] ?? null),
        ], CreateUserAction::messages())->validate();

        $newRoleId = (int) $validated['role_id'];
        $roleChanges = $target->role_id !== $newRoleId;

        if ($roleChanges) {
            $this->ensureNotSelf($actor, $target);
            $this->ensureAnotherActiveGestaoRemains($target);
        }

        $newRoleAcceptsObras = CreateUserAction::roleAcceptsObras($newRoleId);
        $obraIds = $validated['obra_ids'] ?? null;

        return DB::transaction(function () use ($actor, $target, $validated, $newRoleId, $newRoleAcceptsObras, $obraIds): User {
            $before = $this->recorder->snapshot($target);

            $target->update([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'role_id' => $newRoleId,
            ]);

            if (! $newRoleAcceptsObras) {
                $target->obras()->detach();
            } elseif ($obraIds !== null) {
                $target->obras()->sync($obraIds);
            }

            $updated = $target->fresh();
            $after = $this->recorder->snapshot($updated);

            $this->recordChanges($actor, $updated, $before, $after);

            return $updated;
        });
    }

    /**
     * One audit record per changed aspect (RF-20); nothing when the
     * snapshots are identical.
     *
     * @param  array{name: string, email: string, role: string|null, is_active: bool, obra_ids: list<int>}  $before
     * @param  array{name: string, email: string, role: string|null, is_active: bool, obra_ids: list<int>}  $after
     */
    private function recordChanges(User $actor, User $target, array $before, array $after): void
    {
        $identityKeys = array_values(array_filter(
            ['name', 'email'],
            fn (string $key): bool => $before[$key] !== $after[$key],
        ));

        if ($identityKeys !== []) {
            $this->recorder->record(
                $actor,
                $target,
                UserAdminAction::UserUpdated,
                array_intersect_key($before, array_flip($identityKeys)),
                array_intersect_key($after, array_flip($identityKeys)),
            );
        }

        if ($before['role'] !== $after['role']) {
            $this->recorder->record(
                $actor,
                $target,
                UserAdminAction::RoleChanged,
                ['role' => $before['role']],
                ['role' => $after['role']],
            );
        }

        if ($before['obra_ids'] !== $after['obra_ids']) {
            $this->recorder->record(
                $actor,
                $target,
                UserAdminAction::ObraAccessChanged,
                ['obra_ids' => $before['obra_ids']],
                ['obra_ids' => $after['obra_ids']],
            );
        }
    }
}
