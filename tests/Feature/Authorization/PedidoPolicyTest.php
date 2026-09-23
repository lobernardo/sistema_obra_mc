<?php

use App\Models\Obra;
use App\Models\Pedido;
use App\Models\Status;
use App\Models\User;

dataset('operational mutations', [
    'setResponsavel',
    'setPrioridade',
    'setPrevisao',
    'updateStatus',
    'cancelar',
]);

test('obra is denied every operational mutation', function (string $ability) {
    $actor = User::factory()->obra()->create();
    $pedido = Pedido::factory()->create();

    expect($actor->can($ability, $pedido))->toBeFalse();
})->with('operational mutations');

test('suprimentos is permitted every operational mutation', function (string $ability) {
    $actor = User::factory()->suprimentos()->create();
    $pedido = Pedido::factory()->create();

    expect($actor->can($ability, $pedido))->toBeTrue();
})->with('operational mutations');

test('gestao is denied every operational mutation', function (string $ability) {
    $actor = User::factory()->gestao()->create();
    $pedido = Pedido::factory()->create();

    expect($actor->can($ability, $pedido))->toBeFalse();
})->with('operational mutations');

test('obra is denied reading a pedido from an obra it is not associated with', function () {
    $actor = User::factory()->obra()->create();
    $pedido = Pedido::factory()->create();

    expect($actor->can('view', $pedido))->toBeFalse();
});

test('obra associated with multiple obras can view pedidos from all of them', function () {
    $actor = User::factory()->obra()->create();
    $obraA = Obra::factory()->create();
    $obraB = Obra::factory()->create();
    $actor->obras()->attach([$obraA->id, $obraB->id]);

    $status = Status::factory()->solicitado()->create();
    $pedidoA = Pedido::factory()->create(['obra_id' => $obraA->id, 'status_id' => $status->id]);
    $pedidoB = Pedido::factory()->create(['obra_id' => $obraB->id, 'status_id' => $status->id]);

    expect($actor->can('view', $pedidoA))->toBeTrue();
    expect($actor->can('view', $pedidoB))->toBeTrue();
});

test('suprimentos and gestao can view any pedido regardless of obra association', function (string $role) {
    $actor = User::factory()->{$role}()->create();
    $pedido = Pedido::factory()->create();

    expect($actor->can('view', $pedido))->toBeTrue();
})->with(['suprimentos', 'gestao']);

/**
 * RF-01 / CT-05: `create` delegates to the `create-pedido` ability — Obra
 * and Suprimentos create, Gestão and a role-less user never do. The obra
 * checks (association, Concluído, zero obras) moved to CreatePedidoAction.
 */
test('obra and suprimentos can create a pedido', function (string $role) {
    $actor = User::factory()->{$role}()->create();

    expect($actor->can('create', Pedido::class))->toBeTrue();
})->with(['obra', 'suprimentos']);

test('gestao and a user without a recognised papel are denied creating a pedido', function () {
    $gestao = User::factory()->gestao()->create();
    $unrecognised = User::factory()->create();

    expect($gestao->can('create', Pedido::class))->toBeFalse();
    expect($unrecognised->can('create', Pedido::class))->toBeFalse();
});

test('an obra requester can view their own pedido "Outra" and no other obra user can (RF-40)', function () {
    $status = Status::factory()->solicitado()->create();
    $requester = User::factory()->obra()->create();
    $pedido = Pedido::factory()->outra('Galpão provisório')->create([
        'requester_id' => $requester->id,
        'status_id' => $status->id,
    ]);

    $unassociated = User::factory()->obra()->create();
    $associatedToEveryObra = User::factory()->obra()->create();
    $associatedToEveryObra->obras()->attach(Obra::factory()->count(2)->create()->modelKeys());

    expect($requester->can('view', $pedido))->toBeTrue();
    expect($unassociated->can('view', $pedido))->toBeFalse();
    expect($associatedToEveryObra->can('view', $pedido))->toBeFalse();
});

test('suprimentos and gestao can view any pedido "Outra"', function (string $role) {
    $pedido = Pedido::factory()->outra()->create(['status_id' => Status::factory()->solicitado()->create()->id]);

    expect(User::factory()->{$role}()->create()->can('view', $pedido))->toBeTrue();
})->with(['suprimentos', 'gestao']);

test('a pedido "Outra" whose reference equals an obra name grants that obra\'s users no access (RF-05)', function () {
    $obraX = Obra::factory()->create(['name' => 'Residencial Aurora']);
    $userOfX = User::factory()->obra()->create();
    $userOfX->obras()->attach($obraX);

    $pedido = Pedido::factory()->outra('Residencial Aurora')->create(['status_id' => Status::factory()->solicitado()->create()->id]);

    expect($userOfX->can('view', $pedido))->toBeFalse();
});
