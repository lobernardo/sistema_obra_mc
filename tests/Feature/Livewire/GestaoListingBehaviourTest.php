<?php

use App\Domain\Pedidos\AtrasoClassifier;
use App\Domain\Pedidos\RequestedPeriodFilter;
use App\Livewire\Gestao\TodosPedidos;
use App\Models\Obra;
use App\Models\Pedido;
use App\Models\User;
use App\Support\LocalTime;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/**
 * Slice 3 T11 (RF-15..RF-23, CT-03): the Gestão listing opens with
 * `visibleTo`, resolves drill-down dates as "Personalizado" in local days,
 * offers "Somente obras ativas" and keeps the newest pedido first.
 */
beforeEach(function () {
    $this->statuses = seedWorkflowStatuses();

    $this->travelTo(CarbonImmutable::parse('2026-09-22 12:00', LocalTime::TIMEZONE));

    $this->actingAs(User::factory()->gestao()->create());

    $this->pedidoAt = fn (string $moment, string $timezone = LocalTime::TIMEZONE, array $attributes = []): Pedido => Pedido::factory()->create([
        'status_id' => $this->statuses['solicitado']->id,
        'requested_at' => CarbonImmutable::parse($moment, $timezone)->utc(),
        ...$attributes,
    ]);
});

/**
 * @return list<string>
 */
function gestaoListedCodes(mixed $component): array
{
    return collect($component->viewData('pedidos')->items())->pluck('code')->all();
}

test('RF-18: the dashboard drill-down URL resolves as Personalizado and matches the local-day reference query', function () {
    ($this->pedidoAt)('2026-06-01 00:30', attributes: ['needed_at' => '2026-06-10']);
    ($this->pedidoAt)('2026-06-30 23:30', attributes: ['needed_at' => '2026-07-10']);
    ($this->pedidoAt)('2026-06-15 10:00', attributes: ['needed_at' => '2026-12-31']);
    ($this->pedidoAt)('2026-05-31 23:30', attributes: ['needed_at' => '2026-06-10']);
    ($this->pedidoAt)('2026-07-01 00:30', attributes: ['needed_at' => '2026-07-10']);
    ($this->pedidoAt)('2026-06-20 10:00', attributes: ['needed_at' => '2026-06-25', 'status_id' => $this->statuses['entregue']->id]);

    $this->get('/gestao/pedidos?requestedFrom=2026-06-01&requestedTo=2026-06-30&atrasado=true')->assertOk();

    $component = Livewire::withQueryParams(['requestedFrom' => '2026-06-01', 'requestedTo' => '2026-06-30', 'atrasado' => true])
        ->test(TodosPedidos::class)
        ->assertSet('requestedPreset', 'personalizado')
        ->assertSet('requestedFrom', '2026-06-01')
        ->assertSet('requestedTo', '2026-06-30');

    $expected = AtrasoClassifier::scopeAtrasado(RequestedPeriodFilter::applyLocalRange(Pedido::query(), '2026-06-01', '2026-06-30'))
        ->latest('requested_at')
        ->pluck('code')
        ->all();

    expect($expected)->toHaveCount(2)
        ->and(gestaoListedCodes($component))->toBe($expected);
});

test('RF-18: the UTC small-hours pedido follows the local day, chosen in the control', function () {
    $madrugada = ($this->pedidoAt)('2026-09-22T02:30:00Z', 'UTC');

    Livewire::test(TodosPedidos::class)
        ->set('requestedPreset', 'personalizado')
        ->set('requestedFrom', '2026-09-22')
        ->set('requestedTo', '2026-09-22')
        ->assertDontSee($madrugada->code)
        ->set('requestedFrom', '2026-09-21')
        ->set('requestedTo', '2026-09-21')
        ->assertSet('requestedPreset', 'personalizado')
        ->assertSee($madrugada->code);
});

test('RF-18: the UTC small-hours pedido follows the local day, resolved from the URL', function () {
    $madrugada = ($this->pedidoAt)('2026-09-22T02:30:00Z', 'UTC');

    Livewire::withQueryParams(['requestedFrom' => '2026-09-22', 'requestedTo' => '2026-09-22'])
        ->test(TodosPedidos::class)
        ->assertDontSee($madrugada->code);

    Livewire::withQueryParams(['requestedFrom' => '2026-09-21', 'requestedTo' => '2026-09-21'])
        ->test(TodosPedidos::class)
        ->assertSee($madrugada->code);
});

test('RF-19: an unknown preset is neutral; a relative preset beats custom dates', function () {
    $antigo = ($this->pedidoAt)('2025-01-10 12:00');
    $hoje = ($this->pedidoAt)('2026-09-22 09:00');

    $this->get(route('gestao.pedidos.index', ['solicitado' => 'xyz']))->assertOk();

    Livewire::withQueryParams(['solicitado' => 'xyz'])
        ->test(TodosPedidos::class)
        ->assertSet('requestedPreset', '')
        ->assertSee($antigo->code)
        ->assertSee($hoje->code);

    Livewire::withQueryParams(['solicitado' => 'hoje', 'requestedFrom' => '2020-01-01'])
        ->test(TodosPedidos::class)
        ->assertSee($hoje->code)
        ->assertDontSee($antigo->code);
});

test('RF-20: obras ativas keeps active obras and Outra, drops Concluída, and only narrows', function () {
    $pedidoA = ($this->pedidoAt)('2026-09-20 10:00', attributes: ['obra_id' => Obra::factory()->emAndamento()]);
    $pedidoB = ($this->pedidoAt)('2026-09-20 11:00', attributes: ['obra_id' => Obra::factory()->aIniciar()]);
    $pedidoC = ($this->pedidoAt)('2026-09-20 12:00', attributes: ['obra_id' => Obra::factory()->concluida()]);
    $outra = Pedido::factory()->outra()->create([
        'status_id' => $this->statuses['solicitado']->id,
        'requested_at' => CarbonImmutable::parse('2026-09-20 13:00', LocalTime::TIMEZONE)->utc(),
    ]);

    $ligado = Livewire::withQueryParams(['obrasAtivas' => true])->test(TodosPedidos::class);

    expect(gestaoListedCodes($ligado))->toBe([$outra->code, $pedidoB->code, $pedidoA->code]);

    expect(gestaoListedCodes($ligado->set('activeObrasOnly', false)))
        ->toBe([$outra->code, $pedidoC->code, $pedidoB->code, $pedidoA->code]);

    expect(gestaoListedCodes(Livewire::withQueryParams(['obrasAtivas' => true, 'obraId' => $pedidoC->obra_id])->test(TodosPedidos::class)))
        ->toBe([]);
});

test('RF-21: toggling obras ativas writes nothing', function () {
    ($this->pedidoAt)('2026-09-20 10:00', attributes: ['obra_id' => Obra::factory()->concluida()]);
    ($this->pedidoAt)('2026-09-20 11:00');

    $snapshot = fn (): array => [
        'pedidos' => [DB::table('pedidos')->count(), DB::table('pedidos')->max('updated_at')],
        'pedido_events' => [DB::table('pedido_events')->count(), DB::table('pedido_events')->max('created_at')],
        'obras' => [DB::table('obras')->count(), DB::table('obras')->max('updated_at')],
        'obra_profile' => [DB::table('obra_profile')->count(), DB::table('obra_profile')->max('created_at')],
    ];

    $before = $snapshot();

    $this->travel(5)->minutes();

    Livewire::test(TodosPedidos::class)
        ->set('activeObrasOnly', true)
        ->set('activeObrasOnly', false);

    expect($snapshot())->toBe($before);
});

test('choosing a preset returns to page 1', function () {
    collect(range(1, 12))->each(fn () => ($this->pedidoAt)('2026-09-22 10:00'));

    Livewire::test(TodosPedidos::class)
        ->call('gotoPage', 2)
        ->assertSet('paginators.page', 2)
        ->set('requestedPreset', '3d')
        ->assertSet('paginators.page', 1);
});

test('the newest pedido stays first', function () {
    $antigo = ($this->pedidoAt)('2026-09-01 10:00');
    $novo = ($this->pedidoAt)('2026-09-03 10:00');
    $meio = ($this->pedidoAt)('2026-09-02 10:00');

    expect(gestaoListedCodes(Livewire::test(TodosPedidos::class)))->toBe([$novo->code, $meio->code, $antigo->code]);
});

test('RF-23: for gestao the rows equal the query without visibleTo', function () {
    ($this->pedidoAt)('2026-09-20 10:00', attributes: ['obra_id' => Obra::factory()->concluida()]);
    ($this->pedidoAt)('2026-09-21 10:00');
    Pedido::factory()->outra()->create(['status_id' => $this->statuses['solicitado']->id]);

    expect(gestaoListedCodes(Livewire::test(TodosPedidos::class)))
        ->toBe(Pedido::query()->latest('requested_at')->pluck('code')->all());
});

test('active filter counters include the hidden drill-down criteria', function () {
    $component = Livewire::withQueryParams(['statusId' => $this->statuses['solicitado']->id, 'atrasado' => true])
        ->test(TodosPedidos::class);

    expect($component->instance()->activeFilterCount())->toBe(2);

    $drillDown = Livewire::withQueryParams(['pendente' => true, 'entregue' => true, 'requestedFrom' => '2026-06-01', 'obrasAtivas' => true])
        ->test(TodosPedidos::class);

    expect($drillDown->instance()->activeFilterCount())->toBe(4)
        ->and($drillDown->instance()->moreFiltersActiveCount())->toBe(1);
});
