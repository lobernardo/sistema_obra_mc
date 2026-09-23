<?php

use App\Livewire\Kanban\KanbanBoard;
use App\Livewire\Suprimentos\PedidoDetalhe as SuprimentosPedidoDetalhe;
use App\Models\EventType;
use App\Models\Pedido;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->statuses = seedWorkflowStatuses();

    EventType::factory()->mudancaStatus()->create();
    EventType::factory()->entrega()->create();
});

test('a pedido can be moved across the full workflow using only the accessible control', function () {
    $actor = User::factory()->suprimentos()->create();
    $this->actingAs($actor);

    $pedido = Pedido::factory()->create(['status_id' => $this->statuses['solicitado']->id]);

    $workflow = [
        'em_analise',
        'em_compra_preparacao',
        'aguardando_entrega',
        'entregue',
    ];

    foreach ($workflow as $targetSlug) {
        Livewire::test(KanbanBoard::class)
            ->call('moveViaControl', $pedido->id, $this->statuses[$targetSlug]->id);

        expect($pedido->fresh()->status->slug)->toBe($targetSlug);
    }

    expect($pedido->fresh()->events()->count())->toBe(4);
});

test('the Suprimentos detail status select offers neither Finalizado nor Cancelado (UI-07)', function () {
    $actor = User::factory()->suprimentos()->create();
    $this->actingAs($actor);

    $pedido = Pedido::factory()->create(['status_id' => $this->statuses['solicitado']->id]);

    $html = Livewire::test(SuprimentosPedidoDetalhe::class, ['pedido' => $pedido])->html();

    preg_match('/<select id="status_id".*?<\/select>/s', $html, $select);
    preg_match_all('/<option value="(\d+)"/', $select[0] ?? '', $options);

    expect(array_map('intval', $options[1]))->toBe([
        $this->statuses['solicitado']->id,
        $this->statuses['em_analise']->id,
        $this->statuses['em_compra_preparacao']->id,
        $this->statuses['aguardando_entrega']->id,
        $this->statuses['entregue']->id,
    ]);
    expect($select[0])->not->toContain('>'.$this->statuses['finalizado']->name.'<')
        ->not->toContain('>'.$this->statuses['cancelado']->name.'<');
});
