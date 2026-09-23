<?php

use App\Livewire\Gestao\Dashboard;
use App\Livewire\Gestao\TodosPedidos as GestaoTodosPedidos;
use App\Models\Obra;
use App\Models\Pedido;
use App\Models\Priority;
use App\Models\Status;
use App\Models\User;
use App\Services\DashboardIndicatorsService;
use Illuminate\Support\Facades\DB;
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

/**
 * RF-23: every active dashboard filter the listing supports is carried over,
 * so the drill-down row count equals the KPI value that was clicked.
 */
test('the drill-down carries obra, prioridade and periodo so its row count equals the clicked KPI', function () {
    $obraAlvo = Obra::factory()->create();
    $outraObra = Obra::factory()->create();
    $urgente = Priority::factory()->urgente()->create();
    $normal = Priority::factory()->normal()->create();

    $dentroDoRecorte = Pedido::factory()->count(2)->create([
        'obra_id' => $obraAlvo->id,
        'priority_id' => $urgente->id,
        'status_id' => $this->solicitado->id,
        'needed_at' => now()->subDays(3),
        'requested_at' => '2026-06-15 10:00:00',
    ]);

    /** Fora por obra, por prioridade e por período — um de cada. */
    Pedido::factory()->create(['obra_id' => $outraObra->id, 'priority_id' => $urgente->id, 'status_id' => $this->solicitado->id, 'needed_at' => now()->subDays(3), 'requested_at' => '2026-06-15 10:00:00']);
    Pedido::factory()->create(['obra_id' => $obraAlvo->id, 'priority_id' => $normal->id, 'status_id' => $this->solicitado->id, 'needed_at' => now()->subDays(3), 'requested_at' => '2026-06-15 10:00:00']);
    Pedido::factory()->create(['obra_id' => $obraAlvo->id, 'priority_id' => $urgente->id, 'status_id' => $this->solicitado->id, 'needed_at' => now()->subDays(3), 'requested_at' => '2026-07-15 10:00:00']);

    $component = Livewire::test(Dashboard::class)
        ->set('obraId', $obraAlvo->id)
        ->set('priorityId', $urgente->id)
        ->set('requestedFrom', '2026-06-01')
        ->set('requestedTo', '2026-06-30');

    $indicators = $component->instance()->indicators(app(DashboardIndicatorsService::class));
    $drillDownUrl = $component->instance()->drillDownUrl('atrasado');

    expect($indicators['atrasados'])->toBe(2);

    parse_str((string) parse_url($drillDownUrl, PHP_URL_QUERY), $parameters);

    expect(array_keys($parameters))->toEqualCanonicalizing([
        'requestedFrom', 'requestedTo', 'obraId', 'priorityId', 'atrasado',
    ]);

    $listing = Livewire::withQueryParams($parameters)->test(GestaoTodosPedidos::class);

    expect($listing->instance()->pedidos()->total())->toBe($indicators['atrasados']);

    $listing->assertSet('obraId', $obraAlvo->id)
        ->assertSet('priorityId', $urgente->id)
        ->assertSet('atrasoOnly', true);

    $this->get($drillDownUrl)
        ->assertOk()
        ->assertSee($dentroDoRecorte->first()->code);
});

test('the drill-down invents no parameter when no dashboard filter is active', function () {
    Pedido::factory()->create(['status_id' => $this->solicitado->id, 'needed_at' => now()->subDays(2)]);

    $url = Livewire::test(Dashboard::class)->instance()->drillDownUrl('pendente');

    parse_str((string) parse_url($url, PHP_URL_QUERY), $parameters);

    expect(array_keys($parameters))->toBe(['pendente']);
});

/**
 * RF-24: the "Entregues" KPI drills down into the `entregue` status.
 */
test('the entregues drill-down carries the entregue criterion and matches the indicator value', function () {
    $entregue1 = Pedido::factory()->create(['status_id' => $this->entregue->id]);
    $entregue2 = Pedido::factory()->create(['status_id' => $this->entregue->id]);
    $pendente = Pedido::factory()->create(['status_id' => $this->solicitado->id]);

    $component = Livewire::test(Dashboard::class);
    $indicators = $component->instance()->indicators(app(DashboardIndicatorsService::class));
    $drillDownUrl = $component->instance()->drillDownUrl('entregue');

    expect($indicators['entregues'])->toBe(2);

    parse_str((string) parse_url($drillDownUrl, PHP_URL_QUERY), $parameters);

    expect($parameters)->toHaveKey('entregue');

    $listing = Livewire::withQueryParams($parameters)->test(GestaoTodosPedidos::class);

    expect($listing->instance()->pedidos()->total())->toBe($indicators['entregues']);

    $listing->assertSet('entregueOnly', true);

    $this->get($drillDownUrl)
        ->assertOk()
        ->assertSee($entregue1->code)
        ->assertSee($entregue2->code)
        ->assertDontSee($pendente->code);
});

test('the entregue criterion resolves to the entregue status id on first load', function () {
    Pedido::factory()->create(['status_id' => $this->entregue->id]);
    Pedido::factory()->create(['status_id' => $this->solicitado->id]);

    $statements = [];
    DB::listen(function ($query) use (&$statements) {
        $statements[] = [$query->sql, $query->bindings];
    });

    $listing = Livewire::withQueryParams(['entregue' => 'true'])->test(GestaoTodosPedidos::class);

    expect($listing->instance()->pedidos()->total())->toBe(1);

    $resolvedById = collect($statements)
        ->filter(fn (array $statement) => str_contains($statement[0], '"status_id" = ?'))
        ->contains(fn (array $statement) => in_array($this->entregue->id, $statement[1], true));

    expect($resolvedById)->toBeTrue();
});

test('the entregue criterion intersects with an explicit statusId instead of overriding it', function () {
    $entregue = Pedido::factory()->create(['status_id' => $this->entregue->id]);
    $solicitado = Pedido::factory()->create(['status_id' => $this->solicitado->id]);

    $conflitante = Livewire::withQueryParams(['entregue' => 'true', 'statusId' => (string) $this->solicitado->id])
        ->test(GestaoTodosPedidos::class);

    expect($conflitante->instance()->pedidos()->total())->toBe(0);

    $coerente = Livewire::withQueryParams(['entregue' => 'true', 'statusId' => (string) $this->entregue->id])
        ->test(GestaoTodosPedidos::class);

    expect($coerente->instance()->pedidos()->total())->toBe(1);

    $conflitante->assertDontSee($entregue->code)->assertDontSee($solicitado->code);
});

/**
 * RF-46 boundary: A was requested at 21/09 23:30 local and B at 22/09 00:30
 * local (both 22/09 in UTC). The period is a São Paulo calendar range, and
 * the Dashboard and its drill-down agree on it.
 */
test('the periodo filter follows the São Paulo day on the Dashboard and in the drill-down, with equal counts', function (string $localDay, array $expectedLabels) {
    $pedidos = [
        'A' => Pedido::factory()->create(['status_id' => $this->solicitado->id, 'requested_at' => '2026-09-22 02:30:00']),
        'B' => Pedido::factory()->create(['status_id' => $this->solicitado->id, 'requested_at' => '2026-09-22 03:30:00']),
    ];

    $component = Livewire::test(Dashboard::class)
        ->set('requestedFrom', $localDay)
        ->set('requestedTo', $localDay);

    $indicators = $component->instance()->indicators(app(DashboardIndicatorsService::class));
    $response = $this->get($component->instance()->drillDownUrl('pendente'))->assertOk();

    expect($indicators['volumeTotal'])->toBe(count($expectedLabels))
        ->and($indicators['pendentes'])->toBe(count($expectedLabels));

    foreach ($pedidos as $label => $pedido) {
        in_array($label, $expectedLabels, true)
            ? $response->assertSee($pedido->code)
            : $response->assertDontSee($pedido->code);
    }
})->with([
    '22/09 local → B only' => ['2026-09-22', ['B']],
    '21/09 local → A only' => ['2026-09-21', ['A']],
    'empty period → both' => ['', ['A', 'B']],
]);
