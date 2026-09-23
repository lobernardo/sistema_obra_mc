<?php

use App\Models\EventType;
use App\Models\Pedido;
use App\Models\PedidoEvent;
use App\Models\Status;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * RF-42, RNF-06 and CT-09 for the lookup-rows migration of
 * `solicitacao-historico-finalizacao` (status `finalizado`, event types
 * `observacao`, `romaneio_anexado` and `finalizacao`).
 *
 * As in `PedidoOutraDataPrevistaMigrationTest`, the migration objects are
 * loaded from `database/migrations/` and their `up()` / `down()` invoked
 * directly: `RefreshDatabase` applies every migration in one batch (and
 * never seeds), so a real `migrate:rollback` would tear the whole schema
 * down mid-test. Everything done here is rolled back with the test
 * transaction.
 */
const HISTORY_LOOKUP_MIGRATION_FILE = '2026_09_23_085756_insert_finalizado_status_and_history_event_types.php';

const HISTORY_LOOKUP_EVENT_TYPE_SLUGS = ['observacao', 'romaneio_anexado', 'finalizacao'];

/**
 * Migrations this file must sort after: slice 1 plus the other two slice 2
 * migrations (T02 and T03).
 */
const HISTORY_LOOKUP_EARLIER_MIGRATIONS = [
    '2026_09_23_040313_convert_obras_activity_to_status.php',
    '2026_09_23_042010_create_obra_invitations_table.php',
    '2026_09_23_042011_create_obra_admin_events_table.php',
    '2026_09_23_042012_create_account_registration_events_table.php',
    '2026_09_23_083524_add_outra_reference_and_data_prevista_to_pedidos.php',
    '2026_09_23_083916_create_pedido_attachments_table.php',
];

const HISTORY_LOOKUP_PROTECTED_TABLES = [
    'users',
    'obras',
    'obra_profile',
    'pedidos',
    'pedido_events',
    'statuses',
    'event_types',
    'user_admin_events',
    'authentication_events',
    'obra_invitations',
    'obra_admin_events',
    'account_registration_events',
];

function historyLookupMigration(): object
{
    return require database_path('migrations/'.HISTORY_LOOKUP_MIGRATION_FILE);
}

/**
 * @return array<string, int>
 */
function historyLookupProtectedCounts(): array
{
    return collect(HISTORY_LOOKUP_PROTECTED_TABLES)
        ->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->count()])
        ->all();
}

function historyLookupRowCount(): array
{
    return [
        'finalizado' => DB::table('statuses')->where('slug', 'finalizado')->count(),
        ...collect(HISTORY_LOOKUP_EVENT_TYPE_SLUGS)
            ->mapWithKeys(fn (string $slug): array => [$slug => DB::table('event_types')->where('slug', $slug)->count()])
            ->all(),
    ];
}

beforeEach(function () {
    $this->lookupMigration = historyLookupMigration();
});

test('without seeding, finalizado (sort 7) and the 3 history event types exist (RF-42, CT-09)', function () {
    expect(DB::table('roles')->count())->toBe(0, 'the test database must not be seeded');

    $finalizado = Status::query()->where('slug', 'finalizado')->sole();

    expect($finalizado->name)->toBe('Finalizado');
    expect($finalizado->sort_order)->toBe(7);
    expect($finalizado->is_active)->toBeTrue();

    expect(EventType::query()->whereIn('slug', HISTORY_LOOKUP_EVENT_TYPE_SLUGS)->pluck('name', 'slug')->all())
        ->toEqual([
            'observacao' => 'Observação adicionada',
            'romaneio_anexado' => 'Romaneio anexado',
            'finalizacao' => 'Pedido finalizado',
        ]);
});

test('running up() twice keeps exactly one row of each (idempotent)', function () {
    $this->lookupMigration->up();
    $this->lookupMigration->up();

    expect(historyLookupRowCount())->toBe([
        'finalizado' => 1,
        'observacao' => 1,
        'romaneio_anexado' => 1,
        'finalizacao' => 1,
    ]);
});

test('up() never updates an existing row', function () {
    DB::table('event_types')->where('slug', 'observacao')->update(['name' => 'Nome editado']);
    DB::table('statuses')->where('slug', 'finalizado')->update(['name' => 'Finalizado (editado)']);

    $this->lookupMigration->up();

    expect(DB::table('event_types')->where('slug', 'observacao')->value('name'))->toBe('Nome editado');
    expect(DB::table('statuses')->where('slug', 'finalizado')->value('name'))->toBe('Finalizado (editado)');
});

test('with sort_order 7 taken by another slug, up() throws and inserts nothing', function () {
    $this->lookupMigration->down();

    Status::factory()->create(['slug' => 'outro_status', 'sort_order' => 7]);

    $before = historyLookupProtectedCounts();

    expect(fn () => $this->lookupMigration->up())
        ->toThrow(RuntimeException::class, 'sort_order 7 já pertence ao status "outro_status"');

    expect(historyLookupProtectedCounts())->toBe($before);
    expect(historyLookupRowCount())->toBe([
        'finalizado' => 0,
        'observacao' => 0,
        'romaneio_anexado' => 0,
        'finalizacao' => 0,
    ]);
});

test('data-bearing row counts are unchanged except +1 statuses and +3 event_types (RF-42, RNF-06)', function () {
    $status = Status::factory()->solicitado()->create();
    $pedido = Pedido::factory()->create(['status_id' => $status->id]);
    PedidoEvent::query()->create([
        'pedido_id' => $pedido->id,
        'event_type_id' => EventType::factory()->criacaoPedido()->create()->id,
        'actor_id' => $pedido->requester_id,
    ]);

    $this->lookupMigration->down();

    $before = historyLookupProtectedCounts();

    $this->lookupMigration->up();

    $expected = $before;
    $expected['statuses']++;
    $expected['event_types'] += 3;

    expect(historyLookupProtectedCounts())->toBe($expected);
});

test('down() removes the 4 lookup rows when they are unreferenced', function () {
    $before = historyLookupProtectedCounts();

    $this->lookupMigration->down();

    expect(historyLookupRowCount())->toBe([
        'finalizado' => 0,
        'observacao' => 0,
        'romaneio_anexado' => 0,
        'finalizacao' => 0,
    ]);

    $expected = $before;
    $expected['statuses']--;
    $expected['event_types'] -= 3;

    expect(historyLookupProtectedCounts())->toBe($expected);
});

test('down() throws and changes nothing while a pedido is finalizado', function () {
    $finalizado = Status::query()->where('slug', 'finalizado')->sole();
    Pedido::factory()->create(['status_id' => $finalizado->id]);

    $before = historyLookupProtectedCounts();

    expect(fn () => $this->lookupMigration->down())
        ->toThrow(RuntimeException::class, 'Não é possível reverter');

    expect(historyLookupProtectedCounts())->toBe($before);
    expect(array_sum(historyLookupRowCount()))->toBe(4);
});

test('down() throws and changes nothing while an event uses one of the 3 types', function (string $slug) {
    $pedido = Pedido::factory()->create(['status_id' => Status::factory()->solicitado()->create()->id]);
    PedidoEvent::query()->create([
        'pedido_id' => $pedido->id,
        'event_type_id' => EventType::query()->where('slug', $slug)->value('id'),
        'actor_id' => User::factory()->suprimentos()->create()->id,
    ]);

    $before = historyLookupProtectedCounts();

    expect(fn () => $this->lookupMigration->down())
        ->toThrow(RuntimeException::class, 'Não é possível reverter');

    expect(historyLookupProtectedCounts())->toBe($before);
    expect(array_sum(historyLookupRowCount()))->toBe(4);
})->with(HISTORY_LOOKUP_EVENT_TYPE_SLUGS);

test('the down() docblock declares the rollback conditionally destructive (F-14b)', function () {
    $source = file_get_contents(database_path('migrations/'.HISTORY_LOOKUP_MIGRATION_FILE));

    preg_match_all('/\/\*\*.*?\*\//s', $source, $docblocks);

    expect(collect($docblocks[0])->contains(
        fn (string $docblock): bool => str_contains(mb_strtolower($docblock), 'destrutivo')
            || str_contains(mb_strtolower($docblock), 'destructive'),
    ))->toBeTrue();

    expect($source)->toMatch('/CONDICIONALMENTE DESTRUTIVO/');
});

test('the filename sorts after slice 1 and the other slice 2 migrations', function () {
    $sorted = collect(glob(database_path('migrations/*.php')))->map(fn (string $path): string => basename($path))->sort()->values();

    $position = $sorted->search(HISTORY_LOOKUP_MIGRATION_FILE);

    expect($position)->not->toBeFalse();

    foreach (HISTORY_LOOKUP_EARLIER_MIGRATIONS as $earlier) {
        expect($sorted->search($earlier))->not->toBeFalse()->toBeLessThan($position);
    }
});

test('rollback then migrate of the three slice 2 migrations is repeatable (RNF-06)', function () {
    $outraMigration = require database_path('migrations/2026_09_23_083524_add_outra_reference_and_data_prevista_to_pedidos.php');
    $attachmentsMigration = require database_path('migrations/2026_09_23_083916_create_pedido_attachments_table.php');

    foreach ([1, 2] as $round) {
        $this->lookupMigration->down();
        $attachmentsMigration->down();
        $outraMigration->down();

        expect(Schema::hasTable('pedido_attachments'))->toBeFalse();
        expect(array_sum(historyLookupRowCount()))->toBe(0);

        $outraMigration->up();
        $attachmentsMigration->up();
        $this->lookupMigration->up();

        expect(Schema::hasTable('pedido_attachments'))->toBeTrue();
        expect(array_column(Schema::getColumns('pedidos'), 'name'))->toContain('obra_reference', 'data_prevista');
        expect(historyLookupRowCount())->toBe([
            'finalizado' => 1,
            'observacao' => 1,
            'romaneio_anexado' => 1,
            'finalizacao' => 1,
        ]);
    }
});
