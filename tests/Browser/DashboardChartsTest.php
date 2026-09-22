<?php

use App\Models\Obra;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Pest\Browser\Api\PendingAwaitablePage;

/**
 * UI-04 (T29): the prazos donut must survive Livewire re-renders — after every
 * filter change there is still exactly one `<svg>` in the prazos card and its
 * wedges match the numbers rendered next to them. A chart that disappears,
 * duplicates or goes stale after a `wire:model.live` round-trip is invisible to
 * the PHP suite, so it is asserted in a real browser.
 */
const PRAZOS_CHART_AUDIT_SCRIPT = <<<'JS'
    (() => {
        const card = document.querySelector('[data-testid="indicator-prazos"]');
        if (card === null) return { cardFound: false };

        const angleAt = (x, y) => ((Math.atan2(x - 50, 50 - y) * 180 / Math.PI) + 360) % 360;

        const slices = [...card.querySelectorAll('path[data-fatia]')].map((path) => {
            const numbers = (path.getAttribute('d').match(/-?\d+(?:\.\d+)?/g) || []).map(Number);

            return {
                situacao: path.dataset.fatia,
                sweep: ((angleAt(numbers[7], numbers[8]) - angleAt(numbers[0], numbers[1])) + 360) % 360,
                fill: getComputedStyle(path).fill,
            };
        });

        const numbers = {};
        for (const item of card.querySelectorAll('li[data-situacao]')) {
            numbers[item.dataset.situacao] = Number(item.querySelector('span.font-semibold').textContent.trim());
        }

        const root = document.documentElement;

        return {
            cardFound: true,
            svgCount: card.querySelectorAll('svg').length,
            slices,
            numbers,
            ariaLabel: card.getAttribute('aria-label'),
            role: card.getAttribute('role'),
            scrollWidth: root.scrollWidth,
            clientWidth: root.clientWidth,
        };
    })()
    JS;

/**
 * Asserts the single chart in the prazos card mirrors the numbers rendered
 * beside it, within one percentage point.
 */
function assertPrazosChartMatchesNumbers(PendingAwaitablePage $page, string $moment): void
{
    $audit = $page->script(PRAZOS_CHART_AUDIT_SCRIPT);

    expect($audit['cardFound'])->toBeTrue("[{$moment}] the prazos card is missing");
    expect($audit['svgCount'])->toBe(1, "[{$moment}] expected exactly one <svg> in the prazos card, found {$audit['svgCount']}");
    expect($audit['role'])->toBe('img', "[{$moment}] the prazos card lost role=img");
    expect(trim((string) $audit['ariaLabel']))->not->toBeEmpty("[{$moment}] the prazos card lost its aria-label");

    $total = array_sum($audit['numbers']);

    expect(count($audit['slices']))->toBe(3, "[{$moment}] expected three wedges");

    foreach ($audit['slices'] as $slice) {
        $situacao = $slice['situacao'];
        $count = $audit['numbers'][$situacao] ?? null;

        expect($count)->not->toBeNull("[{$moment}] wedge [{$situacao}] has no matching number");

        $expectedShare = $total > 0 ? $count / $total : 0.0;
        $renderedShare = $slice['sweep'] / 360;

        expect(abs($renderedShare - $expectedShare))->toBeLessThanOrEqual(
            0.01,
            "[{$moment}] wedge [{$situacao}] renders {$renderedShare} of the donut for {$count}/{$total}"
        );

        expect($slice['fill'])->not->toBe('rgb(0, 0, 0)', "[{$moment}] wedge [{$situacao}] is unpainted — the fill utility is missing from the build");
    }
}

test('the prazos donut stays present and correct after each dashboard filter change', function () {
    $this->seed(DemoSeeder::class);
    $this->actingAs(User::query()->where('email', 'gestao.demo@example.com')->firstOrFail());

    $alfa = Obra::query()->where('name', 'like', '%Alfa%')->firstOrFail();
    $beta = Obra::query()->where('name', 'like', '%Beta%')->firstOrFail();

    $waitForAtrasados = fn (PendingAwaitablePage $page, int $expected) => $page->page()->waitForFunction(
        '() => document.querySelector(\'[data-testid="indicator-prazos"] li[data-situacao="atrasado"] span.font-semibold\')'
        .'?.textContent.trim() === "'.$expected.'"'
    );

    $page = $this->visit('/gestao/dashboard');
    $page->page()->waitForFunction('() => window.Livewire !== undefined');

    // Demo dataset: 4 pendentes — 2 dentro do prazo, 1 vencendo em breve, 1 atrasado.
    $waitForAtrasados($page, 1);
    assertPrazosChartMatchesNumbers($page, 'sem filtro');

    // First change: obra Alfa — only pedidos dentro do prazo.
    $page->select('obraId', (string) $alfa->id);
    $waitForAtrasados($page, 0);
    assertPrazosChartMatchesNumbers($page, 'filtrado por Alfa');

    // Second change: obra Beta — one vencendo em breve and one atrasado.
    $page->select('obraId', (string) $beta->id);
    $waitForAtrasados($page, 1);
    assertPrazosChartMatchesNumbers($page, 'filtrado por Beta');

    $page->assertNoJavascriptErrors();
});

test('the dashboard keeps the fourth KPI card and the donut inside a 390px viewport', function () {
    $this->seed(DemoSeeder::class);
    $this->actingAs(User::query()->where('email', 'gestao.demo@example.com')->firstOrFail());

    $page = $this->visit('/gestao/dashboard');
    $page->resize(390, 844);
    $page->page()->goto(url('/gestao/dashboard'));
    $page->page()->waitForFunction('() => window.Livewire !== undefined');

    $audit = $page->script(PRAZOS_CHART_AUDIT_SCRIPT);

    expect($audit['scrollWidth'])->toBeLessThanOrEqual(
        $audit['clientWidth'],
        "/gestao/dashboard overflows at 390px: scrollWidth {$audit['scrollWidth']} > clientWidth {$audit['clientWidth']}"
    );

    $page->assertPresent('[data-testid="indicator-entregues"]');
    assertPrazosChartMatchesNumbers($page, '390px');

    $page->assertNoJavascriptErrors();
});
