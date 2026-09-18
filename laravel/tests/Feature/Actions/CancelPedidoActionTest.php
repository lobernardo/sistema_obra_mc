<?php

use App\Actions\Pedidos\CancelPedidoAction;
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
    $this->cancelado = Status::factory()->cancelado()->create();
    EventType::factory()->cancelamento()->create();
    $this->action = new CancelPedidoAction;
});

test('cancelling an active pedido succeeds, sets cancelado and writes 1 cancelamento event', function () {
    $actor = User::factory()->suprimentos()->create();
    $pedido = Pedido::factory()->create(['status_id' => $this->solicitado->id]);

    $updated = $this->action->execute($actor, $pedido);

    expect($updated->status_id)->toBe($this->cancelado->id);

    $event = $pedido->fresh()->events()->first();
    expect($pedido->fresh()->events()->count())->toBe(1);
    expect($event->eventType->slug)->toBe(EventTypeSlug::Cancelamento->value);
    expect($event->actor_id)->toBe($actor->id);
});

test('cancelling an already entregue pedido is rejected', function () {
    $actor = User::factory()->suprimentos()->create();
    $pedido = Pedido::factory()->create(['status_id' => $this->entregue->id]);

    expect(fn () => $this->action->execute($actor, $pedido))
        ->toThrow(PedidoTerminalStateException::class);

    expect($pedido->fresh()->status_id)->toBe($this->entregue->id);
});

test('cancelling an already cancelado pedido is rejected (irreversible)', function () {
    $actor = User::factory()->suprimentos()->create();
    $pedido = Pedido::factory()->create(['status_id' => $this->cancelado->id]);

    expect(fn () => $this->action->execute($actor, $pedido))
        ->toThrow(PedidoTerminalStateException::class);

    expect($pedido->fresh()->status_id)->toBe($this->cancelado->id);
});

test('an obra actor is rejected', function () {
    $actor = User::factory()->obra()->create();
    $pedido = Pedido::factory()->create(['status_id' => $this->solicitado->id]);

    expect(fn () => $this->action->execute($actor, $pedido))
        ->toThrow(AuthorizationException::class);

    expect($pedido->fresh()->status_id)->toBe($this->solicitado->id);
});

test('a gestao actor is rejected', function () {
    $actor = User::factory()->gestao()->create();
    $pedido = Pedido::factory()->create(['status_id' => $this->solicitado->id]);

    expect(fn () => $this->action->execute($actor, $pedido))
        ->toThrow(AuthorizationException::class);

    expect($pedido->fresh()->status_id)->toBe($this->solicitado->id);
});
