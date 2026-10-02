<?php

use App\Enums\ObraAdminAction;
use App\Enums\ObraStatus;
use App\Models\Obra;
use App\Models\ObraAdminEvent;
use App\Models\ObraInvitation;
use App\Models\User;
use App\Services\ObraAdminAuditRecorder;

test('record appends one row with actor, obra, action and whitelisted payloads (CT-07, RF-34)', function () {
    $actor = User::factory()->suprimentos()->create();
    $obra = Obra::factory()->create();
    $invitation = ObraInvitation::factory()->for($obra)->create();

    $event = app(ObraAdminAuditRecorder::class)->record(
        $actor,
        $obra,
        ObraAdminAction::InvitationCreated,
        null,
        null,
        $invitation,
    );

    expect(ObraAdminEvent::query()->count())->toBe(1);
    expect($event->fresh()->actor_id)->toBe($actor->id);
    expect($event->fresh()->obra_id)->toBe($obra->id);
    expect($event->fresh()->obra_invitation_id)->toBe($invitation->id);
    expect($event->fresh()->action)->toBe(ObraAdminAction::InvitationCreated);
});

test('record without a convite leaves obra_invitation_id null (CT-07)', function () {
    $actor = User::factory()->gestao()->create();
    $obra = Obra::factory()->create(['name' => 'Obra Auditada', 'responsavel' => 'Fulano']);
    $recorder = app(ObraAdminAuditRecorder::class);

    $event = $recorder->record($actor, $obra, ObraAdminAction::ObraCreated, null, $recorder->snapshot($obra));

    expect($event->fresh()->obra_invitation_id)->toBeNull();
    expect($event->fresh()->after)->toBe([
        'name' => 'Obra Auditada',
        'responsavel' => 'Fulano',
        'status' => ObraStatus::EmAndamento->value,
    ]);
});

test('a key outside the whitelist throws before writing and leaves 0 rows (CT-07)', function (string $side) {
    $actor = User::factory()->gestao()->create();
    $obra = Obra::factory()->create();
    $payload = ['name' => 'Obra', 'token_hash' => 'segredo'];

    expect(fn () => app(ObraAdminAuditRecorder::class)->record(
        $actor,
        $obra,
        ObraAdminAction::ObraUpdated,
        $side === 'before' ? $payload : null,
        $side === 'after' ? $payload : null,
    ))->toThrow(LogicException::class);

    expect(ObraAdminEvent::query()->count())->toBe(0);
})->with(['before', 'after']);

test('snapshot returns exactly name, responsavel and status slug (CT-07)', function () {
    $obra = Obra::factory()->concluida()->create(['name' => 'Obra X', 'responsavel' => null]);

    $snapshot = app(ObraAdminAuditRecorder::class)->snapshot($obra);

    expect(array_keys($snapshot))->toBe(['name', 'responsavel', 'status']);
    expect($snapshot)->toBe(['name' => 'Obra X', 'responsavel' => null, 'status' => 'concluido']);
});

test('the whitelist is exactly the CT-02 keys', function () {
    expect(ObraAdminAuditRecorder::WHITELIST)->toBe([
        'name', 'responsavel', 'status', 'id', 'is_active', 'pedidos_count', 'used_invitations_count',
    ]);
});

test('every action writes subject_obra_id = the obra id (RF-19, CT-02)', function (ObraAdminAction $action) {
    $actor = User::factory()->gestao()->create();
    $obra = Obra::factory()->create();

    $event = app(ObraAdminAuditRecorder::class)->record($actor, $obra, $action, null, null);

    expect($event->fresh()->subject_obra_id)->toBe($obra->id);
    expect($event->fresh()->obra_id)->toBe($obra->id);
})->with(ObraAdminAction::cases());

test('the new CT-02 keys are accepted in before and after', function () {
    $actor = User::factory()->gestao()->create();
    $obra = Obra::factory()->create();
    $recorder = app(ObraAdminAuditRecorder::class);

    $deleted = $recorder->record($actor, $obra, ObraAdminAction::ObraDeleted, $recorder->deletionSnapshot($obra), null);
    $blocked = $recorder->record($actor, $obra, ObraAdminAction::ObraDeleteBlocked, null, ['pedidos_count' => 2, 'used_invitations_count' => 0]);
    $deactivated = $recorder->record($actor, $obra, ObraAdminAction::ObraDeactivated, ['is_active' => true], ['is_active' => false]);

    expect($deleted->fresh()->before)->toBe([
        'id' => $obra->id,
        'name' => $obra->name,
        'responsavel' => null,
        'status' => 'em_andamento',
        'is_active' => true,
    ]);
    expect($blocked->fresh()->after)->toBe(['pedidos_count' => 2, 'used_invitations_count' => 0]);
    expect($deactivated->fresh()->after)->toBe(['is_active' => false]);
});

test('deletionSnapshot returns id, name, responsavel, status and is_active of an inactive obra (RF-20)', function () {
    $obra = Obra::factory()->concluida()->inactive()->create(['name' => 'Obra Y', 'responsavel' => 'Eng. Ana']);

    expect(app(ObraAdminAuditRecorder::class)->deletionSnapshot($obra))->toBe([
        'id' => $obra->id,
        'name' => 'Obra Y',
        'responsavel' => 'Eng. Ana',
        'status' => 'concluido',
        'is_active' => false,
    ]);
});
