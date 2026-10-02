<?php

use App\Actions\Obras\SetObraActiveAction;
use App\Enums\ObraAdminAction;
use App\Enums\ObraStatus;
use App\Exceptions\Obras\ObraNotFoundException;
use App\Models\InternalNotification;
use App\Models\Obra;
use App\Models\ObraAdminEvent;
use App\Models\ObraInvitation;
use App\Models\Pedido;
use App\Models\PedidoAttachment;
use App\Models\User;
use App\Models\UserAdminEvent;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->action = app(SetObraActiveAction::class);
});

/**
 * Every row of the tables Desativar/Reativar must never touch (RF-06).
 *
 * @return array<string, list<array<string, mixed>>>
 */
function obraActivityUntouchedTables(): array
{
    $snapshot = [];

    foreach (['pedidos', 'pedido_events', 'internal_notifications', 'pedido_attachments', 'obra_profile', 'obra_invitations', 'user_admin_events'] as $table) {
        $snapshot[$table] = DB::table($table)->get()->map(fn (object $row): array => (array) $row)->sortBy(fn (array $row): string => json_encode($row))->values()->all();
    }

    return $snapshot;
}

test('Desativar and Reativar flip is_active and keep the status (RF-05, RF-02)', function (string $papel) {
    $actor = User::factory()->{$papel}()->create();
    $obra = Obra::factory()->emAndamento()->create();

    $deactivated = $this->action->execute($actor, $obra, false);

    expect($deactivated->isActive())->toBeFalse();
    expect($obra->fresh()->is_active)->toBeFalse();
    expect($obra->fresh()->status)->toBe(ObraStatus::EmAndamento);

    $reactivated = $this->action->execute($actor, $obra, true);

    expect($reactivated->isActive())->toBeTrue();
    expect($obra->fresh()->is_active)->toBeTrue();
    expect($obra->fresh()->status)->toBe(ObraStatus::EmAndamento);
})->with(['gestao', 'suprimentos']);

test('nome and responsável are unchanged by a deactivation (RF-02)', function () {
    $obra = Obra::factory()->concluida()->create(['name' => 'Obra Leste', 'responsavel' => 'Eng. Ana']);

    $this->action->execute(User::factory()->gestao()->create(), $obra, false);

    $fresh = $obra->fresh();

    expect($fresh->name)->toBe('Obra Leste');
    expect($fresh->responsavel)->toBe('Eng. Ana');
    expect($fresh->status)->toBe(ObraStatus::Concluido);
});

test('only the obra row and one audit change: pedidos, history, notifications, attachments, associations, convites and user audit are identical (RF-06)', function () {
    $actor = User::factory()->gestao()->create();
    $obra = Obra::factory()->create();
    $member = User::factory()->obra()->create();
    $member->obras()->attach($obra->id);
    $pedido = Pedido::factory()->create(['obra_id' => $obra->id]);
    PedidoAttachment::factory()->create(['pedido_id' => $pedido->id]);
    InternalNotification::factory()->create();
    ObraInvitation::factory()->create(['obra_id' => $obra->id]);
    UserAdminEvent::factory()->create();

    $before = obraActivityUntouchedTables();
    $auditCount = ObraAdminEvent::query()->count();

    $this->action->execute($actor, $obra, false);

    expect(obraActivityUntouchedTables())->toBe($before);
    expect(ObraAdminEvent::query()->count())->toBe($auditCount + 1);

    $this->action->execute($actor, $obra, true);

    expect(obraActivityUntouchedTables())->toBe($before);
    expect(ObraAdminEvent::query()->count())->toBe($auditCount + 2);
});

test('asking for the current value writes nothing and records no audit (RF-07)', function (bool $active) {
    $obra = Obra::factory()->create(['is_active' => $active]);
    $updatedAt = $obra->fresh()->updated_at;

    $this->travel(5)->minutes();

    $result = $this->action->execute(User::factory()->suprimentos()->create(), $obra, $active);

    expect($result->isActive())->toBe($active);
    expect(ObraAdminEvent::query()->count())->toBe(0);
    expect($obra->fresh()->updated_at->equalTo($updatedAt))->toBeTrue();
})->with([true, false]);

test('each change records exactly one audit with the CT-02 payload and subject_obra_id (RF-08)', function () {
    $actor = User::factory()->gestao()->create();
    $obra = Obra::factory()->create();

    $this->action->execute($actor, $obra, false);
    $this->action->execute($actor, $obra, true);

    $events = ObraAdminEvent::query()->orderBy('id')->get();

    expect($events)->toHaveCount(2);

    expect($events[0]->action)->toBe(ObraAdminAction::ObraDeactivated);
    expect($events[0]->before)->toBe(['is_active' => true]);
    expect($events[0]->after)->toBe(['is_active' => false]);

    expect($events[1]->action)->toBe(ObraAdminAction::ObraReactivated);
    expect($events[1]->before)->toBe(['is_active' => false]);
    expect($events[1]->after)->toBe(['is_active' => true]);

    foreach ($events as $event) {
        expect($event->actor_id)->toBe($actor->id);
        expect($event->obra_id)->toBe($obra->id);
        expect($event->subject_obra_id)->toBe($obra->id);
        expect($event->obra_invitation_id)->toBeNull();
    }
});

test('a failure after the update rolls back the flag and writes no audit (RF-08, RNF-02)', function () {
    $obra = Obra::factory()->create();

    ObraAdminEvent::creating(function (): void {
        throw new RuntimeException('falha injetada');
    });

    expect(fn () => $this->action->execute(User::factory()->gestao()->create(), $obra, false))
        ->toThrow(RuntimeException::class, 'falha injetada');

    expect($obra->fresh()->is_active)->toBeTrue();
    expect(ObraAdminEvent::query()->count())->toBe(0);
});

test('an obra actor or an unknown papel is refused with nothing written (RF-09)', function (string $papel) {
    $actor = userForPapel($papel);
    $obra = Obra::factory()->create();

    expect(fn () => $this->action->execute($actor, $obra, false))
        ->toThrow(AuthorizationException::class);

    expect($obra->fresh()->is_active)->toBeTrue();
    expect(ObraAdminEvent::query()->count())->toBe(0);
})->with(['obra', 'sem papel']);

test('an obra deleted before the call throws ObraNotFoundException (RNF-01)', function () {
    $obra = Obra::factory()->create();
    DB::table('obras')->where('id', $obra->id)->delete();

    expect(fn () => $this->action->execute(User::factory()->gestao()->create(), $obra, false))
        ->toThrow(ObraNotFoundException::class, 'A obra informada não foi encontrada.');

    expect(ObraAdminEvent::query()->count())->toBe(0);
});
