<?php

namespace App\Policies;

use App\Models\AuthenticationEvent;
use App\Models\User;

class AuthenticationEventPolicy
{
    /**
     * AuthenticationEvent is an append-only audit trail (RF-27): no user,
     * of any role, is ever authorized to update or delete a recorded event.
     */
    public function update(User $user, AuthenticationEvent $authenticationEvent): bool
    {
        return false;
    }

    public function delete(User $user, AuthenticationEvent $authenticationEvent): bool
    {
        return false;
    }
}
