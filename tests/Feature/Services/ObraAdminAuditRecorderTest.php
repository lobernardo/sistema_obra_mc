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

    expect(array_keys($snapshot))->toBe(ObraAdminAuditRecorder::WHITELIST);
    expect($snapshot)->toBe(['name' => 'Obra X', 'responsavel' => null, 'status' => 'concluido']);
});
