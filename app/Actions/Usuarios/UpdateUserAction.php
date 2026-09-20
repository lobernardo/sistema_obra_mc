<?php

namespace App\Actions\Usuarios;

use App\Actions\Usuarios\Concerns\GuardsGestaoLockout;
use App\Actions\Usuarios\Concerns\GuardsUserAdministration;
use App\Enums\RoleSlug;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Updates nome, e-mail, papel and obra associations of a user (RF-08,
 * RF-09). A papel change is subject to the RF-30 lockout guards. Leaving
 * the `obra` papel detaches every `obra_profile` row (Q-10.2); staying
 * `obra` requires ≥ 1 obra (Q-10.1). An e-mail change only updates the
 * column — no invite is sent and no `password_reset_tokens` row of the old
 * address is deleted (Q-10.3).
 */
class UpdateUserAction
{
    use GuardsGestaoLockout, GuardsUserAdministration;

    /**
     * @param  array{name?: mixed, email?: mixed, role_id?: mixed, obra_ids?: mixed}  $data
     */
    public function execute(User $actor, User $target, array $data): User
    {
        $this->ensureActorManagesUsers($actor);

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

        $newRoleIsObra = Role::query()->whereKey($newRoleId)->value('slug') === RoleSlug::Obra->value;
        $obraIds = $validated['obra_ids'] ?? [];

        return DB::transaction(function () use ($target, $validated, $newRoleId, $newRoleIsObra, $obraIds): User {
            $target->update([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'role_id' => $newRoleId,
            ]);

            if ($newRoleIsObra) {
                $target->obras()->sync($obraIds);
            } else {
                $target->obras()->detach();
            }

            return $target->fresh();
        });
    }
}
