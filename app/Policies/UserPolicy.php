<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Users administration (RF-03..RF-11). Every ability resolves through the
 * `manage-users` gate — only the gate names the papel (RNF-11) — and
 * `changeRole`/`deactivate` additionally refuse the actor's own account
 * (RF-30 self-guard, first line of defense; the Actions re-check it).
 * `changeRole` denies the own account with the PT-BR message
 * SELF_ROLE_CHANGE_DENIED_MESSAGE, shown by the 403 page; `deactivate`
 * keeps the plain boolean denial.
 */
class UserPolicy
{
    /**
     * Denial message for a user trying to change the papel of their own account.
     */
    public const SELF_ROLE_CHANGE_DENIED_MESSAGE = 'Não é possível regredir próprio acesso. Solicite à gestão!';

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

    /**
     * The `manage-users` check runs first so obra/suprimentos never receive the
     * self-change message, not even on their own account.
     */
    public function changeRole(User $actor, User $target): Response
    {
        if (! $this->managesUsers($actor)) {
            return Response::deny();
        }

        if ($actor->is($target)) {
            return Response::deny(self::SELF_ROLE_CHANGE_DENIED_MESSAGE);
        }

        return Response::allow();
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
