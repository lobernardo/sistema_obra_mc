<?php

use App\Enums\StatusSlug;
use App\Livewire\Suprimentos\VisaoGeral;
use App\Models\EventType;
use App\Models\Obra;
use App\Models\Pedido;
use App\Models\PedidoEvent;
use App\Models\Priority;
use App\Models\Status;
use App\Models\User;
use App\Services\DashboardIndicatorsService;
use Livewire\Livewire;

/**
 * RF-27 / RF-28 / RF-29 / UI-05 (T26, T27): the Suprimentos "Visão Geral" —
 * three KPI cards, the count of each non-cancelled workflow status, the Kanban
 * shortcut and the 5 most recent pedidos, all fed by
 * {@see DashboardIndicatorsService} without re-encoding a single rule.
 */
beforeEach(function () {
    $this->statuses = [];

    foreach (StatusSlug::cases() as $slug) {
        $this->statuses[$slug->value] = Status::factory()->create([
            'slug' => $slug->value,
            'name' => ucfirst(str_replace('_', ' ', $slug->value)),
            'sort_order' => array_search($slug, StatusSlug::cases(), true) + 1,
        ]);
    }

    $this->actor = User::factory()->suprimentos()->create();
    $this->actingAs($this->actor);
});

test('non-suprimentos actors are denied the Visão Geral component', function (string $role) {
    $this->actingAs(User::factory()->{$role}()->create());

    Livewire::test(VisaoGeral::class)->assertSee('403');
})->with(['obra', 'gestao']);

test('the three KPI cards carry the service numbers for total, atrasados and entregues hoje', function () {
    $entregue = Pedido::factory()->create([
        'status_id' => $this->statuses['entregue']->id,
        'needed_at' => now()->subDays(5),
        'expected_delivery_at' => null,
    ]);
    PedidoEvent::factory()->create([
        'pedido_id' => $entregue->id,
        'event_type_id' => EventType::factory()->entrega(),
        'actor_id' => $this->actor->id,
    ]);

    Pedido::factory()->count(2)->create([
        'status_id' => $this->statuses['solicitado']->id,
        'needed_at' => now()->subDays(3),
    ]);
    Pedido::factory()->create([
        'status_id' => $this->statuses['em_analise']->id,
        'needed_at' => now()->addDays(10),
    ]);

    $indicators = app(DashboardIndicatorsService::class)->compute([]);
    $html = Livewire::test(VisaoGeral::class)
        ->assertSeeText('Total de pedidos')
        ->assertSeeText('Atrasados')
        ->assertSeeText('Entregues hoje')
        ->html();

    expect($indicators['volumeTotal'])->toBe(4)
        ->and($indicators['atrasados'])->toBe(2)
        ->and($indicators['entreguesHoje'])->toBe(1);

    foreach ([
        'indicator-volume-total' => $indicators['volumeTotal'],
        'indicator-atrasados' => $indicators['atrasados'],
        'indicator-entregues-hoje' => $indicators['entreguesHoje'],
    ] as $testid => $value) {
        preg_match('/data-testid="'.$testid.'".*?<\/div>/s', $html, $card);

        expect($card[0] ?? '')->toContain('data-value')->toContain('>'.$value.'<');
    }
});

test('the per-status counts equal the seeded dataset and match porStatus, with cancelado excluded', function () {
    Pedido::factory()->count(3)->create(['status_id' => $this->statuses['solicitado']->id]);
    Pedido::factory()->count(2)->create(['status_id' => $this->statuses['em_analise']->id]);
    Pedido::factory()->create(['status_id' => $this->statuses['aguardando_entrega']->id]);
    Pedido::factory()->count(4)->create(['status_id' => $this->statuses['cancelado']->id]);

    $porStatus = app(DashboardIndicatorsService::class)->compute([])['porStatus'];
    $html = Livewire::test(VisaoGeral::class)->html();

    $expected = [
        'solicitado' => 3,
        'em_analise' => 2,
        'em_compra_preparacao' => 0,
        'aguardando_entrega' => 1,
        'entregue' => 0,
    ];

    expect(substr_count($html, 'data-status-summary="'))->toBe(count($expected));
    expect($html)->not->toContain('data-status-summary="cancelado"');

    foreach ($expected as $slug => $count) {
        preg_match('/data-status-summary="'.$slug.'".*?<\/li>/s', $html, $row);

        expect(str_contains($row[0] ?? '', '>'.$count.'<'))->toBeTrue("status [{$slug}] does not render {$count}");
        expect($porStatus->firstWhere(fn (array $item) => $item['status']->slug === $slug)['count'])->toBe($count);
    }
});

test('the table shows exactly the 5 most recent pedidos ordered by requested_at descending', function () {
    $obra = Obra::factory()->create();
    $priority = Priority::factory()->normal()->create();

    $pedidos = collect(range(0, 7))->map(fn (int $offset) => Pedido::factory()->create([
        'obra_id' => $obra->id,
        'priority_id' => $priority->id,
        'responsible_id' => $this->actor->id,
        'status_id' => $this->statuses['solicitado']->id,
        'requested_at' => now()->subDays($offset),
    ]));

    $html = Livewire::test(VisaoGeral::class)->html();

    expect(substr_count($html, 'data-pedido-code="'))->toBe(2 * 5, 'the table and the card list each render 5 rows');

    $maisRecentes = $pedidos->take(5);

    foreach ($maisRecentes as $pedido) {
        expect($html)->toContain('data-pedido-code="'.$pedido->code.'"');
    }

    foreach ($pedidos->slice(5) as $pedido) {
        expect($html)->not->toContain('data-pedido-code="'.$pedido->code.'"');
    }

    $positions = $maisRecentes->map(fn (Pedido $pedido) => strpos($html, 'data-pedido-code="'.$pedido->code.'"'));

    expect($positions->toArray())->toBe($positions->sort()->values()->toArray(), 'rows are not in requested_at descending order');
});

test('the screen links to the Kanban and to the full listing', function () {
    $html = Livewire::test(VisaoGeral::class)->html();

    expect($html)->toContain('href="'.route('suprimentos.kanban').'"')
        ->toContain('href="'.route('suprimentos.pedidos.index').'"')
        ->toContain('data-testid="atalho-kanban"')
        ->toContain('data-testid="ver-todos"');
});

test('the screen reuses only the existing visual components', function () {
    $blade = file_get_contents(resource_path('views/livewire/suprimentos/visao-geral.blade.php'));

    expect($blade)->toContain('page-title')
        ->toContain('section-title')
        ->toContain('class="card')
        ->toContain('<x-pedido-table')
        ->toContain('<x-status-badge');

    preg_match_all('/<x-([a-z0-9-]+)/', $blade, $components);

    expect(array_unique($components[1]))->each->toBeIn(['pedido-table', 'status-badge', 'priority-badge', 'atraso-indicator']);
});

test('the component consumes the service unfiltered and re-encodes no classifier rule', function () {
    $source = file_get_contents(app_path('Livewire/Suprimentos/VisaoGeral.php'));
    $blade = file_get_contents(resource_path('views/livewire/suprimentos/visao-geral.blade.php'));

    expect($source)->toContain('compute([])');

    foreach ([$source, $blade] as $contents) {
        expect($contents)->not->toContain('needed_at')
            ->not->toContain('AtrasoClassifier')
            ->not->toContain('PendenteClassifier')
            ->not->toContain('PrazoClassifier');
    }
});
