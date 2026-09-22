<?php

use App\Livewire\Gestao\Dashboard;
use App\Livewire\Gestao\TodosPedidos as GestaoTodosPedidos;
use App\Models\Obra;
use App\Models\Pedido;
use App\Models\Status;
use App\Models\User;
use App\Services\DashboardIndicatorsService;
use Livewire\Livewire;

beforeEach(function () {
    $this->solicitado = Status::factory()->solicitado()->create();
    $this->entregue = Status::factory()->entregue()->create();

    $this->actor = User::factory()->gestao()->create();
    $this->actingAs($this->actor);
});

test('drilling down from atrasados navigates to a listing matching the indicator count exactly', function () {
    $obra = Obra::factory()->create();

    $atrasado1 = Pedido::factory()->create(['obra_id' => $obra->id, 'status_id' => $this->solicitado->id, 'needed_at' => now()->subDays(2)]);
    $atrasado2 = Pedido::factory()->create(['obra_id' => $obra->id, 'status_id' => $this->solicitado->id, 'needed_at' => now()->subDays(5)]);
    $noPrazo = Pedido::factory()->create(['obra_id' => $obra->id, 'status_id' => $this->solicitado->id, 'needed_at' => now()->addDays(2)]);
    $entregueVencido = Pedido::factory()->create(['obra_id' => $obra->id, 'status_id' => $this->entregue->id, 'needed_at' => now()->subDays(5)]);

    $component = Livewire::test(Dashboard::class);
    $indicators = $component->instance()->indicators(app(DashboardIndicatorsService::class));
    $drillDownUrl = $component->instance()->drillDownUrl('atrasado');

    expect($indicators['atrasados'])->toBe(2);

    $this->get($drillDownUrl)
        ->assertOk()
        ->assertSee($atrasado1->code)
        ->assertSee($atrasado2->code)
        ->assertDontSee($noPrazo->code)
        ->assertDontSee($entregueVencido->code);
});

test('drilling down from pendentes navigates to a listing matching the indicator count exactly', function () {
    $pendente1 = Pedido::factory()->create(['status_id' => $this->solicitado->id]);
    $pendente2 = Pedido::factory()->create(['status_id' => $this->solicitado->id]);
    $entregue = Pedido::factory()->create(['status_id' => $this->entregue->id]);

    $component = Livewire::test(Dashboard::class);
    $indicators = $component->instance()->indicators(app(DashboardIndicatorsService::class));
    $drillDownUrl = $component->instance()->drillDownUrl('pendente');

    expect($indicators['pendentes'])->toBe(2);

    $this->get($drillDownUrl)
        ->assertOk()
        ->assertSee($pendente1->code)
        ->assertSee($pendente2->code)
        ->assertDontSee($entregue->code);
});

test('the periodo filter is preserved across the drill-down link', function () {
    $inRange = Pedido::factory()->create(['status_id' => $this->solicitado->id, 'needed_at' => now()->subDays(2), 'requested_at' => '2026-06-15 10:00:00']);
    $outOfRange = Pedido::factory()->create(['status_id' => $this->solicitado->id, 'needed_at' => now()->subDays(2), 'requested_at' => '2026-07-15 10:00:00']);

    $component = Livewire::test(Dashboard::class)
        ->set('requestedFrom', '2026-06-01')
        ->set('requestedTo', '2026-06-30');

    $indicators = $component->instance()->indicators(app(DashboardIndicatorsService::class));
    $drillDownUrl = $component->instance()->drillDownUrl('atrasado');

    expect($indicators['atrasados'])->toBe(1);

    $this->get($drillDownUrl)
        ->assertOk()
        ->assertSee($inRange->code)
        ->assertDontSee($outOfRange->code);
});

/**
 * RF-20: the drill-down parameters are now consumed by `#[Url]` bindings
 * instead of manual `mount()` reads. The target listing must hydrate every
 * one of them on first paint — this is what keeps the legacy links exact.
 */
test('the drill-down parameters hydrate the target listing filter state on first paint', function () {
    Pedido::factory()->create(['status_id' => $this->solicitado->id, 'needed_at' => now()->subDays(2), 'requested_at' => '2026-06-15 10:00:00']);

    $component = Livewire::test(Dashboard::class)
        ->set('requestedFrom', '2026-06-01')
        ->set('requestedTo', '2026-06-30');

    $drillDownUrl = $component->instance()->drillDownUrl('atrasado');

    parse_str((string) parse_url($drillDownUrl, PHP_URL_QUERY), $parameters);

    expect($parameters)->toHaveKeys(['requestedFrom', 'requestedTo', 'atrasado']);

    Livewire::withQueryParams($parameters)
        ->test(GestaoTodosPedidos::class)
        ->assertSet('requestedFrom', '2026-06-01')
        ->assertSet('requestedTo', '2026-06-30')
        ->assertSet('atrasoOnly', true)
        ->assertSet('pendenteOnly', null);

    $pendenteUrl = $component->instance()->drillDownUrl('pendente');
    parse_str((string) parse_url($pendenteUrl, PHP_URL_QUERY), $pendenteParameters);

    Livewire::withQueryParams($pendenteParameters)
        ->test(GestaoTodosPedidos::class)
        ->assertSet('pendenteOnly', true)
        ->assertSet('atrasoOnly', false);
});
