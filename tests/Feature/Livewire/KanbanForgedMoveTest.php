<?php

use App\Exceptions\Pedidos\PedidoTerminalStateException;
use App\Livewire\Kanban\KanbanBoard;
use App\Models\Pedido;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->statuses = seedWorkflowStatuses();
});

test('a forged move to cancelado (a status never offered as a column) is rejected and the pedido stays put', function () {
    $actor = User::factory()->suprimentos()->create();
    $this->actingAs($actor);

    $pedido = Pedido::factory()->create(['status_id' => $this->statuses['solicitado']->id]);

    Livewire::test(KanbanBoard::class)
        ->call('moveCard', $pedido->id, 0, $this->statuses['cancelado']->id);

    expect($pedido->fresh()->status_id)->toBe($this->statuses['solicitado']->id);

    Livewire::test(KanbanBoard::class)
        ->assertSee($pedido->code)
        ->assertDontSee($this->statuses['cancelado']->name);
});

test('a forged move against a terminal pedido is rejected and the pedido stays put', function () {
    $actor = User::factory()->suprimentos()->create();
    $this->actingAs($actor);

    $pedido = Pedido::factory()->create(['status_id' => $this->statuses['entregue']->id]);

    expect(fn () => Livewire::test(KanbanBoard::class)
        ->call('moveCard', $pedido->id, 0, $this->statuses['solicitado']->id))
        ->toThrow(PedidoTerminalStateException::class);

    expect($pedido->fresh()->status_id)->toBe($this->statuses['entregue']->id);
});

test('a non-suprimentos actor cannot reach the component to forge a move at all', function (string $role) {
    $actor = User::factory()->{$role}()->create();
    $this->actingAs($actor);

    $pedido = Pedido::factory()->create(['status_id' => $this->statuses['solicitado']->id]);

    Livewire::test(KanbanBoard::class)->assertSee('403');

    expect($pedido->fresh()->status_id)->toBe($this->statuses['solicitado']->id);
})->with(['obra', 'gestao']);

test('a forged move to finalizado is rejected on status_id and writes no history', function (string $method) {
    $actor = User::factory()->suprimentos()->create();
    $this->actingAs($actor);

    $pedido = Pedido::factory()->create(['status_id' => $this->statuses['em_analise']->id]);

    $arguments = $method === 'moveCard'
        ? [$pedido->id, 0, $this->statuses['finalizado']->id]
        : [$pedido->id, $this->statuses['finalizado']->id];

    Livewire::test(KanbanBoard::class)
        ->call($method, ...$arguments)
        ->assertHasErrors(['status_id']);

    expect($pedido->fresh()->status_id)->toBe($this->statuses['em_analise']->id);
    expect($pedido->fresh()->events()->count())->toBe(0);
})->with(['moveCard', 'moveViaControl']);
