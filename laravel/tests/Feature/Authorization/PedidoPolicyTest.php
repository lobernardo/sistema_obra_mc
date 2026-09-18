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

test('obra can create a pedido only for an obra it is associated with', function () {
    $actor = User::factory()->obra()->create();
    $associatedObra = Obra::factory()->create();
    $unassociatedObra = Obra::factory()->create();
    $actor->obras()->attach($associatedObra->id);

    expect($actor->can('create', [Pedido::class, $associatedObra]))->toBeTrue();
    expect($actor->can('create', [Pedido::class, $unassociatedObra]))->toBeFalse();
});

test('suprimentos and gestao are denied creating a pedido', function (string $role) {
    $actor = User::factory()->{$role}()->create();
    $obra = Obra::factory()->create();

    expect($actor->can('create', [Pedido::class, $obra]))->toBeFalse();
})->with(['suprimentos', 'gestao']);
