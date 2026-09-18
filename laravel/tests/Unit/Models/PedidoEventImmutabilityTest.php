<?php

use App\Models\EventType;
use App\Models\Pedido;
use App\Models\User;

test('updating a PedidoEvent after creation throws', function () {
    $requester = User::factory()->obra()->create();
    $pedido = Pedido::factory()->create(['requester_id' => $requester->id]);
    $eventType = EventType::factory()->criacaoPedido()->create();

    $event = $pedido->events()->create([
        'event_type_id' => $eventType->id,
        'actor_id' => $requester->id,
    ]);

    expect(fn () => $event->update(['new_value' => 'tampered']))
        ->toThrow(LogicException::class);
});

test('deleting a PedidoEvent throws', function () {
    $requester = User::factory()->obra()->create();
    $pedido = Pedido::factory()->create(['requester_id' => $requester->id]);
    $eventType = EventType::factory()->criacaoPedido()->create();

    $event = $pedido->events()->create([
        'event_type_id' => $eventType->id,
        'actor_id' => $requester->id,
    ]);

    expect(fn () => $event->delete())->toThrow(LogicException::class);
});

test('PedidoEventPolicy denies update and delete for every actor', function () {
    $requester = User::factory()->obra()->create();
    $suprimentos = User::factory()->suprimentos()->create();
    $pedido = Pedido::factory()->create(['requester_id' => $requester->id]);
    $eventType = EventType::factory()->criacaoPedido()->create();

    $event = $pedido->events()->create([
        'event_type_id' => $eventType->id,
        'actor_id' => $requester->id,
    ]);

    expect($suprimentos->can('update', $event))->toBeFalse();
    expect($suprimentos->can('delete', $event))->toBeFalse();
});
