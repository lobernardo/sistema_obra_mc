<?php

use App\Enums\StatusSlug;
use App\Livewire\Kanban\KanbanBoard;
use App\Models\EventType;
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
