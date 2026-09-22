<?php

namespace App\Actions\Usuarios;

use App\Actions\Usuarios\Concerns\GuardsGestaoLockout;
use App\Actions\Usuarios\Concerns\GuardsUserAdministration;
use App\Enums\UserAdminAction;
use App\Models\User;
use App\Services\UserAdminAuditRecorder;
use Illuminate\Support\Facades\DB;

/**
 * Activates or deactivates a user (RF-10, RF-11). Deactivation passes the
 * RF-30 lockout guards and only flips `users.is_active`: no `users`,
 * `pedidos`, `pedido_events`, `obra_profile` or `sessions` row is ever
 * deleted — the live session is cut by `EnsureUserIsActive` on the next
 * request (RF-31, Q-06). No `PedidoEvent` is written: history is
 * pedido-scoped.
 *
 * Audit (RF-19, RF-20): `user_activated` / `user_deactivated` with
 * `{is_active}` before/after, written inside the same transaction as the
 * flip so both commit or roll back together (RNF-10). A no-op (value
 * unchanged) emits no record.
 */
class SetUserActiveAction
{
    use GuardsGestaoLockout, GuardsUserAdministration;

    public function __construct(private readonly UserAdminAuditRecorder $recorder) {}

    public function execute(User $actor, User $target, bool $active): User
    {
        $this->ensureActorManagesUsers($actor);

        if (! $active) {
            $this->ensureNotSelf($actor, $target);
            $this->ensureAnotherActiveGestaoRemains($target);
        }

        return DB::transaction(function () use ($actor, $target, $active): User {
            $wasActive = (bool) $target->is_active;

            $target->update(['is_active' => $active]);

            if ($wasActive !== $active) {
                $this->recorder->record(
                    $actor,
                    $target,
                    $active ? UserAdminAction::UserActivated : UserAdminAction::UserDeactivated,
                    ['is_active' => $wasActive],
                    ['is_active' => $active],
                );
            }

            return $target->fresh();
        });
    }
}
