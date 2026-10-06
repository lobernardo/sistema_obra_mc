<?php

use App\Enums\RoleSlug;
use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;

dataset('target abilities', [
    'update',
    'changeRole',
    'activate',
    'deactivate',
    'sendAccessLink',
]);

dataset('actor-only abilities', [
    'viewAny',
    'create',
]);

dataset('non-admin papéis', [
    'obra',
    'suprimentos',
]);

test('gestao is permitted every target ability on another user', function (string $ability) {
    $actor = User::factory()->gestao()->create();
    $target = User::factory()->obra()->create();

    expect($actor->can($ability, $target))->toBeTrue();
})->with('target abilities');

test('gestao is permitted every actor-only ability', function (string $ability) {
    $actor = User::factory()->gestao()->create();

    expect($actor->can($ability, User::class))->toBeTrue();
})->with('actor-only abilities');

test('obra and suprimentos are denied every target ability', function (string $factoryState, string $ability) {
    $actor = User::factory()->{$factoryState}()->create();
    $target = User::factory()->obra()->create();

    expect($actor->can($ability, $target))->toBeFalse();
})->with('non-admin papéis')->with('target abilities');

test('obra and suprimentos are denied every actor-only ability', function (string $factoryState, string $ability) {
    $actor = User::factory()->{$factoryState}()->create();

    expect($actor->can($ability, User::class))->toBeFalse();
})->with('non-admin papéis')->with('actor-only abilities');

test('gestao cannot change the role of or deactivate its own account', function (string $ability) {
    $actor = User::factory()->gestao()->create();

    expect($actor->can($ability, $actor))->toBeFalse();
})->with(['changeRole', 'deactivate']);

test('gestao may still update, activate and re-issue the access link of its own account', function (string $ability) {
    $actor = User::factory()->gestao()->create();

    expect($actor->can($ability, $actor))->toBeTrue();
})->with(['update', 'activate', 'sendAccessLink']);

test('RoleSlug keeps exactly the 3 papéis — no fourth Admin role is introduced', function () {
    expect(RoleSlug::cases())->toHaveCount(3);
    expect(array_map(fn (RoleSlug $case) => $case->value, RoleSlug::cases()))
        ->toBe(['obra', 'suprimentos', 'gestao']);
});

test('the inactive factory state creates a deactivated user', function () {
    $user = User::factory()->inactive()->create();

    expect($user->is_active)->toBeFalse();
});

test('gestao is denied changeRole on its own account with the PT-BR message (RF-01)', function () {
    $actor = User::factory()->gestao()->create();

    $response = Gate::forUser($actor)->inspect('changeRole', $actor);

    expect($response->denied())->toBeTrue();
    expect($response->message())->toBe('Não é possível regredir próprio acesso. Solicite à gestão!');
    expect(UserPolicy::SELF_ROLE_CHANGE_DENIED_MESSAGE)->toBe('Não é possível regredir próprio acesso. Solicite à gestão!');
    expect($actor->can('changeRole', $actor))->toBeFalse();
});

test('obra and suprimentos are denied changeRole without the self-change message (RF-02)', function (string $factoryState) {
    $actor = User::factory()->{$factoryState}()->create();
    $other = User::factory()->obra()->create();

    foreach ([$other, $actor] as $target) {
        $response = Gate::forUser($actor)->inspect('changeRole', $target);

        expect($response->denied())->toBeTrue();
        expect($response->message())->not->toBe(UserPolicy::SELF_ROLE_CHANGE_DENIED_MESSAGE);
    }
})->with('non-admin papéis');

test('deactivate on the own account keeps its plain denial without the self-change message (RF-05)', function () {
    $actor = User::factory()->gestao()->create();

    $response = Gate::forUser($actor)->inspect('deactivate', $actor);

    expect($response->denied())->toBeTrue();
    expect($response->message())->not->toBe(UserPolicy::SELF_ROLE_CHANGE_DENIED_MESSAGE);
    expect((new ReflectionMethod(UserPolicy::class, 'deactivate'))->getReturnType()?->getName())->toBe('bool');
});
