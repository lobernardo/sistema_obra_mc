<?php

namespace App\Policies;

use App\Models\ObraAdminEvent;
use App\Models\User;

class ObraAdminEventPolicy
{
    /**
     * ObraAdminEvent is an append-only audit trail (CT-07): no user, of any role, is
     * ever authorized to update or delete a recorded event.
     */
    public function update(User $user, ObraAdminEvent $obraAdminEvent): bool
    {
        return false;
    }

    public function delete(User $user, ObraAdminEvent $obraAdminEvent): bool
    {
        return false;
    }
}
