<?php

use App\Enums\AuthenticationEventType;
use App\Enums\UserAdminAction;
use App\Models\AuthenticationEvent;
use App\Models\EventType;
use App\Models\Pedido;
use App\Models\User;
use App\Models\UserAdminEvent;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * RF-23, RF-24, RF-28, D-05, D-11 — `demo:reset` is the single flow that
 * removes audit rows, and only those referencing a demo user, via
 * `DB::table(...)` before `users`. Fixture per trail:
 *
 *   (i)   demo → demo            deleted
 *   (ii)  real actor → demo target deleted (mixed row, D-05)
 *   (iii) demo actor → real target deleted (mixed row, D-05)
 *   (iv)  real → real            kept
 *   (v)   `login_failed` with `user_id = null` and a demo e-mail — kept
 *         (no FK to satisfy)
 *
 * The `restrictOnDelete` FKs make an Eloquent delete of a real user with
 * audit rows fail at the database (RF-24, RF-28).
 */
function createDemoUser(string $email): User
{
    return User::factory()->gestao()->create(['email' => $email, 'is_demo' => true]);
}

function createRealUser(string $email): User
{
    return User::factory()->gestao()->create(['email' => $email, 'is_demo' => false]);
}

function adminRow(User $actor, User $target): int
{
    return UserAdminEvent::query()->create([
        'actor_id' => $actor->id,
        'target_id' => $target->id,
        'action' => UserAdminAction::UserUpdated,
        'before' => ['name' => 'antes'],
        'after' => ['name' => 'depois'],
    ])->id;
}

function authRow(?User $user, string $email, AuthenticationEventType $event = AuthenticationEventType::LoginSuccess): int
{
    return AuthenticationEvent::query()->create([
        'event' => $event,
        'user_id' => $user?->id,
        'email' => $email,
        'ip' => '127.0.0.1',
        'user_agent' => 'Pest',
    ])->id;
}

test('demo:reset deletes every audit row referencing a demo user (mixed rows included) and keeps the real-only and null-user rows (RF-24, RF-28, D-05)', function () {
    $demoA = createDemoUser('demo-a@example.com');
    $demoB = createDemoUser('demo-b@example.com');
    $realA = createRealUser('real-a@example.com');
    $realB = createRealUser('real-b@example.com');

    $admin = [
        'i' => adminRow($demoA, $demoB),
        'ii' => adminRow($realA, $demoA),
        'iii' => adminRow($demoB, $realA),
        'iv' => adminRow($realA, $realB),
    ];

    $auth = [
        'i' => authRow($demoA, 'demo-a@example.com'),
        'ii' => authRow($demoB, 'demo-b@example.com', AuthenticationEventType::SessionRevoked),
        'iv' => authRow($realA, 'real-a@example.com'),
        'iv_b' => authRow($realB, 'real-b@example.com', AuthenticationEventType::Logout),
        'v' => authRow(null, 'demo-a@example.com', AuthenticationEventType::LoginFailed),
        'v_b' => authRow(null, 'ninguem@example.com', AuthenticationEventType::LoginFailed),
    ];

    expect(UserAdminEvent::query()->count())->toBe(4);
    expect(AuthenticationEvent::query()->count())->toBe(6);

    Artisan::call('demo:reset', ['--force' => true]);

    expect(Artisan::output())->toContain('Dados de demonstração removidos.');
    expect(User::query()->where('is_demo', true)->count())->toBe(0);
    expect(User::query()->whereKey($realA->id)->exists())->toBeTrue();
    expect(User::query()->whereKey($realB->id)->exists())->toBeTrue();

    expect(UserAdminEvent::query()->whereKey($admin['i'])->exists())->toBeFalse();
    expect(UserAdminEvent::query()->whereKey($admin['ii'])->exists())->toBeFalse();
    expect(UserAdminEvent::query()->whereKey($admin['iii'])->exists())->toBeFalse();
    expect(UserAdminEvent::query()->whereKey($admin['iv'])->exists())->toBeTrue();
    expect(UserAdminEvent::query()->count())->toBe(1);

    expect(AuthenticationEvent::query()->whereKey($auth['i'])->exists())->toBeFalse();
    expect(AuthenticationEvent::query()->whereKey($auth['ii'])->exists())->toBeFalse();
    expect(AuthenticationEvent::query()->whereKey($auth['iv'])->exists())->toBeTrue();
    expect(AuthenticationEvent::query()->whereKey($auth['iv_b'])->exists())->toBeTrue();
    expect(AuthenticationEvent::query()->whereKey($auth['v'])->exists())->toBeTrue();
    expect(AuthenticationEvent::query()->whereKey($auth['v_b'])->exists())->toBeTrue();
    expect(AuthenticationEvent::query()->count())->toBe(4);

    $survivor = AuthenticationEvent::query()->findOrFail($auth['v']);
    expect($survivor->user_id)->toBeNull();
    expect($survivor->email)->toBe('demo-a@example.com');
});

test('demo:reset succeeds when no audit row references a demo user and leaves every row in place', function () {
    $demo = createDemoUser('demo@example.com');
    $realA = createRealUser('real-a@example.com');
    $realB = createRealUser('real-b@example.com');

    $adminId = adminRow($realA, $realB);
    $authId = authRow($realA, 'real-a@example.com');
    $nullId = authRow(null, 'demo@example.com', AuthenticationEventType::LoginFailed);

    Artisan::call('demo:reset', ['--force' => true]);

    expect(User::query()->whereKey($demo->id)->exists())->toBeFalse();
    expect(UserAdminEvent::query()->whereKey($adminId)->exists())->toBeTrue();
    expect(AuthenticationEvent::query()->whereKey($authId)->exists())->toBeTrue();
    expect(AuthenticationEvent::query()->whereKey($nullId)->exists())->toBeTrue();
});

test('demo:reset is atomic: a failure after the audit deletes rolls the trail back', function () {
    $demo = createDemoUser('demo@example.com');
    $real = createRealUser('real@example.com');
    $adminId = adminRow($real, $demo);
    $authId = authRow($demo, 'demo@example.com');

    // A real pedido-independent FK to the demo user that the command does
    // not handle: a real user's audit row pointing at nothing else, plus a
    // foreign row that blocks the `users` delete. `pedido_events.actor_id`
    // is `restrictOnDelete` and is never touched by `demo:reset` when the
    // pedido itself is real.
    $realPedido = Pedido::factory()->create(['is_demo' => false]);
    $eventType = EventType::factory()->create();
    $realPedido->events()->create(['event_type_id' => $eventType->id, 'actor_id' => $demo->id]);

    expect(fn () => Artisan::call('demo:reset', ['--force' => true]))->toThrow(QueryException::class);

    expect(User::query()->whereKey($demo->id)->exists())->toBeTrue();
    expect(UserAdminEvent::query()->whereKey($adminId)->exists())->toBeTrue();
    expect(AuthenticationEvent::query()->whereKey($authId)->exists())->toBeTrue();
});

test('deleting a real user that has audit rows is refused by the restrict FKs (RF-24, RF-28)', function () {
    $actor = createRealUser('actor@example.com');
    $target = createRealUser('target@example.com');
    $authenticated = createRealUser('autenticado@example.com');

    adminRow($actor, $target);
    authRow($authenticated, 'autenticado@example.com');

    expect(fn () => DB::transaction(fn () => $actor->delete()))->toThrow(QueryException::class);
    expect(fn () => DB::transaction(fn () => $target->delete()))->toThrow(QueryException::class);
    expect(fn () => DB::transaction(fn () => $authenticated->delete()))->toThrow(QueryException::class);

    expect(User::query()->whereKey($actor->id)->exists())->toBeTrue();
    expect(User::query()->whereKey($target->id)->exists())->toBeTrue();
    expect(User::query()->whereKey($authenticated->id)->exists())->toBeTrue();
    expect(UserAdminEvent::query()->count())->toBe(1);
    expect(AuthenticationEvent::query()->count())->toBe(1);
});
