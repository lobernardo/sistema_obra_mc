<?php

use App\Actions\Usuarios\SetUserActiveAction;
use App\Actions\Usuarios\UpdateUserAction;
use App\Enums\AuthenticationEventType;
use App\Enums\RoleSlug;
use App\Http\Middleware\EnsureUserIsActive;
use App\Livewire\Auth\LoginForm;
use App\Livewire\Auth\ResetPassword;
use App\Models\AuthenticationEvent;
use App\Models\Obra;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;

/**
 * RF-30 (G-09..G-11), RF-14, RF-16, RF-17, AC-F12, AC-F13, AC-F16 —
 * adversarial session suite (decision D-12): what a live session can still
 * do after Gestão or the user themself changes the account behind it.
 * Duplicating the proofs of Phases D–F is intentional: this file is the
 * single runnable security regression pack.
 *
 * Simulation (suite runs `SESSION_DRIVER=array`):
 * - G-09/G-10: the guard singleton of the test kernel caches the user
 *   object between requests, while production rebuilds it from `users` on
 *   every request by the id kept in the session. `adversarialSignIn()`
 *   writes that id and `adversarialReloadUser()` drops the cached object,
 *   so the next request reads `role_id`/`is_active` fresh from the table.
 * - G-11: "session A" and "session B" are requests carrying
 *   `withSession(['password_hash_web' => $previousHash])` plus
 *   `actingAs($user)`, where `$previousHash` is what `AuthenticateSession`
 *   itself stored on an authenticated GET before the reset — exactly what a
 *   cookie-backed session would still hold after the server-side change.
 *
 * Every assertion is an HTTP status, `Auth::check()` or a trail row count.
 */
const ADVERSARIAL_SESSION_HASH_KEY = 'password_hash_web';

const ADVERSARIAL_PASSWORD_BEFORE = 'g11-senha-anterior-123';

const ADVERSARIAL_PASSWORD_AFTER = 'g11-senha-posterior-456';

function adversarialSignIn(User $user): void
{
    test()->actingAs($user);

    session()->put(Auth::guard('web')->getName(), $user->getAuthIdentifier());
}

function adversarialReloadUser(): void
{
    Auth::guard('web')->forgetUser();
}

function adversarialForgetBrowser(): void
{
    Auth::guard('web')->forgetUser();
    session()->flush();
}

function adversarialRequestWithStoredHash(User $user, string $storedHash, string $routeName): TestResponse
{
    adversarialForgetBrowser();

    return test()
        ->withSession([ADVERSARIAL_SESSION_HASH_KEY => $storedHash])
        ->actingAs($user->fresh())
        ->get(route($routeName));
}

function adversarialTrailCount(AuthenticationEventType $event, ?int $userId = null): int
{
    return AuthenticationEvent::query()
        ->where('event', $event->value)
        ->when($userId !== null, fn ($query) => $query->where('user_id', $userId))
        ->count();
}

test('G-09 a gestao downgraded to obra via UpdateUserAction keeps the session: old area 403, Auth::check() true, /home reroutes, no session_revoked/logout row (RF-17, D-10, AC-F13)', function () {
    $user = User::factory()->gestao()->create();
    $admin = User::factory()->gestao()->create();
    $obra = Obra::factory()->create();
    $obraRole = Role::query()->firstOrCreate(['slug' => RoleSlug::Obra->value], ['name' => 'Obra']);

    adversarialSignIn($user);
    $this->get(route('gestao.dashboard'))->assertOk();
    $this->get(route('gestao.usuarios.index'))->assertOk();

    app(UpdateUserAction::class)->execute($admin, User::query()->findOrFail($user->id), [
        'name' => $user->name,
        'email' => $user->email,
        'role_id' => $obraRole->id,
        'obra_ids' => [$obra->id],
    ]);
    adversarialReloadUser();

    $this->get(route('gestao.dashboard'))->assertForbidden();
    expect(Auth::check())->toBeTrue();
    expect(Auth::id())->toBe($user->id);

    $this->get(route('gestao.usuarios.index'))->assertForbidden();
    $this->get(route('gestao.pedidos.index'))->assertForbidden();
    $this->get(route('suprimentos.kanban'))->assertForbidden();
    expect(Auth::check())->toBeTrue();

    $this->get(route('home'))->assertRedirect(route('obra.pedidos.index'));
    $this->get(route('obra.pedidos.index'))->assertOk();

    expect(Auth::id())->toBe($user->id);
    expect(Auth::user()->role->slug)->toBe(RoleSlug::Obra->value);
    expect(adversarialTrailCount(AuthenticationEventType::SessionRevoked, $user->id))->toBe(0);
    expect(adversarialTrailCount(AuthenticationEventType::Logout, $user->id))->toBe(0);
    expect(AuthenticationEvent::query()->count())->toBe(0);
});

test('G-10 a user deactivated via SetUserActiveAction is redirected to login on the next request of the live session, with exactly 1 session_revoked and 0 logout rows (RF-16, AC-F12)', function () {
    $user = User::factory()->suprimentos()->create();
    $gestao = User::factory()->gestao()->create();

    adversarialSignIn($user);
    $this->get(route('suprimentos.kanban'))->assertOk();
    expect(Auth::check())->toBeTrue();

    app(SetUserActiveAction::class)->execute($gestao, User::query()->findOrFail($user->id), false);
    adversarialReloadUser();

    $this->get(route('suprimentos.kanban'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('status', EnsureUserIsActive::DEACTIVATED_MESSAGE);

    expect(Auth::check())->toBeFalse();
    expect(adversarialTrailCount(AuthenticationEventType::SessionRevoked))->toBe(1);
    expect(adversarialTrailCount(AuthenticationEventType::SessionRevoked, $user->id))->toBe(1);
    expect(adversarialTrailCount(AuthenticationEventType::Logout))->toBe(0);

    $this->get(route('suprimentos.kanban'))->assertRedirect(route('login'));
    expect(Auth::check())->toBeFalse();
    expect(adversarialTrailCount(AuthenticationEventType::SessionRevoked))->toBe(1);
});

test('G-11 two sessions opened before a password reset are both refused afterwards, each cut recorded as session_revoked, and the new password logs in (RF-14, RF-15, AC-F16)', function () {
    $user = User::factory()->obra()->create([
        'email' => 'g11@example.com',
        'password' => Hash::make(ADVERSARIAL_PASSWORD_BEFORE),
    ]);
    $rememberTokenBefore = $user->remember_token;

    $this->actingAs($user)->get(route('obra.pedidos.index'))->assertOk();
    $previousHash = session(ADVERSARIAL_SESSION_HASH_KEY);
    expect($previousHash)->toBeString()->not->toBe('');

    adversarialRequestWithStoredHash($user, $previousHash, 'obra.pedidos.index')->assertOk();

    adversarialForgetBrowser();
    $token = Password::broker('users')->createToken($user);

    Livewire::test(ResetPassword::class, ['token' => $token])
        ->set('email', $user->email)
        ->set('password', ADVERSARIAL_PASSWORD_AFTER)
        ->set('password_confirmation', ADVERSARIAL_PASSWORD_AFTER)
        ->call('resetPassword')
        ->assertHasNoErrors()
        ->assertRedirect(route('login'));

    expect(Hash::check(ADVERSARIAL_PASSWORD_AFTER, $user->fresh()->password))->toBeTrue();
    expect($user->fresh()->remember_token)->not->toBe($rememberTokenBefore);
    expect(adversarialTrailCount(AuthenticationEventType::SessionRevoked))->toBe(0);

    adversarialRequestWithStoredHash($user, $previousHash, 'obra.pedidos.index')->assertRedirect(route('login'));
    expect(Auth::check())->toBeFalse();
    expect(adversarialTrailCount(AuthenticationEventType::SessionRevoked, $user->id))->toBe(1);

    adversarialRequestWithStoredHash($user, $previousHash, 'obra.pedidos.index')->assertRedirect(route('login'));
    expect(Auth::check())->toBeFalse();
    expect(adversarialTrailCount(AuthenticationEventType::SessionRevoked, $user->id))->toBe(2);
    expect(adversarialTrailCount(AuthenticationEventType::Logout))->toBe(0);

    adversarialForgetBrowser();

    Livewire::test(LoginForm::class)
        ->set('email', 'g11@example.com')
        ->set('password', ADVERSARIAL_PASSWORD_BEFORE)
        ->call('authenticate')
        ->assertHasErrors(['email']);
    expect(Auth::check())->toBeFalse();

    Livewire::test(LoginForm::class)
        ->set('email', 'g11@example.com')
        ->set('password', ADVERSARIAL_PASSWORD_AFTER)
        ->call('authenticate')
        ->assertHasNoErrors()
        ->assertRedirect(route('home'));

    $this->get(route('obra.pedidos.index'))->assertOk();
    expect(Auth::id())->toBe($user->id);
    expect(session(ADVERSARIAL_SESSION_HASH_KEY))->not->toBe($previousHash);
    expect(adversarialTrailCount(AuthenticationEventType::SessionRevoked))->toBe(2);
});
