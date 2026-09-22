<?php

use App\Livewire\Auth\AcceptInvite;
use App\Models\AuthenticationEvent;
use App\Models\EventType;
use App\Models\Obra;
use App\Models\Pedido;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

/**
 * RF-05, RF-06, RF-08, RF-09, RNF-09 and CT-07 for the Fase 0 migration.
 *
 * The migration object is loaded from `database/migrations/` and its `up()` /
 * `down()` are invoked directly. That is exactly what `php artisan migrate`
 * and `php artisan migrate:rollback` execute, and it is the only way to
 * exercise them here: `tests/Pest.php` applies `RefreshDatabase` to the whole
 * Feature suite, so every migration is already applied inside one batch and a
 * real `migrate:rollback` would tear the entire schema down mid-test.
 *
 * PostgreSQL DDL is transactional, so `create`/`drop index` inside the test
 * transaction is rolled back with it and no run leaks into the next.
 */
const EMAIL_MIGRATION_FILE = '2026_09_22_155011_normalize_user_emails_and_add_lower_unique_index.php';

const EMAIL_LOWER_INDEX = 'users_email_lower_unique';

function emailNormalizationMigration(): object
{
    return require database_path('migrations/'.EMAIL_MIGRATION_FILE);
}

function emailLowerIndexExists(): bool
{
    foreach (Schema::getIndexes('users') as $index) {
        if ($index['name'] === EMAIL_LOWER_INDEX) {
            return true;
        }
    }

    return false;
}

/**
 * `pg_get_indexdef` for the functional index, or `null` when it is absent.
 */
function emailLowerIndexDefinition(): ?string
{
    $rows = DB::select(
        'select indexdef from pg_indexes where tablename = ? and indexname = ?',
        ['users', EMAIL_LOWER_INDEX],
    );

    return $rows === [] ? null : (string) $rows[0]->indexdef;
}

/**
 * Writes an e-mail straight through the query builder, bypassing every
 * application-layer normalization, to reproduce a pre-Fase-0 row.
 */
function storeRawUserEmail(User $user, string $email): void
{
    DB::table('users')->where('id', $user->getKey())->update(['email' => $email]);
}

function storeRawResetToken(string $email, string $token): void
{
    DB::table('password_reset_tokens')->insert([
        'email' => $email,
        'token' => Hash::make($token),
        'created_at' => now(),
    ]);
}

/**
 * The migration source with every comment and docblock stripped, so a
 * prohibition can be asserted against real code without flagging the prose
 * that documents the same prohibition.
 */
function migrationCodeWithoutComments(string $source): string
{
    $tokens = array_filter(
        PhpToken::tokenize($source),
        fn (PhpToken $token): bool => ! $token->is([T_COMMENT, T_DOC_COMMENT]),
    );

    return implode('', array_map(fn (PhpToken $token): string => $token->text, $tokens));
}

/**
 * Byte-level snapshot of `users`, used by the idempotency criterion.
 *
 * @return list<array<string, mixed>>
 */
function usersByteSnapshot(): array
{
    return array_map(fn (object $row): array => (array) $row, DB::select('select * from users order by id'));
}

/**
 * One row in each of the three append-only trails, every payload carrying a
 * mixed-case e-mail, plus their byte-level snapshot.
 *
 * @return array<string, list<array<string, mixed>>>
 */
function auditTrailsByteSnapshot(): array
{
    return [
        'pedido_events' => array_map(fn (object $row): array => (array) $row, DB::select('select * from pedido_events order by id')),
        'user_admin_events' => array_map(fn (object $row): array => (array) $row, DB::select('select * from user_admin_events order by id')),
        'authentication_events' => array_map(fn (object $row): array => (array) $row, DB::select('select * from authentication_events order by id')),
    ];
}

beforeEach(function () {
    $this->migration = emailNormalizationMigration();

    // The suite starts with the migration already applied; drop the index so
    // each case can plant pre-migration data and run `up()` itself.
    $this->migration->down();

    expect(emailLowerIndexExists())->toBeFalse();
});

test('the migration rewrites both e-mail columns to their canonical form (RF-05)', function () {
    $first = User::factory()->gestao()->create();
    $second = User::factory()->obra()->create();

    storeRawUserEmail($first, 'Marcelo@Example.com');
    storeRawUserEmail($second, '  ANA@Example.ORG  ');
    storeRawResetToken('Marcelo@Example.com', 'token-um');
    storeRawResetToken(' Bruno@Example.com ', 'token-dois');

    expect(DB::scalar('select count(*) from users where email <> lower(email)'))->toBeGreaterThan(0);

    $this->migration->up();

    expect((int) DB::scalar('select count(*) from users where email <> lower(email)'))->toBe(0);
    expect((int) DB::scalar('select count(*) from password_reset_tokens where email <> lower(email)'))->toBe(0);

    expect(DB::table('users')->where('id', $first->getKey())->value('email'))->toBe('marcelo@example.com');
    expect(DB::table('users')->where('id', $second->getKey())->value('email'))->toBe('ana@example.org');
    expect(DB::table('password_reset_tokens')->pluck('email')->sort()->values()->all())
        ->toBe(['bruno@example.com', 'marcelo@example.com']);
});

test('an invite token issued before the migration still completes the first-access flow afterwards (RF-05, RF-04)', function () {
    $user = User::factory()->suprimentos()->create(['email' => 'marcelo@example.com']);

    // Pre-Fase-0 state: the column keeps the typed casing and the broker
    // wrote the token row against that same mixed-case address.
    storeRawUserEmail($user, 'Marcelo@Example.com');
    $token = Password::broker('invites')->createToken($user->fresh());

    expect(DB::table('password_reset_tokens')->where('email', 'Marcelo@Example.com')->exists())->toBeTrue();

    $this->migration->up();

    $randomHash = $user->fresh()->password;

    Livewire::withQueryParams(['email' => 'Marcelo@Example.com'])
        ->test(AcceptInvite::class, ['token' => $token])
        ->set('password', 'senha-pos-migracao-2026')
        ->set('password_confirmation', 'senha-pos-migracao-2026')
        ->call('acceptInvite')
        ->assertHasNoErrors()
        ->assertRedirect(route('login'));

    $user->refresh();

    expect($user->email)->toBe('marcelo@example.com');
    expect(Hash::check('senha-pos-migracao-2026', $user->password))->toBeTrue();
    expect($user->password)->not->toBe($randomHash);
    expect(DB::table('password_reset_tokens')->where('email', 'marcelo@example.com')->exists())->toBeFalse();
});

test('a collision in users aborts the migration, names both addresses and leaves everything unchanged (RF-06)', function () {
    $first = User::factory()->obra()->create();
    $second = User::factory()->obra()->create();

    storeRawUserEmail($first, 'a@example.com');
    storeRawUserEmail($second, 'A@example.com');

    $before = usersByteSnapshot();

    try {
        $this->migration->up();

        $this->fail('Expected a RuntimeException.');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())
            ->toContain('Normalização de e-mail abortada')
            ->toContain('users')
            ->toContain('a@example.com')
            ->toContain('A@example.com');
    }

    expect(usersByteSnapshot())->toBe($before);
    expect(emailLowerIndexExists())->toBeFalse();
    expect(DB::table('users')->where('id', $first->getKey())->value('email'))->toBe('a@example.com');
    expect(DB::table('users')->where('id', $second->getKey())->value('email'))->toBe('A@example.com');
});

test('a collision in password_reset_tokens alone also aborts before any write (RF-06)', function () {
    $user = User::factory()->obra()->create();
    storeRawUserEmail($user, 'Ana@Example.com');
    storeRawResetToken('ana@example.com', 'token-um');
    storeRawResetToken('Ana@Example.com', 'token-dois');

    $usersBefore = usersByteSnapshot();
    $tokensBefore = DB::table('password_reset_tokens')->orderBy('email')->pluck('email')->all();

    expect(fn () => $this->migration->up())
        ->toThrow(RuntimeException::class);

    expect(usersByteSnapshot())->toBe($usersBefore);
    expect(DB::table('password_reset_tokens')->orderBy('email')->pluck('email')->all())->toBe($tokensBefore);
    expect(emailLowerIndexExists())->toBeFalse();
});

test('the functional unique index is created with the lower(email) expression and the column type is unchanged (RF-08, CT-07)', function () {
    $this->migration->up();

    $index = collect(Schema::getIndexes('users'))->firstWhere('name', EMAIL_LOWER_INDEX);

    expect($index)->not->toBeNull();
    expect($index['unique'])->toBeTrue();
    expect($index['primary'])->toBeFalse();
    expect(emailLowerIndexDefinition())->toContain('lower((email)::text)');

    $emailColumn = collect(Schema::getColumns('users'))->firstWhere('name', 'email');

    expect($emailColumn['type'])->toBe('character varying(255)');
});

test('a raw insert of a case variant is refused by the database with the application layer bypassed (RF-08)', function () {
    $existing = User::factory()->obra()->create(['email' => 'a@example.com']);

    $this->migration->up();

    expect(fn () => DB::table('users')->insert([
        'name' => 'Forjado',
        'email' => 'A@EXAMPLE.com',
        'password' => Hash::make('senha-forjada-2026'),
        'role_id' => $existing->role_id,
        'is_active' => true,
        'is_demo' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(UniqueConstraintViolationException::class);
});

test('rollback drops the index without touching the column type (RF-08, RNF-09)', function () {
    $this->migration->up();

    expect(emailLowerIndexExists())->toBeTrue();

    $this->migration->down();

    expect(emailLowerIndexExists())->toBeFalse();
    expect(emailLowerIndexDefinition())->toBeNull();

    $emailColumn = collect(Schema::getColumns('users'))->firstWhere('name', 'email');

    expect($emailColumn['type'])->toBe('character varying(255)');

    // The unique constraint declared by the original users migration survives.
    expect(collect(Schema::getIndexes('users'))->firstWhere('name', 'users_email_unique'))->not->toBeNull();
});

test('migrate, rollback and migrate again on already-normalized data succeed and leave users byte-identical (RNF-09)', function () {
    User::factory()->gestao()->create(['email' => 'gestora@example.com']);
    User::factory()->obra()->create(['email' => 'obra@example.com']);
    storeRawResetToken('obra@example.com', 'token-um');

    $this->migration->up();

    $afterFirstMigration = usersByteSnapshot();
    $tokensAfterFirstMigration = DB::table('password_reset_tokens')->orderBy('email')->pluck('email')->all();

    $this->migration->down();
    $this->migration->up();
    $this->migration->down();
    $this->migration->up();

    expect(emailLowerIndexExists())->toBeTrue();
    expect(usersByteSnapshot())->toBe($afterFirstMigration);
    expect(DB::table('password_reset_tokens')->orderBy('email')->pluck('email')->all())->toBe($tokensAfterFirstMigration);
});

test('no row of the three append-only trails is touched by the migration (RF-09)', function () {
    $actor = User::factory()->gestao()->create();
    $target = User::factory()->obra()->create();
    storeRawUserEmail($target, 'Marcelo@Example.com');

    $obra = Obra::factory()->create();
    $target->obras()->attach($obra->id);
    $eventType = EventType::factory()->criacaoPedido()->create();
    $pedido = Pedido::factory()->create(['obra_id' => $obra->id, 'requester_id' => $target->id]);
    $pedido->events()->create([
        'event_type_id' => $eventType->id,
        'actor_id' => $target->id,
        'previous_value' => null,
        'new_value' => 'Marcelo@Example.com',
    ]);

    DB::table('user_admin_events')->insert([
        'actor_id' => $actor->id,
        'target_id' => $target->id,
        'action' => 'user_created',
        'before' => null,
        'after' => json_encode(['email' => 'Marcelo@Example.com']),
        'created_at' => now(),
    ]);

    AuthenticationEvent::query()->create([
        'event' => 'login_failed',
        'user_id' => $target->id,
        'email' => 'Marcelo@Example.com',
        'ip' => '203.0.113.10',
        'user_agent' => 'PHPUnit',
    ]);

    $before = auditTrailsByteSnapshot();

    expect($before['pedido_events'])->toHaveCount(1);
    expect($before['user_admin_events'])->toHaveCount(1);
    expect($before['authentication_events'])->toHaveCount(1);

    $this->migration->up();

    expect(auditTrailsByteSnapshot())->toBe($before);
    expect(DB::table('users')->where('id', $target->getKey())->value('email'))->toBe('marcelo@example.com');
});

test('the migration installs no extension, declares no citext and never deactivates an account (CT-07, RF-06)', function () {
    $source = file_get_contents(database_path('migrations/'.EMAIL_MIGRATION_FILE));
    $code = migrationCodeWithoutComments($source);

    foreach (['citext', 'extension', 'is_active', 'drop table', 'delete from', 'truncate', 'alter table'] as $forbidden) {
        $this->assertStringNotContainsStringIgnoringCase(
            $forbidden,
            $code,
            "A migration de Fase 0 não pode referenciar {$forbidden}.",
        );
    }

    expect($code)->toContain('create unique index');
    expect($code)->toContain('drop index if exists');
});

test('no code of the migration names a table other than users and password_reset_tokens (RF-09, CT-07)', function () {
    $code = migrationCodeWithoutComments(file_get_contents(database_path('migrations/'.EMAIL_MIGRATION_FILE)));

    foreach (['pedido_events', 'user_admin_events', 'authentication_events', 'pedidos', 'obras', 'obra_profile', 'sessions'] as $table) {
        $this->assertStringNotContainsString(
            $table,
            $code,
            "A migration de Fase 0 referencia a tabela {$table} em código.",
        );
    }

    expect($code)->toContain('users')->toContain('password_reset_tokens');
});
