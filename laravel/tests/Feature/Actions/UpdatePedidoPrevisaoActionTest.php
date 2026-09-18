<?php

use App\Actions\Pedidos\UpdatePedidoPrevisaoAction;
use App\Enums\EventTypeSlug;
use App\Exceptions\Pedidos\PedidoTerminalStateException;
use App\Models\EventType;
use App\Models\Pedido;
use App\Models\Status;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(function () {
    $this->solicitado = Status::factory()->solicitado()->create();
    $this->entregue = Status::factory()->entregue()->create();
    EventType::factory()->alteracaoPrevisao()->create();
    $this->action = new UpdatePedidoPrevisaoAction;
});

test('a valid change generates an event with previous and new values', function () {
    $actor = User::factory()->suprimentos()->create();
    $pedido = Pedido::factory()->create(['status_id' => $this->solicitado->id, 'expected_delivery_at' => '2026-07-01']);

    $updated = $this->action->execute($actor, $pedido, '2026-07-10');

    expect($updated->expected_delivery_at->toDateString())->toBe('2026-07-10');

    $event = $pedido->fresh()->events()->first();
    expect($pedido->fresh()->events()->count())->toBe(1);
    expect($event->eventType->slug)->toBe(EventTypeSlug::AlteracaoPrevisao->value);
    expect($event->previous_value)->toBe('2026-07-01');
    expect($event->new_value)->toBe('2026-07-10');
});

test('setting the same date is a no-op and generates no event', function () {
    $actor = User::factory()->suprimentos()->create();
    $pedido = Pedido::factory()->create(['status_id' => $this->solicitado->id, 'expected_delivery_at' => '2026-07-01']);

    $this->action->execute($actor, $pedido, '2026-07-01');

    expect($pedido->fresh()->events()->count())->toBe(0);
});

test('a non-suprimentos actor is rejected', function () {
    $actor = User::factory()->obra()->create();
    $pedido = Pedido::factory()->create(['status_id' => $this->solicitado->id]);

    expect(fn () => $this->action->execute($actor, $pedido, '2026-07-10'))
        ->toThrow(AuthorizationException::class);

    expect($pedido->fresh()->events()->count())->toBe(0);
});

test('a terminal pedido rejects the mutation', function () {
    $actor = User::factory()->suprimentos()->create();
    $pedido = Pedido::factory()->create(['status_id' => $this->entregue->id]);

    expect(fn () => $this->action->execute($actor, $pedido, '2026-07-10'))
        ->toThrow(PedidoTerminalStateException::class);

    expect($pedido->fresh()->events()->count())->toBe(0);
});
