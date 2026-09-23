<?php

namespace App\Policies;

use App\Models\Obra;
use App\Models\ObraInvitation;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Convites de obra (RF-23, RF-25, RF-07): generating and revoking resolve
 * through the `manage-obras` gate, like the rest of the Obras area.
 */
class ObraInvitationPolicy
{
    public function create(User $actor, Obra $obra): bool
    {
        return $this->managesObras($actor);
    }

    public function revoke(User $actor, ObraInvitation $invitation): bool
    {
        return $this->managesObras($actor);
    }

    private function managesObras(User $actor): bool
    {
        return Gate::forUser($actor)->allows('manage-obras');
    }
}
