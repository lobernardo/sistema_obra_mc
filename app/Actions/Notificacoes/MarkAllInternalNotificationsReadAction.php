<?php

namespace App\Actions\Notificacoes;

use App\Models\InternalNotification;
use App\Models\User;

/**
 * "Marcar todas como lidas" (RF-19, CT-05): one UPDATE over every unread
 * notification of the actor and of no other user. Only `read_at` is
 * written; already read rows keep their first timestamp.
 */
class MarkAllInternalNotificationsReadAction
{
    /**
     * @return int the number of notifications marked as read
     */
    public function execute(User $actor): int
    {
        return InternalNotification::query()
            ->where('recipient_id', $actor->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }
}
