<?php

use App\Enums\StatusSlug;
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
            'name' => ucfirst($slug->value),
            'sort_order' => array_search($slug, StatusSlug::cases(), true) + 1,
        ]);
    }
});

test('non-suprimentos actors are denied access to the component', function (string $role) {
    $actor = User::factory()->{$role}()->create();

    $this->actingAs($actor);

    Livewire::test(KanbanBoard::class)->assertSee('403');
})->with(['obra', 'gestao']);

test('the 5 active columns render in sort_order and cancelado is excluded', function () {
    $actor = User::factory()->suprimentos()->create();
    $this->actingAs($actor);

    $expectedOrder = [
        $this->statuses['solicitado']->name,
        $this->statuses['em_analise']->name,
        $this->statuses['em_compra_preparacao']->name,
        $this->statuses['aguardando_entrega']->name,
        $this->statuses['entregue']->name,
    ];

    $html = Livewire::test(KanbanBoard::class)->assertDontSee($this->statuses['cancelado']->name)->html();

    $positions = array_map(fn (string $name) => strpos($html, $name), $expectedOrder);

    expect($positions)->not->toContain(false);
    expect($positions)->toBe(collect($positions)->sort()->values()->all());
});

test('a pedido in cancelado never appears in any column', function () {
    $actor = User::factory()->suprimentos()->create();
    $this->actingAs($actor);

    $cancelado = Pedido::factory()->create(['status_id' => $this->statuses['cancelado']->id]);
    $solicitado = Pedido::factory()->create(['status_id' => $this->statuses['solicitado']->id]);

    Livewire::test(KanbanBoard::class)
        ->assertSee($solicitado->code)
        ->assertDontSee($cancelado->code);
});

test('dropping a card back into its own column is a no-op that writes no history', function () {
    $actor = User::factory()->suprimentos()->create();
    $this->actingAs($actor);

    $pedido = Pedido::factory()->create(['status_id' => $this->statuses['solicitado']->id]);

    Livewire::test(KanbanBoard::class)
        ->call('moveCard', $pedido->id, 0, $this->statuses['solicitado']->id)
        ->assertHasNoErrors();

    expect($pedido->fresh()->status_id)->toBe($this->statuses['solicitado']->id);
    expect($pedido->fresh()->events()->count())->toBe(0);
});
