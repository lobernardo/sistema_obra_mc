<?php

namespace App\Policies;

use App\Models\Obra;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Obras area and user × obra associations (RF-07, CT-03). Every ability
 * resolves through the `manage-obras` gate — only the gate names the
 * papéis (gestao, suprimentos). `setActive` (Desativar/Reativar) and
 * `delete` (Excluir) follow the same gate (`obras-ativacao-exclusao`
 * RF-24, superseding RF-06): the policy only says who may ask; whether an
 * obra can actually be deleted is decided by `DeleteObraAction`.
 */
class ObraPolicy
{
    public function viewAny(User $actor): bool
    {
        return $this->managesObras($actor);
    }

    public function create(User $actor): bool
    {
        return $this->managesObras($actor);
    }

    public function update(User $actor, Obra $obra): bool
    {
        return $this->managesObras($actor);
    }

    public function manageAssociations(User $actor): bool
    {
        return $this->managesObras($actor);
    }

    public function setActive(User $actor, Obra $obra): bool
    {
        return $this->managesObras($actor);
    }

    public function delete(User $actor, Obra $obra): bool
    {
        return $this->managesObras($actor);
    }

    private function managesObras(User $actor): bool
    {
        return Gate::forUser($actor)->allows('manage-obras');
    }
}
