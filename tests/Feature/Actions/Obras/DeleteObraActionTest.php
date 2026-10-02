<?php

use App\Actions\Obras\DeleteObraAction;
use App\Enums\ObraAdminAction;
use App\Enums\ObraStatus;
use App\Enums\UserAdminAction;
use App\Models\AccountRegistrationEvent;
use App\Models\InternalNotification;
use App\Models\Obra;
use App\Models\ObraAdminEvent;
use App\Models\ObraInvitation;
use App\Models\Pedido;
use App\Models\PedidoAttachment;
use App\Models\User;
use App\Models\UserAdminEvent;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->action = app(DeleteObraAction::class);
    $this->actor = User::factory()->gestao()->create();
    $this->solicitadoId = seedWorkflowStatuses()['solicitado']->id;
});

/**
 * @return array<string, list<string>>
 */
function obraDeletionErrors(Closure $callback): array
{
    try {
        $callback();
    } catch (ValidationException $exception) {
        return $exception->errors();
    }

    throw new RuntimeException('A ValidationException was expected.');
}

/**
 * Every row of the given tables, in a stable order, for before/after diffs.
 *
 * @param  list<string>  $tables
 * @return array<string, list<array<string, mixed>>>
 */
function obraDeletionTableRows(array $tables): array
{
    $snapshot = [];

    foreach ($tables as $table) {
        $snapshot[$table] = DB::table($table)->get()->map(fn (object $row): array => (array) $row)->sortBy(fn (array $row): string => json_encode($row))->values()->all();
    }

    return $snapshot;
}

/**
 * The obra-side tables a blocked attempt or a rolled-back deletion must
 * leave identical.
 *
 * @return array<string, list<array<string, mixed>>>
 */
function obraDeletionObraSideRows(): array
{
    return obraDeletionTableRows(['obras', 'obra_invitations', 'obra_profile', 'obra_admin_events', 'user_admin_events', 'account_registration_events']);
}

/**
 * The pedido-side state no deletion attempt may ever touch (RF-22).
 *
 * @return array{rows: array<string, list<array<string, mixed>>>, files: list<string>}
 */
function obraDeletionPedidoSideState(): array
{
    $files = Storage::disk('pedido_anexos')->allFiles();
    sort($files);

    return [
        'rows' => obraDeletionTableRows(['pedidos', 'pedido_events', 'internal_notifications', 'pedido_attachments']),
        'files' => $files,
    ];
}

function obraDeletionDeadlockException(): QueryException
{
    $pdoException = new PDOException('SQLSTATE[40P01]: Deadlock detected');
    $pdoException->errorInfo = ['40P01', 7, 'ERROR: deadlock detected'];

    return new QueryException('pgsql', 'delete from "obras" where "id" = ?', [1], $pdoException);
}

test('an obra without dependencies is physically deleted (RF-14)', function (string $papel) {
    $actor = User::factory()->{$papel}()->create();
    $obra = Obra::factory()->create();

    $this->action->execute($actor, $obra);

    expect(Obra::query()->whereKey($obra->id)->exists())->toBeFalse();
})->with(['gestao', 'suprimentos']);

test('one pedido or one used convite blocks the deletion: only one obra_delete_blocked row is written (RF-15, RF-21)', function (string $dependency) {
    $obra = Obra::factory()->create(['name' => 'Obra Bloqueada']);
    $member = User::factory()->obra()->create();
    $member->obras()->attach($obra->id);
    ObraInvitation::factory()->create(['obra_id' => $obra->id]);
    ObraAdminEvent::factory()->create(['obra_id' => $obra->id, 'action' => ObraAdminAction::ObraCreated]);

    if ($dependency === 'pedido') {
        Pedido::factory()->create(['obra_id' => $obra->id, 'status_id' => $this->solicitadoId]);
    } else {
        $used = ObraInvitation::factory()->used()->create(['obra_id' => $obra->id]);
        AccountRegistrationEvent::factory()->create(['obra_invitation_id' => $used->id]);
    }

    $before = obraDeletionObraSideRows();
    $auditIds = ObraAdminEvent::query()->pluck('id')->all();

    $errors = obraDeletionErrors(fn () => $this->action->execute($this->actor, $obra));

    $expectedCounts = $dependency === 'pedido' ? [1, 0] : [0, 1];

    expect($errors)->toBe(['excluir' => [sprintf(
        'Não é possível excluir a obra «Obra Bloqueada»: ela possui %d pedido(s) e %d convite(s) utilizado(s). Desative a obra para impedir novos usos.',
        ...$expectedCounts,
    )]]);

    $blocked = ObraAdminEvent::query()->whereNotIn('id', $auditIds)->get();

    expect($blocked)->toHaveCount(1);
    expect($blocked[0]->action)->toBe(ObraAdminAction::ObraDeleteBlocked);
    expect($blocked[0]->actor_id)->toBe($this->actor->id);
    expect($blocked[0]->obra_id)->toBe($obra->id);
    expect($blocked[0]->subject_obra_id)->toBe($obra->id);
    expect($blocked[0]->before)->toBeNull();
    expect($blocked[0]->after)->toBe(['pedidos_count' => $expectedCounts[0], 'used_invitations_count' => $expectedCounts[1]]);

    $after = obraDeletionObraSideRows();
    $after['obra_admin_events'] = array_values(array_filter($after['obra_admin_events'], fn (array $row): bool => in_array($row['id'], $auditIds, true)));

    expect($after)->toBe($before);
})->with(['pedido', 'convite utilizado']);

test('2 pedidos and 0 used convites yield the exact RF-16 message (RF-16)', function () {
    $obra = Obra::factory()->create(['name' => 'X']);
    Pedido::factory()->count(2)->create(['obra_id' => $obra->id, 'status_id' => $this->solicitadoId]);

    expect(obraDeletionErrors(fn () => $this->action->execute($this->actor, $obra)))->toBe([
        'excluir' => ['Não é possível excluir a obra «X»: ela possui 2 pedido(s) e 0 convite(s) utilizado(s). Desative a obra para impedir novos usos.'],
    ]);

    expect(Obra::query()->whereKey($obra->id)->exists())->toBeTrue();
});

test('demo pedidos and terminal pedidos block the deletion too (RF-15)', function () {
    $obra = Obra::factory()->create();
    Pedido::factory()->create(['obra_id' => $obra->id, 'is_demo' => true, 'status_id' => seedWorkflowStatuses()['cancelado']->id]);

    expect(obraDeletionErrors(fn () => $this->action->execute($this->actor, $obra)))->toHaveKey('excluir');
    expect(Obra::query()->whereKey($obra->id)->exists())->toBeTrue();
});

test('pending, expired and revoked convites are deleted and account_registration_events is untouched (RF-17)', function () {
    $obra = Obra::factory()->create();
    $pending = ObraInvitation::factory()->create(['obra_id' => $obra->id]);
    $expired = ObraInvitation::factory()->expired()->create(['obra_id' => $obra->id]);
    $revoked = ObraInvitation::factory()->revoked()->create(['obra_id' => $obra->id]);
    $otherObraInvitation = ObraInvitation::factory()->create();
    AccountRegistrationEvent::factory()->count(2)->create();

    $registrations = obraDeletionTableRows(['account_registration_events']);

    $this->action->execute($this->actor, $obra);

    expect(ObraInvitation::query()->whereKey([$pending->id, $expired->id, $revoked->id])->count())->toBe(0);
    expect(ObraInvitation::query()->whereKey($otherObraInvitation->id)->exists())->toBeTrue();
    expect(obraDeletionTableRows(['account_registration_events']))->toBe($registrations);
    expect(Obra::query()->whereKey($obra->id)->exists())->toBeFalse();
});

test('associations cascade and each affected user gets one obra_access_changed with the before/after lists (RF-18, CT-03)', function () {
    $obra = Obra::factory()->create();
    $otherObra = Obra::factory()->create();
    $obraUser = User::factory()->obra()->create();
    $suprimentosUser = User::factory()->suprimentos()->create();
    $unrelated = User::factory()->obra()->create();
    $obraUser->obras()->attach([$obra->id, $otherObra->id]);
    $suprimentosUser->obras()->attach($obra->id);
    $unrelated->obras()->attach($otherObra->id);

    $this->action->execute($this->actor, $obra);

    expect(DB::table('obra_profile')->where('obra_id', $obra->id)->exists())->toBeFalse();
    expect($obraUser->obras()->pluck('obras.id')->all())->toBe([$otherObra->id]);
    expect($unrelated->obras()->pluck('obras.id')->all())->toBe([$otherObra->id]);

    $events = UserAdminEvent::query()->orderBy('target_id')->get();

    expect($events)->toHaveCount(2);

    $byTarget = $events->keyBy('target_id');

    expect($byTarget[$obraUser->id]->action)->toBe(UserAdminAction::ObraAccessChanged);
    expect($byTarget[$obraUser->id]->actor_id)->toBe($this->actor->id);
    expect($byTarget[$obraUser->id]->before)->toBe(['obra_ids' => [$obra->id, $otherObra->id]]);
    expect($byTarget[$obraUser->id]->after)->toBe(['obra_ids' => [$otherObra->id]]);

    expect($byTarget[$suprimentosUser->id]->action)->toBe(UserAdminAction::ObraAccessChanged);
    expect($byTarget[$suprimentosUser->id]->before)->toBe(['obra_ids' => [$obra->id]]);
    expect($byTarget[$suprimentosUser->id]->after)->toBe(['obra_ids' => []]);
});

test('earlier obra audit rows survive with null FKs and keep subject_obra_id (RF-19)', function () {
    $obra = Obra::factory()->create();
    $invitation = ObraInvitation::factory()->revoked()->create(['obra_id' => $obra->id]);

    $earlier = collect([
        ObraAdminEvent::factory()->create(['obra_id' => $obra->id, 'action' => ObraAdminAction::ObraCreated, 'before' => null]),
        ObraAdminEvent::factory()->create(['obra_id' => $obra->id, 'action' => ObraAdminAction::ObraUpdated]),
        ObraAdminEvent::factory()->create(['obra_id' => $obra->id, 'obra_invitation_id' => $invitation->id, 'action' => ObraAdminAction::InvitationCreated, 'before' => null, 'after' => null]),
        ObraAdminEvent::factory()->create(['obra_id' => $obra->id, 'obra_invitation_id' => $invitation->id, 'action' => ObraAdminAction::InvitationRevoked, 'before' => null, 'after' => null]),
    ]);

    $this->action->execute($this->actor, $obra);

    foreach ($earlier as $event) {
        $row = DB::table('obra_admin_events')->where('id', $event->id)->first();

        expect($row)->not->toBeNull();
        expect($row->obra_id)->toBeNull();
        expect($row->obra_invitation_id)->toBeNull();
        expect($row->subject_obra_id)->toBe($obra->id);
        expect($row->action)->toBe($event->action->value);
    }
});

test('obra_deleted holds the 5-key snapshot and subject_obra_id = before.id (RF-20)', function () {
    $obra = Obra::factory()->concluida()->inactive()->create(['name' => 'Obra Antiga', 'responsavel' => 'Eng. Bia']);

    $this->action->execute($this->actor, $obra);

    $deleted = ObraAdminEvent::query()->where('action', ObraAdminAction::ObraDeleted->value)->get();

    expect($deleted)->toHaveCount(1);
    expect($deleted[0]->actor_id)->toBe($this->actor->id);
    expect($deleted[0]->before)->toBe([
        'id' => $obra->id,
        'name' => 'Obra Antiga',
        'responsavel' => 'Eng. Bia',
        'status' => ObraStatus::Concluido->value,
        'is_active' => false,
    ]);
    expect($deleted[0]->after)->toBeNull();
    expect($deleted[0]->obra_id)->toBeNull();
    expect($deleted[0]->subject_obra_id)->toBe($deleted[0]->before['id']);
});

test('pedidos, history, notifications, attachments and files are identical after a successful and a blocked attempt (RF-22)', function () {
    Storage::fake('pedido_anexos');

    $otherObra = Obra::factory()->create();
    $pedido = Pedido::factory()->create(['obra_id' => $otherObra->id, 'status_id' => $this->solicitadoId]);
    $attachment = PedidoAttachment::factory()->create(['pedido_id' => $pedido->id]);
    Storage::disk('pedido_anexos')->put($attachment->path, 'conteúdo');
    InternalNotification::factory()->create();
    Pedido::factory()->outra('Galpão')->create(['status_id' => $this->solicitadoId]);

    $deletable = Obra::factory()->create();
    $blocked = Obra::factory()->create();
    Pedido::factory()->create(['obra_id' => $blocked->id, 'status_id' => $this->solicitadoId]);

    $state = obraDeletionPedidoSideState();

    $this->action->execute($this->actor, $deletable);

    expect(obraDeletionPedidoSideState())->toBe($state);

    obraDeletionErrors(fn () => $this->action->execute($this->actor, $blocked));

    expect(obraDeletionPedidoSideState())->toBe($state);
});

test('a failure after the obra_deleted audit rolls everything back (RNF-02)', function () {
    $obra = Obra::factory()->create();
    $member = User::factory()->obra()->create();
    $member->obras()->attach($obra->id);
    ObraInvitation::factory()->create(['obra_id' => $obra->id]);
    ObraAdminEvent::factory()->create(['obra_id' => $obra->id, 'action' => ObraAdminAction::ObraCreated]);

    $before = obraDeletionObraSideRows();

    DB::listen(function (QueryExecuted $query): void {
        if (str_contains($query->sql, 'insert into "user_admin_events"')) {
            throw new RuntimeException('falha injetada');
        }
    });

    expect(fn () => $this->action->execute($this->actor, $obra))
        ->toThrow(RuntimeException::class, 'falha injetada');

    expect(obraDeletionObraSideRows())->toBe($before);
});

test('a deadlock inside the deletion becomes a 422 on excluir with nothing written (Q-02, RNF-01)', function () {
    $obra = Obra::factory()->create();
    $member = User::factory()->obra()->create();
    $member->obras()->attach($obra->id);
    ObraInvitation::factory()->create(['obra_id' => $obra->id]);

    $before = obraDeletionObraSideRows();

    Obra::deleting(function (): void {
        throw obraDeletionDeadlockException();
    });

    expect(obraDeletionErrors(fn () => $this->action->execute($this->actor, $obra)))->toBe([
        'excluir' => ['Não foi possível excluir a obra agora porque ela foi alterada ao mesmo tempo por outra operação. Tente novamente.'],
    ]);

    expect(obraDeletionObraSideRows())->toBe($before);
});

test('a deletion attempt issues at most 15 statements for 1 and 50 users and convites (RNF-03)', function (int $count, bool $blocked) {
    $obra = Obra::factory()->create();
    $otherObra = Obra::factory()->create();
    $creator = User::factory()->gestao()->create();

    foreach (User::factory()->obra()->count($count)->create() as $user) {
        $user->obras()->attach([$obra->id, $otherObra->id]);
    }

    ObraInvitation::factory()->count($count)->create(['obra_id' => $obra->id, 'created_by' => $creator->id]);

    if ($blocked) {
        ObraInvitation::factory()->used()->create(['obra_id' => $obra->id, 'created_by' => $creator->id]);
    }

    $actor = User::factory()->gestao()->create();
    $obra = Obra::query()->findOrFail($obra->id);

    $statements = 0;
    DB::listen(function () use (&$statements): void {
        $statements++;
    });

    try {
        $this->action->execute($actor, $obra);
    } catch (ValidationException) {
    }

    expect($statements)->toBeLessThanOrEqual(15);
    expect(Obra::query()->whereKey($obra->id)->exists())->toBe($blocked);

    if (! $blocked) {
        expect(UserAdminEvent::query()->count())->toBe($count);
    }
})->with([1, 50])->with([false, true]);

test('an obra actor or an unknown papel is refused with nothing written (RF-09)', function (string $papel) {
    $obra = Obra::factory()->create();
    $before = obraDeletionObraSideRows();

    expect(fn () => $this->action->execute(userForPapel($papel), $obra))
        ->toThrow(AuthorizationException::class);

    expect(obraDeletionObraSideRows())->toBe($before);
})->with(['obra', 'sem papel']);
