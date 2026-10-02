<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * RF-01, RF-19, CT-01 and RNF-05 for the `obras.is_active` migration.
 *
 * As in `ObraStatusMigrationTest`, the migration object is loaded from
 * `database/migrations/` and its `up()` / `down()` are invoked directly.
 * PostgreSQL DDL is transactional, so every change made here is rolled back
 * with the test transaction.
 */
const OBRA_ACTIVITY_MIGRATION_FILE = '2026_10_02_022750_add_is_active_to_obras_and_relax_obra_admin_events_fks.php';

function obraActivityFlagMigration(): object
{
    return require database_path('migrations/'.OBRA_ACTIVITY_MIGRATION_FILE);
}

/**
 * @return array<string, string> FK name => `confdeltype` of `obra_admin_events`
 */
function obraAdminEventsForeignKeyDeleteTypes(): array
{
    return collect(DB::select(
        "select conname, confdeltype from pg_constraint where conrelid = 'obra_admin_events'::regclass and contype = 'f' order by conname",
    ))->mapWithKeys(fn (object $row): array => [$row->conname => $row->confdeltype])->all();
}

/**
 * @return array<string, array<string, mixed>>
 */
function obraActivitySchemaFingerprint(): array
{
    return [
        'obras' => Schema::getColumns('obras'),
        'obra_admin_events' => Schema::getColumns('obra_admin_events'),
        'obra_admin_events_fks' => obraAdminEventsForeignKeyDeleteTypes(),
    ];
}

function insertPreActivityObra(string $name, string $status): int
{
    return DB::table('obras')->insertGetId([
        'name' => $name,
        'status' => $status,
        'is_demo' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function insertPreActivityAuditRow(int $actorId, int $obraId, ?int $invitationId = null): int
{
    return DB::table('obra_admin_events')->insertGetId([
        'actor_id' => $actorId,
        'obra_id' => $obraId,
        'obra_invitation_id' => $invitationId,
        'action' => $invitationId === null ? 'obra_created' : 'invitation_created',
        'before' => null,
        'after' => json_encode(['name' => 'X']),
        'created_at' => now(),
    ]);
}

beforeEach(function () {
    $this->migration = obraActivityFlagMigration();

    // The suite starts with the migration applied; roll it back so each case
    // plants pre-migration data and runs `up()` itself.
    $this->migration->down();

    expect(Schema::getColumnListing('obras'))->not->toContain('is_active');
    expect(Schema::getColumnListing('obra_admin_events'))->not->toContain('subject_obra_id');
});

test('the backfill sets is_active from status: only concluido becomes inactive (RF-01)', function () {
    $aIniciar = insertPreActivityObra('Residencial Aurora', 'a_iniciar');
    $emAndamento = insertPreActivityObra('Comercial Boreal', 'em_andamento');
    $concluido = insertPreActivityObra('Industrial Cerrado', 'concluido');

    $this->migration->up();

    expect(DB::table('obras')->orderBy('id')->pluck('is_active', 'id')->all())->toBe([
        $aIniciar => true,
        $emAndamento => true,
        $concluido => false,
    ]);
    expect(DB::table('obras')->orderBy('id')->pluck('status', 'id')->all())->toBe([
        $aIniciar => 'a_iniciar',
        $emAndamento => 'em_andamento',
        $concluido => 'concluido',
    ]);
});

test('pre-existing audit rows keep obra_id and get subject_obra_id = obra_id (RF-19, RNF-05)', function () {
    $actor = User::factory()->gestao()->create();
    $first = insertPreActivityObra('Residencial Aurora', 'em_andamento');
    $second = insertPreActivityObra('Industrial Cerrado', 'concluido');
    $invitation = DB::table('obra_invitations')->insertGetId([
        'obra_id' => $second,
        'token_hash' => str_repeat('a', 64),
        'created_by' => $actor->id,
        'created_at' => now(),
        'expires_at' => now()->addDay(),
    ]);

    $rows = [
        insertPreActivityAuditRow($actor->id, $first),
        insertPreActivityAuditRow($actor->id, $second),
        insertPreActivityAuditRow($actor->id, $second, $invitation),
    ];

    $this->migration->up();

    $stored = DB::table('obra_admin_events')->whereIn('id', $rows)->orderBy('id')->get();

    expect($stored)->toHaveCount(3);
    expect($stored->pluck('obra_id')->all())->toBe([$first, $second, $second]);
    expect($stored->pluck('subject_obra_id')->all())->toBe([$first, $second, $second]);
    expect($stored->pluck('obra_invitation_id')->all())->toBe([null, null, $invitation]);
});

test('subject_obra_id is NOT NULL without FK, obra_id is nullable and both FKs are ON DELETE SET NULL (CT-01)', function () {
    $this->migration->up();

    $columns = collect(Schema::getColumns('obra_admin_events'))->keyBy('name');

    expect($columns['subject_obra_id']['type_name'])->toBe('int8');
    expect($columns['subject_obra_id']['nullable'])->toBeFalse();
    expect($columns['obra_id']['nullable'])->toBeTrue();

    expect(obraAdminEventsForeignKeyDeleteTypes())->toBe([
        'obra_admin_events_actor_id_foreign' => 'r',
        'obra_admin_events_obra_id_foreign' => 'n',
        'obra_admin_events_obra_invitation_id_foreign' => 'n',
    ]);
});

test('a newly inserted obra defaults to is_active = true (RF-01)', function () {
    $this->migration->up();

    $id = DB::table('obras')->insertGetId([
        'name' => 'Obra Nova',
        'is_demo' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(DB::table('obras')->where('id', $id)->value('is_active'))->toBeTrue();
});

test('down() aborts in PT-BR and changes nothing while an audit row has a null obra_id (CT-01)', function () {
    $actor = User::factory()->gestao()->create();
    $obra = insertPreActivityObra('Residencial Aurora', 'em_andamento');
    insertPreActivityAuditRow($actor->id, $obra);

    $this->migration->up();

    $orphan = DB::table('obra_admin_events')->insertGetId([
        'actor_id' => $actor->id,
        'obra_id' => null,
        'subject_obra_id' => 999999,
        'action' => 'obra_deleted',
        'before' => json_encode(['id' => 999999]),
        'after' => null,
        'created_at' => now(),
    ]);

    $schemaBefore = obraActivitySchemaFingerprint();
    $rowsBefore = DB::table('obra_admin_events')->orderBy('id')->get()->all();
    $obrasBefore = DB::table('obras')->orderBy('id')->get()->all();

    try {
        $this->migration->down();

        $this->fail('Expected a RuntimeException.');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())
            ->toContain('Rollback abortado')
            ->toContain('obra_id nulo')
            ->toContain('nenhuma linha foi alterada');
    }

    expect(obraActivitySchemaFingerprint())->toEqual($schemaBefore);
    expect(DB::table('obra_admin_events')->orderBy('id')->get()->all())->toEqual($rowsBefore);
    expect(DB::table('obras')->orderBy('id')->get()->all())->toEqual($obrasBefore);
    expect(DB::table('obra_admin_events')->where('id', $orphan)->exists())->toBeTrue();
});

test('down() restores RESTRICT FKs and NOT NULL, and a down() → up() round-trip leaves the schema identical (CT-01, RNF-05)', function () {
    $actor = User::factory()->gestao()->create();
    $obra = insertPreActivityObra('Residencial Aurora', 'em_andamento');
    insertPreActivityAuditRow($actor->id, $obra);

    $this->migration->up();
    $applied = obraActivitySchemaFingerprint();

    $this->migration->down();

    expect(Schema::getColumnListing('obras'))->not->toContain('is_active');
    expect(Schema::getColumnListing('obra_admin_events'))->not->toContain('subject_obra_id');
    expect(collect(Schema::getColumns('obra_admin_events'))->firstWhere('name', 'obra_id')['nullable'])->toBeFalse();
    expect(obraAdminEventsForeignKeyDeleteTypes())->toBe([
        'obra_admin_events_actor_id_foreign' => 'r',
        'obra_admin_events_obra_id_foreign' => 'r',
        'obra_admin_events_obra_invitation_id_foreign' => 'r',
    ]);

    $this->migration->up();

    expect(obraActivitySchemaFingerprint())->toEqual($applied);
    expect(DB::table('obra_admin_events')->value('subject_obra_id'))->toBe($obra);
});

test('up() aborts before any write when an expected FK is missing (RNF-05)', function () {
    DB::statement('alter table obra_admin_events drop constraint obra_admin_events_obra_invitation_id_foreign');

    expect(fn () => $this->migration->up())
        ->toThrow(RuntimeException::class, 'obra_admin_events_obra_invitation_id_foreign');

    expect(Schema::getColumnListing('obras'))->not->toContain('is_active');
    expect(Schema::getColumnListing('obra_admin_events'))->not->toContain('subject_obra_id');
});
