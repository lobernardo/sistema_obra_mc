<?php

use App\Actions\Usuarios\SetUserActiveAction;
use App\Actions\Usuarios\UpdateUserAction;
use App\Enums\AuthenticationEventType;
use App\Enums\RoleSlug;
use App\Http\Middleware\EnsureUserIsActive;
use App\Livewire\Auth\AcceptInvite;
use App\Livewire\Auth\LoginForm;
use App\Livewire\Auth\ResetPassword;
use App\Models\AuthenticationEvent;
use App\Models\Obra;
use App\Models\Role;
use App\Models\User;
use App\Services\AuthenticationEventRecorder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Testing\TestResponse;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * RF-26, RF-27, RF-29, CT-03, AC-F11, AC-F22 — every authentication
 * outcome appends exactly one immutable row to `authentication_events`
 * through `AuthenticationEventRecorder`, with the six-slug catalog fixed by
 * D-04/D-08/D-09:
 *
 * - `login_success` / `login_failed` from `LoginForm::authenticate` (the
 *   limiter-tripped branch included — D-08), `user_id` resolved by
 *   normalized e-mail so an inactive account is attributed too;
 * - `logout` only from `POST /logout`;
 * - `password_reset` (broker `users`) vs `password_defined` (broker
 *   `invites`), written explicitly by `DefinesPasswordFromToken` (D-04);
 * - `session_revoked` from the two forced cuts (deactivation and
 *   credential change), never a `logout` (D-09).
 *
 * A failing write is reported and never alters the response (RF-29).
 */
const AUTH_EVENTS_EMAIL = 'trilha@example.com';

const AUTH_EVENTS_PASSWORD = 'senha-trilha-123456';

const AUTH_EVENTS_NEW_PASSWORD = 'senha-nova-654321';

function createTrailUser(array $attributes = []): User
{
    return User::factory()->obra()->create([
        'email' => AUTH_EVENTS_EMAIL,
        'password' => Hash::make(AUTH_EVENTS_PASSWORD),
        ...$attributes,
    ]);
}

function loginThroughComponent(string $email, string $password): Testable
{
    return Livewire::test(LoginForm::class)
        ->set('email', $email)
        ->set('password', $password)
        ->call('authenticate');
}

function trailRows(AuthenticationEventType $event): Collection
{
    return AuthenticationEvent::query()->where('event', $event->value)->orderBy('id')->get();
}

function trailCount(AuthenticationEventType $event): int
{
    return AuthenticationEvent::query()->where('event', $event->value)->count();
}

/**
 * Drives one login through the real `/livewire/update` transport so the IP
 * and the user agent of the request can be chosen (mirrors
 * `LoginRateLimitTest::attemptLoginFromIp`).
 */
function loginThroughTransport(string $email, string $password, string $ip, string $userAgent): TestResponse
{
    preg_match('/wire:snapshot="([^"]+)"/', test()->get(route('login'))->assertOk()->getContent(), $matches);
    expect($matches)->toHaveCount(2);
    $snapshot = htmlspecialchars_decode($matches[1], ENT_QUOTES | ENT_SUBSTITUTE);

    Livewire::flushState();

    $updateUri = app('router')->getRoutes()->getByName('default-livewire.update')->uri();

    return test()
        ->withServerVariables(['REMOTE_ADDR' => $ip, 'HTTP_USER_AGENT' => $userAgent])
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

test('an accepted login writes exactly one login_success with user_id, normalized e-mail, ip and user agent (RF-26, CT-03)', function () {
    $user = createTrailUser();

    loginThroughTransport(' Trilha@Example.COM ', AUTH_EVENTS_PASSWORD, '198.51.100.23', 'Mozilla/5.0 (Trilha)')->assertOk();

    expect(Auth::id())->toBe($user->id);
    expect(AuthenticationEvent::query()->count())->toBe(1);

    $row = trailRows(AuthenticationEventType::LoginSuccess)->sole();

    expect($row->user_id)->toBe($user->id);
    expect($row->email)->toBe(AUTH_EVENTS_EMAIL);
    expect($row->ip)->toBe('198.51.100.23');
    expect($row->user_agent)->toBe('Mozilla/5.0 (Trilha)');
    expect($row->created_at)->not->toBeNull();
    expect($row->getAttributes())->not->toHaveKey('updated_at');
});

test('a wrong password writes login_failed attributed to the account, with the same message as LoginTest (RF-26, AC-F11)', function () {
    $user = createTrailUser();

    loginThroughComponent(AUTH_EVENTS_EMAIL, 'senha-errada-000000')
        ->assertHasErrors(['email'])
        ->assertSee('E-mail ou senha inválidos.');

    expect(Auth::check())->toBeFalse();
    expect(AuthenticationEvent::query()->count())->toBe(1);

    $row = trailRows(AuthenticationEventType::LoginFailed)->sole();

    expect($row->user_id)->toBe($user->id);
    expect($row->email)->toBe(AUTH_EVENTS_EMAIL);
    expect($row->ip)->toBe('127.0.0.1');
});

test('an inactive account writes login_failed with user_id set, unlike the guard Failed event (RF-26, AC-F11)', function () {
    $user = createTrailUser(['is_active' => false]);

    loginThroughComponent(AUTH_EVENTS_EMAIL, AUTH_EVENTS_PASSWORD)
        ->assertHasErrors(['email'])
        ->assertSee('E-mail ou senha inválidos.');

    expect(Auth::check())->toBeFalse();
    expect(AuthenticationEvent::query()->count())->toBe(1);

    $row = trailRows(AuthenticationEventType::LoginFailed)->sole();

    expect($row->user_id)->toBe($user->id);
    expect($row->email)->toBe(AUTH_EVENTS_EMAIL);
});

test('an unknown e-mail writes login_failed with user_id null and the normalized e-mail (RF-26, RF-28)', function () {
    loginThroughComponent(' Ninguem@Example.com ', 'qualquer-senha-123')
        ->assertHasErrors(['email'])
        ->assertSee('E-mail ou senha inválidos.');

    expect(AuthenticationEvent::query()->count())->toBe(1);

    $row = trailRows(AuthenticationEventType::LoginFailed)->sole();

    expect($row->user_id)->toBeNull();
    expect($row->email)->toBe('ninguem@example.com');
});

test('a limiter-tripped attempt writes login_failed without consulting the guard (RF-09, D-08)', function () {
    $user = createTrailUser();

    for ($attempt = 1; $attempt <= 5; $attempt++) {
        loginThroughComponent(AUTH_EVENTS_EMAIL, 'senha-errada-000000')->assertHasErrors(['email']);
    }

    expect(trailCount(AuthenticationEventType::LoginFailed))->toBe(5);

    loginThroughComponent(AUTH_EVENTS_EMAIL, AUTH_EVENTS_PASSWORD)
        ->assertHasErrors(['email'])
        ->assertSee(LoginForm::THROTTLED_MESSAGE);

    expect(Auth::check())->toBeFalse();
    expect(trailCount(AuthenticationEventType::LoginFailed))->toBe(6);
    expect(trailCount(AuthenticationEventType::LoginSuccess))->toBe(0);
    expect(trailRows(AuthenticationEventType::LoginFailed)->last()->user_id)->toBe($user->id);
});

test('POST /logout writes exactly one logout and no session_revoked (RF-26, D-09)', function () {
    $user = createTrailUser();

    $this->actingAs($user);
    $this->get(route('obra.pedidos.index'))->assertOk();

    $this->post(route('logout'))->assertRedirect(route('login'));

    expect(Auth::check())->toBeFalse();
    expect(trailCount(AuthenticationEventType::Logout))->toBe(1);
    expect(trailCount(AuthenticationEventType::SessionRevoked))->toBe(0);

    $row = trailRows(AuthenticationEventType::Logout)->sole();

    expect($row->user_id)->toBe($user->id);
    expect($row->email)->toBe(AUTH_EVENTS_EMAIL);
    expect($row->ip)->toBe('127.0.0.1');
});

test('the recovery flow writes password_reset and not password_defined (RF-26, D-04)', function () {
    $user = createTrailUser();
    $token = Password::broker('users')->createToken($user);

    Livewire::test(ResetPassword::class, ['token' => $token])
        ->set('email', AUTH_EVENTS_EMAIL)
        ->set('password', AUTH_EVENTS_NEW_PASSWORD)
        ->set('password_confirmation', AUTH_EVENTS_NEW_PASSWORD)
        ->call('resetPassword')
        ->assertHasNoErrors()
        ->assertRedirect(route('login'));

    expect(Hash::check(AUTH_EVENTS_NEW_PASSWORD, $user->fresh()->password))->toBeTrue();
    expect(AuthenticationEvent::query()->count())->toBe(1);
    expect(trailCount(AuthenticationEventType::PasswordDefined))->toBe(0);

    $row = trailRows(AuthenticationEventType::PasswordReset)->sole();

    expect($row->user_id)->toBe($user->id);
    expect($row->email)->toBe(AUTH_EVENTS_EMAIL);
});

test('the first-access flow writes password_defined and not password_reset (RF-26, D-04)', function () {
    $user = createTrailUser();
    $token = Password::broker('invites')->createToken($user);

    Livewire::test(AcceptInvite::class, ['token' => $token])
        ->set('email', AUTH_EVENTS_EMAIL)
        ->set('password', AUTH_EVENTS_NEW_PASSWORD)
        ->set('password_confirmation', AUTH_EVENTS_NEW_PASSWORD)
        ->call('acceptInvite')
        ->assertHasNoErrors()
        ->assertRedirect(route('login'));

    expect(Hash::check(AUTH_EVENTS_NEW_PASSWORD, $user->fresh()->password))->toBeTrue();
    expect(AuthenticationEvent::query()->count())->toBe(1);
    expect(trailCount(AuthenticationEventType::PasswordReset))->toBe(0);

    $row = trailRows(AuthenticationEventType::PasswordDefined)->sole();

    expect($row->user_id)->toBe($user->id);
    expect($row->email)->toBe(AUTH_EVENTS_EMAIL);
});

test('a rejected token writes no password event (RF-26)', function () {
    createTrailUser();

    Livewire::test(ResetPassword::class, ['token' => 'token-invalido'])
        ->set('email', AUTH_EVENTS_EMAIL)
        ->set('password', AUTH_EVENTS_NEW_PASSWORD)
        ->set('password_confirmation', AUTH_EVENTS_NEW_PASSWORD)
        ->call('resetPassword')
        ->assertHasErrors(['email']);

    expect(AuthenticationEvent::query()->count())->toBe(0);
});

test('the user agent is stripped of control characters and truncated to 255 (CT-03)', function () {
    $user = createTrailUser();
    $hostile = "Mozilla/5.0\x00 (X11)\n\r\t".str_repeat('A', 1000)."\x7F";

    $recorder = new AuthenticationEventRecorder(Request::create('/login', 'POST', server: [
        'REMOTE_ADDR' => '2001:db8:85a3::8a2e:370:7334',
        'HTTP_USER_AGENT' => $hostile,
    ]));

    $recorder->loginSucceeded($user);

    $row = trailRows(AuthenticationEventType::LoginSuccess)->sole();

    expect(mb_strlen($row->user_agent))->toBe(255);
    expect($row->user_agent)->not->toMatch('/[\x00-\x1F\x7F]/');
    expect($row->user_agent)->toStartWith('Mozilla/5.0 (X11)AAAA');
    expect($row->ip)->toBe('2001:db8:85a3::8a2e:370:7334');
});

test('an absent user agent is stored as null', function () {
    $user = createTrailUser();

    $recorder = new AuthenticationEventRecorder(Request::create('/login', 'POST', server: [
        'REMOTE_ADDR' => '127.0.0.1',
        'HTTP_USER_AGENT' => '',
    ]));

    $recorder->loggedOut($user);

    expect(trailRows(AuthenticationEventType::Logout)->sole()->user_agent)->toBeNull();
});

test('a failing trail write is reported and never changes the login outcome (RF-29)', function () {
    Exceptions::fake();
    $user = createTrailUser();

    AuthenticationEvent::creating(function (): void {
        throw new RuntimeException('trilha indisponível');
    });

    loginThroughComponent(AUTH_EVENTS_EMAIL, AUTH_EVENTS_PASSWORD)
        ->assertHasNoErrors()
        ->assertRedirect(route('home'));

    expect(Auth::check())->toBeTrue();
    expect(Auth::id())->toBe($user->id);
    expect(AuthenticationEvent::query()->count())->toBe(0);

    Exceptions::assertReported(fn (RuntimeException $e): bool => $e->getMessage() === 'trilha indisponível');
});

test('a failing trail write is reported and never changes the logout response (RF-29)', function () {
    Exceptions::fake();
    $user = createTrailUser();

    AuthenticationEvent::creating(function (): void {
        throw new RuntimeException('trilha indisponível');
    });

    $this->actingAs($user);
    $this->post(route('logout'))->assertRedirect(route('login'));

    expect(Auth::check())->toBeFalse();
    expect(AuthenticationEvent::query()->count())->toBe(0);
    Exceptions::assertReported(RuntimeException::class);
});

test('no authentication row ever holds the submitted password, its hash or the reset token (RF-27, RF-22)', function () {
    $user = createTrailUser();

    loginThroughComponent(AUTH_EVENTS_EMAIL, 'senha-errada-000000');
    loginThroughComponent(AUTH_EVENTS_EMAIL, AUTH_EVENTS_PASSWORD)->assertRedirect(route('home'));
    $this->post(route('logout'));

    $token = Password::broker('users')->createToken($user);

    Livewire::test(ResetPassword::class, ['token' => $token])
        ->set('email', AUTH_EVENTS_EMAIL)
        ->set('password', AUTH_EVENTS_NEW_PASSWORD)
        ->set('password_confirmation', AUTH_EVENTS_NEW_PASSWORD)
        ->call('resetPassword')
        ->assertHasNoErrors();

    $dump = json_encode(AuthenticationEvent::query()->get()->map->getAttributes()->all(), JSON_THROW_ON_ERROR);

    expect(AuthenticationEvent::query()->count())->toBe(4);
    expect($dump)
        ->not->toContain(AUTH_EVENTS_PASSWORD)
        ->not->toContain(AUTH_EVENTS_NEW_PASSWORD)
        ->not->toContain('senha-errada-000000')
        ->not->toContain($token)
        ->not->toContain($user->fresh()->password);
});

test('deactivation cuts the live session with exactly one session_revoked and no logout (RF-16, D-09)', function () {
    $user = createTrailUser();
    $gestao = User::factory()->gestao()->create();

    $this->actingAs($user);
    $this->get(route('obra.pedidos.index'))->assertOk();

    (app(SetUserActiveAction::class))->execute($gestao, $user, false);

    $this->get(route('obra.pedidos.index'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('status', EnsureUserIsActive::DEACTIVATED_MESSAGE);

    expect(Auth::check())->toBeFalse();
    expect(trailCount(AuthenticationEventType::SessionRevoked))->toBe(1);
    expect(trailCount(AuthenticationEventType::Logout))->toBe(0);

    $row = trailRows(AuthenticationEventType::SessionRevoked)->sole();

    expect($row->user_id)->toBe($user->id);
    expect($row->email)->toBe(AUTH_EVENTS_EMAIL);
    expect($row->ip)->toBe('127.0.0.1');
});

test('two pre-reset sessions cut by AuthenticateSession write two session_revoked and no logout (RF-14, D-09)', function () {
    $user = createTrailUser();

    $this->actingAs($user);
    $this->get(route('obra.pedidos.index'))->assertOk();
    $previousHash = session('password_hash_web');
    expect($previousHash)->toBeString()->not->toBe('');

    Auth::guard('web')->forgetUser();
    session()->flush();

    $token = Password::broker('users')->createToken($user);

    Livewire::test(ResetPassword::class, ['token' => $token])
        ->set('email', AUTH_EVENTS_EMAIL)
        ->set('password', AUTH_EVENTS_NEW_PASSWORD)
        ->set('password_confirmation', AUTH_EVENTS_NEW_PASSWORD)
        ->call('resetPassword')
        ->assertHasNoErrors();

    expect(trailCount(AuthenticationEventType::PasswordReset))->toBe(1);
    expect(trailCount(AuthenticationEventType::SessionRevoked))->toBe(0);

    foreach (['sessão A', 'sessão B'] as $browser) {
        Auth::guard('web')->forgetUser();
        session()->flush();

        $this->withSession(['password_hash_web' => $previousHash])
            ->actingAs($user->fresh())
            ->get(route('obra.pedidos.index'))
            ->assertRedirect(route('login'));

        expect(Auth::check())->toBeFalse($browser);
    }

    expect(trailCount(AuthenticationEventType::SessionRevoked))->toBe(2);
    expect(trailCount(AuthenticationEventType::Logout))->toBe(0);
    expect(trailRows(AuthenticationEventType::SessionRevoked)->pluck('user_id')->unique()->all())->toBe([$user->id]);
});

test('a papel change keeps the session and writes neither session_revoked nor logout (RF-17, D-10)', function () {
    $user = User::factory()->gestao()->create(['email' => AUTH_EVENTS_EMAIL]);
    $admin = User::factory()->gestao()->create();
    $obra = Obra::factory()->create();
    $obraRole = Role::query()->firstOrCreate(['slug' => RoleSlug::Obra->value], ['name' => 'Obra']);

    $this->actingAs($user);
    session()->put(Auth::guard('web')->getName(), $user->getAuthIdentifier());
    $this->get(route('gestao.dashboard'))->assertOk();

    (app(UpdateUserAction::class))->execute($admin, User::query()->findOrFail($user->id), [
        'name' => $user->name,
        'email' => $user->email,
        'role_id' => $obraRole->id,
        'obra_ids' => [$obra->id],
    ]);
    Auth::guard('web')->forgetUser();

    $this->get(route('gestao.dashboard'))->assertForbidden();
    $this->get(route('home'))->assertRedirect(route('obra.pedidos.index'));
    $this->get(route('obra.pedidos.index'))->assertOk();

    expect(Auth::id())->toBe($user->id);
    expect(trailCount(AuthenticationEventType::SessionRevoked))->toBe(0);
    expect(trailCount(AuthenticationEventType::Logout))->toBe(0);
    expect(AuthenticationEvent::query()->count())->toBe(0);
});
