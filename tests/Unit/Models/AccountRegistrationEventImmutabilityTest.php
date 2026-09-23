<?php

use App\Enums\AccountOrigin;
use App\Models\AccountRegistrationEvent;
use App\Models\ObraInvitation;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

test('updating an AccountRegistrationEvent after creation throws (CT-07, RF-22)', function () {
    $event = AccountRegistrationEvent::factory()->create(['ip' => '10.0.0.1']);

    expect(fn () => $event->update(['ip' => '10.0.0.2']))->toThrow(LogicException::class);

    expect($event->fresh()->ip)->toBe('10.0.0.1');
});

test('saving an existing AccountRegistrationEvent throws (CT-07, RF-22)', function () {
    $event = AccountRegistrationEvent::factory()->create();
    $event->origin = AccountOrigin::Convite;

    expect(fn () => $event->save())->toThrow(LogicException::class);

    expect($event->fresh()->origin)->toBe(AccountOrigin::NovoCadastro);
});

test('deleting an AccountRegistrationEvent throws (CT-07, RF-22)', function () {
    $event = AccountRegistrationEvent::factory()->create();

    expect(fn () => $event->delete())->toThrow(LogicException::class);

    expect(AccountRegistrationEvent::query()->whereKey($event->id)->exists())->toBeTrue();
});

test('AccountRegistrationEvent has no updated_at, casts origin and stores neither password nor e-mail (RF-22)', function () {
    $user = User::factory()->obra()->create();
    $invitation = ObraInvitation::factory()->create();

    $event = AccountRegistrationEvent::query()->create([
        'user_id' => $user->id,
        'origin' => AccountOrigin::Convite,
        'obra_invitation_id' => $invitation->id,
        'ip' => '2001:0db8:85a3:0000:0000:8a2e:0370:7334',
    ]);

    $fresh = $event->fresh();

    expect(AccountRegistrationEvent::UPDATED_AT)->toBeNull();
    expect($fresh->getAttributes())->not->toHaveKey('updated_at');
    expect($fresh->origin)->toBe(AccountOrigin::Convite);
    expect($fresh->user->is($user))->toBeTrue();
    expect($fresh->invitation->is($invitation))->toBeTrue();
    expect(Schema::getColumnListing('account_registration_events'))
        ->toEqualCanonicalizing(['id', 'user_id', 'origin', 'obra_invitation_id', 'ip', 'created_at']);
});

test('AccountRegistrationEventPolicy denies update and delete for every papel (CT-07)', function (string $factoryState) {
    $user = User::factory()->{$factoryState}()->create();
    $event = AccountRegistrationEvent::factory()->create();

    expect($user->can('update', $event))->toBeFalse();
    expect($user->can('delete', $event))->toBeFalse();
})->with(['obra', 'suprimentos', 'gestao']);
