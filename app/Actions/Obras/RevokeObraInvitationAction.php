<?php

namespace App\Actions\Obras;

use App\Actions\Obras\Concerns\GuardsObraAdministration;
use App\Enums\ObraAdminAction;
use App\Models\ObraInvitation;
use App\Models\User;
use App\Services\ObraAdminAuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Revokes a pending convite (RF-25). The revocation is a single conditional
 * UPDATE that only matches while `used_at` and `revoked_at` are null and
 * `expires_at` is in the future (CT-02, RF-32), so a concurrent consumption
 * or revocation can never be overwritten. Zero affected rows → PT-BR 422
 * and rollback; otherwise one `invitation_revoked` audit is written in the
 * same transaction (RF-34).
 */
class RevokeObraInvitationAction
{
    use GuardsObraAdministration;

    public function __construct(private readonly ObraAdminAuditRecorder $recorder) {}

    /**
     * @throws ValidationException
     */
    public function execute(User $actor, ObraInvitation $invitation): ObraInvitation
    {
        $this->ensureActorManagesObras($actor);

        $obra = $invitation->obra;

        DB::transaction(function () use ($actor, $invitation, $obra): void {
            $now = now();

            $affected = ObraInvitation::query()
                ->whereKey($invitation->getKey())
                ->whereNull('used_at')
                ->whereNull('revoked_at')
                ->where('expires_at', '>', $now)
                ->update([
                    'revoked_by' => $actor->getKey(),
                    'revoked_at' => $now,
                ]);

            if ($affected === 0) {
                throw ValidationException::withMessages([
                    'invitation' => 'Somente convites pendentes podem ser revogados.',
                ]);
            }

            $this->recorder->record($actor, $obra, ObraAdminAction::InvitationRevoked, null, null, $invitation);
        });

        return $invitation->refresh();
    }
}
