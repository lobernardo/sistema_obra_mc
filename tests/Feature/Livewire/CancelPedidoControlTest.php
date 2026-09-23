<?php

use App\Enums\EventTypeSlug;
use App\Livewire\Suprimentos\PedidoDetalhe;
use App\Models\EventType;
use App\Models\Pedido;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->statuses = seedWorkflowStatuses();

    EventType::factory()->cancelamento()->create();
});

test('cancellation requires confirmation before it is applied', function () {
    $actor = User::factory()->suprimentos()->create();
    $pedido = Pedido::factory()->create(['status_id' => $this->statuses['solicitado']->id]);
    $this->actingAs($actor);

    Livewire::test(PedidoDetalhe::class, ['pedido' => $pedido])
        ->assertDontSee('Confirmar cancelamento')
        ->call('confirmCancel')
        ->assertSee('Confirmar cancelamento');

    expect($pedido->fresh()->status->slug)->toBe('solicitado');
});

test('confirming cancellation persists cancelado and writes a cancelamento event', function () {
    $actor = User::factory()->suprimentos()->create();
    $pedido = Pedido::factory()->create(['status_id' => $this->statuses['solicitado']->id]);
    $this->actingAs($actor);

    Livewire::test(PedidoDetalhe::class, ['pedido' => $pedido])
        ->call('confirmCancel')
        ->call('cancelarPedido');

    $fresh = $pedido->fresh();
    expect($fresh->status->slug)->toBe('cancelado');

    $event = $fresh->events()->first();
    expect($fresh->events()->count())->toBe(1);
    expect($event->eventType->slug)->toBe(EventTypeSlug::Cancelamento->value);
});

test('aborting the confirmation dialog leaves the pedido untouched', function () {
    $actor = User::factory()->suprimentos()->create();
    $pedido = Pedido::factory()->create(['status_id' => $this->statuses['solicitado']->id]);
    $this->actingAs($actor);

    Livewire::test(PedidoDetalhe::class, ['pedido' => $pedido])
        ->call('confirmCancel')
        ->call('abortCancel')
        ->assertDontSee('Confirmar cancelamento');

    expect($pedido->fresh()->status->slug)->toBe('solicitado');
});
