<?php

use App\Livewire\Concerns\FiltersByRequestedPeriod;
use App\Livewire\Gestao\TodosPedidos as GestaoTodosPedidos;
use App\Livewire\Suprimentos\TodosPedidos as SuprimentosTodosPedidos;
use App\Models\Pedido;
use App\Models\Status;
use App\Models\User;
use App\Support\LocalTime;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

/**
 * RF-15..RF-19, CT-03: both operational listings use
 * {@see FiltersByRequestedPeriod}, keep the preset in
 * the URL as `solicitado` and normalize it in `mount()`.
 */
beforeEach(function () {
    $this->solicitado = Status::factory()->solicitado()->create();

    $this->travelTo(CarbonImmutable::parse('2026-09-22 12:00', LocalTime::TIMEZONE));
});

dataset('listings', [
    'suprimentos' => [SuprimentosTodosPedidos::class, 'suprimentos'],
    'gestao' => [GestaoTodosPedidos::class, 'gestao'],
]);

function pedidoRequestedAt(string $utc, int $statusId): Pedido
{
    return Pedido::factory()->create([
        'status_id' => $statusId,
        'requested_at' => CarbonImmutable::parse($utc, 'UTC'),
    ]);
}

test('drill-down dates without a preset resolve as Personalizado and filter by local days', function (string $component, string $role) {
    $this->actingAs(User::factory()->{$role}()->create());

    $dentro = pedidoRequestedAt('2026-09-21T12:00:00Z', $this->solicitado->id);
    $madrugadaUtc = pedidoRequestedAt('2026-09-22T02:30:00Z', $this->solicitado->id);
    $fora = pedidoRequestedAt('2026-09-22T12:00:00Z', $this->solicitado->id);

    Livewire::withQueryParams(['requestedFrom' => '2026-09-21', 'requestedTo' => '2026-09-21'])
        ->test($component)
        ->assertSet('requestedPreset', 'personalizado')
        ->assertSet('requestedFrom', '2026-09-21')
        ->assertSet('requestedTo', '2026-09-21')
        ->assertSee($dentro->code)
        ->assertSee($madrugadaUtc->code)
        ->assertDontSee($fora->code);
})->with('listings');

test('a relative preset wins over custom dates and clears them', function (string $component, string $role) {
    $this->actingAs(User::factory()->{$role}()->create());

    $hoje = pedidoRequestedAt('2026-09-22T12:00:00Z', $this->solicitado->id);
    $ontem = pedidoRequestedAt('2026-09-22T02:30:00Z', $this->solicitado->id);

    Livewire::withQueryParams(['solicitado' => 'hoje', 'requestedFrom' => '2020-01-01', 'requestedTo' => '2020-01-31'])
        ->test($component)
        ->assertSet('requestedPreset', 'hoje')
        ->assertSet('requestedFrom', '')
        ->assertSet('requestedTo', '')
        ->assertSee($hoje->code)
        ->assertDontSee($ontem->code);
})->with('listings');

test('an unknown preset is neutral and answers without error', function (string $component, string $role) {
    $this->actingAs(User::factory()->{$role}()->create());

    $antigo = pedidoRequestedAt('2025-01-10T12:00:00Z', $this->solicitado->id);

    Livewire::withQueryParams(['solicitado' => 'xyz'])
        ->test($component)
        ->assertOk()
        ->assertSet('requestedPreset', '')
        ->assertSee($antigo->code);
})->with('listings');

test('choosing a relative preset clears custom dates and limparFiltros resets the preset', function (string $component, string $role) {
    $this->actingAs(User::factory()->{$role}()->create());

    Livewire::test($component)
        ->set('requestedFrom', '2026-06-01')
        ->set('requestedPreset', '7d')
        ->assertSet('requestedFrom', '')
        ->assertSet('requestedPreset', '7d')
        ->call('limparFiltros')
        ->assertSet('requestedPreset', '')
        ->assertSet('requestedFrom', '')
        ->assertSet('requestedTo', '');
})->with('listings');
