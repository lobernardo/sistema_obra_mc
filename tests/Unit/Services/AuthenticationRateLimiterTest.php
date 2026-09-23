<?php

use App\Services\AuthenticationRateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

const RATE_LIMIT_EMAIL = 'User@Example.com';

const RATE_LIMIT_IP = '203.0.113.10';

/**
 * Resolves a named limiter exactly as the service does, returning the
 * `Limit` declared in `AppServiceProvider`.
 */
function resolveNamedLimit(string $name): Limit
{
    $limit = RateLimiter::limiter($name)(null);

    expect($limit)->toBeInstanceOf(Limit::class);

    return $limit;
}

test('normalizeEmail maps case and whitespace variants to one canonical value (RF-12)', function () {
    $variants = ['User@Example.com', ' user@example.com ', 'user@example.com', "\tUSER@EXAMPLE.COM\n"];

    $normalized = array_map(fn (string $email): string => AuthenticationRateLimiter::normalizeEmail($email), $variants);

    expect(array_unique($normalized))->toBe(['user@example.com']);
});

test('the three e-mail variants build one and the same limiter key for every key family (RF-12)', function () {
    $limiter = new AuthenticationRateLimiter;
    $variants = ['User@Example.com', ' user@example.com ', 'user@example.com'];

    foreach (['loginKey', 'recoveryKey'] as $method) {
        $keys = array_map(fn (string $email): string => $limiter->{$method}($email, RATE_LIMIT_IP), $variants);

        expect(array_unique($keys))->toHaveCount(1, "{$method} must not depend on e-mail case or whitespace.");
    }

    $accountKeys = array_map(fn (string $email): string => $limiter->loginAccountKey($email), $variants);

    expect(array_unique($accountKeys))->toHaveCount(1);
});

test('the named limiters resolve to exactly 5/60, 20/900, 3/60 and 6/60 (RF-09, RF-11, D-02)', function () {
    expect(resolveNamedLimit('login'))->maxAttempts->toBe(5)->decaySeconds->toBe(60);
    expect(resolveNamedLimit('login-account'))->maxAttempts->toBe(20)->decaySeconds->toBe(900);
    expect(resolveNamedLimit('recovery'))->maxAttempts->toBe(3)->decaySeconds->toBe(60);
    expect(resolveNamedLimit('recovery-ip'))->maxAttempts->toBe(6)->decaySeconds->toBe(60);
});

test('the thresholds are literals in the provider, never read from env or config (D-02)', function () {
    $provider = file_get_contents(app_path('Providers/AppServiceProvider.php'));
    $service = file_get_contents(app_path('Services/AuthenticationRateLimiter.php'));

    expect(substr_count($provider, "RateLimiter::for('"))->toBe(7);

    foreach (['login', 'login-account', 'recovery', 'recovery-ip', 'register', 'register-ip', 'invite-ip'] as $name) {
        expect($provider)->toContain("RateLimiter::for('{$name}'");
    }

    expect($service)->not->toContain('env(')->not->toContain('config(');
    expect(preg_match('/configureRateLimiting\(\): void\s*\{(.*?)\n    \}/s', $provider, $match))->toBe(1);
    expect($match[1])->not->toContain('env(')->not->toContain('config(');
});

test('tooManyLoginAttempts trips after 5 hits and is released by clearLogin on both keys (RF-09, RF-10)', function () {
    $limiter = new AuthenticationRateLimiter;

    for ($attempt = 1; $attempt <= 4; $attempt++) {
        $limiter->hitLogin(RATE_LIMIT_EMAIL, RATE_LIMIT_IP);

        expect($limiter->tooManyLoginAttempts(RATE_LIMIT_EMAIL, RATE_LIMIT_IP))->toBeFalse("Attempt {$attempt} must still be allowed.");
    }

    $limiter->hitLogin(RATE_LIMIT_EMAIL, RATE_LIMIT_IP);

    expect($limiter->tooManyLoginAttempts(RATE_LIMIT_EMAIL, RATE_LIMIT_IP))->toBeTrue();
    expect(RateLimiter::attempts($limiter->loginKey(RATE_LIMIT_EMAIL, RATE_LIMIT_IP)))->toBe(5);
    expect(RateLimiter::attempts($limiter->loginAccountKey(RATE_LIMIT_EMAIL)))->toBe(5);

    $limiter->clearLogin(RATE_LIMIT_EMAIL, RATE_LIMIT_IP);

    expect($limiter->tooManyLoginAttempts(RATE_LIMIT_EMAIL, RATE_LIMIT_IP))->toBeFalse();
    expect(RateLimiter::attempts($limiter->loginKey(RATE_LIMIT_EMAIL, RATE_LIMIT_IP)))->toBe(0);
    expect(RateLimiter::attempts($limiter->loginAccountKey(RATE_LIMIT_EMAIL)))->toBe(0);
});

test('the e-mail-only login limiter trips at 20 hits spread over distinct IPs (RF-09, D-01)', function () {
    $limiter = new AuthenticationRateLimiter;

    for ($hit = 0; $hit < 20; $hit++) {
        $ip = '198.51.100.'.(1 + intdiv($hit, 4));

        expect($limiter->tooManyLoginAttempts(RATE_LIMIT_EMAIL, $ip))->toBeFalse();

        $limiter->hitLogin(RATE_LIMIT_EMAIL, $ip);
    }

    expect($limiter->tooManyLoginAttempts(RATE_LIMIT_EMAIL, '198.51.100.99'))->toBeTrue();
    expect(RateLimiter::attempts($limiter->loginAccountKey(RATE_LIMIT_EMAIL)))->toBe(20);
    expect(RateLimiter::attempts($limiter->loginKey(RATE_LIMIT_EMAIL, '198.51.100.1')))->toBe(4);
});

test('tooManyRecoveryAttempts trips at 3 hits per e-mail + IP and at 6 hits per IP (RF-11)', function () {
    $limiter = new AuthenticationRateLimiter;

    for ($hit = 0; $hit < 3; $hit++) {
        expect($limiter->tooManyRecoveryAttempts(RATE_LIMIT_EMAIL, RATE_LIMIT_IP))->toBeFalse();

        $limiter->hitRecovery(RATE_LIMIT_EMAIL, RATE_LIMIT_IP);
    }

    expect($limiter->tooManyRecoveryAttempts(RATE_LIMIT_EMAIL, RATE_LIMIT_IP))->toBeTrue();
    expect($limiter->tooManyRecoveryAttempts('outra@example.com', RATE_LIMIT_IP))->toBeFalse();

    for ($hit = 0; $hit < 3; $hit++) {
        $limiter->hitRecovery("desconhecida-{$hit}@example.com", RATE_LIMIT_IP);
    }

    expect(RateLimiter::attempts($limiter->recoveryIpKey(RATE_LIMIT_IP)))->toBe(6);
    expect($limiter->tooManyRecoveryAttempts('nunca-vista@example.com', RATE_LIMIT_IP))->toBeTrue();
    expect($limiter->tooManyRecoveryAttempts('nunca-vista@example.com', '203.0.113.11'))->toBeFalse();
});

test('counters live in the application cache store and vanish on Cache::flush (RF-13)', function () {
    $limiter = new AuthenticationRateLimiter;

    $limiter->hitLogin(RATE_LIMIT_EMAIL, RATE_LIMIT_IP);
    $limiter->hitRecovery(RATE_LIMIT_EMAIL, RATE_LIMIT_IP);

    expect(RateLimiter::attempts($limiter->loginKey(RATE_LIMIT_EMAIL, RATE_LIMIT_IP)))->toBe(1);
    expect(RateLimiter::attempts($limiter->loginAccountKey(RATE_LIMIT_EMAIL)))->toBe(1);
    expect(RateLimiter::attempts($limiter->recoveryKey(RATE_LIMIT_EMAIL, RATE_LIMIT_IP)))->toBe(1);
    expect(RateLimiter::attempts($limiter->recoveryIpKey(RATE_LIMIT_IP)))->toBe(1);

    Cache::flush();

    expect(RateLimiter::attempts($limiter->loginKey(RATE_LIMIT_EMAIL, RATE_LIMIT_IP)))->toBe(0);
    expect(RateLimiter::attempts($limiter->loginAccountKey(RATE_LIMIT_EMAIL)))->toBe(0);
    expect(RateLimiter::attempts($limiter->recoveryKey(RATE_LIMIT_EMAIL, RATE_LIMIT_IP)))->toBe(0);
    expect(RateLimiter::attempts($limiter->recoveryIpKey(RATE_LIMIT_IP)))->toBe(0);
    expect(new AuthenticationRateLimiter)->tooManyLoginAttempts(RATE_LIMIT_EMAIL, RATE_LIMIT_IP)->toBeFalse();
    expect(config('cache.limiter'))->toBeNull();
});

test('no limiter key contains the e-mail in clear text, only its sha256 and the IP (RNF-09)', function () {
    $limiter = new AuthenticationRateLimiter;
    $normalized = AuthenticationRateLimiter::normalizeEmail(RATE_LIMIT_EMAIL);
    $hash = hash('sha256', $normalized);

    $keys = [
        $limiter->loginKey(RATE_LIMIT_EMAIL, RATE_LIMIT_IP),
        $limiter->loginAccountKey(RATE_LIMIT_EMAIL),
        $limiter->recoveryKey(RATE_LIMIT_EMAIL, RATE_LIMIT_IP),
        $limiter->recoveryIpKey(RATE_LIMIT_IP),
    ];

    foreach ($keys as $key) {
        expect(mb_strtolower($key))->not->toContain($normalized)->not->toContain('example.com');
    }

    expect($keys[0])->toBe("login:{$hash}:".RATE_LIMIT_IP);
    expect($keys[1])->toBe("login-account:{$hash}");
    expect($keys[2])->toBe("recovery:{$hash}:".RATE_LIMIT_IP);
    expect($keys[3])->toBe('recovery-ip:'.RATE_LIMIT_IP);
});

test('the account-creation and convite lookup limiters resolve to exactly 3/600, 10/3600 and 20/60 (RF-19, RF-19b)', function () {
    expect(resolveNamedLimit('register'))->maxAttempts->toBe(3)->decaySeconds->toBe(600);
    expect(resolveNamedLimit('register-ip'))->maxAttempts->toBe(10)->decaySeconds->toBe(3600);
    expect(resolveNamedLimit('invite-ip'))->maxAttempts->toBe(20)->decaySeconds->toBe(60);
});

test('the registration and invite keys carry only the e-mail sha256 and the IP (RF-19, RF-38)', function () {
    $limiter = new AuthenticationRateLimiter;
    $normalized = AuthenticationRateLimiter::normalizeEmail(RATE_LIMIT_EMAIL);
    $hash = hash('sha256', $normalized);
    $password = 'senha-secreta-123';
    $token = bin2hex(random_bytes(32));

    $keys = [
        $limiter->registerKey(RATE_LIMIT_EMAIL, RATE_LIMIT_IP),
        $limiter->registerIpKey(RATE_LIMIT_IP),
        $limiter->inviteIpKey(RATE_LIMIT_IP),
    ];

    foreach ($keys as $key) {
        expect(mb_strtolower($key))
            ->not->toContain($normalized)
            ->not->toContain('example.com')
            ->not->toContain($password)
            ->not->toContain($token)
            ->not->toContain(hash('sha256', $token));
    }

    expect($keys[0])->toBe("register:{$hash}:".RATE_LIMIT_IP);
    expect($keys[1])->toBe('register-ip:'.RATE_LIMIT_IP);
    expect($keys[2])->toBe('invite-ip:'.RATE_LIMIT_IP);

    $variants = ['User@Example.com', ' user@example.com ', 'user@example.com'];
    $registerKeys = array_map(fn (string $email): string => $limiter->registerKey($email, RATE_LIMIT_IP), $variants);

    expect(array_unique($registerKeys))->toHaveCount(1);
});

test('tooManyRegistrationAttempts trips after 3 hits for one e-mail + IP (RF-19)', function () {
    $limiter = new AuthenticationRateLimiter;

    for ($hit = 1; $hit <= 3; $hit++) {
        expect($limiter->tooManyRegistrationAttempts(RATE_LIMIT_EMAIL, RATE_LIMIT_IP))->toBeFalse("Submission {$hit} must still be allowed.");

        $limiter->hitRegistration(RATE_LIMIT_EMAIL, RATE_LIMIT_IP);
    }

    expect($limiter->tooManyRegistrationAttempts(RATE_LIMIT_EMAIL, RATE_LIMIT_IP))->toBeTrue();
    expect(RateLimiter::attempts($limiter->registerKey(RATE_LIMIT_EMAIL, RATE_LIMIT_IP)))->toBe(3);
    expect(RateLimiter::attempts($limiter->registerIpKey(RATE_LIMIT_IP)))->toBe(3);
    expect($limiter->tooManyRegistrationAttempts('outra@example.com', RATE_LIMIT_IP))->toBeFalse();
    expect($limiter->tooManyRegistrationAttempts(RATE_LIMIT_EMAIL, '203.0.113.11'))->toBeFalse();
});

test('the IP-only registration key trips after 10 hits spread over distinct e-mails (RF-19)', function () {
    $limiter = new AuthenticationRateLimiter;

    for ($hit = 0; $hit < 10; $hit++) {
        $email = "pessoa-{$hit}@example.com";

        expect($limiter->tooManyRegistrationAttempts($email, RATE_LIMIT_IP))->toBeFalse();

        $limiter->hitRegistration($email, RATE_LIMIT_IP);
    }

    expect(RateLimiter::attempts($limiter->registerIpKey(RATE_LIMIT_IP)))->toBe(10);
    expect($limiter->tooManyRegistrationAttempts('nunca-vista@example.com', RATE_LIMIT_IP))->toBeTrue();
    expect($limiter->tooManyRegistrationAttempts('nunca-vista@example.com', '203.0.113.11'))->toBeFalse();
});

test('tooManyInviteLookups trips after 20 hitInviteLookup from one IP (RF-19b)', function () {
    $limiter = new AuthenticationRateLimiter;

    for ($hit = 1; $hit <= 20; $hit++) {
        expect($limiter->tooManyInviteLookups(RATE_LIMIT_IP))->toBeFalse("Lookup {$hit} must still be allowed.");

        $limiter->hitInviteLookup(RATE_LIMIT_IP);
    }

    expect($limiter->tooManyInviteLookups(RATE_LIMIT_IP))->toBeTrue();
    expect(RateLimiter::attempts($limiter->inviteIpKey(RATE_LIMIT_IP)))->toBe(20);
    expect($limiter->tooManyInviteLookups('203.0.113.11'))->toBeFalse();
});
