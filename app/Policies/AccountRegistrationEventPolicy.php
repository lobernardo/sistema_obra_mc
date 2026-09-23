<?php

namespace App\Policies;

use App\Models\AccountRegistrationEvent;
use App\Models\User;

class AccountRegistrationEventPolicy
{
    /**
     * AccountRegistrationEvent is an append-only audit trail (CT-07): no user, of any role, is
     * ever authorized to update or delete a recorded event.
     */
    public function update(User $user, AccountRegistrationEvent $accountRegistrationEvent): bool
    {
        return false;
    }

    public function delete(User $user, AccountRegistrationEvent $accountRegistrationEvent): bool
    {
        return false;
    }
}
