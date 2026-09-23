<?php

use App\Models\AuthenticationEvent;
use App\Models\EventType;
use App\Models\Pedido;
use App\Models\Status;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * RF-36, RF-36b and RNF-06 for the obra status migration.
 *
 * As in `EmailNormalizationMigrationTest`, the migration object is loaded
 * from `database/migrations/` and its `up()` / `down()` are invoked directly:
 * `RefreshDatabase` applies every migration in one batch, so a real
 * `migrate:rollback` would tear the entire schema down mid-test. PostgreSQL
 * DDL is transactional, so every column, check and index created or dropped
 * here is rolled back with the test transaction.
 */
const OBRA_STATUS_MIGRATION_FILE = '2026_09_23_040313_convert_obras_activity_to_status.php';

const OBRA_NAME_INDEX = 'obras_name_normalized_unique';

const OBRA_STATUS_CHECK = 'obras_status_check';

/**
 * The seven tables whose rows RF-36 forbids the migration to remove.
 */
const OBRA_STATUS_PROTECTED_TABLES = [
    'users',
    'obras',
    'obra_profile',
    'pedidos',
    'pedido_events',
    'user_admin_events',
    'authentication_events',
];

function obraStatusMigration(): object
{
    return require database_path('migrations/'.OBRA_STATUS_MIGRATION_FILE);
}

function obrasColumnNames(): array
{
    return array_column(Schema::getColumns('obras'), 'name');
}

function obraNameIndexExists(): bool
{
    return collect(Schema::getIndexes('obras'))->contains('name', OBRA_NAME_INDEX);
}

function obraStatusCheckExists(): bool
{
    return (int) DB::scalar(
        "select count(*) from pg_constraint where conname = ? and conrelid = 'obras'::regclass",
        [OBRA_STATUS_CHECK],
    ) === 1;
}

/**
 * Inserts a pre-migration obra row straight through the query builder, since
 * the `Obra` model and its factory already speak the post-migration schema.
 */
function insertLegacyObra(string $name, bool $isActive): int
{
    return DB::table('obras')->insertGetId([
        'name' => $name,
        'is_active' => $isActive,
        'is_demo' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/**
 * @return array<string, int>
 */
function protectedTableCounts(): array
{
    return collect(OBRA_STATUS_PROTECTED_TABLES)
        ->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->count()])
        ->all();
}

beforeEach(function () {
    $this->migration = obraStatusMigration();

    // The suite starts with the migration applied; roll it back so each case
    // plants pre-migration data (with `is_active`) and runs `up()` itself.
    $this->migration->down();

    expect(obrasColumnNames())->toContain('is_active')->not->toContain('status', 'responsavel');
    expect(obraNameIndexExists())->toBeFalse();
});

test('is_active true becomes em_andamento, false becomes concluido, and is_active is dropped (RF-36, NC-02)', function () {
    $ativa = insertLegacyObra('Residencial Aurora', true);
    $inativa = insertLegacyObra('Comercial Boreal', false);

    $this->migration->up();

    expect(DB::table('obras')->where('id', $ativa)->value('status'))->toBe('em_andamento');
    expect(DB::table('obras')->where('id', $inativa)->value('status'))->toBe('concluido');
    expect(DB::table('obras')->whereNull('status')->count())->toBe(0);
    expect(DB::table('obras')->whereNotNull('responsavel')->count())->toBe(0);
    expect(obrasColumnNames())->toContain('status', 'responsavel')->not->toContain('is_active');
    expect(obraStatusCheckExists())->toBeTrue();
});

test('the row counts of the seven protected tables are identical before and after the migration (RF-36, RNF-06)', function () {
    $actor = User::factory()->gestao()->create();
    $requester = User::factory()->obra()->create();
    $ativa = insertLegacyObra('Residencial Aurora', true);
    $inativa = insertLegacyObra('Comercial Boreal', false);
    $requester->obras()->attach([$ativa, $inativa]);

    $eventType = EventType::factory()->criacaoPedido()->create();
    $status = Status::factory()->solicitado()->create();

    foreach ([$ativa, $inativa] as $obraId) {
        $pedido = Pedido::factory()->create(['obra_id' => $obraId, 'requester_id' => $requester->id, 'status_id' => $status->id]);
        $pedido->events()->create([
            'event_type_id' => $eventType->id,
            'actor_id' => $requester->id,
            'previous_value' => null,
            'new_value' => null,
        ]);
    }

    DB::table('user_admin_events')->insert([
        'actor_id' => $actor->id,
        'target_id' => $requester->id,
        'action' => 'user_created',
        'before' => null,
        'after' => json_encode(['name' => $requester->name]),
        'created_at' => now(),
    ]);

    AuthenticationEvent::query()->create([
        'event' => 'login_failed',
        'user_id' => $requester->id,
        'email' => $requester->email,
        'ip' => '203.0.113.10',
        'user_agent' => 'PHPUnit',
    ]);

    $before = protectedTableCounts();

    foreach ($before as $table => $count) {
        expect($count)->toBeGreaterThan(0, "{$table} must have rows for the criterion to be meaningful");
    }

    $this->migration->up();

    expect(protectedTableCounts())->toBe($before);
});

test('colliding obra names abort the migration, name both values and leave the schema untouched (RF-36b)', function () {
    $first = insertLegacyObra('Obra X', true);
    $second = insertLegacyObra(' obra x', false);

    $before = DB::table('obras')->orderBy('id')->get()->all();

    try {
        $this->migration->up();

        $this->fail('Expected a RuntimeException.');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())
            ->toContain('Conversão do status das obras abortada')
            ->toContain('Obra X')
            ->toContain(' obra x');
    }

    expect(obrasColumnNames())->toContain('is_active')->not->toContain('status', 'responsavel');
    expect(obraStatusCheckExists())->toBeFalse();
    expect(obraNameIndexExists())->toBeFalse();
    expect(DB::table('obras')->orderBy('id')->get()->all())->toEqual($before);
    expect(DB::table('obras')->where('id', $first)->value('is_active'))->toBeTrue();
    expect(DB::table('obras')->where('id', $second)->value('is_active'))->toBeFalse();
});

test('the normalized-name unique index is created and refuses a raw case or whitespace variant (RF-36b, CT-01)', function () {
    insertLegacyObra('Residencial Aurora', true);

    $this->migration->up();

    $index = collect(Schema::getIndexes('obras'))->firstWhere('name', OBRA_NAME_INDEX);

    expect($index)->not->toBeNull();
    expect($index['unique'])->toBeTrue();
    expect(DB::scalar('select indexdef from pg_indexes where tablename = ? and indexname = ?', ['obras', OBRA_NAME_INDEX]))
        ->toContain('lower(btrim((name)::text))');

    expect(fn () => DB::table('obras')->insert([
        'name' => '  RESIDENCIAL aurora ',
        'status' => 'em_andamento',
        'is_demo' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(UniqueConstraintViolationException::class);
});

test('the status check refuses a value outside the three statuses and a new obra defaults to a_iniciar (CT-01)', function () {
    $this->migration->up();

    $id = DB::table('obras')->insertGetId([
        'name' => 'Obra Padrão',
        'is_demo' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(DB::table('obras')->where('id', $id)->value('status'))->toBe('a_iniciar');

    expect(fn () => DB::table('obras')->insert([
        'name' => 'Obra Inválida',
        'status' => 'pausada',
        'is_demo' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class, OBRA_STATUS_CHECK);
});

test('rollback then migrate is repeatable and the rollback loses the A iniciar distinction and every responsavel (RNF-06, F-14a)', function () {
    $aIniciar = insertLegacyObra('Residencial Aurora', true);
    $emAndamento = insertLegacyObra('Comercial Boreal', true);
    $concluida = insertLegacyObra('Industrial Cerrado', false);

    $this->migration->up();

    DB::table('obras')->where('id', $aIniciar)->update(['status' => 'a_iniciar', 'responsavel' => 'Eng. Marta']);
    DB::table('obras')->where('id', $concluida)->update(['responsavel' => 'Eng. Paulo']);

    $this->migration->down();

    expect(obrasColumnNames())->toContain('is_active')->not->toContain('status', 'responsavel');
    expect(obraNameIndexExists())->toBeFalse();
    expect(obraStatusCheckExists())->toBeFalse();
    expect(DB::table('obras')->where('id', $aIniciar)->value('is_active'))->toBeTrue();
    expect(DB::table('obras')->where('id', $emAndamento)->value('is_active'))->toBeTrue();
    expect(DB::table('obras')->where('id', $concluida)->value('is_active'))->toBeFalse();

    $this->migration->up();
    $this->migration->down();
    $this->migration->up();

    expect(obraNameIndexExists())->toBeTrue();
    expect(obraStatusCheckExists())->toBeTrue();
    expect(DB::table('obras')->orderBy('id')->pluck('status', 'id')->all())->toBe([
        $aIniciar => 'em_andamento',
        $emAndamento => 'em_andamento',
        $concluida => 'concluido',
    ]);
    expect(DB::table('obras')->whereNotNull('responsavel')->count())->toBe(0);
});

test('the migration code never deletes, truncates or drops a table (RF-36)', function () {
    $tokens = array_filter(
        PhpToken::tokenize(file_get_contents(database_path('migrations/'.OBRA_STATUS_MIGRATION_FILE))),
        fn (PhpToken $token): bool => ! $token->is([T_COMMENT, T_DOC_COMMENT]),
    );
    $code = implode('', array_map(fn (PhpToken $token): string => $token->text, $tokens));

    foreach (['delete from', 'truncate', 'drop table', 'Schema::drop'] as $forbidden) {
        $this->assertStringNotContainsStringIgnoringCase(
            $forbidden,
            $code,
            "A migration de status da obra não pode conter {$forbidden}.",
        );
    }
});

test('the migration docblock declares the data-losing rollback (F-14a)', function () {
    $source = file_get_contents(database_path('migrations/'.OBRA_STATUS_MIGRATION_FILE));

    expect($source)
        ->toContain('COM PERDA DE DADOS')
        ->toContain('"A iniciar" e "Em andamento"')
        ->toContain('`responsavel`');
});
