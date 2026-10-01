<?php

namespace App\Actions\Notificacoes;

use App\Models\InternalNotification;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Marks one of the actor's own notifications as read (RF-18, RF-20, CT-05).
 *
 * The notification is looked up through `forRecipient`, so an id of another
 * user's notification — or of one whose pedido the actor can no longer view —
 * is a 404 and nothing changes. The UPDATE is conditional on `read_at` being
 * null, so marking again keeps the first timestamp. Only `read_at` is ever
 * written.
 *
 * Returns the notification with its `pedido` loaded, so opening a
 * notification (page and bell) reuses this same marking and re-authorization
 * path before navigating (UI-02).
 */
class MarkInternalNotificationReadAction
{
    public function execute(User $actor, int $notificationId): InternalNotification
    {
        $notification = InternalNotification::query()
            ->forRecipient($actor)
            ->findOrFail($notificationId);

        Gate::forUser($actor)->authorize('update', $notification);

        InternalNotification::query()
            ->whereKey($notification->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return $notification->refresh()->load('pedido');
    }
}
