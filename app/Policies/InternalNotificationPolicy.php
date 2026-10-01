<?php

namespace App\Policies;

use App\Models\InternalNotification;
use App\Models\User;

class InternalNotificationPolicy
{
    /**
     * Only the recipient may mark a notification as read (RF-18, RF-20).
     */
    public function update(User $user, InternalNotification $internalNotification): bool
    {
        return $internalNotification->recipient_id === $user->id;
    }

    /**
     * Notifications are append-only (RF-22): no user ever deletes one.
     */
    public function delete(User $user, InternalNotification $internalNotification): bool
    {
        return false;
    }
}
