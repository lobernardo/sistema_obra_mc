<?php

use App\Livewire\Obra\Acompanhamento;
use App\Models\Obra;
use App\Models\Pedido;
use App\Models\User;
use App\Support\LocalTime;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

/**
 * Slice 3 T12 (RF-15..RF-19, RF-22, RF-23, CT-03): Acompanhamento gains the
 * "Solicitado" axis, always applied after `visibleTo`, with the same property
 * names as the other listings and no "Somente obras ativas".
 */
beforeEach(function () {
    $this->statuses = seedWorkflowStatuses();

    $this->travelTo(CarbonImmutable::parse('2026-09-22 12:00', LocalTime::TIMEZONE));

    $this->user = User::factory()->obra()->create();
    $this->ownObra = Obra::factory()->emAndamento()->create();
    $this->foreignObra = Obra::factory()->emAndamento()->create();
    $this->user->obras()->attach($this->ownObra->id);

    $this->actingAs($this->user);

    $this->pedidoAt = fn (string $moment, ?Obra $obra = null, string $timezone = LocalTime::TIMEZONE): Pedido => Pedido::factory()->create([
        'obra_id' => ($obra ?? $this->ownObra)->id,
        'requester_id' => $obra === null ? $this->user->id : User::factory()->obra(),
        'status_id' => $this->statuses['solicitado']->id,
        'requested_at' => CarbonImmutable::parse($moment, $timezone)->utc(),
    ]);
});

/**
 * @return list<string>
 */
function acompanhamentoListedCodes(mixed $component): array
{
    return collect($component->viewData('pedidos')->items())->pluck('code')->all();
}

test('presets list only the user\'s visible pedidos inside the local-day window', function (string $preset, array $expectedDays) {
    $own = collect(['09-22', '09-21', '09-19', '09-15', '08-01'])
        ->mapWithKeys(fn (string $day) => [$day => ($this->pedidoAt)("2026-{$day} 12:00")->code]);

    $foreign = collect(['09-22', '09-21', '09-19', '09-15', '08-01'])
        ->map(fn (string $day) => ($this->pedidoAt)("2026-{$day} 12:00", $this->foreignObra)->code);

    $listed = acompanhamentoListedCodes(Livewire::withQueryParams(['solicitado' => $preset])->test(Acompanhamento::class));

    expect(collect($listed)->sort()->values()->all())
        ->toBe(collect($expectedDays)->map(fn (string $day) => $own[$day])->sort()->values()->all())
        ->and(array_intersect($listed, $foreign->all()))->toBe([]);
})->with([
    'hoje' => ['hoje', ['09-22']],
    '3d' => ['3d', ['09-22', '09-21']],
    '7d' => ['7d', ['09-22', '09-21', '09-19']],
    'mes' => ['mes', ['09-22', '09-21', '09-19', '09-15']],
]);

test('a forged obraId of a foreign obra with any preset returns no rows', function (string $preset) {
    ($this->pedidoAt)('2026-09-22 10:00');
    ($this->pedidoAt)('2026-09-22 10:00', $this->foreignObra);

    $component = Livewire::withQueryParams(['obraId' => $this->foreignObra->id, 'solicitado' => $preset])
        ->test(Acompanhamento::class);

    expect(acompanhamentoListedCodes($component))->toBe([]);
})->with(['', 'hoje', '3d', '7d', 'mes', 'personalizado']);

test('obrasAtivas in the URL changes nothing', function () {
    $concluida = Obra::factory()->concluida()->create();
    $this->user->obras()->attach($concluida->id);

    $emAndamento = ($this->pedidoAt)('2026-09-20 10:00');
    $naConcluida = Pedido::factory()->create([
        'obra_id' => $concluida->id,
        'requester_id' => $this->user->id,
        'status_id' => $this->statuses['solicitado']->id,
    ]);

    $semParametro = acompanhamentoListedCodes(Livewire::test(Acompanhamento::class));
    $comParametro = acompanhamentoListedCodes(Livewire::withQueryParams(['obrasAtivas' => true])->test(Acompanhamento::class));

    expect($comParametro)->toBe($semParametro)
        ->and($comParametro)->toContain($emAndamento->code, $naConcluida->code);
});

test('limparFiltros clears every Solicitado parameter', function () {
    Livewire::withQueryParams(['requestedFrom' => '2026-09-01', 'requestedTo' => '2026-09-10'])
        ->test(Acompanhamento::class)
        ->assertSet('requestedPreset', 'personalizado')
        ->call('limparFiltros')
        ->assertSet('requestedPreset', '')
        ->assertSet('requestedFrom', '')
        ->assertSet('requestedTo', '');

    Livewire::withQueryParams(['solicitado' => '7d'])
        ->test(Acompanhamento::class)
        ->call('limparFiltros')
        ->assertSet('requestedPreset', '');
});

test('a relative preset clears custom dates; an unknown preset is neutral', function () {
    $antigo = ($this->pedidoAt)('2025-01-10 12:00');

    Livewire::test(Acompanhamento::class)
        ->set('requestedFrom', '2026-09-01')
        ->set('requestedPreset', '7d')
        ->assertSet('requestedFrom', '')
        ->assertDontSee($antigo->code);

    $this->get(route('obra.pedidos.index', ['solicitado' => 'xyz']))->assertOk();

    Livewire::withQueryParams(['solicitado' => 'xyz'])
        ->test(Acompanhamento::class)
        ->assertSet('requestedPreset', '')
        ->assertSee($antigo->code);
});

test('choosing a preset returns to page 1', function () {
    collect(range(1, 12))->each(fn () => ($this->pedidoAt)('2026-09-22 10:00'));

    Livewire::test(Acompanhamento::class)
        ->call('gotoPage', 2)
        ->assertSet('paginators.page', 2)
        ->set('requestedPreset', 'hoje')
        ->assertSet('paginators.page', 1);
});

test('statusId + atrasado count as two active filters, and Solicitado counts once', function () {
    $component = Livewire::withQueryParams(['statusId' => $this->statuses['solicitado']->id, 'atrasado' => true])
        ->test(Acompanhamento::class);

    expect($component->instance()->activeFilterCount())->toBe(2);

    $periodo = Livewire::withQueryParams(['requestedFrom' => '2026-09-01', 'requestedTo' => '2026-09-10'])
        ->test(Acompanhamento::class);

    expect($periodo->instance()->activeFilterCount())->toBe(1);
});

test('the UTC small-hours pedido follows the local day in Personalizado', function () {
    $madrugada = ($this->pedidoAt)('2026-09-22T02:30:00Z', null, 'UTC');

    Livewire::withQueryParams(['requestedFrom' => '2026-09-22', 'requestedTo' => '2026-09-22'])
        ->test(Acompanhamento::class)
        ->assertDontSee($madrugada->code);

    Livewire::withQueryParams(['requestedFrom' => '2026-09-21', 'requestedTo' => '2026-09-21'])
        ->test(Acompanhamento::class)
        ->assertSee($madrugada->code);
});
