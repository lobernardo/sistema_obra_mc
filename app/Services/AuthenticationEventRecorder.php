<?php

namespace App\Services;

use App\Enums\AuthenticationEventType;
use App\Models\AuthenticationEvent;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Single writer of the authentication trail (`authentication_events`,
 * RF-26..RF-29, CT-03): one method per slug, so IP/user-agent sanitisation
 * and the e-mail normalisation live in exactly one place. No method ever
 * receives a password, hash, token, session id or cookie (RF-27); callers
 * hand over only the `User` (or the submitted e-mail for a refused login).
 *
 * Every write is wrapped in `rescue()` (RF-29): a failure to append the
 * record is reported through the exception handler and never changes the
 * outcome or the rendered response of the flow that triggered it.
 *
 * `ip` is `Request::ip()` behind `trustProxies(at: '*')` — derived from
 * `X-Forwarded-For` and client-influenceable (D-01); stored as-is, without
 * retention or purge (D-07).
 */
final class AuthenticationEventRecorder
{
    public const int USER_AGENT_MAX_LENGTH = 255;

    public function __construct(private readonly Request $request) {}

    /**
     * `LoginForm::authenticate` accepted (`Auth::attempt` true).
     */
    public function loginSucceeded(User $user): void
    {
        $this->write(AuthenticationEventType::LoginSuccess, $user, null);
    }

    /**
     * Any refused attempt — wrong password, unknown e-mail, inactive account
     * or a tripped limiter (D-08). `$user` is the row matching the
     * normalized e-mail when one exists (also for inactive accounts, which
     * the guard's `Failed` event would report as `null`), `null` otherwise.
     */
    public function loginFailed(string $email, ?User $user): void
    {
        $this->write(AuthenticationEventType::LoginFailed, $user, $email);
    }

    /**
     * Only the explicit `POST /logout` route (D-09).
     */
    public function loggedOut(User $user): void
    {
        $this->write(AuthenticationEventType::Logout, $user, null);
    }

    /**
     * Password redefined through the recovery broker (`passwords.users`).
     */
    public function passwordReset(User $user): void
    {
        $this->write(AuthenticationEventType::PasswordReset, $user, null);
    }

    /**
     * Password defined through the first-access broker (`passwords.invites`)
     * (D-04).
     */
    public function passwordDefined(User $user): void
    {
        $this->write(AuthenticationEventType::PasswordDefined, $user, null);
    }

    /**
     * A session cut by the system, not by the user: deactivation
     * (`EnsureUserIsActive`) or credential change (`AuthenticateSession`)
     * (D-09).
     */
    public function sessionRevoked(User $user): void
    {
        $this->write(AuthenticationEventType::SessionRevoked, $user, null);
    }

    /**
     * Appends one record, never throwing to the caller (RF-29). The e-mail
     * stored is the normalized submitted one or, when absent, the user's.
     */
    private function write(AuthenticationEventType $event, ?User $user, ?string $email): void
    {
        $normalizedEmail = $email !== null
            ? AuthenticationRateLimiter::normalizeEmail($email)
            : $user?->email;

        $attributes = [
            'event' => $event,
            'user_id' => $user?->getKey(),
            'email' => $normalizedEmail !== null ? mb_substr($normalizedEmail, 0, 255) : null,
            'ip' => $this->clientIp(),
            'user_agent' => $this->sanitizedUserAgent(),
        ];

        rescue(fn () => AuthenticationEvent::query()->create($attributes), report: true);
    }

    /**
     * `Request::ip()` as resolved behind `trustProxies(at: '*')`: derived
     * from `X-Forwarded-For`, so possibly client-influenced (D-01). Capped
     * at the 45-char IPv6 width of the column.
     */
    private function clientIp(): ?string
    {
        $ip = $this->request->ip();

        return $ip !== null ? mb_substr($ip, 0, 45) : null;
    }

    /**
     * User agent stripped of control characters and truncated to the
     * column width (CT-03).
     */
    private function sanitizedUserAgent(): ?string
    {
        $userAgent = (string) $this->request->userAgent();

        if ($userAgent === '') {
            return null;
        }

        $stripped = (string) preg_replace('/[\x00-\x1F\x7F]/u', '', $userAgent);

        return mb_substr($stripped, 0, self::USER_AGENT_MAX_LENGTH);
    }
}
