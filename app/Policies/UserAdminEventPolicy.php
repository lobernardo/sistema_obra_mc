<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserAdminEvent;

class UserAdminEventPolicy
{
    /**
     * UserAdminEvent is an append-only audit trail (RF-23): no user, of any
     * role, is ever authorized to update or delete a recorded event.
     */
    public function update(User $user, UserAdminEvent $userAdminEvent): bool
    {
        return false;
    }

    public function delete(User $user, UserAdminEvent $userAdminEvent): bool
    {
        return false;
    }
}
