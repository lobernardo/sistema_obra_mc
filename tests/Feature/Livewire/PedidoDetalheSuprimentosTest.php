<?php

use App\Livewire\Suprimentos\PedidoDetalhe;
use App\Models\EventType;
use App\Models\Pedido;
use App\Models\Priority;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->statuses = seedWorkflowStatuses();

    $this->priority = Priority::factory()->urgente()->create();

    EventType::factory()->alteracaoResponsavel()->create();
    EventType::factory()->alteracaoPrioridade()->create();
    EventType::factory()->alteracaoPrevisao()->create();
    EventType::factory()->mudancaStatus()->create();
    EventType::factory()->entrega()->create();
});

test('non-suprimentos actors are denied access to the component', function (string $role) {
    $actor = User::factory()->{$role}()->create();
    $pedido = Pedido::factory()->create(['status_id' => $this->statuses['solicitado']->id]);

    $this->actingAs($actor);

    Livewire::test(PedidoDetalhe::class, ['pedido' => $pedido])->assertSee('403');
})->with(['obra', 'gestao']);

test('the responsavel control persists a change and writes an event', function () {
    $actor = User::factory()->suprimentos()->create();
    $responsible = User::factory()->suprimentos()->create();
    $pedido = Pedido::factory()->create(['status_id' => $this->statuses['solicitado']->id]);
    $this->actingAs($actor);

    Livewire::test(PedidoDetalhe::class, ['pedido' => $pedido])
        ->set('responsible_id', $responsible->id)
        ->call('updateResponsavel');

    expect($pedido->fresh()->responsible_id)->toBe($responsible->id);
});

test('the prioridade control persists a change and writes an event', function () {
    $actor = User::factory()->suprimentos()->create();
    $pedido = Pedido::factory()->create(['status_id' => $this->statuses['solicitado']->id]);
    $this->actingAs($actor);

    Livewire::test(PedidoDetalhe::class, ['pedido' => $pedido])
        ->set('priority_id', $this->priority->id)
        ->call('updatePrioridade');

    expect($pedido->fresh()->priority_id)->toBe($this->priority->id);
});

test('the previsao control persists a change and writes an event', function () {
    $actor = User::factory()->suprimentos()->create();
    $pedido = Pedido::factory()->create(['status_id' => $this->statuses['solicitado']->id]);
    $this->actingAs($actor);

    Livewire::test(PedidoDetalhe::class, ['pedido' => $pedido])
        ->set('expected_delivery_at', '2026-08-01')
        ->call('updatePrevisao');

    expect($pedido->fresh()->expected_delivery_at->toDateString())->toBe('2026-08-01');
});

test('the status control persists a change and writes an event', function () {
    $actor = User::factory()->suprimentos()->create();
    $pedido = Pedido::factory()->create(['status_id' => $this->statuses['solicitado']->id]);
    $this->actingAs($actor);

    Livewire::test(PedidoDetalhe::class, ['pedido' => $pedido])
        ->set('status_id', $this->statuses['em_analise']->id)
        ->call('updateStatus');

    expect($pedido->fresh()->status->slug)->toBe('em_analise');
});

test('all 5 operational controls are absent for a terminal pedido', function () {
    $actor = User::factory()->suprimentos()->create();
    $pedido = Pedido::factory()->create(['status_id' => $this->statuses['entregue']->id]);
    $this->actingAs($actor);

    Livewire::test(PedidoDetalhe::class, ['pedido' => $pedido])
        ->assertDontSee('Salvar responsável')
        ->assertDontSee('Salvar prioridade')
        ->assertDontSee('Salvar previsão')
        ->assertDontSee('Mover status')
        ->assertDontSee('Cancelar pedido');
});
