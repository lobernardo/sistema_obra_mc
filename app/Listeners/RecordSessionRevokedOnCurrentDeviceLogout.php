<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\AuthenticationEventRecorder;
use Illuminate\Auth\Events\CurrentDeviceLogout;

/**
 * Records the `session_revoked` cut made by
 * `Illuminate\Session\Middleware\AuthenticateSession` when a session's
 * stored password hash no longer matches `users.password` after a reset or
 * first-access definition (RF-14, RF-26, D-09).
 *
 * `CurrentDeviceLogout` is dispatched only by
 * `SessionGuard::logoutCurrentDevice()`, which in this application is
 * called only by `AuthenticateSession::logout()`. The explicit sign-out
 * (`Auth::logout()`, used by `POST /logout` and by `EnsureUserIsActive`)
 * dispatches the generic `Logout` event instead, so this listener never
 * duplicates a `logout` or the deactivation `session_revoked`.
 *
 * Registered by event discovery of `app/Listeners` (no `Event::listen` in
 * `AppServiceProvider`, no `withEvents(false)` in `bootstrap/app.php`);
 * compatible with the `event:cache` step of the Railpack build.
 */
class RecordSessionRevokedOnCurrentDeviceLogout
{
    public function __construct(private readonly AuthenticationEventRecorder $recorder) {}

    /**
     * Handle the event.
     */
    public function handle(CurrentDeviceLogout $event): void
    {
        if ($event->user instanceof User) {
            $this->recorder->sessionRevoked($event->user);
        }
    }
}
