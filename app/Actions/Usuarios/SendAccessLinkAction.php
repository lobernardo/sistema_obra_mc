<?php

namespace App\Actions\Usuarios;

use App\Actions\Usuarios\Concerns\GuardsUserAdministration;
use App\Enums\UserAdminAction;
use App\Models\User;
use App\Notifications\FirstAccessInvite;
use App\Services\UserAdminAuditRecorder;
use Illuminate\Support\Facades\Password;

/**
 * Issues (or re-issues) the first-access link for a user on behalf of a
 * `manage-users` actor (RF-14, RF-15). The `passwords.invites` broker
 * creates a hashed, 72 h token and the `FirstAccessInvite` e-mail goes
 * synchronously to the target's own address (RNF-08). The broker status is
 * returned untouched so the caller decides how to surface `throttled`
 * (Q-05). Nothing here reads, resets or displays `users.password` (RF-18,
 * RF-25).
 *
 * Audit (RF-19, RF-20, D-03): when — and only when — the broker returns
 * `RESET_LINK_SENT`, an `access_link_sent` (`resend: false`, the
 * `CreateUserAction` path) or `access_link_resent` (`resend: true`, the
 * Gestão listing path) record is appended with `before`/`after` = null.
 * The slug is selected exclusively by the explicit `$resend` argument —
 * never inferred from the caller, token state or invite age. Because the
 * e-mail dispatch cannot be rolled back, the record is written outside
 * any transaction and an insert failure propagates to the caller.
 */
class SendAccessLinkAction
{
    use GuardsUserAdministration;

    public function __construct(private readonly UserAdminAuditRecorder $recorder) {}

    /**
     * @return string one of the `Password::*` status constants
     */
    public function execute(User $actor, User $target, bool $resend = false): string
    {
        $this->ensureActorManagesUsers($actor);

        $status = Password::broker('invites')->sendResetLink(
            ['email' => $target->email],
            function (User $user, string $token): void {
                $user->notify(new FirstAccessInvite($token));
            },
        );

        if ($status === Password::RESET_LINK_SENT) {
            $this->recorder->record(
                $actor,
                $target,
                $resend ? UserAdminAction::AccessLinkResent : UserAdminAction::AccessLinkSent,
                null,
                null,
            );
        }

        return $status;
    }
}
