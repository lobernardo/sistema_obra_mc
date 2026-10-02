<?php

use App\Livewire\Auth\AcceptInvite;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

/**
 * The migration object is loaded from `database/migrations/` and its `up()` /
 * `down()` are invoked directly, as in `EmailNormalizationMigrationTest`:
 * `RefreshDatabase` already applied it, so the test first drops the table to
 * reproduce the pre-deploy state where both brokers shared
 * `password_reset_tokens`. PostgreSQL DDL is transactional, so nothing leaks.
 */
function passwordInviteTokensMigration(): object
{
    return require database_path('migrations/2026_10_02_203543_create_password_invite_tokens_table.php');
}

function storeSharedTokenRow(string $email, string $token, DateTimeInterface $createdAt): void
{
    DB::table('password_reset_tokens')->insert([
        'email' => $email,
        'token' => Hash::make($token),
        'created_at' => $createdAt,
    ]);
}

function recordAccessLinkAudit(User $actor, User $target, string $action, DateTimeInterface $createdAt): void
{
    DB::table('user_admin_events')->insert([
        'actor_id' => $actor->id,
        'target_id' => $target->id,
        'action' => $action,
        'before' => null,
        'after' => null,
        'created_at' => $createdAt,
    ]);
}

beforeEach(function () {
    Schema::drop('password_invite_tokens');

    $this->gestao = User::factory()->gestao()->create();
});

test('pending invites move to password_invite_tokens and reset tokens stay where they were', function () {
    $expiredForReset = User::factory()->obra()->create(['email' => 'antigo@example.com']);
    $freshInvite = User::factory()->suprimentos()->create(['email' => 'reenviado@example.com']);
    $resetAfterInvite = User::factory()->obra()->create(['email' => 'reset@example.com']);
    $plainReset = User::factory()->obra()->create(['email' => 'esqueci@example.com']);

    storeSharedTokenRow('antigo@example.com', 'token-antigo', now()->subHours(2));

    storeSharedTokenRow('reenviado@example.com', 'token-reenviado', now()->subMinutes(10));
    recordAccessLinkAudit($this->gestao, $freshInvite, 'access_link_resent', now()->subMinutes(10));

    recordAccessLinkAudit($this->gestao, $resetAfterInvite, 'access_link_sent', now()->subDay());
    storeSharedTokenRow('reset@example.com', 'token-reset', now()->subMinutes(10));

    storeSharedTokenRow('esqueci@example.com', 'token-esqueci', now()->subMinutes(5));

    passwordInviteTokensMigration()->up();

    expect(DB::table('password_invite_tokens')->orderBy('email')->pluck('email')->all())
        ->toBe(['antigo@example.com', 'reenviado@example.com']);
    expect(DB::table('password_reset_tokens')->orderBy('email')->pluck('email')->all())
        ->toBe(['esqueci@example.com', 'reset@example.com']);

    Livewire::test(AcceptInvite::class, ['token' => 'token-reenviado'])
        ->set('email', 'reenviado@example.com')
        ->set('password', 'senha-do-convite-123')
        ->set('password_confirmation', 'senha-do-convite-123')
        ->call('acceptInvite')
        ->assertHasNoErrors()
        ->assertRedirect(route('login'));

    expect(Hash::check('senha-do-convite-123', $freshInvite->fresh()->password))->toBeTrue();
    expect($expiredForReset->fresh()->password)->not->toBeNull();
    expect($plainReset->fresh()->password)->not->toBeNull();
});

test('down() moves the invite rows back into password_reset_tokens and drops the table', function () {
    $user = User::factory()->obra()->create(['email' => 'antigo@example.com']);

    storeSharedTokenRow('antigo@example.com', 'token-antigo', now()->subHours(2));

    $migration = passwordInviteTokensMigration();
    $migration->up();
    $migration->down();

    expect(Schema::hasTable('password_invite_tokens'))->toBeFalse();
    expect(DB::table('password_reset_tokens')->where('email', $user->email)->exists())->toBeTrue();
});
