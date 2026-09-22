<?php

use App\Enums\UserAdminAction;
use App\Models\User;
use App\Models\UserAdminEvent;

test('updating a UserAdminEvent after creation throws (RF-23)', function () {
    $event = UserAdminEvent::factory()->create();

    expect(fn () => $event->update(['after' => ['name' => 'tampered']]))
        ->toThrow(LogicException::class);

    expect($event->fresh()->after)->not->toBe(['name' => 'tampered']);
});

test('deleting a UserAdminEvent throws (RF-23)', function () {
    $event = UserAdminEvent::factory()->create();

    expect(fn () => $event->delete())->toThrow(LogicException::class);

    expect(UserAdminEvent::query()->whereKey($event->id)->exists())->toBeTrue();
});

test('UserAdminEvent has no updated_at and casts action, before and after', function () {
    $actor = User::factory()->gestao()->create();
    $target = User::factory()->obra()->create();

    $event = UserAdminEvent::query()->create([
        'actor_id' => $actor->id,
        'target_id' => $target->id,
        'action' => UserAdminAction::UserCreated,
        'before' => null,
        'after' => ['name' => 'Alvo'],
    ]);

    $fresh = $event->fresh();

    expect(UserAdminEvent::UPDATED_AT)->toBeNull();
    expect($fresh->getAttributes())->not->toHaveKey('updated_at');
    expect($fresh->action)->toBe(UserAdminAction::UserCreated);
    expect($fresh->before)->toBeNull();
    expect($fresh->after)->toBe(['name' => 'Alvo']);
    expect($fresh->actor->is($actor))->toBeTrue();
    expect($fresh->target->is($target))->toBeTrue();
});

test('UserAdminEventPolicy denies update and delete for every papel (RF-23)', function (string $factoryState) {
    $user = User::factory()->{$factoryState}()->create();
    $event = UserAdminEvent::factory()->create();

    expect($user->can('update', $event))->toBeFalse();
    expect($user->can('delete', $event))->toBeFalse();
})->with(['obra', 'suprimentos', 'gestao']);
