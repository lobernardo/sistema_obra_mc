<?php

use App\Domain\Pedidos\AtrasoClassifier;
use App\Domain\Pedidos\PendenteClassifier;
use App\Enums\EventTypeSlug;
use App\Enums\StatusSlug;
use App\Livewire\Gestao\Dashboard;
use App\Models\EventType;
use App\Models\Obra;
use App\Models\Pedido;
use App\Models\PedidoEvent;
use App\Models\Priority;
use App\Models\Status;
use App\Models\User;
use App\Services\DashboardIndicatorsService;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
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
        /** 5 no HEAD + a única query autorizada por RNF-10 (`entreguesHoje`). */
        ->and($queriesSemEntregues)->toBeLessThanOrEqual(6);
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

/**
 * RF-29 / CT-05: "Entregues hoje" is the delivery that actually happened —
 * an `entrega` event dated today on a pedido already in `entregue` — never the
 * forecast, which is nullable and would silently drop deliveries.
 */
function registrarEntrega(Pedido $pedido, ?CarbonInterface $quando = null): PedidoEvent
{
    $entrega = EventType::query()->where('slug', EventTypeSlug::Entrega->value)->first()
        ?? EventType::factory()->entrega()->create();

    $event = PedidoEvent::factory()->create([
        'pedido_id' => $pedido->id,
        'event_type_id' => $entrega->id,
        'actor_id' => User::factory()->suprimentos(),
    ]);

    if ($quando !== null) {
        DB::table('pedido_events')->where('id', $event->id)->update(['created_at' => $quando]);
    }

    return $event;
}

test('entreguesHoje counts the deliveries recorded today, including the one without a forecast', function () {
    $entregueHoje = Pedido::factory()->create([
        'status_id' => $this->statuses['entregue']->id,
        'expected_delivery_at' => now()->toDateString(),
    ]);
    $entregueOntem = Pedido::factory()->create([
        'status_id' => $this->statuses['entregue']->id,
        'expected_delivery_at' => now()->subDay()->toDateString(),
    ]);
    $entregueHojeSemPrevisao = Pedido::factory()->create([
        'status_id' => $this->statuses['entregue']->id,
        'expected_delivery_at' => null,
    ]);

    registrarEntrega($entregueHoje);
    registrarEntrega($entregueOntem, now()->subDay());
    registrarEntrega($entregueHojeSemPrevisao);

    $indicators = app(DashboardIndicatorsService::class)->compute();

    expect($entregueHoje->events()->count())->toBe(1)
        ->and($entregueOntem->events()->first()->created_at->isYesterday())->toBeTrue()
        ->and($entregueHojeSemPrevisao->expected_delivery_at)->toBeNull()
        ->and($indicators['entregues'])->toBe(3)
        ->and($indicators['entreguesHoje'])->toBe(2);
});

test('entreguesHoje ignores a pedido that is not in the entregue status and one with no entrega event', function () {
    $aguardando = Pedido::factory()->create(['status_id' => $this->statuses['aguardando_entrega']->id]);
    registrarEntrega($aguardando);

    Pedido::factory()->create(['status_id' => $this->statuses['entregue']->id]);

    $entregue = Pedido::factory()->create(['status_id' => $this->statuses['entregue']->id]);
    PedidoEvent::factory()->create([
        'pedido_id' => $entregue->id,
        'event_type_id' => EventType::factory()->mudancaStatus(),
        'actor_id' => User::factory()->suprimentos(),
    ]);

    expect(app(DashboardIndicatorsService::class)->compute()['entreguesHoje'])->toBe(0);
});

test('entreguesHoje respects the dashboard filters', function () {
    $obraA = Obra::factory()->create();
    $obraB = Obra::factory()->create();

    registrarEntrega(Pedido::factory()->create([
        'status_id' => $this->statuses['entregue']->id,
        'obra_id' => $obraA->id,
    ]));
    registrarEntrega(Pedido::factory()->create([
        'status_id' => $this->statuses['entregue']->id,
        'obra_id' => $obraB->id,
    ]));

    $service = app(DashboardIndicatorsService::class);

    expect($service->compute()['entreguesHoje'])->toBe(2)
        ->and($service->compute(['obraId' => $obraA->id])['entreguesHoje'])->toBe(1)
        ->and($service->compute(['statusId' => $this->statuses['solicitado']->id])['entreguesHoje'])->toBe(0);
});

/**
 * RNF-10: `entreguesHoje` is authorized to add **exactly one** query to
 * `compute()` — the three at HEAD (dataset, statuses, obras) plus its single
 * constant `whereExists`, never one per pedido.
 */
test('compute adds exactly one query — a single constant one over pedido_events — at any dataset size', function () {
    $service = app(DashboardIndicatorsService::class);

    /**
     * @return array{total: int, pedidoEvents: int}
     */
    $measure = function () use ($service): array {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $service->compute();
        $queries = DB::getQueryLog();
        DB::flushQueryLog();
        DB::disableQueryLog();

        return [
            'total' => count($queries),
            'pedidoEvents' => count(array_filter($queries, fn (array $query) => str_contains($query['query'], 'pedido_events'))),
        ];
    };

    foreach (range(1, 5) as $ignored) {
        registrarEntrega(Pedido::factory()->create(['status_id' => $this->statuses['entregue']->id]));
    }

    $smallDataset = $measure();

    foreach (range(1, 45) as $ignored) {
        registrarEntrega(Pedido::factory()->create(['status_id' => $this->statuses['entregue']->id]));
    }

    $largeDataset = $measure();

    expect($smallDataset['pedidoEvents'])->toBe(1, 'entreguesHoje must issue exactly one query, never one per pedido')
        ->and($largeDataset['pedidoEvents'])->toBe(1)
        ->and($largeDataset['total'])->toBe($smallDataset['total'])
        ->and($smallDataset['total'] - $smallDataset['pedidoEvents'])->toBe(5, 'the HEAD key set must keep issuing its 5 queries')
        ->and($service->compute()['entreguesHoje'])->toBe(50);
});

test('the entrega slug is encoded only in the service, never in a Suprimentos component or the Visão Geral view', function () {
    $sources = collect(File::allFiles(app_path('Livewire/Suprimentos')))
        ->map(fn ($file) => $file->getPathname())
        ->push(resource_path('views/livewire/suprimentos/visao-geral.blade.php'));

    foreach ($sources as $source) {
        $contents = file_get_contents($source);

        expect($contents)->not->toContain("'entrega'")
            ->not->toContain('EventTypeSlug::Entrega')
            ->not->toContain('pedido_events');
    }

    expect(file_get_contents(app_path('Services/DashboardIndicatorsService.php')))
        ->toContain('EventTypeSlug::Entrega->value');
});

/**
 * RF-26: the three indicator sections are exposed as images with a PT-BR
 * description of their distribution, while their textual numbers and the
 * per-row `data-*` attributes stay exactly as before.
 */
test('the three indicator sections expose role=img and a non-empty aria-label naming the section and its totals', function () {
    $this->actingAs(User::factory()->gestao()->create());

    $obra = Obra::factory()->create(['name' => 'Obra Central']);
    Pedido::factory()->count(3)->create([
        'obra_id' => $obra->id,
        'status_id' => $this->statuses['solicitado']->id,
        'needed_at' => now()->addDays(20),
    ]);
    Pedido::factory()->create([
        'obra_id' => $obra->id,
        'status_id' => $this->statuses['em_analise']->id,
        'needed_at' => now()->subDays(3),
    ]);

    $html = Livewire::test(Dashboard::class)->html();

    foreach (['indicator-por-status', 'indicator-prazos', 'indicator-por-obra'] as $testid) {
        preg_match('/<div data-testid="'.$testid.'"([^>]*)>/', $html, $tag);

        expect($tag[1] ?? '')->toContain('role="img"');

        preg_match('/aria-label="([^"]*)"/', $tag[1] ?? '', $label);

        expect(trim($label[1] ?? ''))->not->toBeEmpty("[{$testid}] has no aria-label");
    }

    preg_match('/data-testid="indicator-por-status"[^>]*aria-label="([^"]*)"/', $html, $porStatus);
    preg_match('/data-testid="indicator-prazos"[^>]*aria-label="([^"]*)"/', $html, $prazos);
    preg_match('/data-testid="indicator-por-obra"[^>]*aria-label="([^"]*)"/', $html, $porObra);

    expect($porStatus[1])->toContain('Distribuição por status')->toContain('Solicitado 3');
    expect($prazos[1])->toContain('Prazos')->toContain('4 pedidos pendentes')->toContain('Atrasados 1');
    expect($porObra[1])->toContain('Visão por obra')->toContain('Obra Central 4');
});

test('the per-row data attributes of the three sections keep rendering their values', function () {
    $this->actingAs(User::factory()->gestao()->create());

    $obra = Obra::factory()->create();
    Pedido::factory()->count(2)->create([
        'obra_id' => $obra->id,
        'status_id' => $this->statuses['solicitado']->id,
        'needed_at' => now()->addDays(20),
    ]);

    $html = Livewire::test(Dashboard::class)->html();

    expect($html)->toContain('data-status="solicitado"')
        ->toContain('data-situacao="dentro_do_prazo"')
        ->toContain('data-situacao="vencendo_em_breve"')
        ->toContain('data-situacao="atrasado"')
        ->toContain('data-obra="'.$obra->id.'"');

    preg_match('/data-status="solicitado".*?<\/li>/s', $html, $statusRow);
    preg_match('/data-obra="'.$obra->id.'".*?<\/li>/s', $html, $obraRow);

    expect($statusRow[0])->toContain('>2<');
    expect($obraRow[0])->toContain('>2<');
});
