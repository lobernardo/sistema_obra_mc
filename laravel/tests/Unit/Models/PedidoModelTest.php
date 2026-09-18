<?php

use App\Models\EventType;
use App\Models\Obra;
use App\Models\Pedido;
use App\Models\Priority;
use App\Models\Status;
use App\Models\User;

test('Pedido exposes all its relations', function () {
    $obra = Obra::factory()->create();
    $requester = User::factory()->obra()->create();
    $responsible = User::factory()->suprimentos()->create();
    $status = Status::factory()->solicitado()->create();
    $priority = Priority::factory()->normal()->create();

    $pedido = Pedido::factory()->create([
        'obra_id' => $obra->id,
        'requester_id' => $requester->id,
        'responsible_id' => $responsible->id,
        'status_id' => $status->id,
        'priority_id' => $priority->id,
    ]);

    $eventType = EventType::factory()->criacaoPedido()->create();
    $pedido->events()->create([
        'event_type_id' => $eventType->id,
        'actor_id' => $requester->id,
    ]);

    expect($pedido->obra->is($obra))->toBeTrue();
    expect($pedido->requester->is($requester))->toBeTrue();
    expect($pedido->responsible->is($responsible))->toBeTrue();
    expect($pedido->status->is($status))->toBeTrue();
    expect($pedido->priority->is($priority))->toBeTrue();
    expect($pedido->events)->toHaveCount(1);
    expect($pedido->events->first()->eventType->is($eventType))->toBeTrue();
});

test('Pedido declares an explicit fillable list', function () {
    $pedido = new Pedido;

    expect($pedido->getFillable())->toEqualCanonicalizing([
        'code',
        'obra_id',
        'requester_id',
        'requested_at',
        'needed_at',
        'items_description',
        'status_id',
        'priority_id',
        'responsible_id',
        'expected_delivery_at',
        'is_demo',
    ]);
});

test('PedidoEvent exposes pedido, eventType and actor relations', function () {
    $requester = User::factory()->obra()->create();
    $pedido = Pedido::factory()->create(['requester_id' => $requester->id]);
    $eventType = EventType::factory()->criacaoPedido()->create();

    $event = $pedido->events()->create([
        'event_type_id' => $eventType->id,
        'actor_id' => $requester->id,
    ]);

    expect($event->pedido->is($pedido))->toBeTrue();
    expect($event->eventType->is($eventType))->toBeTrue();
    expect($event->actor->is($requester))->toBeTrue();
});
