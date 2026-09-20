<?php

namespace App\Actions\Usuarios;

use App\Actions\Usuarios\Concerns\GuardsUserAdministration;
use App\Models\User;
use App\Notifications\FirstAccessInvite;
use Illuminate\Support\Facades\Password;

/**
 * Issues (or re-issues) the first-access link for a user on behalf of a
 * `manage-users` actor (RF-14, RF-15). The `passwords.invites` broker
 * creates a hashed, 72 h token and the `FirstAccessInvite` e-mail goes
 * synchronously to the target's own address (RNF-08). The broker status is
 * returned untouched so the caller decides how to surface `throttled`
 * (Q-05). Nothing here reads, resets or displays `users.password` (RF-18,
 * RF-25).
 */
class SendAccessLinkAction
{
    use GuardsUserAdministration;

    /**
     * @return string one of the `Password::*` status constants
     */
    public function execute(User $actor, User $target): string
    {
        $this->ensureActorManagesUsers($actor);

        return Password::broker('invites')->sendResetLink(
            ['email' => $target->email],
            function (User $user, string $token): void {
                $user->notify(new FirstAccessInvite($token));
            },
        );
    }
}
