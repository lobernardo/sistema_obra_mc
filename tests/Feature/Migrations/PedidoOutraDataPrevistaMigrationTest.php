<?php

use App\Domain\Pedidos\DataPrevistaCalculator;
use App\Models\AccountRegistrationEvent;
use App\Models\AuthenticationEvent;
use App\Models\EventType;
use App\Models\Obra;
use App\Models\ObraAdminEvent;
use App\Models\ObraInvitation;
use App\Models\Status;
use App\Models\User;
use App\Models\UserAdminEvent;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * RF-06, RF-12, RF-42, RNF-06 and CT-02 for the `pedidos` "Outra" / Data
 * prevista migration and the `pedido_attachments` migration.
 *
 * As in `EmailNormalizationMigrationTest` and `ObraStatusMigrationTest`, the
 * migration objects are loaded from `database/migrations/` and their `up()` /
 * `down()` are invoked directly: `RefreshDatabase` applies every migration in
 * one batch, so a real `migrate:rollback` would tear the whole schema down
 * mid-test. PostgreSQL DDL is transactional, so everything done here is
 * rolled back with the test transaction.
 */
const PEDIDO_OUTRA_MIGRATION_FILE = '2026_09_23_083524_add_outra_reference_and_data_prevista_to_pedidos.php';

const PEDIDO_ATTACHMENTS_MIGRATION_FILE = '2026_09_23_083916_create_pedido_attachments_table.php';

/**
 * Slice 1 migrations the two new files must sort after.
 */
const PEDIDO_OUTRA_SLICE_ONE_MIGRATIONS = [
    '2026_09_23_040313_convert_obras_activity_to_status.php',
    '2026_09_23_042010_create_obra_invitations_table.php',
    '2026_09_23_042011_create_obra_admin_events_table.php',
    '2026_09_23_042012_create_account_registration_events_table.php',
];

/**
 * The tables whose row counts RF-42 forbids the migrations to change.
 */
const PEDIDO_OUTRA_PROTECTED_TABLES = [
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

function pedidoOutraMigration(): object
{
    return require database_path('migrations/'.PEDIDO_OUTRA_MIGRATION_FILE);
}

function pedidoAttachmentsMigration(): object
{
    return require database_path('migrations/'.PEDIDO_ATTACHMENTS_MIGRATION_FILE);
}

function pedidosColumnNames(): array
{
    return array_column(Schema::getColumns('pedidos'), 'name');
}

/**
 * Inserts a pre-migration pedido straight through the query builder, since
 * the `Pedido` model already speaks the post-migration schema.
 */
function insertLegacyPedido(string $requestedAtUtc, ?string $expectedDeliveryAt = null): int
{
    $requester = User::factory()->obra()->create();
    $obra = Obra::factory()->create();
    $status = Status::query()->firstWhere('slug', 'solicitado') ?? Status::factory()->solicitado()->create();

    return DB::table('pedidos')->insertGetId([
        'code' => 'PED-LEG-'.fake()->unique()->numberBetween(1, 999999),
        'obra_id' => $obra->id,
        'requester_id' => $requester->id,
        'requested_at' => CarbonImmutable::parse($requestedAtUtc)->utc()->format('Y-m-d H:i:s'),
        'needed_at' => '2026-12-01',
        'items_description' => 'Cimento',
        'status_id' => $status->id,
        'expected_delivery_at' => $expectedDeliveryAt,
        'is_demo' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/**
 * @return array<string, int>
 */
function pedidoOutraProtectedCounts(): array
{
    return collect(PEDIDO_OUTRA_PROTECTED_TABLES)
        ->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->count()])
        ->all();
}

function pedidoOutraMigrationCode(): string
{
    $tokens = array_filter(
        PhpToken::tokenize(file_get_contents(database_path('migrations/'.PEDIDO_OUTRA_MIGRATION_FILE))),
        fn (PhpToken $token): bool => ! $token->is([T_COMMENT, T_DOC_COMMENT]),
    );

    return implode('', array_map(fn (PhpToken $token): string => $token->text, $tokens));
}

beforeEach(function () {
    $this->outraMigration = pedidoOutraMigration();
    $this->attachmentsMigration = pedidoAttachmentsMigration();

    // The suite starts with both migrations applied; roll them back (newest
    // first) so each case plants pre-migration pedidos and runs `up()` itself.
    $this->attachmentsMigration->down();
    $this->outraMigration->down();

    expect(Schema::hasTable('pedido_attachments'))->toBeFalse();
    expect(pedidosColumnNames())->not->toContain('obra_reference', 'data_prevista');
});

test('existing pedidos are backfilled with data_prevista from requested_at in São Paulo (RF-12)', function () {
    $monday = insertLegacyPedido('2026-09-21T12:00:00Z', '2026-10-30');
    $thursdayNight = insertLegacyPedido('2026-09-25T01:30:00Z');
    $beforeHoliday = insertLegacyPedido('2026-10-09T15:00:00Z', '2026-10-20');

    $deliveryBefore = DB::table('pedidos')->orderBy('id')->pluck('expected_delivery_at', 'id')->all();

    $this->outraMigration->up();

    expect(DB::table('pedidos')->where('id', $monday)->value('data_prevista'))->toBe('2026-09-24');
    expect(DB::table('pedidos')->where('id', $thursdayNight)->value('data_prevista'))->toBe('2026-09-29');
    expect(DB::table('pedidos')->where('id', $beforeHoliday)->value('data_prevista'))->toBe('2026-10-15');
    expect(DB::table('pedidos')->whereNull('data_prevista')->count())->toBe(0);
    expect(DB::table('pedidos')->orderBy('id')->pluck('expected_delivery_at', 'id')->all())->toBe($deliveryBefore);

    $column = collect(Schema::getColumns('pedidos'))->firstWhere('name', 'data_prevista');
    expect($column['nullable'])->toBeFalse();
    expect($column['type_name'])->toBe('date');
});

test('the frozen rule matches the live DataPrevistaCalculator (RF-12, F-14c)', function () {
    $this->outraMigration->up();

    $instants = [
        '2026-09-21T12:00:00Z',
        '2026-09-25T12:00:00Z',
        '2026-09-26T12:00:00Z',
        '2026-09-27T12:00:00Z',
        '2026-10-09T12:00:00Z',
        '2026-09-25T01:30:00Z',
        '2026-04-01T12:00:00Z',
    ];

    for ($day = CarbonImmutable::parse('2026-01-01', 'UTC'); $day->year === 2026; $day = $day->addDay()) {
        $instants[] = $day->setTime(12, 0)->toIso8601ZuluString();
        $instants[] = $day->setTime(1, 30)->toIso8601ZuluString();
    }

    foreach ($instants as $instant) {
        expect($this->outraMigration->frozenDataPrevista($instant))
            ->toBe(DataPrevistaCalculator::forRequestedAt(CarbonImmutable::parse($instant))->toDateString(), $instant);
    }

    expect($this->outraMigration->frozenDataPrevista('2026-04-01T12:00:00Z'))->toBe('2026-04-07');
});

test('the migration file references no live application class (F-14c)', function () {
    $code = pedidoOutraMigrationCode();

    foreach (['App\\Domain\\', 'App\\Support\\', 'App\\Models\\'] as $forbidden) {
        expect($code)->not->toContain($forbidden);
    }
});

test('row counts of the protected tables are identical before and after re-running both migrations (RF-42, RNF-06)', function () {
    $legacy = insertLegacyPedido('2026-09-21T12:00:00Z');
    $actor = User::factory()->gestao()->create();
    $eventType = EventType::factory()->criacaoPedido()->create();

    DB::table('pedido_events')->insert([
        'pedido_id' => $legacy,
        'event_type_id' => $eventType->id,
        'actor_id' => $actor->id,
        'created_at' => now(),
    ]);
    User::find(DB::table('pedidos')->where('id', $legacy)->value('requester_id'))
        ->obras()->attach(DB::table('pedidos')->where('id', $legacy)->value('obra_id'));

    UserAdminEvent::factory()->create();
    AuthenticationEvent::factory()->create();
    $invitation = ObraInvitation::factory()->create();
    ObraAdminEvent::factory()->create();
    AccountRegistrationEvent::factory()->create();

    $before = pedidoOutraProtectedCounts();

    foreach ($before as $table => $count) {
        expect($count)->toBeGreaterThan(0, "{$table} must have rows for the criterion to be meaningful");
    }

    $this->outraMigration->up();
    $this->attachmentsMigration->up();

    expect(pedidoOutraProtectedCounts())->toBe($before);
    expect($invitation->fresh())->not->toBeNull();
});

test('data_prevista is filterable and sortable in raw SQL and carries its index (CT-06)', function () {
    $later = insertLegacyPedido('2026-10-09T15:00:00Z');
    $earlier = insertLegacyPedido('2026-09-21T12:00:00Z');
    insertLegacyPedido('2026-01-05T12:00:00Z');

    $this->outraMigration->up();

    $rows = DB::select('select id from pedidos where data_prevista >= ? order by data_prevista', ['2026-09-01']);

    expect(array_column($rows, 'id'))->toBe([$earlier, $later]);
    expect(collect(Schema::getIndexes('pedidos'))->firstWhere('name', 'pedidos_data_prevista_index')['columns'])
        ->toBe(['data_prevista']);
});

test('the two obra_reference checks fire, obra_id accepts null and the FK still restricts (RF-06, CT-02)', function () {
    $legacy = insertLegacyPedido('2026-09-21T12:00:00Z');

    $this->outraMigration->up();

    $template = (array) DB::table('pedidos')->where('id', $legacy)->first();
    unset($template['id']);

    $row = fn (array $overrides): array => array_merge($template, ['code' => 'PED-T-'.fake()->unique()->numberBetween(1, 999999)], $overrides);

    // Each rejected insert runs inside a savepoint so the failure does not
    // abort the surrounding test transaction.
    $insert = fn (array $overrides) => DB::transaction(fn () => DB::table('pedidos')->insert($row($overrides)));

    expect(fn () => $insert(['obra_reference' => 'Galpão']))
        ->toThrow(QueryException::class, 'pedidos_obra_reference_only_without_obra');

    expect(fn () => $insert(['obra_id' => null, 'obra_reference' => '   ']))
        ->toThrow(QueryException::class, 'pedidos_obra_reference_not_blank');

    $outra = DB::table('pedidos')->insertGetId($row(['obra_id' => null, 'obra_reference' => 'Galpão provisório']));
    expect(DB::table('pedidos')->where('id', $outra)->value('obra_id'))->toBeNull();

    $semReferencia = DB::table('pedidos')->insertGetId($row(['obra_id' => null, 'obra_reference' => null]));
    expect(DB::table('pedidos')->where('id', $semReferencia)->exists())->toBeTrue();

    expect(fn () => $insert(['obra_id' => 999999999]))
        ->toThrow(QueryException::class, 'pedidos_obra_id_foreign');

    $foreignKey = collect(Schema::getForeignKeys('pedidos'))->first(fn (array $fk): bool => $fk['columns'] === ['obra_id']);
    expect($foreignKey['foreign_table'])->toBe('obras');
    expect($foreignKey['on_delete'])->toBe('restrict');
});

test('rollback then migrate is repeatable (RNF-06)', function () {
    $legacy = insertLegacyPedido('2026-09-25T01:30:00Z');

    $this->outraMigration->up();
    $this->attachmentsMigration->up();
    $this->attachmentsMigration->down();
    $this->outraMigration->down();

    expect(pedidosColumnNames())->not->toContain('obra_reference', 'data_prevista');
    expect(collect(Schema::getColumns('pedidos'))->firstWhere('name', 'obra_id')['nullable'])->toBeFalse();

    $this->outraMigration->up();
    $this->attachmentsMigration->up();

    expect(Schema::hasTable('pedido_attachments'))->toBeTrue();
    expect(DB::table('pedidos')->where('id', $legacy)->value('data_prevista'))->toBe('2026-09-29');
});

test('down() refuses and changes nothing while an "Outra" pedido exists (RF-42)', function () {
    $legacy = insertLegacyPedido('2026-09-21T12:00:00Z');

    $this->outraMigration->up();

    $template = (array) DB::table('pedidos')->where('id', $legacy)->first();
    unset($template['id']);
    DB::table('pedidos')->insert(array_merge($template, ['code' => 'PED-OUTRA-1', 'obra_id' => null, 'obra_reference' => 'Galpão']));

    $before = DB::table('pedidos')->orderBy('id')->get()->all();

    expect(fn () => $this->outraMigration->down())
        ->toThrow(RuntimeException::class, 'Existem pedidos "Outra" sem obra; a reversão exigiria apagar dados.');

    expect(pedidosColumnNames())->toContain('obra_reference', 'data_prevista');
    expect(DB::table('pedidos')->orderBy('id')->get()->all())->toEqual($before);
});

test('the migration code never deletes, truncates or drops a table (RF-42)', function () {
    $code = pedidoOutraMigrationCode();

    foreach (['delete from', 'truncate', 'drop table', 'Schema::drop', '->delete('] as $forbidden) {
        $this->assertStringNotContainsStringIgnoringCase($forbidden, $code, "A migration de pedidos não pode conter {$forbidden}.");
    }
});

test('both new migration filenames sort after every slice 1 migration (RF-42)', function () {
    $sorted = collect(glob(database_path('migrations/*.php')))->map(fn (string $path): string => basename($path))->sort()->values();

    $outraPosition = $sorted->search(PEDIDO_OUTRA_MIGRATION_FILE);
    $attachmentsPosition = $sorted->search(PEDIDO_ATTACHMENTS_MIGRATION_FILE);

    expect($outraPosition)->not->toBeFalse();
    expect($attachmentsPosition)->toBeGreaterThan($outraPosition);

    foreach (PEDIDO_OUTRA_SLICE_ONE_MIGRATIONS as $sliceOne) {
        expect($sorted->search($sliceOne))->toBeLessThan($outraPosition);
    }
});
