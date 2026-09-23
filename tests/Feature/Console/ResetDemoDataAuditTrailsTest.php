<?php

use App\Enums\AccountOrigin;
use App\Enums\AuthenticationEventType;
use App\Enums\ObraAdminAction;
use App\Enums\UserAdminAction;
use App\Models\AccountRegistrationEvent;
use App\Models\AuthenticationEvent;
use App\Models\EventType;
use App\Models\Obra;
use App\Models\ObraAdminEvent;
use App\Models\ObraInvitation;
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

/**
 * @param  array<string, mixed>  $attributes
 */
function invitationRow(Obra $obra, User $creator, array $attributes = []): ObraInvitation
{
    return ObraInvitation::factory()->for($obra)->create(['created_by' => $creator->id, ...$attributes]);
}

function obraAuditRow(User $actor, Obra $obra, ?ObraInvitation $invitation = null): int
{
    return ObraAdminEvent::query()->create([
        'actor_id' => $actor->id,
        'obra_id' => $obra->id,
        'obra_invitation_id' => $invitation?->id,
        'action' => $invitation === null ? ObraAdminAction::ObraUpdated : ObraAdminAction::InvitationCreated,
        'before' => null,
        'after' => ['name' => $obra->name],
    ])->id;
}

function registrationRow(User $user, ?ObraInvitation $invitation = null): int
{
    return AccountRegistrationEvent::query()->create([
        'user_id' => $user->id,
        'origin' => $invitation === null ? AccountOrigin::NovoCadastro : AccountOrigin::Convite,
        'obra_invitation_id' => $invitation?->id,
        'ip' => '127.0.0.1',
    ])->id;
}

test('demo:reset removes convites and obra/registration audits touching demo data in every combination and keeps the real-only rows (RF-35)', function () {
    $demoUser = createDemoUser('demo@example.com');
    $demoObraUser = User::factory()->obra()->create(['email' => 'demo-obra@example.com', 'is_demo' => true]);
    $realUser = createRealUser('real@example.com');
    $realObraUserA = User::factory()->obra()->create(['email' => 'real-obra-a@example.com', 'is_demo' => false]);
    $realObraUserB = User::factory()->obra()->create(['email' => 'real-obra-b@example.com', 'is_demo' => false]);
    $realObraUserC = User::factory()->obra()->create(['email' => 'real-obra-c@example.com', 'is_demo' => false]);

    $demoObra = Obra::factory()->create(['is_demo' => true]);
    $realObra = Obra::factory()->create(['is_demo' => false]);

    $invitations = [
        'demo_obra_used_by_real' => invitationRow($demoObra, $realUser, ['used_by' => $realObraUserA->id, 'used_at' => now()]),
        'demo_creator' => invitationRow($realObra, $demoUser),
        'demo_revoker' => invitationRow($realObra, $realUser, ['revoked_by' => $demoUser->id, 'revoked_at' => now()]),
        'demo_consumer' => invitationRow($realObra, $realUser, ['used_by' => $demoObraUser->id, 'used_at' => now()]),
        'real_used' => invitationRow($realObra, $realUser, ['used_by' => $realObraUserB->id, 'used_at' => now()]),
        'real_revoked' => invitationRow($realObra, $realUser, ['revoked_by' => $realUser->id, 'revoked_at' => now()]),
        'real_pending' => invitationRow($realObra, $realUser),
    ];

    $obraAudits = [
        'demo_obra' => obraAuditRow($realUser, $demoObra),
        'demo_actor' => obraAuditRow($demoUser, $realObra),
        'doomed_invitation' => obraAuditRow($realUser, $realObra, $invitations['demo_creator']),
        'doomed_consumed' => obraAuditRow($realObraUserA, $demoObra, $invitations['demo_obra_used_by_real']),
        'real_invitation' => obraAuditRow($realUser, $realObra, $invitations['real_used']),
        'real_pending' => obraAuditRow($realUser, $realObra, $invitations['real_pending']),
        'real_obra' => obraAuditRow($realUser, $realObra),
    ];

    $registrations = [
        'demo_novo_cadastro' => registrationRow($demoObraUser),
        'demo_convite' => registrationRow($demoObraUser, $invitations['demo_consumer']),
        'real_user_doomed_invitation' => registrationRow($realObraUserA, $invitations['demo_obra_used_by_real']),
        'real_convite' => registrationRow($realObraUserB, $invitations['real_used']),
        'real_novo_cadastro' => registrationRow($realObraUserC),
    ];

    $this->artisan('demo:reset', ['--force' => true])->assertExitCode(0);

    foreach (['demo_obra_used_by_real', 'demo_creator', 'demo_revoker', 'demo_consumer'] as $key) {
        expect(ObraInvitation::query()->whereKey($invitations[$key]->id)->exists())->toBeFalse("convite {$key} deveria ter sido removido");
    }

    foreach (['real_used', 'real_revoked', 'real_pending'] as $key) {
        expect(ObraInvitation::query()->whereKey($invitations[$key]->id)->exists())->toBeTrue("convite {$key} deveria sobreviver");
    }

    foreach (['demo_obra', 'demo_actor', 'doomed_invitation', 'doomed_consumed'] as $key) {
        expect(ObraAdminEvent::query()->whereKey($obraAudits[$key])->exists())->toBeFalse("auditoria {$key} deveria ter sido removida");
    }

    foreach (['real_invitation', 'real_pending', 'real_obra'] as $key) {
        expect(ObraAdminEvent::query()->whereKey($obraAudits[$key])->exists())->toBeTrue("auditoria {$key} deveria sobreviver");
    }

    foreach (['demo_novo_cadastro', 'demo_convite', 'real_user_doomed_invitation'] as $key) {
        expect(AccountRegistrationEvent::query()->whereKey($registrations[$key])->exists())->toBeFalse("registro {$key} deveria ter sido removido");
    }

    foreach (['real_convite', 'real_novo_cadastro'] as $key) {
        expect(AccountRegistrationEvent::query()->whereKey($registrations[$key])->exists())->toBeTrue("registro {$key} deveria sobreviver");
    }

    expect(ObraInvitation::query()->count())->toBe(3);
    expect(ObraAdminEvent::query()->count())->toBe(3);
    expect(AccountRegistrationEvent::query()->count())->toBe(2);
    expect(Obra::query()->whereKey($demoObra->id)->exists())->toBeFalse();
    expect(Obra::query()->whereKey($realObra->id)->exists())->toBeTrue();
    expect(User::query()->where('is_demo', true)->count())->toBe(0);
    expect(User::query()->whereKey([$realUser->id, $realObraUserA->id, $realObraUserB->id, $realObraUserC->id])->count())->toBe(4);

    $survivor = AccountRegistrationEvent::query()->findOrFail($registrations['real_novo_cadastro']);
    expect($survivor->origin)->toBe(AccountOrigin::NovoCadastro);
    expect($survivor->user_id)->toBe($realObraUserC->id);
});

test('deleting a convite, obra or user referenced by the new trails is refused by the restrict FKs (CT-02, CT-07)', function () {
    $creator = createRealUser('criador@example.com');
    $obra = Obra::factory()->create(['is_demo' => false]);
    $invitation = invitationRow($obra, $creator);
    $registered = User::factory()->obra()->create(['email' => 'registrado@example.com', 'is_demo' => false]);

    obraAuditRow($creator, $obra, $invitation);
    registrationRow($registered, $invitation);

    expect(fn () => DB::transaction(fn () => DB::table('obra_invitations')->where('id', $invitation->id)->delete()))->toThrow(QueryException::class);
    expect(fn () => DB::transaction(fn () => $obra->delete()))->toThrow(QueryException::class);
    expect(fn () => DB::transaction(fn () => $creator->delete()))->toThrow(QueryException::class);
    expect(fn () => DB::transaction(fn () => $registered->delete()))->toThrow(QueryException::class);

    expect(ObraInvitation::query()->whereKey($invitation->id)->exists())->toBeTrue();
    expect(Obra::query()->whereKey($obra->id)->exists())->toBeTrue();
    expect(User::query()->whereKey([$creator->id, $registered->id])->count())->toBe(2);
});
