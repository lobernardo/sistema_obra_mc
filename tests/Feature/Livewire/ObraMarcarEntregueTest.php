<?php

use App\Enums\EventTypeSlug;
use App\Exceptions\Pedidos\PedidoTerminalStateException;
use App\Livewire\Obra\PedidoDetalhe;
use App\Models\Obra;
use App\Models\Pedido;
use App\Models\PedidoEvent;
use App\Models\User;
use Livewire\Livewire;

/**
 * "Marcar como entregue" in the Obra detail (UI-06, RF-27..RF-29): a
 * two-step confirmation offered only while the status is active, never in
 * any listing or card.
 */
beforeEach(function () {
    $this->statuses = seedWorkflowStatuses();
    seedHistoryEventTypes();

    $this->obraUser = User::factory()->obra()->create();
    $this->obra = Obra::factory()->create();
    $this->obraUser->obras()->attach($this->obra->id);
});

function obraEntregaPedido(string $status): Pedido
{
    return Pedido::factory()->for(test()->obraUser, 'requester')->create([
        'obra_id' => test()->obra->id,
        'status_id' => test()->statuses[$status]->id,
    ]);
}

test('the button is present on an active pedido (UI-06)', function (string $status) {
    $this->actingAs($this->obraUser);

    Livewire::test(PedidoDetalhe::class, ['pedido' => obraEntregaPedido($status)])
        ->assertSee('Marcar como entregue')
        ->assertSeeHtml('wire:click="confirmarEntrega"');
})->with(['solicitado', 'em_analise', 'em_compra_preparacao', 'aguardando_entrega']);

test('the button is absent on a terminal pedido (UI-06)', function (string $status) {
    $this->actingAs($this->obraUser);

    Livewire::test(PedidoDetalhe::class, ['pedido' => obraEntregaPedido($status)])
        ->assertDontSee('Marcar como entregue')
        ->assertDontSeeHtml('confirmarEntrega')
        ->assertDontSeeHtml('marcarComoEntregue');
})->with(['entregue', 'cancelado', 'finalizado']);

test('the first click only opens the confirmation; confirming updates badge and history (UI-06)', function () {
    $pedido = obraEntregaPedido('aguardando_entrega');
    $this->actingAs($this->obraUser);

    $component = Livewire::test(PedidoDetalhe::class, ['pedido' => $pedido])
        ->call('confirmarEntrega')
        ->assertSet('confirmingEntrega', true)
        ->assertSeeHtml('role="alertdialog"')
        ->assertSee('Confirmar entrega');

    expect($pedido->fresh()->status_id)->toBe($this->statuses['aguardando_entrega']->id);

    $component->call('marcarComoEntregue')
        ->assertHasNoErrors()
        ->assertSet('confirmingEntrega', false)
        ->assertSee('Pedido marcado como entregue.')
        ->assertDontSee('Marcar como entregue')
        ->assertSeeHtml('data-status="entregue"');

    expect($pedido->fresh()->status_id)->toBe($this->statuses['entregue']->id)
        ->and(PedidoEvent::query()->where('pedido_id', $pedido->id)
            ->whereHas('eventType', fn ($query) => $query->where('slug', EventTypeSlug::Entrega->value))
            ->count())->toBe(1);
});

test('abortarEntrega closes the confirmation without changing the status', function () {
    $pedido = obraEntregaPedido('em_analise');
    $this->actingAs($this->obraUser);

    Livewire::test(PedidoDetalhe::class, ['pedido' => $pedido])
        ->call('confirmarEntrega')
        ->call('abortarEntrega')
        ->assertSet('confirmingEntrega', false)
        ->assertSee('Marcar como entregue');

    expect($pedido->fresh()->status_id)->toBe($this->statuses['em_analise']->id)
        ->and(PedidoEvent::query()->where('pedido_id', $pedido->id)->count())->toBe(0);
});

test('a forged marcarComoEntregue on a terminal pedido answers 409 and writes nothing (RF-28)', function () {
    $pedido = obraEntregaPedido('cancelado');
    $this->actingAs($this->obraUser);

    expect(fn () => Livewire::test(PedidoDetalhe::class, ['pedido' => $pedido])->call('marcarComoEntregue'))
        ->toThrow(PedidoTerminalStateException::class);

    expect($pedido->fresh()->status_id)->toBe($this->statuses['cancelado']->id)
        ->and(PedidoEvent::query()->where('pedido_id', $pedido->id)->count())->toBe(0);
});

test('a forged marcarComoEntregue by gestao or suprimentos on an open Obra detail → 403, 0 events (RF-28)', function (string $role) {
    $pedido = obraEntregaPedido('aguardando_entrega');
    $this->actingAs($this->obraUser);
    $testable = Livewire::test(PedidoDetalhe::class, ['pedido' => $pedido]);

    $this->actingAs(User::factory()->{$role}()->create());

    $testable->call('marcarComoEntregue')->assertForbidden();

    expect($pedido->fresh()->status_id)->toBe($this->statuses['aguardando_entrega']->id)
        ->and(PedidoEvent::query()->where('pedido_id', $pedido->id)->count())->toBe(0);
})->with(['gestao', 'suprimentos']);

test('/obra/pedidos has no "Marcar como entregue" action (UI-06)', function () {
    obraEntregaPedido('aguardando_entrega');

    $this->actingAs($this->obraUser)
        ->get(route('obra.pedidos.index'))
        ->assertOk()
        ->assertDontSee('Marcar como entregue')
        ->assertDontSee('confirmarEntrega', false)
        ->assertDontSee('marcarComoEntregue', false);
});
