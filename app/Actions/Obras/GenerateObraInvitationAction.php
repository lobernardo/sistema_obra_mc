<?php

namespace App\Actions\Obras;

use App\Actions\Obras\Concerns\GuardsObraAdministration;
use App\Enums\ObraAdminAction;
use App\Models\Obra;
use App\Models\ObraInvitation;
use App\Models\User;
use App\Services\ObraAdminAuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Generates one convite for an obra that is not Concluído (RF-23, RF-24,
 * RF-33). The token is 256 bits from the CSPRNG as 64 lowercase hex
 * characters; only its SHA-256 digest is persisted (RNF-01). The row and
 * its `invitation_created` audit are written in one transaction (RF-34),
 * with `expires_at` = `created_at` + 24 h taken from the same clock read.
 *
 * The plaintext token exists only in the returned URL, and only in its
 * fragment — `<APP_URL>/convite#<token>` — so it never reaches a request
 * path, query string or access log (RF-38). It is never written to a
 * column, log, audit row, exception message/context or `report()`.
 */
class GenerateObraInvitationAction
{
    use GuardsObraAdministration;

    public const VALIDITY_HOURS = 24;

    public function __construct(private readonly ObraAdminAuditRecorder $recorder) {}

    /**
     * @return array{invitation: ObraInvitation, url: string}
     *
     * @throws ValidationException
     */
    public function execute(User $actor, Obra $obra): array
    {
        $this->ensureActorManagesObras($actor);

        if (! $obra->status->isActive()) {
            throw ValidationException::withMessages([
                'obra' => 'Não é possível gerar convite para uma obra concluída.',
            ]);
        }

        $token = bin2hex(random_bytes(32));

        $invitation = DB::transaction(function () use ($actor, $obra, $token): ObraInvitation {
            $now = now();

            $invitation = new ObraInvitation([
                'obra_id' => $obra->getKey(),
                'token_hash' => ObraInvitation::hashToken($token),
                'created_by' => $actor->getKey(),
                'expires_at' => $now->copy()->addHours(self::VALIDITY_HOURS),
            ]);
            $invitation->created_at = $now;
            $invitation->save();

            $this->recorder->record($actor, $obra, ObraAdminAction::InvitationCreated, null, null, $invitation);

            return $invitation;
        });

        return [
            'invitation' => $invitation,
            'url' => url('/convite').'#'.$token,
        ];
    }
}
