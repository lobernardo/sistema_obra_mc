<?php

namespace App\Actions\Usuarios;

use App\Actions\Usuarios\Concerns\GuardsGestaoLockout;
use App\Actions\Usuarios\Concerns\GuardsUserAdministration;
use App\Models\User;

/**
 * Activates or deactivates a user (RF-10, RF-11). Deactivation passes the
 * RF-30 lockout guards and only flips `users.is_active`: no `users`,
 * `pedidos`, `pedido_events`, `obra_profile` or `sessions` row is ever
 * deleted — the live session is cut by `EnsureUserIsActive` on the next
 * request (RF-31, Q-06). No `PedidoEvent` is written: history is
 * pedido-scoped.
 */
class SetUserActiveAction
{
    use GuardsGestaoLockout, GuardsUserAdministration;

    public function execute(User $actor, User $target, bool $active): User
    {
        $this->ensureActorManagesUsers($actor);

        if (! $active) {
            $this->ensureNotSelf($actor, $target);
            $this->ensureAnotherActiveGestaoRemains($target);
        }

        $target->update(['is_active' => $active]);

        return $target->fresh();
    }
}
