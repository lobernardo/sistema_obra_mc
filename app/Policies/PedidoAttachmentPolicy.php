<?php

namespace App\Policies;

use App\Models\PedidoAttachment;
use App\Models\User;

class PedidoAttachmentPolicy
{
    /**
     * PedidoAttachment is append-only (RF-19): no user, of any role, is ever
     * authorized to update or delete a stored attachment.
     */
    public function update(User $user, PedidoAttachment $pedidoAttachment): bool
    {
        return false;
    }

    public function delete(User $user, PedidoAttachment $pedidoAttachment): bool
    {
        return false;
    }
}
