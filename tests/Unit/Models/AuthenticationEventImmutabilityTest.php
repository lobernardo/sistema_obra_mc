<?php

use App\Enums\AuthenticationEventType;
use App\Models\AuthenticationEvent;
use App\Models\User;

test('updating an AuthenticationEvent after creation throws (RF-27)', function () {
    $event = AuthenticationEvent::factory()->create();

    expect(fn () => $event->update(['email' => 'tampered@example.com']))
        ->toThrow(LogicException::class);

    expect($event->fresh()->email)->not->toBe('tampered@example.com');
});

test('deleting an AuthenticationEvent throws (RF-27)', function () {
    $event = AuthenticationEvent::factory()->create();

    expect(fn () => $event->delete())->toThrow(LogicException::class);

    expect(AuthenticationEvent::query()->whereKey($event->id)->exists())->toBeTrue();
});

test('AuthenticationEvent has no updated_at, casts event and accepts a null user (CT-03)', function () {
    $user = User::factory()->obra()->create();

    $event = AuthenticationEvent::query()->create([
        'event' => AuthenticationEventType::LoginSuccess,
        'user_id' => $user->id,
        'email' => $user->email,
        'ip' => '203.0.113.10',
        'user_agent' => 'Mozilla/5.0',
    ]);

    $fresh = $event->fresh();

    expect(AuthenticationEvent::UPDATED_AT)->toBeNull();
    expect($fresh->getAttributes())->not->toHaveKey('updated_at');
    expect($fresh->event)->toBe(AuthenticationEventType::LoginSuccess);
    expect($fresh->user->is($user))->toBeTrue();
    expect($fresh->created_at)->not->toBeNull();

    $anonymous = AuthenticationEvent::query()->create([
        'event' => AuthenticationEventType::LoginFailed,
        'user_id' => null,
        'email' => 'ninguem@example.com',
        'ip' => '2001:db8::1',
        'user_agent' => null,
    ]);

    expect($anonymous->fresh()->user_id)->toBeNull();
    expect($anonymous->fresh()->user)->toBeNull();
    expect($anonymous->fresh()->event)->toBe(AuthenticationEventType::LoginFailed);
});

test('the catalog has exactly the six RF-26 slugs and no password_changed (D-04)', function () {
    $slugs = array_map(fn (AuthenticationEventType $case): string => $case->value, AuthenticationEventType::cases());

    expect($slugs)->toBe(['login_success', 'login_failed', 'logout', 'password_reset', 'password_defined', 'session_revoked']);
    expect(AuthenticationEventType::tryFrom('password_changed'))->toBeNull();
});

test('AuthenticationEventPolicy denies update and delete for every papel (RF-27)', function (string $factoryState) {
    $user = User::factory()->{$factoryState}()->create();
    $event = AuthenticationEvent::factory()->create();

    expect($user->can('update', $event))->toBeFalse();
    expect($user->can('delete', $event))->toBeFalse();
})->with(['obra', 'suprimentos', 'gestao']);
