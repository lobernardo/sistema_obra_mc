<?php

use App\Livewire\Auth\LoginForm;
use App\Models\User;
use App\Services\AuthenticationRateLimiter;
use Illuminate\Cache\ArrayStore;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

const LOGIN_RL_EMAIL = 'conta@example.com';

const LOGIN_RL_PASSWORD = 'senha-correta-a1b2c3';

const LOGIN_RL_WRONG_PASSWORD = 'senha-errada-x9y8z7';

/**
 * Drives one login attempt through the component (same client IP for every
 * call — `Livewire::test` rebuilds the request with `REMOTE_ADDR=127.0.0.1`).
 */
function attemptLogin(string $email, string $password): Testable
{
    return Livewire::test(LoginForm::class)
        ->set('email', $email)
        ->set('password', $password)
        ->call('authenticate');
}

/**
 * Strips the per-instance Livewire attributes so two renders of the login
 * form can be compared byte for byte (RNF-11).
 */
function normalizeLoginHtml(string $html): string
{
    return (string) preg_replace('/\s(wire:snapshot|wire:effects|wire:id)="[^"]*"/', '', $html);
}

/**
 * Extracts the login page component's `wire:snapshot`, as the browser would
 * send it back on the first `/livewire/update` call.
 */
function loginPageSnapshot(): string
{
    preg_match('/wire:snapshot="([^"]+)"/', test()->get(route('login'))->assertOk()->getContent(), $matches);

    expect($matches)->toHaveCount(2, 'No wire:snapshot found on the login page.');

    return htmlspecialchars_decode($matches[1], ENT_QUOTES | ENT_SUBSTITUTE);
}

/**
 * Drives one login attempt through the real `/livewire/update` transport from
 * a given client IP. `Livewire::test` cannot vary the IP (its subsequent
 * requests always carry `REMOTE_ADDR=127.0.0.1`), so the account-wide limiter
 * scenarios use the HTTP endpoint with `withServerVariables`, mirroring
 * `EnsureUserIsActiveTest`.
 */
function attemptLoginFromIp(string $ip, string $snapshot, string $email, string $password): TestResponse
{
    Livewire::flushState();

    $updateUri = app('router')->getRoutes()->getByName('default-livewire.update')->uri();

    return test()
        ->withServerVariables(['REMOTE_ADDR' => $ip])
        ->withHeaders(['X-Livewire' => 'true'])
        ->postJson('/'.$updateUri, [
            '_token' => csrf_token(),
            'components' => [[
                'snapshot' => $snapshot,
                'updates' => ['email' => $email, 'password' => $password],
                'calls' => [['method' => 'authenticate', 'params' => []]],
            ]],
        ]);
}

/**
 * Raw contents of the `array` cache store the limiters write to
 * (`ArrayStore::$storage`), serialized for a plain substring scan.
 */
function limiterStorageDump(): string
{
    $store = Cache::store('array')->getStore();

    expect($store)->toBeInstanceOf(ArrayStore::class);

    $storage = (new ReflectionProperty(ArrayStore::class, 'storage'))->getValue($store);

    return json_encode($storage, JSON_THROW_ON_ERROR);
}

function createLoginRateLimitUser(string $email = LOGIN_RL_EMAIL): User
{
    return User::factory()->obra()->create([
        'email' => $email,
        'password' => Hash::make(LOGIN_RL_PASSWORD),
    ]);
}

test('5 wrong passwords from one IP block the 6th attempt even with the correct password (RF-09, UI-02)', function () {
    createLoginRateLimitUser();

    for ($attempt = 1; $attempt <= 5; $attempt++) {
        attemptLogin(LOGIN_RL_EMAIL, LOGIN_RL_WRONG_PASSWORD)
            ->assertHasErrors(['email'])
            ->assertSee('E-mail ou senha inválidos.');
    }

    expect(Auth::check())->toBeFalse();

    attemptLogin(LOGIN_RL_EMAIL, LOGIN_RL_PASSWORD)
        ->assertHasErrors(['email'])
        ->assertSee(LoginForm::THROTTLED_MESSAGE)
        ->assertDontSee('E-mail ou senha inválidos.')
        ->assertNoRedirect();

    expect(Auth::check())->toBeFalse();
});

test('a tripped limiter does not consult the guard: the 6th attempt leaves the counters untouched', function () {
    createLoginRateLimitUser();
    $limiter = app(AuthenticationRateLimiter::class);

    for ($attempt = 1; $attempt <= 5; $attempt++) {
        attemptLogin(LOGIN_RL_EMAIL, LOGIN_RL_WRONG_PASSWORD);
    }

    attemptLogin(LOGIN_RL_EMAIL, LOGIN_RL_PASSWORD)->assertHasErrors(['email']);

    expect(RateLimiter::attempts($limiter->loginKey(LOGIN_RL_EMAIL, '127.0.0.1')))->toBe(5);
    expect(RateLimiter::attempts($limiter->loginAccountKey(LOGIN_RL_EMAIL)))->toBe(5);
    expect(Auth::check())->toBeFalse();
});

test('the account-wide limiter blocks the 21st attempt after 20 failures spread over 5 IPs (RF-09, D-01)', function () {
    createLoginRateLimitUser();
    $snapshot = loginPageSnapshot();

    for ($attempt = 0; $attempt < 20; $attempt++) {
        $ip = '198.51.100.'.(1 + intdiv($attempt, 4));

        attemptLoginFromIp($ip, $snapshot, LOGIN_RL_EMAIL, LOGIN_RL_WRONG_PASSWORD)->assertOk();
    }

    $limiter = app(AuthenticationRateLimiter::class);

    expect(RateLimiter::attempts($limiter->loginAccountKey(LOGIN_RL_EMAIL)))->toBe(20);
    expect(RateLimiter::attempts($limiter->loginKey(LOGIN_RL_EMAIL, '198.51.100.1')))->toBe(4);
    expect(RateLimiter::attempts($limiter->loginKey(LOGIN_RL_EMAIL, '198.51.100.6')))->toBe(0);

    $refused = attemptLoginFromIp('198.51.100.6', $snapshot, LOGIN_RL_EMAIL, LOGIN_RL_PASSWORD);

    $refused->assertOk();
    expect($refused->status())->not->toBe(429);
    expect($refused->getContent())->toContain(LoginForm::THROTTLED_MESSAGE);
    expect(Auth::check())->toBeFalse();
});

test('the e-mail + IP limiter releases 61 seconds after the last failure (RF-10)', function () {
    $user = createLoginRateLimitUser();

    for ($attempt = 1; $attempt <= 5; $attempt++) {
        attemptLogin(LOGIN_RL_EMAIL, LOGIN_RL_WRONG_PASSWORD);
    }

    attemptLogin(LOGIN_RL_EMAIL, LOGIN_RL_PASSWORD)->assertHasErrors(['email']);
    expect(Auth::check())->toBeFalse();

    Carbon::setTestNow(now()->addSeconds(61));

    attemptLogin(LOGIN_RL_EMAIL, LOGIN_RL_PASSWORD)
        ->assertHasNoErrors()
        ->assertRedirect(route('home'));

    expect(Auth::id())->toBe($user->id);
});

test('the account-wide limiter outlives the e-mail + IP window and releases after 15 minutes (RF-10)', function () {
    $user = createLoginRateLimitUser();
    $snapshot = loginPageSnapshot();

    for ($attempt = 0; $attempt < 20; $attempt++) {
        attemptLoginFromIp('198.51.100.'.(1 + intdiv($attempt, 4)), $snapshot, LOGIN_RL_EMAIL, LOGIN_RL_WRONG_PASSWORD)->assertOk();
    }

    Carbon::setTestNow(now()->addSeconds(61));

    $stillRefused = attemptLoginFromIp('198.51.100.7', $snapshot, LOGIN_RL_EMAIL, LOGIN_RL_PASSWORD)->assertOk();

    expect($stillRefused->getContent())->toContain(LoginForm::THROTTLED_MESSAGE);
    expect(Auth::check())->toBeFalse();

    Carbon::setTestNow(now()->addMinutes(15)->addSecond());

    $accepted = attemptLoginFromIp('198.51.100.8', $snapshot, LOGIN_RL_EMAIL, LOGIN_RL_PASSWORD)->assertOk();

    expect($accepted->getContent())->not->toContain(LoginForm::THROTTLED_MESSAGE)->not->toContain('E-mail ou senha inválidos.');
    expect(Auth::id())->toBe($user->id);
});

test('a successful login clears both counters, so later failures count from zero (RF-10)', function () {
    $user = createLoginRateLimitUser();
    $limiter = app(AuthenticationRateLimiter::class);

    for ($attempt = 1; $attempt <= 4; $attempt++) {
        attemptLogin(LOGIN_RL_EMAIL, LOGIN_RL_WRONG_PASSWORD)->assertHasErrors(['email']);
    }

    attemptLogin(LOGIN_RL_EMAIL, LOGIN_RL_PASSWORD)->assertRedirect(route('home'));

    expect(Auth::id())->toBe($user->id);
    expect(RateLimiter::attempts($limiter->loginKey(LOGIN_RL_EMAIL, '127.0.0.1')))->toBe(0);
    expect(RateLimiter::attempts($limiter->loginAccountKey(LOGIN_RL_EMAIL)))->toBe(0);

    Auth::logout();

    for ($attempt = 1; $attempt <= 4; $attempt++) {
        attemptLogin(LOGIN_RL_EMAIL, LOGIN_RL_WRONG_PASSWORD)
            ->assertHasErrors(['email'])
            ->assertSee('E-mail ou senha inválidos.');
    }

    attemptLogin(LOGIN_RL_EMAIL, LOGIN_RL_PASSWORD)->assertRedirect(route('home'));

    expect(Auth::id())->toBe($user->id);

    Auth::logout();

    for ($attempt = 1; $attempt <= 5; $attempt++) {
        attemptLogin(LOGIN_RL_EMAIL, LOGIN_RL_WRONG_PASSWORD);
    }

    attemptLogin(LOGIN_RL_EMAIL, LOGIN_RL_PASSWORD)->assertHasErrors(['email'])->assertSee(LoginForm::THROTTLED_MESSAGE);
    expect(Auth::check())->toBeFalse();
});

test('case and whitespace variants of the e-mail share one counter and trip at 5 in total (RF-12)', function () {
    createLoginRateLimitUser();
    $limiter = app(AuthenticationRateLimiter::class);

    $variants = ['Conta@Example.com', ' conta@example.com ', 'CONTA@EXAMPLE.COM', "\tconta@example.com", 'conta@example.com'];

    foreach ($variants as $variant) {
        attemptLogin($variant, LOGIN_RL_WRONG_PASSWORD)
            ->assertSet('email', LOGIN_RL_EMAIL)
            ->assertHasErrors(['email']);
    }

    expect(RateLimiter::attempts($limiter->loginKey(LOGIN_RL_EMAIL, '127.0.0.1')))->toBe(5);
    expect(RateLimiter::attempts($limiter->loginAccountKey(LOGIN_RL_EMAIL)))->toBe(5);

    attemptLogin(' Conta@Example.COM ', LOGIN_RL_PASSWORD)
        ->assertSet('email', LOGIN_RL_EMAIL)
        ->assertHasErrors(['email'])
        ->assertSee(LoginForm::THROTTLED_MESSAGE);

    expect(Auth::check())->toBeFalse();
});

test('the throttled response is identical for an existing and a non-existing e-mail (UI-02, RNF-11)', function () {
    createLoginRateLimitUser();
    $unknown = 'ninguem@example.com';

    foreach ([LOGIN_RL_EMAIL, $unknown] as $email) {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            attemptLogin($email, LOGIN_RL_WRONG_PASSWORD)->assertHasErrors(['email']);
        }
    }

    $existing = attemptLogin(LOGIN_RL_EMAIL, LOGIN_RL_PASSWORD)->assertHasErrors(['email']);
    $missing = attemptLogin($unknown, LOGIN_RL_PASSWORD)->assertHasErrors(['email']);

    expect($existing->errors()->first('email'))->toBe(LoginForm::THROTTLED_MESSAGE);
    expect($missing->errors()->first('email'))->toBe(LoginForm::THROTTLED_MESSAGE);
    expect($existing->errors()->all())->toBe($missing->errors()->all());
    expect(normalizeLoginHtml($missing->html()))->toBe(normalizeLoginHtml($existing->html()));
    expect(LoginForm::THROTTLED_MESSAGE)->not->toMatch('/\d/');
    expect(Auth::check())->toBeFalse();
});

test('the limiter store never holds the submitted password or the e-mail in clear text (RNF-09)', function () {
    createLoginRateLimitUser();
    $limiter = app(AuthenticationRateLimiter::class);

    for ($attempt = 1; $attempt <= 5; $attempt++) {
        attemptLogin(LOGIN_RL_EMAIL, LOGIN_RL_WRONG_PASSWORD);
    }

    attemptLogin(LOGIN_RL_EMAIL, LOGIN_RL_PASSWORD)->assertHasErrors(['email']);

    $dump = limiterStorageDump();

    expect(RateLimiter::attempts($limiter->loginKey(LOGIN_RL_EMAIL, '127.0.0.1')))->toBe(5);
    expect($dump)->toContain(hash('sha256', LOGIN_RL_EMAIL))
        ->not->toContain(LOGIN_RL_PASSWORD)
        ->not->toContain(LOGIN_RL_WRONG_PASSWORD)
        ->not->toContain(LOGIN_RL_EMAIL);
});
