<?php

namespace App\Actions\Obras;

use App\Actions\Obras\Concerns\GuardsObraAdministration;
use App\Enums\ObraAdminAction;
use App\Models\Obra;
use App\Models\ObraInvitation;
use App\Models\User;
use App\Services\ObraAdminAuditRecorder;
use App\Support\ObraGoneViolation;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Generates one convite for an active obra — `Obra::isActive()`, whatever
 * its Status (RF-23, RF-24, RF-33; `obras-ativacao-exclusao` RF-04, RF-11). The token is 256 bits from the CSPRNG as 64 lowercase hex
 * characters; only its SHA-256 digest is persisted (RNF-01). The row and
 * its `invitation_created` audit are written in one transaction (RF-34),
 * with `expires_at` = `created_at` + 24 h taken from the same clock read.
 *
 * The plaintext token exists only in the returned URL, and only in its
 * fragment — `<APP_URL>/convite#<token>` — so it never reaches a request
 * path, query string or access log (RF-38). It is never written to a
 * column, log, audit row, exception message/context or `report()`.
 *
 * An obra deleted concurrently, after the activity check, surfaces as an FK
 * violation on `obra_invitations_obra_id_foreign` /
 * `obra_admin_events_obra_id_foreign` (or a deadlock), caught outside the
 * transaction and turned into the 422 "A obra informada não foi
 * encontrada." on `obra` — a fixed message that carries no token
 * (`obras-ativacao-exclusao` RNF-01).
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

        if (! $obra->isActive()) {
            throw ValidationException::withMessages([
                'obra' => 'Não é possível gerar convite para uma obra inativa.',
            ]);
        }

        $token = bin2hex(random_bytes(32));

        try {
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
        } catch (QueryException $exception) {
            if (ObraGoneViolation::matches($exception, ['obra_invitations_obra_id_foreign', 'obra_admin_events_obra_id_foreign'])) {
                throw ObraGoneViolation::exception('obra');
            }

            throw $exception;
        }

        return [
            'invitation' => $invitation,
            'url' => url('/convite').'#'.$token,
        ];
    }
}
