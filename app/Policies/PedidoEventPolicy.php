<?php

namespace App\Policies;

use App\Models\PedidoEvent;
use App\Models\User;

class PedidoEventPolicy
{
    /**
     * PedidoEvent is an append-only audit trail: no user, of any role, is ever
     * authorized to update or delete a recorded event.
     */
    public function update(User $user, PedidoEvent $pedidoEvent): bool
    {
        return false;
    }

    public function delete(User $user, PedidoEvent $pedidoEvent): bool
    {
        return false;
    }
}
