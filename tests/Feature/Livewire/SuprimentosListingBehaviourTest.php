<?php

use App\Domain\Pedidos\AtrasoClassifier;
use App\Livewire\Suprimentos\TodosPedidos;
use App\Models\Obra;
use App\Models\Pedido;
use App\Models\User;
use App\Support\LocalTime;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/**
 * Slice 3 T10 (RF-14, RF-16..RF-23, CT-03): the Suprimentos listing opens
 * with `visibleTo`, orders oldest first with `id` as tie-breaker, applies the
 * "Solicitado" period in local days and the "Somente obras ativas" filter.
 */
beforeEach(function () {
    $this->statuses = seedWorkflowStatuses();

    $this->travelTo(CarbonImmutable::parse('2026-09-22 12:00', LocalTime::TIMEZONE));

    $this->actingAs(User::factory()->suprimentos()->create());

    $this->pedidoAt = fn (string $moment, string $timezone = LocalTime::TIMEZONE, array $attributes = []): Pedido => Pedido::factory()->create([
        'status_id' => $this->statuses['solicitado']->id,
        'requested_at' => CarbonImmutable::parse($moment, $timezone)->utc(),
        ...$attributes,
    ]);
});

/**
 * @return list<string>
 */
function suprimentosListedCodes(mixed $component): array
{
    return collect($component->viewData('pedidos')->items())->pluck('code')->all();
}

test('RF-14: orders by requested_at ascending, terminal pedidos mixed in', function () {
    $primeiro = ($this->pedidoAt)('2026-09-01 10:00', attributes: ['status_id' => $this->statuses['entregue']->id]);
    $terceiro = ($this->pedidoAt)('2026-09-03 10:00');
    $segundo = ($this->pedidoAt)('2026-09-02 10:00');

    $component = Livewire::test(TodosPedidos::class);

    expect(suprimentosListedCodes($component))->toBe([$primeiro->code, $segundo->code, $terceiro->code]);
});

test('RF-14: identical requested_at breaks ties by id across pages, stably', function () {
    $pedidos = collect(range(1, 12))->map(fn () => ($this->pedidoAt)('2026-09-10 10:00'));
    $idsAscending = $pedidos->sortBy('id')->pluck('code')->values();

    $firstPage = suprimentosListedCodes(Livewire::test(TodosPedidos::class));
    $secondPage = suprimentosListedCodes(Livewire::test(TodosPedidos::class)->call('gotoPage', 2));

    expect($firstPage)->toBe($idsAscending->take(10)->all())
        ->and($secondPage)->toBe($idsAscending->slice(10)->values()->all())
        ->and(array_intersect($firstPage, $secondPage))->toBe([])
        ->and(suprimentosListedCodes(Livewire::test(TodosPedidos::class)))->toBe($firstPage);
});

test('RF-16: each preset lists exactly its local-day window and the indicators follow', function (string $preset, array $expectedDays) {
    $byDay = collect(['09-22', '09-21', '09-20', '09-19', '09-16', '09-15', '08-24', '08-23', '08-01'])
        ->mapWithKeys(fn (string $day) => [$day => ($this->pedidoAt)("2026-{$day} 12:00")->code]);

    $component = Livewire::test(TodosPedidos::class)->set('requestedPreset', $preset);

    $expected = collect($expectedDays)->map(fn (string $day) => $byDay[$day])->sort()->values()->all();

    expect(collect(suprimentosListedCodes($component))->sort()->values()->all())->toBe($expected)
        ->and($component->instance()->indicators()['total'])->toBe(count($expectedDays));
})->with([
    'hoje' => ['hoje', ['09-22']],
    '3d' => ['3d', ['09-22', '09-21', '09-20']],
    '7d' => ['7d', ['09-22', '09-21', '09-20', '09-19', '09-16']],
    'mes' => ['mes', ['09-22', '09-21', '09-20', '09-19', '09-16', '09-15', '08-24']],
]);

test('RF-16: choosing a preset returns to page 1', function () {
    collect(range(1, 12))->each(fn () => ($this->pedidoAt)('2026-09-22 10:00'));

    Livewire::test(TodosPedidos::class)
        ->call('gotoPage', 2)
        ->assertSet('paginators.page', 2)
        ->set('requestedPreset', 'hoje')
        ->assertSet('paginators.page', 1);
});

test('RF-16: the UTC small-hours pedido belongs to the previous local day for Hoje', function () {
    $madrugada = ($this->pedidoAt)('2026-09-22T02:30:00Z', 'UTC');

    Livewire::withQueryParams(['solicitado' => 'hoje'])
        ->test(TodosPedidos::class)
        ->assertDontSee($madrugada->code);

    $this->travelTo(CarbonImmutable::parse('2026-09-21 20:00', LocalTime::TIMEZONE));

    Livewire::withQueryParams(['solicitado' => 'hoje'])
        ->test(TodosPedidos::class)
        ->assertSee($madrugada->code);
});

test('RF-18: Personalizado compares local days and the row shows the local date', function () {
    $madrugada = ($this->pedidoAt)('2026-09-22T02:30:00Z', 'UTC');

    Livewire::withQueryParams(['requestedFrom' => '2026-09-22', 'requestedTo' => '2026-09-22'])
        ->test(TodosPedidos::class)
        ->assertSet('requestedPreset', 'personalizado')
        ->assertDontSee($madrugada->code);

    Livewire::withQueryParams(['requestedFrom' => '2026-09-21', 'requestedTo' => '2026-09-21'])
        ->test(TodosPedidos::class)
        ->assertSee($madrugada->code)
        ->assertSee('21/09/2026');
});

test('RF-17: a relative preset clears the custom dates and limparFiltros resets everything', function () {
    Livewire::test(TodosPedidos::class)
        ->set('requestedFrom', '2026-09-01')
        ->set('requestedPreset', '7d')
        ->assertSet('requestedFrom', '')
        ->set('activeObrasOnly', true)
        ->set('search', 'x')
        ->call('limparFiltros')
        ->assertSet('requestedPreset', '')
        ->assertSet('requestedFrom', '')
        ->assertSet('requestedTo', '')
        ->assertSet('activeObrasOnly', false)
        ->assertSet('search', '');
});

test('RF-19: an unknown preset is neutral; a relative preset beats custom dates', function () {
    $antigo = ($this->pedidoAt)('2025-01-10 12:00');
    $hoje = ($this->pedidoAt)('2026-09-22 09:00');

    $this->get(route('suprimentos.pedidos.index', ['solicitado' => 'xyz']))->assertOk();

    Livewire::withQueryParams(['solicitado' => 'xyz'])
        ->test(TodosPedidos::class)
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
    $outra = Pedido::factory()->outra('Galpão')->create([
        'status_id' => $this->statuses['solicitado']->id,
        'requested_at' => CarbonImmutable::parse('2026-09-20 13:00', LocalTime::TIMEZONE)->utc(),
    ]);

    $ligado = Livewire::withQueryParams(['obrasAtivas' => true])->test(TodosPedidos::class);

    expect(suprimentosListedCodes($ligado))->toBe([$pedidoA->code, $pedidoB->code, $outra->code])
        ->and($ligado->instance()->indicators()['total'])->toBe(3);

    $desligado = $ligado->set('activeObrasOnly', false);

    expect(suprimentosListedCodes($desligado))->toBe([$pedidoA->code, $pedidoB->code, $pedidoC->code, $outra->code])
        ->and($desligado->instance()->indicators()['total'])->toBe(4);

    $intersecao = Livewire::withQueryParams(['obrasAtivas' => true, 'obraId' => $pedidoC->obra_id])->test(TodosPedidos::class);

    expect(suprimentosListedCodes($intersecao))->toBe([])
        ->and($intersecao->instance()->indicators()['total'])->toBe(0);
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

test('RF-23: for suprimentos the rows equal a reference query without visibleTo', function (array $params, Closure $reference) {
    ($this->pedidoAt)('2026-09-22 10:00', attributes: ['obra_id' => Obra::factory()->concluida()]);
    ($this->pedidoAt)('2026-09-21 10:00', attributes: ['needed_at' => '2026-09-01']);
    ($this->pedidoAt)('2026-09-01 10:00', attributes: ['status_id' => $this->statuses['entregue']->id]);
    Pedido::factory()->outra()->create(['status_id' => $this->statuses['solicitado']->id]);

    $expected = $reference(Pedido::query())->orderBy('requested_at')->orderBy('id')->pluck('code')->all();

    expect(suprimentosListedCodes(Livewire::withQueryParams($params)->test(TodosPedidos::class)))->toBe($expected);
})->with([
    'sem filtro' => [[], fn ($query) => $query],
    'atrasado' => [['atrasado' => true], fn ($query) => AtrasoClassifier::scopeAtrasado($query)],
    'obras ativas + 7d' => [['obrasAtivas' => true, 'solicitado' => '7d'], fn ($query) => $query
        ->where(fn ($inner) => $inner->whereNull('obra_id')->orWhereHas('obra', fn ($obra) => $obra->where('status', '!=', 'concluido')))
        ->where('requested_at', '>=', CarbonImmutable::parse('2026-09-16 00:00', LocalTime::TIMEZONE)->utc())],
]);

test('statusId + atrasado count as two active filters', function () {
    $component = Livewire::withQueryParams(['statusId' => $this->statuses['solicitado']->id, 'atrasado' => true])
        ->test(TodosPedidos::class);

    expect($component->instance()->activeFilterCount())->toBe(2)
        ->and($component->instance()->moreFiltersActiveCount())->toBe(1);
});
