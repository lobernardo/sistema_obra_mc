<?php

namespace App\Actions\Obras\Concerns;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

/**
 * Shared actor guard for the obra, convite and association Actions (RF-07):
 * only an actor granted the `manage-obras` ability may write. The gate —
 * not this trait — names the papéis.
 */
trait GuardsObraAdministration
{
    /**
     * @throws AuthorizationException
     */
    private function ensureActorManagesObras(User $actor): void
    {
        if (Gate::forUser($actor)->denies('manage-obras')) {
            throw new AuthorizationException('Apenas os perfis Gestão e Suprimentos podem administrar obras.');
        }
    }
}
