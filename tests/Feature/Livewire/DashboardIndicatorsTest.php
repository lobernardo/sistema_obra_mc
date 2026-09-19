<?php

use App\Domain\Pedidos\AtrasoClassifier;
use App\Domain\Pedidos\PendenteClassifier;
use App\Enums\StatusSlug;
use App\Livewire\Gestao\Dashboard;
use App\Models\Obra;
use App\Models\Pedido;
use App\Models\Status;
use App\Models\User;
use App\Services\DashboardIndicatorsService;
use Livewire\Livewire;

beforeEach(function () {
    $this->statuses = [];

    foreach (StatusSlug::cases() as $slug) {
        $this->statuses[$slug->value] = Status::factory()->create([
            'slug' => $slug->value,
            'name' => ucfirst($slug->value),
            'sort_order' => array_search($slug, StatusSlug::cases(), true) + 1,
        ]);
    }
});

test('non-gestao actors are denied access to the dashboard', function (string $role) {
    $actor = User::factory()->{$role}()->create();

    $this->actingAs($actor);

    Livewire::test(Dashboard::class)->assertSee('403');
})->with(['obra', 'suprimentos']);

test('the atrasados indicator count is identical to the AtrasoClassifier-derived count on the same dataset', function () {
    Pedido::factory()->create(['status_id' => $this->statuses['solicitado']->id, 'needed_at' => now()->subDays(2)]);
    Pedido::factory()->create(['status_id' => $this->statuses['em_analise']->id, 'needed_at' => now()->subDays(5)]);
    Pedido::factory()->create(['status_id' => $this->statuses['solicitado']->id, 'needed_at' => now()->addDays(2)]);
    Pedido::factory()->create(['status_id' => $this->statuses['entregue']->id, 'needed_at' => now()->subDays(5)]);

    $expectedAtrasados = Pedido::query()->with('status')->get()
        ->filter(fn (Pedido $pedido) => AtrasoClassifier::isAtrasado($pedido))
        ->count();

    $indicators = app(DashboardIndicatorsService::class)->compute();

    expect($expectedAtrasados)->toBe(2);
    expect($indicators['atrasados'])->toBe($expectedAtrasados);
});

test('the pendentes indicator count is identical to the PendenteClassifier-derived count on the same dataset', function () {
    Pedido::factory()->create(['status_id' => $this->statuses['solicitado']->id]);
    Pedido::factory()->create(['status_id' => $this->statuses['em_analise']->id]);
    Pedido::factory()->create(['status_id' => $this->statuses['entregue']->id]);
    Pedido::factory()->create(['status_id' => $this->statuses['cancelado']->id]);

    $expectedPendentes = Pedido::query()->with('status')->get()
        ->filter(fn (Pedido $pedido) => PendenteClassifier::isPendente($pedido))
        ->count();

    $indicators = app(DashboardIndicatorsService::class)->compute();

    expect($expectedPendentes)->toBe(2);
    expect($indicators['pendentes'])->toBe($expectedPendentes);
});

test('distribuicao por status sums to the volume total', function () {
    Pedido::factory()->count(3)->create(['status_id' => $this->statuses['solicitado']->id]);
    Pedido::factory()->count(2)->create(['status_id' => $this->statuses['em_analise']->id]);
    Pedido::factory()->create(['status_id' => $this->statuses['entregue']->id]);

    $indicators = app(DashboardIndicatorsService::class)->compute();

    expect($indicators['porStatus']->sum('count'))->toBe($indicators['volumeTotal'])->toBe(6);
});

test('visao por obra sums to the volume total', function () {
    $obraA = Obra::factory()->create();
    $obraB = Obra::factory()->create();

    Pedido::factory()->count(2)->create(['obra_id' => $obraA->id, 'status_id' => $this->statuses['solicitado']->id]);
    Pedido::factory()->count(3)->create(['obra_id' => $obraB->id, 'status_id' => $this->statuses['solicitado']->id]);

    $indicators = app(DashboardIndicatorsService::class)->compute();

    expect($indicators['porObra']->sum('count'))->toBe($indicators['volumeTotal'])->toBe(5);
});

test('prazos sums to the pendentes total', function () {
    Pedido::factory()->create(['status_id' => $this->statuses['solicitado']->id, 'needed_at' => now()->subDays(2)]);
    Pedido::factory()->create(['status_id' => $this->statuses['solicitado']->id, 'needed_at' => now()->addDay()]);
    Pedido::factory()->create(['status_id' => $this->statuses['solicitado']->id, 'needed_at' => now()->addDays(10)]);
    Pedido::factory()->create(['status_id' => $this->statuses['entregue']->id, 'needed_at' => now()->subDays(5)]);

    $indicators = app(DashboardIndicatorsService::class)->compute();

    expect($indicators['prazos']->sum('count'))->toBe($indicators['pendentes'])->toBe(3);
});

test('the dashboard component renders all 6 indicators for a gestao actor', function () {
    $actor = User::factory()->gestao()->create();
    $this->actingAs($actor);

    Pedido::factory()->create(['status_id' => $this->statuses['solicitado']->id]);

    Livewire::test(Dashboard::class)
        ->assertSeeText('Volume total')
        ->assertSeeText('Pendentes')
        ->assertSeeText('Atrasados')
        ->assertSeeText('Distribuição por status')
        ->assertSeeText('Prazos')
        ->assertSeeText('Visão por obra');
});
