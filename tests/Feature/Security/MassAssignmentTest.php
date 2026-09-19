<?php

use App\Models\EventType;
use App\Models\Obra;
use App\Models\Pedido;
use App\Models\PedidoEvent;
use App\Models\Priority;
use App\Models\Role;
use App\Models\Status;
use App\Models\User;

/**
 * RNF-08: every writable Model must declare an explicit `#[Fillable]` list
 * (this app's Laravel 13 equivalent of `protected $fillable`) rather than
 * relying on `$guarded = []`, so an unexpected key merged into a payload
 * cannot silently set an attribute the caller must not control.
 */
dataset('writable models', [
    'Pedido' => [Pedido::class, ['code', 'obra_id', 'requester_id', 'requested_at', 'needed_at', 'items_description', 'status_id', 'priority_id', 'responsible_id', 'expected_delivery_at', 'is_demo']],
    'PedidoEvent' => [PedidoEvent::class, ['pedido_id', 'event_type_id', 'previous_value', 'new_value', 'actor_id']],
    'User' => [User::class, ['name', 'email', 'password', 'role_id', 'is_active', 'is_demo']],
    'Obra' => [Obra::class, ['name', 'is_active', 'is_demo']],
    'Status' => [Status::class, ['name', 'slug', 'description', 'sort_order', 'is_active']],
    'Priority' => [Priority::class, ['name', 'slug', 'sort_order', 'is_active']],
    'Role' => [Role::class, ['name', 'slug', 'description', 'is_active']],
    'EventType' => [EventType::class, ['name', 'slug', 'description', 'is_active']],
]);

test('each writable model declares an explicit non-empty fillable list', function (string $class, array $expectedFillable) {
    expect((new $class)->getFillable())
        ->not->toBeEmpty()
        ->toEqualCanonicalizing($expectedFillable);
})->with('writable models');

dataset('guarded columns per model', [
    'Pedido.created_at' => [Pedido::class, 'created_at'],
    'Pedido.id' => [Pedido::class, 'id'],
    'PedidoEvent.created_at' => [PedidoEvent::class, 'created_at'],
    'User.remember_token' => [User::class, 'remember_token'],
    'User.email_verified_at' => [User::class, 'email_verified_at'],
    'Obra.created_at' => [Obra::class, 'created_at'],
    'Status.created_at' => [Status::class, 'created_at'],
    'Priority.created_at' => [Priority::class, 'created_at'],
    'Role.created_at' => [Role::class, 'created_at'],
    'EventType.created_at' => [EventType::class, 'created_at'],
]);

test('mass assignment silently drops a column absent from the fillable list', function (string $class, string $guardedColumn) {
    $model = new $class;

    $model->fill([$guardedColumn => 'forged-value']);

    expect($model->getAttributes())->not->toHaveKey($guardedColumn);
})->with('guarded columns per model');

test('an unexpected key merged into a create payload cannot set a non-fillable attribute', function () {
    $role = Role::factory()->create();

    $user = User::create([
        'name' => 'Forged User',
        'email' => 'forged@example.test',
        'password' => 'secret-password',
        'role_id' => $role->id,
        'is_active' => true,
        'is_demo' => false,
        // Not a real column, proving unfillable keys are ignored rather than
        // causing a hard failure when they slip into a mass-assigned payload.
        'is_admin' => true,
    ]);

    expect($user->getAttributes())->not->toHaveKey('is_admin');
});
