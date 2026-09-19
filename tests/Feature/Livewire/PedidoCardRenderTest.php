<?php

use App\Enums\StatusSlug;
use App\Livewire\Kanban\KanbanBoard;
use App\Models\Obra;
use App\Models\Pedido;
use App\Models\Priority;
use App\Models\Status;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    foreach (StatusSlug::cases() as $slug) {
        Status::factory()->create([
            'slug' => $slug->value,
            'sort_order' => array_search($slug, StatusSlug::cases(), true) + 1,
        ]);
    }
});

test('the card renders all 7 required fields', function () {
    $actor = User::factory()->suprimentos()->create();
    $this->actingAs($actor);

    $obra = Obra::factory()->create(['name' => 'Residencial Aurora']);
    $priority = Priority::factory()->urgente()->create();
    $responsible = User::factory()->suprimentos()->create(['name' => 'Fulano Responsavel']);

    $pedido = Pedido::factory()->create([
        'obra_id' => $obra->id,
        'status_id' => Status::query()->where('slug', StatusSlug::Solicitado->value)->value('id'),
        'priority_id' => $priority->id,
        'responsible_id' => $responsible->id,
        'expected_delivery_at' => '2026-08-01',
        'needed_at' => '2026-07-15',
    ]);

    Livewire::test(KanbanBoard::class)
        ->assertSee($pedido->code)
        ->assertSee('Residencial Aurora')
        ->assertSee('15/07/2026')
        ->assertSee($priority->name)
        ->assertSee('Fulano Responsavel')
        ->assertSee('01/08/2026');
});

test('an atrasado pedido card carries the distinct CSS class', function () {
    $actor = User::factory()->suprimentos()->create();
    $this->actingAs($actor);

    $atrasado = Pedido::factory()->create([
        'status_id' => Status::query()->where('slug', StatusSlug::Solicitado->value)->value('id'),
        'needed_at' => now()->subDays(3),
    ]);

    $noPrazo = Pedido::factory()->create([
        'status_id' => Status::query()->where('slug', StatusSlug::Solicitado->value)->value('id'),
        'needed_at' => now()->addDays(3),
    ]);

    $html = Livewire::test(KanbanBoard::class)->html();

    $atrasadoCardStart = strpos($html, 'pedido-'.$atrasado->id.'"');
    $noPrazoCardStart = strpos($html, 'pedido-'.$noPrazo->id.'"');

    expect(substr($html, $atrasadoCardStart, 400))->toContain('pedido-atrasado');
    expect(substr($html, $noPrazoCardStart, 400))->not->toContain('pedido-atrasado');
});
