<?php

use App\Enums\StatusSlug;
use App\Livewire\Kanban\KanbanBoard;
use App\Models\Pedido;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->statuses = seedWorkflowStatuses(fn (StatusSlug $slug): string => ucfirst($slug->value));
});

test('non-suprimentos actors are denied access to the component', function (string $role) {
    $actor = User::factory()->{$role}()->create();

    $this->actingAs($actor);

    Livewire::test(KanbanBoard::class)->assertSee('403');
})->with(['obra', 'gestao']);

test('the 6 columns (4 active, entregue, finalizado) render in sort_order and cancelado is excluded', function () {
    $actor = User::factory()->suprimentos()->create();
    $this->actingAs($actor);

    $expectedOrder = [
        $this->statuses['solicitado']->name,
        $this->statuses['em_analise']->name,
        $this->statuses['em_compra_preparacao']->name,
        $this->statuses['aguardando_entrega']->name,
        $this->statuses['entregue']->name,
        $this->statuses['finalizado']->name,
    ];

    $html = Livewire::test(KanbanBoard::class)->assertDontSee($this->statuses['cancelado']->name)->html();

    $positions = array_map(fn (string $name) => strpos($html, $name), $expectedOrder);

    expect($positions)->not->toContain(false);
    expect($positions)->toBe(collect($positions)->sort()->values()->all());

    preg_match_all('/data-column="([a-z_]+)"/', $html, $columns);

    expect($columns[1])->toBe([
        'solicitado',
        'em_analise',
        'em_compra_preparacao',
        'aguardando_entrega',
        'entregue',
        'finalizado',
    ]);
    expect($html)->toContain('xl:grid-cols-6');
});

test('a finalizado pedido is shown in the finalizado column without a "Mover para" control', function () {
    $actor = User::factory()->suprimentos()->create();
    $this->actingAs($actor);

    $finalizado = Pedido::factory()->create(['status_id' => $this->statuses['finalizado']->id]);

    $html = Livewire::test(KanbanBoard::class)->html();

    preg_match('/<section[^>]*data-column="finalizado".*?<\/section>/s', $html, $column);

    expect($column[0] ?? '')->toContain($finalizado->code)
        ->not->toContain('Mover para');
});

test('the "Mover para" options of an active card never include finalizado nor cancelado', function () {
    $actor = User::factory()->suprimentos()->create();
    $this->actingAs($actor);

    Pedido::factory()->create(['status_id' => $this->statuses['solicitado']->id]);

    $html = Livewire::test(KanbanBoard::class)->html();

    preg_match('/<select[^>]*aria-label="Mover pedido.*?<\/select>/s', $html, $select);
    preg_match_all('/<option value="(\d+)"/', $select[0] ?? '', $options);

    $offered = array_map('intval', $options[1]);

    expect($offered)->toBe([
        $this->statuses['solicitado']->id,
        $this->statuses['em_analise']->id,
        $this->statuses['em_compra_preparacao']->id,
        $this->statuses['aguardando_entrega']->id,
        $this->statuses['entregue']->id,
    ]);
    expect($offered)->not->toContain($this->statuses['finalizado']->id)
        ->not->toContain($this->statuses['cancelado']->id);
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

test('dropping a card onto the finalizado column is rejected as an invalid transition', function () {
    $actor = User::factory()->suprimentos()->create();
    $this->actingAs($actor);

    $pedido = Pedido::factory()->create(['status_id' => $this->statuses['aguardando_entrega']->id]);

    Livewire::test(KanbanBoard::class)
        ->call('moveCard', $pedido->id, 0, $this->statuses['finalizado']->id)
        ->assertHasErrors(['status_id'])
        ->assertSee('Transição de status inválida.');

    expect($pedido->fresh()->status_id)->toBe($this->statuses['aguardando_entrega']->id);
    expect($pedido->fresh()->events()->count())->toBe(0);
});
