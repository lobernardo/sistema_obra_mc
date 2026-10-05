<?php

use App\Models\InternalNotification;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T02 — CT-02, RF-22, RNF-04: schema of `internal_notifications`.
 *
 * As in `ObraStatusMigrationTest`, `down()` is invoked on the migration
 * object directly; PostgreSQL DDL is transactional, so the drop is rolled
 * back with the test transaction.
 */
const INTERNAL_NOTIFICATIONS_MIGRATION_FILE = '2026_10_01_184752_create_internal_notifications_table.php';

const ALLOW_IGNORADO_MIGRATION_FILE = '2026_10_05_224532_allow_ignorado_in_internal_notifications_email_status.php';

function internalNotificationsEmailStatusCheckDefinition(): string
{
    return (string) DB::scalar(
        "select pg_get_constraintdef(oid) from pg_constraint where conname = 'internal_notifications_email_status_check'",
    );
}

/**
 * @return array<string, mixed>|null
 */
function internalNotificationsForeignKey(string $column): ?array
{
    foreach (Schema::getForeignKeys('internal_notifications') as $foreignKey) {
        if ($foreignKey['columns'] === [$column]) {
            return $foreignKey;
        }
    }

    return null;
}

function internalNotificationsIndexDefinition(string $name): ?string
{
    $definition = DB::scalar(
        "select indexdef from pg_indexes where tablename = 'internal_notifications' and indexname = ?",
        [$name],
    );

    return $definition === null ? null : (string) $definition;
}

/**
 * @return array<string, mixed>
 */
function internalNotificationRowFor(InternalNotification $existing, array $overrides = []): array
{
    return array_merge([
        'recipient_id' => $existing->recipient_id,
        'pedido_id' => $existing->pedido_id,
        'pedido_event_id' => $existing->pedido_event_id,
        'event_type_slug' => $existing->event_type_slug,
        'actor_id' => $existing->actor_id,
    ], $overrides);
}

test('the table has exactly the CT-02 columns and no updated_at', function () {
    expect(array_column(Schema::getColumns('internal_notifications'), 'name'))->toEqualCanonicalizing([
        'id', 'recipient_id', 'pedido_id', 'pedido_event_id', 'event_type_slug', 'actor_id',
        'created_at', 'read_at', 'email_status', 'email_status_at',
    ]);

    $columns = collect(Schema::getColumns('internal_notifications'))->keyBy('name');

    expect($columns['read_at']['nullable'])->toBeTrue();
    expect($columns['email_status_at']['nullable'])->toBeTrue();
    expect($columns['created_at']['nullable'])->toBeFalse();
    expect($columns['email_status']['nullable'])->toBeFalse();
    expect($columns['email_status']['type'])->toBe('character varying(10)');
    expect($columns['email_status']['default'])->toContain('pendente');
    expect($columns['event_type_slug']['type'])->toBe('character varying(40)');
});

test('foreign keys point to the right tables with the right delete actions', function (string $column, string $table, string $onDelete) {
    $foreignKey = internalNotificationsForeignKey($column);

    expect($foreignKey)->not->toBeNull();
    expect($foreignKey['foreign_table'])->toBe($table);
    expect($foreignKey['foreign_columns'])->toBe(['id']);
    expect($foreignKey['on_delete'])->toBe($onDelete);
})->with([
    'recipient' => ['recipient_id', 'users', 'restrict'],
    'pedido' => ['pedido_id', 'pedidos', 'cascade'],
    'event' => ['pedido_event_id', 'pedido_events', 'cascade'],
    'actor' => ['actor_id', 'users', 'restrict'],
]);

test('one notification per event and recipient is enforced by a unique index', function () {
    $existing = InternalNotification::factory()->create();

    expect(fn () => DB::table('internal_notifications')->insert(internalNotificationRowFor($existing)))
        ->toThrow(UniqueConstraintViolationException::class);
});

test('the e-mail status defaults to pendente and the check rejects unknown values', function () {
    $existing = InternalNotification::factory()->create();
    $other = InternalNotification::factory()->create();

    $id = DB::table('internal_notifications')->insertGetId(internalNotificationRowFor($existing, ['recipient_id' => $other->recipient_id]));

    expect(DB::table('internal_notifications')->where('id', $id)->value('email_status'))->toBe('pendente');
    expect(DB::table('internal_notifications')->where('id', $id)->value('created_at'))->not->toBeNull();

    expect(fn () => DB::table('internal_notifications')->where('id', $id)->update(['email_status' => 'lido']))
        ->toThrow(QueryException::class, 'internal_notifications_email_status_check');
});

test('the unread index is partial on read_at is null, ordered by created_at desc', function () {
    $definition = internalNotificationsIndexDefinition('internal_notifications_unread_index');

    expect($definition)->not->toBeNull();
    expect($definition)->toContain('(recipient_id, created_at DESC)');
    expect($definition)->toContain('WHERE (read_at IS NULL)');
});

test('the page index covers recipient and created_at', function () {
    $index = collect(Schema::getIndexes('internal_notifications'))
        ->first(fn (array $index): bool => $index['columns'] === ['recipient_id', 'created_at'] && ! str_contains((string) internalNotificationsIndexDefinition($index['name']), 'WHERE'));

    expect($index)->not->toBeNull();
});

test('deleting the pedido cascades to its notifications', function () {
    $notification = InternalNotification::factory()->create();

    DB::table('pedidos')->where('id', $notification->pedido_id)->delete();

    expect(DB::table('internal_notifications')->where('id', $notification->id)->exists())->toBeFalse();
});

test('deleting the source event cascades to its notifications', function () {
    $notification = InternalNotification::factory()->create();

    DB::table('pedido_events')->where('id', $notification->pedido_event_id)->delete();

    expect(DB::table('internal_notifications')->where('id', $notification->id)->exists())->toBeFalse();
});

test('a recipient with notifications cannot be deleted', function () {
    $notification = InternalNotification::factory()->create();

    expect(fn () => DB::table('users')->where('id', $notification->recipient_id)->delete())
        ->toThrow(QueryException::class);
});

test('down drops the table', function () {
    $migration = require database_path('migrations/'.INTERNAL_NOTIFICATIONS_MIGRATION_FILE);

    $migration->down();

    expect(Schema::hasTable('internal_notifications'))->toBeFalse();

    $migration->up();

    expect(Schema::hasTable('internal_notifications'))->toBeTrue();
});

test('the e-mail status check accepts ignorado (RF-03, CT-03)', function () {
    $notification = InternalNotification::factory()->create();

    DB::table('internal_notifications')->where('id', $notification->id)->update(['email_status' => 'ignorado']);

    expect(DB::table('internal_notifications')->where('id', $notification->id)->value('email_status'))->toBe('ignorado');
});

test('the e-mail status check still rejects values outside the four states (CT-03)', function (string $value) {
    $notification = InternalNotification::factory()->create();

    expect(fn () => DB::table('internal_notifications')->where('id', $notification->id)->update(['email_status' => $value]))
        ->toThrow(QueryException::class, 'internal_notifications_email_status_check');
})->with(['lido', 'IGNORADO', '']);

test('down() of the ignorado migration aborts in PT-BR without touching the check while an ignorado row exists (CT-03)', function () {
    $notification = InternalNotification::factory()->create();
    DB::table('internal_notifications')->where('id', $notification->id)->update(['email_status' => 'ignorado']);

    $migration = require database_path('migrations/'.ALLOW_IGNORADO_MIGRATION_FILE);

    expect(fn () => $migration->down())->toThrow(RuntimeException::class, 'Rollback abortado');

    expect(internalNotificationsEmailStatusCheckDefinition())->toContain('ignorado');
    expect(DB::table('internal_notifications')->where('id', $notification->id)->value('email_status'))->toBe('ignorado');
});

test('down() of the ignorado migration restores the three original states, and up() brings ignorado back (CT-03)', function () {
    $notification = InternalNotification::factory()->create();
    $migration = require database_path('migrations/'.ALLOW_IGNORADO_MIGRATION_FILE);

    $migration->down();

    expect(internalNotificationsEmailStatusCheckDefinition())->not->toContain('ignorado');
    expect(fn () => DB::transaction(fn () => DB::table('internal_notifications')->where('id', $notification->id)->update(['email_status' => 'ignorado'])))
        ->toThrow(QueryException::class, 'internal_notifications_email_status_check');

    $migration->up();

    DB::table('internal_notifications')->where('id', $notification->id)->update(['email_status' => 'ignorado']);

    expect(DB::table('internal_notifications')->where('id', $notification->id)->value('email_status'))->toBe('ignorado');
});
