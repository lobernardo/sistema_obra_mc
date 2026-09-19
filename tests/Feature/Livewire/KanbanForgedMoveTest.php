<?php

use App\Enums\StatusSlug;
use App\Exceptions\Pedidos\PedidoTerminalStateException;
use App\Livewire\Kanban\KanbanBoard;
use App\Models\Pedido;
use App\Models\Status;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->statuses = [];

    foreach (StatusSlug::cases() as $slug) {
        $this->statuses[$slug->value] = Status::factory()->create([
            'slug' => $slug->value,
            'sort_order' => array_search($slug, StatusSlug::cases(), true) + 1,
        ]);
    }
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
