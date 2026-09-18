<?php

use App\Models\EventType;
use App\Models\Priority;
use App\Models\Role;
use App\Models\Status;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;

test('Role exposes its fillable columns and users relation', function () {
    $role = Role::factory()->suprimentos()->create();
    $user = User::factory()->create(['role_id' => $role->id]);

    expect($role->users)->toHaveCount(1);
    expect($role->users->first()->is($user))->toBeTrue();
    expect($role->getFillable())->toContain('name', 'slug', 'description', 'is_active');
});

test('Status exposes pedidos relation and orders by sort_order', function () {
    $entregue = Status::factory()->entregue()->create();
    $solicitado = Status::factory()->solicitado()->create();
    $emAnalise = Status::factory()->emAnalise()->create();

    expect(Status::ordered()->pluck('slug')->all())->toBe(['solicitado', 'em_analise', 'entregue']);
    expect($solicitado->getFillable())->toContain('name', 'slug', 'description', 'sort_order', 'is_active');
});

test('Priority exposes pedidos relation and orders by sort_order', function () {
    $urgente = Priority::factory()->urgente()->create();
    $baixa = Priority::factory()->baixa()->create();
    $normal = Priority::factory()->normal()->create();

    expect(Priority::ordered()->pluck('slug')->all())->toBe(['baixa', 'normal', 'urgente']);
    expect($baixa->getFillable())->toContain('name', 'slug', 'sort_order', 'is_active');
});

test('EventType exposes its fillable columns and pedidoEvents relation', function () {
    $eventType = EventType::factory()->criacaoPedido()->create();

    expect($eventType->pedidoEvents())->toBeInstanceOf(HasMany::class);
    expect($eventType->getFillable())->toContain('name', 'slug', 'description', 'is_active');
});
