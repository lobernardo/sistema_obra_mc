<?php

use App\Actions\Obras\Concerns\GuardsObraAdministration;
use App\Models\Obra;
use App\Models\ObraInvitation;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

dataset('obra managers', ['gestao', 'suprimentos']);

/**
 * Minimal consumer of the trait: the guard is private, so the test calls it
 * through a public wrapper exactly as the Actions do.
 */
function obraAdministrationGuard(): object
{
    return new class
    {
        use GuardsObraAdministration;

        public function check(User $actor): void
        {
            $this->ensureActorManagesObras($actor);
        }
    };
}

test('gestao and suprimentos may list, create, edit obras and manage associations', function (string $factoryState) {
    $actor = User::factory()->{$factoryState}()->create();
    $obra = Obra::factory()->create();

    expect($actor->can('viewAny', Obra::class))->toBeTrue();
    expect($actor->can('create', Obra::class))->toBeTrue();
    expect($actor->can('update', $obra))->toBeTrue();
    expect($actor->can('manageAssociations', Obra::class))->toBeTrue();
})->with('obra managers');

test('an obra user is denied every obra ability', function () {
    $actor = User::factory()->obra()->create();
    $obra = Obra::factory()->create();

    expect($actor->can('viewAny', Obra::class))->toBeFalse();
    expect($actor->can('create', Obra::class))->toBeFalse();
    expect($actor->can('update', $obra))->toBeFalse();
    expect($actor->can('manageAssociations', Obra::class))->toBeFalse();
});

test('gestao and suprimentos may deactivate, reactivate and delete an obra (RF-24)', function (string $factoryState) {
    $actor = User::factory()->{$factoryState}()->create();
    $obra = Obra::factory()->create();

    expect($actor->can('setActive', $obra))->toBeTrue();
    expect($actor->can('delete', $obra))->toBeTrue();
})->with('obra managers');

test('an obra user and an unknown papel may neither deactivate nor delete an obra (RF-24)', function (string $papel) {
    $actor = $papel === 'unknown'
        ? User::factory()->for(Role::factory()->state(['slug' => 'unknown']))->create()
        : User::factory()->{$papel}()->create();
    $obra = Obra::factory()->create();

    expect($actor->can('setActive', $obra))->toBeFalse();
    expect($actor->can('delete', $obra))->toBeFalse();
})->with(['obra', 'unknown']);

test('gestao and suprimentos may generate and revoke convites', function (string $factoryState) {
    $actor = User::factory()->{$factoryState}()->create();
    $invitation = ObraInvitation::factory()->create();

    expect($actor->can('create', [ObraInvitation::class, $invitation->obra]))->toBeTrue();
    expect($actor->can('revoke', $invitation))->toBeTrue();
})->with('obra managers');

test('an obra user may neither generate nor revoke convites', function () {
    $actor = User::factory()->obra()->create();
    $invitation = ObraInvitation::factory()->create();

    expect($actor->can('create', [ObraInvitation::class, $invitation->obra]))->toBeFalse();
    expect($actor->can('revoke', $invitation))->toBeFalse();
});

test('the Action guard throws a PT-BR AuthorizationException for an obra actor', function () {
    $actor = User::factory()->obra()->create();

    expect(fn () => obraAdministrationGuard()->check($actor))
        ->toThrow(AuthorizationException::class, 'Apenas os perfis Gestão e Suprimentos podem administrar obras.');
});

test('the Action guard lets gestao and suprimentos through', function (string $factoryState) {
    $actor = User::factory()->{$factoryState}()->create();

    expect(fn () => obraAdministrationGuard()->check($actor))->not->toThrow(AuthorizationException::class);
})->with('obra managers');
