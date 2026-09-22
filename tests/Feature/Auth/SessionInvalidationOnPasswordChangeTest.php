<?php

use App\Enums\AuthenticationEventType;
use App\Livewire\Auth\AcceptInvite;
use App\Livewire\Auth\LoginForm;
use App\Livewire\Auth\ResetPassword;
use App\Models\AuthenticationEvent;
use App\Models\User;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Testing\TestResponse;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * RF-14, RF-15, RF-18 (CT-06) — every session that existed before a
 * password redefinition (`passwords.users`, `ResetPassword`) or definition
 * (`passwords.invites`, `AcceptInvite`) is refused on its next request by
 * `Illuminate\Session\Middleware\AuthenticateSession`, appended to the
 * `web` group in `bootstrap/app.php`.
 *
 * Simulation: the suite runs with `SESSION_DRIVER=array`, so "session A"
 * and "session B" are two requests that carry
 * `withSession(['password_hash_web' => $previousHash])` plus
 * `actingAs($user)`. `$previousHash` is the value the middleware itself
 * stored on a first authenticated GET before the reset, read back with
 * `session('password_hash_web')` — exactly what a cookie-backed session
 * would still hold after the password changed on the server. Between
 * "browsers" the in-memory session store and the guard's cached user are
 * discarded (`forgetCurrentBrowser()`), as a separate cookie jar would.
 *
 * Each forced cut appends exactly one `session_revoked` row and never a
 * `logout` (RF-26, D-09): `AuthenticateSession::logout()` dispatches
 * `CurrentDeviceLogout`, recorded by
 * `RecordSessionRevokedOnCurrentDeviceLogout`.
 */
const SESSION_PASSWORD_HASH_KEY = 'password_hash_web';

const PASSWORD_BEFORE_CHANGE = 'senha-anterior-123';

const PASSWORD_AFTER_CHANGE = 'senha-posterior-456';

/**
 * Drops the guard's cached user and the in-memory session, so the next
 * request behaves like one issued from another browser/cookie jar.
 */
function forgetCurrentBrowser(): void
{
    Auth::guard('web')->forgetUser();
    session()->flush();
}

/**
 * Issues a request from a session that still carries the password hash
 * stored before the change, for a user whose row now has the new hash.
 */
function requestWithStoredPasswordHash(User $user, string $storedHash, string $routeName): TestResponse
{
    forgetCurrentBrowser();

    return test()
        ->withSession([SESSION_PASSWORD_HASH_KEY => $storedHash])
        ->actingAs($user->fresh())
        ->get(route($routeName));
}

/**
 * Extracts the page component's `wire:snapshot` from a rendered full-page
 * Livewire response (see `EnsureUserIsActiveTest::firstLivewireSnapshot`).
 */
function pageSnapshotBeforePasswordChange(TestResponse $response): string
{
    preg_match('/wire:snapshot="([^"]+)"/', $response->getContent(), $matches);

    expect($matches)->toHaveCount(2, 'No wire:snapshot found in the rendered page.');

    return htmlspecialchars_decode($matches[1], ENT_QUOTES | ENT_SUBSTITUTE);
}

/**
 * Replays a `$refresh` call to `/livewire/update` from a page that was
 * opened before the password change, on a session that still carries the
 * previous hash. The JSON transport of the test kernel receives the 401
 * of `AuthenticationException` (a browser tab gets the `/login` redirect).
 */
function livewireRefreshWithStoredPasswordHash(User $user, string $storedHash, string $snapshot): TestResponse
{
    forgetCurrentBrowser();
    Livewire::flushState();

    $updateUri = app('router')->getRoutes()->getByName('default-livewire.update')->uri();

    return test()
        ->withSession([SESSION_PASSWORD_HASH_KEY => $storedHash])
        ->actingAs($user->fresh())
        ->withHeaders(['X-Livewire' => 'true'])
        ->postJson('/'.$updateUri, [
            '_token' => csrf_token(),
            'components' => [[
                'snapshot' => $snapshot,
                'updates' => [],
                'calls' => [['method' => '$refresh', 'params' => []]],
            ]],
        ]);
}

function sessionRevokedCount(): int
{
    return AuthenticationEvent::query()->where('event', AuthenticationEventType::SessionRevoked->value)->count();
}

function logoutEventCount(): int
{
    return AuthenticationEvent::query()->where('event', AuthenticationEventType::Logout->value)->count();
}

function loginFromFreshBrowser(string $email, string $password): Testable
{
    forgetCurrentBrowser();

    return Livewire::test(LoginForm::class)
        ->set('email', $email)
        ->set('password', $password)
        ->call('authenticate');
}

/**
 * Runs the two-session invalidation scenario for one password-defining
 * flow: the flow is given the user and must define PASSWORD_AFTER_CHANGE.
 *
 * @param  callable(User): void  $definePassword
 */
function assertPreExistingSessionsAreCutAfter(callable $definePassword): void
{
    $user = User::factory()->obra()->create([
        'email' => 'sessao@example.com',
        'password' => Hash::make(PASSWORD_BEFORE_CHANGE),
    ]);
    $rememberTokenBefore = $user->remember_token;

    $openPage = test()->actingAs($user)->get(route('obra.pedidos.index'))->assertOk();
    $snapshot = pageSnapshotBeforePasswordChange($openPage);
    $previousHash = session(SESSION_PASSWORD_HASH_KEY);

    expect($previousHash)->toBeString()->not->toBe('');

    forgetCurrentBrowser();
    $definePassword($user);

    expect(Hash::check(PASSWORD_AFTER_CHANGE, $user->fresh()->password))->toBeTrue();
    expect($user->fresh()->remember_token)->not->toBe($rememberTokenBefore);
    expect(sessionRevokedCount())->toBe(0);

    requestWithStoredPasswordHash($user, $previousHash, 'obra.pedidos.index')
        ->assertRedirect(route('login'));
    expect(Auth::check())->toBeFalse();
    expect(sessionRevokedCount())->toBe(1);

    requestWithStoredPasswordHash($user, $previousHash, 'obra.pedidos.index')
        ->assertRedirect(route('login'));
    expect(Auth::check())->toBeFalse();
    expect(sessionRevokedCount())->toBe(2);

    livewireRefreshWithStoredPasswordHash($user, $previousHash, $snapshot)
        ->assertUnauthorized();
    expect(Auth::check())->toBeFalse();
    expect(sessionRevokedCount())->toBe(3);
    expect(logoutEventCount())->toBe(0);

    $revoked = AuthenticationEvent::query()->where('event', AuthenticationEventType::SessionRevoked->value)->get();
    expect($revoked->pluck('user_id')->unique()->all())->toBe([$user->id]);
    expect($revoked->pluck('email')->unique()->all())->toBe(['sessao@example.com']);

    loginFromFreshBrowser('sessao@example.com', PASSWORD_AFTER_CHANGE)
        ->assertHasNoErrors()
        ->assertRedirect(route('home'));

    test()->get(route('obra.pedidos.index'))->assertOk();
    expect(Auth::id())->toBe($user->id);
    expect(session(SESSION_PASSWORD_HASH_KEY))->not->toBe($previousHash);
    expect(sessionRevokedCount())->toBe(3);
    expect(logoutEventCount())->toBe(0);
}

test('AuthenticateSession is appended to the web group and reaches the authenticated routes and /livewire/update (RF-14)', function () {
    $router = app('router');

    expect($router->getMiddlewareGroups()['web'])->toContain(AuthenticateSession::class);

    foreach (['home', 'obra.pedidos.index', 'suprimentos.kanban', 'gestao.dashboard', 'default-livewire.update'] as $routeName) {
        $resolved = $router->gatherRouteMiddleware($router->getRoutes()->getByName($routeName));

        expect($resolved)->toContain(AuthenticateSession::class);
    }
});

test('a password reset cuts sessions A and B, refuses an open Livewire page and lets the new password in (RF-14, RF-15, G-11)', function () {
    assertPreExistingSessionsAreCutAfter(function (User $user): void {
        $token = Password::broker('users')->createToken($user);

        Livewire::test(ResetPassword::class, ['token' => $token])
            ->set('email', $user->email)
            ->set('password', PASSWORD_AFTER_CHANGE)
            ->set('password_confirmation', PASSWORD_AFTER_CHANGE)
            ->call('resetPassword')
            ->assertHasNoErrors()
            ->assertRedirect(route('login'));
    });
});

test('a first-access password definition cuts sessions A and B, refuses an open Livewire page and lets the new password in (RF-14, RF-15)', function () {
    assertPreExistingSessionsAreCutAfter(function (User $user): void {
        $token = Password::broker('invites')->createToken($user);

        Livewire::test(AcceptInvite::class, ['token' => $token])
            ->set('email', $user->email)
            ->set('password', PASSWORD_AFTER_CHANGE)
            ->set('password_confirmation', PASSWORD_AFTER_CHANGE)
            ->call('acceptInvite')
            ->assertHasNoErrors()
            ->assertRedirect(route('login'));
    });
});

test('a session without a stored hash is not cut and receives the hash on its first request (R-06)', function () {
    $user = User::factory()->suprimentos()->create();

    $this->actingAs($user);
    expect(session()->has(SESSION_PASSWORD_HASH_KEY))->toBeFalse();

    $this->get(route('suprimentos.kanban'))->assertOk();

    expect(session(SESSION_PASSWORD_HASH_KEY))
        ->toBe(Auth::guard('web')->hashPasswordForCookie($user->password));

    $this->get(route('suprimentos.kanban'))->assertOk();
    expect(Auth::id())->toBe($user->id);
});

test('a session whose stored hash matches the current password keeps working across requests', function () {
    $user = User::factory()->gestao()->create();

    $this->actingAs($user);
    $this->get(route('gestao.dashboard'))->assertOk();
    $storedHash = session(SESSION_PASSWORD_HASH_KEY);

    requestWithStoredPasswordHash($user, $storedHash, 'gestao.dashboard')->assertOk();
    expect(Auth::id())->toBe($user->id);
});

test('POST /logout still invalidates the session (stored hash included) and regenerates the CSRF token (RF-18)', function () {
    $user = User::factory()->obra()->create();

    $this->actingAs($user);
    $this->get(route('obra.pedidos.index'))->assertOk();
    $csrfBefore = csrf_token();
    expect(session()->has(SESSION_PASSWORD_HASH_KEY))->toBeTrue();

    $this->post(route('logout'))->assertRedirect(route('login'));

    expect(Auth::check())->toBeFalse();
    expect(session()->has(SESSION_PASSWORD_HASH_KEY))->toBeFalse();
    expect(csrf_token())->not->toBe($csrfBefore);
    expect(logoutEventCount())->toBe(1);
    expect(sessionRevokedCount())->toBe(0);

    $this->get(route('obra.pedidos.index'))->assertRedirect(route('login'));
});
