<?php

namespace App\Services;

use App\Support\EmailNormalizer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Thin wrapper around the framework `RateLimiter` for the four guest flows
 * that accept untrusted input: login, "Esqueci minha senha", account
 * creation (Novo Cadastro and the convite new-account path, sharing the
 * `register`/`register-ip` counters — RF-19) and the convite token lookup
 * (`invite-ip`, RF-19b). The lookup is guarded on the Livewire lookup call
 * (the POST that carries the token), never on the convite GET, whose URL
 * carries no token (NC-08); its key is the IP only — never the token nor
 * its hash (RF-38).
 *
 * The thresholds live only in the named limiters declared in
 * `AppServiceProvider::boot()` (RF-09, RF-11, D-02); this class resolves
 * them at call time so no number is duplicated here. Keys carry the
 * sha256 of the normalized e-mail and the client IP — never the e-mail
 * in clear text and never a password (RNF-09). Counters are stored in the
 * default cache store (`CACHE_STORE`), so they are shared across
 * FrankenPHP threads and survive a restart (RF-13).
 */
final class AuthenticationRateLimiter
{
    public const LOGIN_LIMITER = 'login';

    public const LOGIN_ACCOUNT_LIMITER = 'login-account';

    public const RECOVERY_LIMITER = 'recovery';

    public const RECOVERY_IP_LIMITER = 'recovery-ip';

    public const REGISTER_LIMITER = 'register';

    public const REGISTER_IP_LIMITER = 'register-ip';

    public const INVITE_IP_LIMITER = 'invite-ip';

    /**
     * Canonical form of a submitted e-mail (RF-12): trimmed and
     * lower-cased, so case or whitespace variants share one counter.
     *
     * The public signature is kept because `LoginForm`, `ForgotPassword`
     * and `AuthenticationEventRecorder` call it; the rule itself lives only
     * in `EmailNormalizer` (RF-01), so the limiter keys and every write
     * path share one definition.
     */
    public static function normalizeEmail(string $email): string
    {
        return EmailNormalizer::normalize($email);
    }

    /**
     * Either login limiter (e-mail + IP, or e-mail only) has reached its
     * ceiling for this account.
     */
    public function tooManyLoginAttempts(string $email, string $ip): bool
    {
        return RateLimiter::tooManyAttempts($this->loginKey($email, $ip), $this->limit(self::LOGIN_LIMITER)->maxAttempts)
            || RateLimiter::tooManyAttempts($this->loginAccountKey($email), $this->limit(self::LOGIN_ACCOUNT_LIMITER)->maxAttempts);
    }

    /**
     * Records one failed login against both keys, each with its own decay.
     */
    public function hitLogin(string $email, string $ip): void
    {
        RateLimiter::hit($this->loginKey($email, $ip), $this->limit(self::LOGIN_LIMITER)->decaySeconds);
        RateLimiter::hit($this->loginAccountKey($email), $this->limit(self::LOGIN_ACCOUNT_LIMITER)->decaySeconds);
    }

    /**
     * Resets both login counters after a successful authentication (RF-10).
     */
    public function clearLogin(string $email, string $ip): void
    {
        RateLimiter::clear($this->loginKey($email, $ip));
        RateLimiter::clear($this->loginAccountKey($email));
    }

    /**
     * Either recovery limiter (e-mail + IP, or IP only) has reached its
     * ceiling.
     */
    public function tooManyRecoveryAttempts(string $email, string $ip): bool
    {
        return RateLimiter::tooManyAttempts($this->recoveryKey($email, $ip), $this->limit(self::RECOVERY_LIMITER)->maxAttempts)
            || RateLimiter::tooManyAttempts($this->recoveryIpKey($ip), $this->limit(self::RECOVERY_IP_LIMITER)->maxAttempts);
    }

    /**
     * Records one recovery submission against both keys (every submission
     * counts, accepted or refused — RF-11).
     */
    public function hitRecovery(string $email, string $ip): void
    {
        RateLimiter::hit($this->recoveryKey($email, $ip), $this->limit(self::RECOVERY_LIMITER)->decaySeconds);
        RateLimiter::hit($this->recoveryIpKey($ip), $this->limit(self::RECOVERY_IP_LIMITER)->decaySeconds);
    }

    /**
     * Either account-creation limiter (e-mail + IP, or IP only) has reached
     * its ceiling (RF-19).
     */
    public function tooManyRegistrationAttempts(string $email, string $ip): bool
    {
        return RateLimiter::tooManyAttempts($this->registerKey($email, $ip), $this->limit(self::REGISTER_LIMITER)->maxAttempts)
            || RateLimiter::tooManyAttempts($this->registerIpKey($ip), $this->limit(self::REGISTER_IP_LIMITER)->maxAttempts);
    }

    /**
     * Records one account-creation submission against both keys; every
     * submission counts, including duplicates and invalid ones (RF-20).
     */
    public function hitRegistration(string $email, string $ip): void
    {
        RateLimiter::hit($this->registerKey($email, $ip), $this->limit(self::REGISTER_LIMITER)->decaySeconds);
        RateLimiter::hit($this->registerIpKey($ip), $this->limit(self::REGISTER_IP_LIMITER)->decaySeconds);
    }

    /**
     * The convite lookup limiter has reached its ceiling for this IP
     * (RF-19b); checked before the token is hashed or looked up.
     */
    public function tooManyInviteLookups(string $ip): bool
    {
        return RateLimiter::tooManyAttempts($this->inviteIpKey($ip), $this->limit(self::INVITE_IP_LIMITER)->maxAttempts);
    }

    /**
     * Records one convite lookup for this IP (RF-19b).
     */
    public function hitInviteLookup(string $ip): void
    {
        RateLimiter::hit($this->inviteIpKey($ip), $this->limit(self::INVITE_IP_LIMITER)->decaySeconds);
    }

    public function loginKey(string $email, string $ip): string
    {
        return self::LOGIN_LIMITER.':'.$this->hashEmail($email).':'.$ip;
    }

    public function loginAccountKey(string $email): string
    {
        return self::LOGIN_ACCOUNT_LIMITER.':'.$this->hashEmail($email);
    }

    public function recoveryKey(string $email, string $ip): string
    {
        return self::RECOVERY_LIMITER.':'.$this->hashEmail($email).':'.$ip;
    }

    public function recoveryIpKey(string $ip): string
    {
        return self::RECOVERY_IP_LIMITER.':'.$ip;
    }

    public function registerKey(string $email, string $ip): string
    {
        return self::REGISTER_LIMITER.':'.$this->hashEmail($email).':'.$ip;
    }

    public function registerIpKey(string $ip): string
    {
        return self::REGISTER_IP_LIMITER.':'.$ip;
    }

    public function inviteIpKey(string $ip): string
    {
        return self::INVITE_IP_LIMITER.':'.$ip;
    }

    /**
     * Resolves the `Limit` declared for a named limiter in
     * `AppServiceProvider`; the closures there ignore the request argument.
     */
    private function limit(string $name): Limit
    {
        $limit = RateLimiter::limiter($name)(null);

        if (! $limit instanceof Limit) {
            throw new \LogicException("Named limiter [{$name}] must resolve to a single Limit.");
        }

        return $limit;
    }

    private function hashEmail(string $email): string
    {
        return hash('sha256', self::normalizeEmail($email));
    }
}
