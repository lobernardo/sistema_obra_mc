<?php

use App\Enums\ObraAdminAction;
use App\Models\Obra;
use App\Models\ObraAdminEvent;
use App\Models\ObraInvitation;
use App\Models\User;

test('updating an ObraAdminEvent after creation throws (CT-07)', function () {
    $event = ObraAdminEvent::factory()->create();

    expect(fn () => $event->update(['after' => ['name' => 'adulterado']]))
        ->toThrow(LogicException::class);

    expect($event->fresh()->after)->not->toBe(['name' => 'adulterado']);
});

test('saving an existing ObraAdminEvent throws (CT-07)', function () {
    $event = ObraAdminEvent::factory()->create();
    $event->action = ObraAdminAction::InvitationRevoked;

    expect(fn () => $event->save())->toThrow(LogicException::class);

    expect($event->fresh()->action)->toBe(ObraAdminAction::ObraUpdated);
});

test('deleting an ObraAdminEvent throws (CT-07)', function () {
    $event = ObraAdminEvent::factory()->create();

    expect(fn () => $event->delete())->toThrow(LogicException::class);

    expect(ObraAdminEvent::query()->whereKey($event->id)->exists())->toBeTrue();
});

test('ObraAdminEvent has no updated_at and casts action, before and after', function () {
    $actor = User::factory()->gestao()->create();
    $obra = Obra::factory()->create();
    $invitation = ObraInvitation::factory()->for($obra)->create();

    $event = ObraAdminEvent::query()->create([
        'actor_id' => $actor->id,
        'obra_id' => $obra->id,
        'obra_invitation_id' => $invitation->id,
        'action' => ObraAdminAction::InvitationCreated,
        'before' => null,
        'after' => ['status' => 'em_andamento'],
    ]);

    $fresh = $event->fresh();

    expect(ObraAdminEvent::UPDATED_AT)->toBeNull();
    expect($fresh->getAttributes())->not->toHaveKey('updated_at');
    expect($fresh->action)->toBe(ObraAdminAction::InvitationCreated);
    expect($fresh->before)->toBeNull();
    expect($fresh->after)->toBe(['status' => 'em_andamento']);
    expect($fresh->actor->is($actor))->toBeTrue();
    expect($fresh->obra->is($obra))->toBeTrue();
    expect($fresh->invitation->is($invitation))->toBeTrue();
});

test('ObraAdminEventPolicy denies update and delete for every papel (CT-07)', function (string $factoryState) {
    $user = User::factory()->{$factoryState}()->create();
    $event = ObraAdminEvent::factory()->create();

    expect($user->can('update', $event))->toBeFalse();
    expect($user->can('delete', $event))->toBeFalse();
})->with(['obra', 'suprimentos', 'gestao']);
