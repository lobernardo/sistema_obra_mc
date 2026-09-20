<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Users administration (RF-03..RF-11). Every ability resolves through the
 * `manage-users` gate — only the gate names the papel (RNF-11) — and
 * `changeRole`/`deactivate` additionally refuse the actor's own account
 * (RF-30 self-guard, first line of defense; the Actions re-check it).
 */
class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $this->managesUsers($actor);
    }

    public function create(User $actor): bool
    {
        return $this->managesUsers($actor);
    }

    public function update(User $actor, User $target): bool
    {
        return $this->managesUsers($actor);
    }

    public function changeRole(User $actor, User $target): bool
    {
        return $this->managesUsers($actor) && ! $actor->is($target);
    }

    public function activate(User $actor, User $target): bool
    {
        return $this->managesUsers($actor);
    }

    public function deactivate(User $actor, User $target): bool
    {
        return $this->managesUsers($actor) && ! $actor->is($target);
    }

    public function sendAccessLink(User $actor, User $target): bool
    {
        return $this->managesUsers($actor);
    }

    private function managesUsers(User $actor): bool
    {
        return Gate::forUser($actor)->allows('manage-users');
    }
}
