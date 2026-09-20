<?php

namespace App\Actions\Usuarios\Concerns;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

/**
 * Shared actor guard for the users-administration Actions (RF-05): only an
 * actor granted the `manage-users` ability may create, edit, activate or
 * deactivate users. The gate — not this trait — names the papel (RNF-11).
 */
trait GuardsUserAdministration
{
    /**
     * @throws AuthorizationException
     */
    private function ensureActorManagesUsers(User $actor): void
    {
        if (Gate::forUser($actor)->denies('manage-users')) {
            throw new AuthorizationException('Apenas o perfil "gestao" pode administrar usuários.');
        }
    }
}
