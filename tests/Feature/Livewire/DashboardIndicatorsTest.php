<?php

use App\Domain\Pedidos\AtrasoClassifier;
use App\Domain\Pedidos\PendenteClassifier;
use App\Enums\StatusSlug;
use App\Livewire\Gestao\Dashboard;
use App\Models\Obra;
use App\Models\Pedido;
use App\Models\Priority;
use App\Models\Status;
use App\Models\User;
use App\Services\DashboardIndicatorsService;
use Illuminate\Support\Facades\DB;
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

/**
 * RF-22 / CT-05: `entregues` counts, inside the filtered set, the pedidos
 * whose status slug is `entregue`.
 */
test('the entregues indicator counts only the pedidos whose status slug is entregue', function () {
    Pedido::factory()->count(2)->create(['status_id' => $this->statuses['entregue']->id]);
    Pedido::factory()->create(['status_id' => $this->statuses['solicitado']->id]);
    Pedido::factory()->create(['status_id' => $this->statuses['aguardando_entrega']->id]);
    Pedido::factory()->create(['status_id' => $this->statuses['cancelado']->id]);

    $indicators = app(DashboardIndicatorsService::class)->compute();

    expect($indicators['entregues'])->toBe(2)
        ->and($indicators['volumeTotal'])->toBe(5);
});

test('the entregues indicator is correct under each of the five dashboard filters', function () {
    $obraA = Obra::factory()->create();
    $obraB = Obra::factory()->create();
    $urgente = Priority::factory()->urgente()->create();
    $normal = Priority::factory()->normal()->create();
    $responsavelA = User::factory()->suprimentos()->create();
    $responsavelB = User::factory()->suprimentos()->create();

    $alvo = Pedido::factory()->create([
        'status_id' => $this->statuses['entregue']->id,
        'obra_id' => $obraA->id,
        'priority_id' => $urgente->id,
        'responsible_id' => $responsavelA->id,
        'requested_at' => '2026-06-15 10:00:00',
    ]);

    Pedido::factory()->create([
        'status_id' => $this->statuses['entregue']->id,
        'obra_id' => $obraB->id,
        'priority_id' => $normal->id,
        'responsible_id' => $responsavelB->id,
        'requested_at' => '2026-07-15 10:00:00',
    ]);

    Pedido::factory()->create([
        'status_id' => $this->statuses['solicitado']->id,
        'obra_id' => $obraA->id,
        'priority_id' => $urgente->id,
        'responsible_id' => $responsavelA->id,
        'requested_at' => '2026-06-16 10:00:00',
    ]);

    $service = app(DashboardIndicatorsService::class);

    expect($service->compute()['entregues'])->toBe(2)
        ->and($service->compute(['obraId' => $obraA->id])['entregues'])->toBe(1)
        ->and($service->compute(['statusId' => $this->statuses['entregue']->id])['entregues'])->toBe(2)
        ->and($service->compute(['statusId' => $this->statuses['solicitado']->id])['entregues'])->toBe(0)
        ->and($service->compute(['priorityId' => $urgente->id])['entregues'])->toBe(1)
        ->and($service->compute(['responsibleId' => $responsavelB->id])['entregues'])->toBe(1)
        ->and($service->compute(['requestedFrom' => '2026-06-01', 'requestedTo' => '2026-06-30'])['entregues'])->toBe(1);

    expect($alvo->fresh()->status->slug)->toBe('entregue');
});

test('the computed array key set matches the shape documented in the service PHPDoc', function () {
    Pedido::factory()->create(['status_id' => $this->statuses['entregue']->id]);

    $indicators = app(DashboardIndicatorsService::class)->compute();

    $documented = [];
    preg_match('/@return array\{(.*?)\}\s*\*\//s', file_get_contents(app_path('Services/DashboardIndicatorsService.php')), $matches);

    foreach (explode("\n", $matches[1] ?? '') as $line) {
        if (preg_match('/^\s*\*\s*([a-zA-Z]+):/', $line, $key)) {
            $documented[] = $key[1];
        }
    }

    expect($documented)->toContain('entregues')
        ->and(array_keys($indicators))->toEqualCanonicalizing($documented);
});

test('the entregues key adds no query to the service', function () {
    Pedido::factory()->count(2)->create(['status_id' => $this->statuses['solicitado']->id]);

    $service = app(DashboardIndicatorsService::class);
    $service->compute();

    $measure = function () use ($service): array {
        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });

        return [$service->compute(), $queries];
    };

    [$semEntregues, $queriesSemEntregues] = $measure();

    Pedido::factory()->count(3)->create(['status_id' => $this->statuses['entregue']->id]);

    [$comEntregues, $queriesComEntregues] = $measure();

    expect($semEntregues['entregues'])->toBe(0)
        ->and($comEntregues['entregues'])->toBe(3)
        ->and($queriesComEntregues)->toBe($queriesSemEntregues)
        ->and($queriesSemEntregues)->toBeLessThanOrEqual(5);
});

/**
 * RF-22/RF-24/UI-05: the fourth KPI card follows exactly the markup pattern
 * of the two existing drill-down cards.
 */
test('the entregues card renders with the same markup pattern as the other drill-down cards', function () {
    $actor = User::factory()->gestao()->create();
    $this->actingAs($actor);

    Pedido::factory()->count(2)->create(['status_id' => $this->statuses['entregue']->id]);
    Pedido::factory()->create(['status_id' => $this->statuses['solicitado']->id]);

    $html = Livewire::test(Dashboard::class)
        ->assertSeeText('Entregues')
        ->html();

    expect($html)->toContain('data-testid="indicator-entregues"')
        ->toContain('border-t-concluido')
        ->toContain('text-concluido');

    foreach (['indicator-pendentes', 'indicator-atrasados', 'indicator-entregues'] as $testid) {
        preg_match('/data-testid="'.$testid.'"(.*?)<\/div>/s', $html, $card);

        expect($card[1] ?? '')->toContain('data-value')
            ->toContain('border-t-4')
            ->toContain('hover:underline');
    }

    expect($html)->toContain(route('gestao.pedidos.index', ['entregue' => 'true']));
});

test('the dashboard renders the four KPI cards in a grid that fits four columns', function () {
    $actor = User::factory()->gestao()->create();
    $this->actingAs($actor);

    Pedido::factory()->create(['status_id' => $this->statuses['entregue']->id]);

    $html = Livewire::test(Dashboard::class)->html();

    expect(substr_count($html, 'data-testid="indicator-'))->toBe(7)
        ->and($html)->toContain('grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4')
        ->and($html)->not->toContain('sm:grid-cols-3');
});
