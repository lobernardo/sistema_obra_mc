<?php

use App\Models\Status;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Pest\Browser\Api\PendingAwaitablePage;

/**
 * navegacao-sidebar-listagens T18 (UI-06, UI-07, RNF-02): the compact filter
 * panel of the three pedido listings in a real browser.
 *
 * - Desktop (1280×800 and 1440×900): the primary controls share one row, the
 *   whole panel takes at most two rows with "Mais filtros" closed, the
 *   disclosure survives the Livewire re-render and counts its active axes,
 *   and "Personalizado" alone renders De/Até.
 * - Mobile (390×844): the panel is collapsed behind "Filtros (n)", its
 *   controls are 44 px touch targets, nothing overflows and the first pedido
 *   card starts above the fold.
 *
 * Every "row" is a set of control tops within 4 px of each other.
 */
dataset('filter listings', [
    'Obra' => ['obra.demo@example.com', '/obra/pedidos', false],
    'Suprimentos' => ['suprimentos.demo@example.com', '/suprimentos/pedidos', true],
    'Gestão' => ['gestao.demo@example.com', '/gestao/pedidos', true],
]);

dataset('filter desktop viewports', [
    '1280×800' => [1280, 800],
    '1440×900' => [1440, 900],
]);

/**
 * Resizes, navigates and waits until the filter panel is interactive
 * (Livewire booted and Alpine initialized on the panel form).
 */
function filtersOpenPage(PendingAwaitablePage $page, string $path, int $width, int $height): void
{
    $page->resize($width, $height);
    $page->page()->goto(url($path));
    $page->page()->locator('form.filter-panel')->waitFor(['state' => 'attached']);
    $ready = $page->script(<<<'JS'
        () => new Promise((resolve) => {
            const started = performance.now();
            const poll = () => {
                const ready = document.readyState === 'complete'
                    && window.Livewire !== undefined
                    && document.querySelector('form.filter-panel')?._x_dataStack !== undefined;
                if (ready || performance.now() - started > 5000) {
                    resolve(ready);
                    return;
                }
                requestAnimationFrame(poll);
            };
            poll();
        })
        JS);

    expect($ready)->toBeTrue("{$path}: Livewire/Alpine did not boot");
}

/**
 * Geometry of the rendered filter controls: the tops of the primary row, the
 * tops of every rendered control, touch-target heights (a checkbox is
 * measured by its wrapping label), unlabelled controls, visible selects,
 * the document overflow and the top of the first pedido card.
 *
 * @return array{primaryTops: list<float>, allTops: list<float>, heights: list<array{control: string, height: float}>, unlabelled: list<string>, visibleSelects: int, scrollWidth: int, clientWidth: int, firstCardTop: ?float}
 */
function filtersAudit(PendingAwaitablePage $page): array
{
    return $page->script(<<<'JS'
        () => {
            const rendered = (el) => el.getClientRects().length > 0 && getComputedStyle(el).visibility !== 'hidden';
            const controls = (root) => [...root.querySelectorAll('select, input, button')].filter(rendered);
            const panel = document.getElementById('filtros-painel');
            const primaryRow = panel.firstElementChild;
            const describe = (el) => el.id || el.getAttribute('data-testid') || el.textContent.trim();
            // A checkbox is operated through its wrapping label, so the label is the control box.
            const box = (el) => (el.type === 'checkbox' ? el.closest('label') : el).getBoundingClientRect();

            const heights = controls(document.querySelector('form.filter-panel'))
                .map((el) => ({ control: describe(el), height: box(el).height }));

            const unlabelled = controls(panel)
                .filter((el) => el.tagName !== 'BUTTON' && el.labels.length === 0 && !el.getAttribute('aria-label'))
                .map(describe);

            const card = document.querySelector('[data-testid="pedido-card"]');

            return {
                primaryTops: controls(primaryRow).map((el) => box(el).top),
                allTops: controls(panel).map((el) => box(el).top),
                heights,
                unlabelled,
                visibleSelects: [...document.querySelectorAll('main select')].filter(rendered).length,
                scrollWidth: document.documentElement.scrollWidth,
                clientWidth: document.documentElement.clientWidth,
                firstCardTop: card && rendered(card) ? card.getBoundingClientRect().top : null,
            };
        }
        JS);
}

/**
 * Groups tops that lie within 4 px of each other; returns the row count.
 *
 * @param  list<float>  $tops
 */
function filtersRowCount(array $tops): int
{
    sort($tops);
    $rows = 0;
    $rowStart = null;

    foreach ($tops as $top) {
        if ($rowStart === null || $top - $rowStart > 4) {
            $rows++;
            $rowStart = $top;
        }
    }

    return $rows;
}

test('on desktop the filters take one primary row, at most two rows, keep "Mais filtros" open and toggle De/Até', function (string $email, string $path, bool $hasMoreFilters, int $width, int $height) {
    $this->seed(DemoSeeder::class);
    $this->actingAs(User::query()->where('email', $email)->firstOrFail());

    $page = $this->visit($path);
    filtersOpenPage($page, $path, $width, $height);

    $label = "[{$path}] at {$width}px";
    $audit = filtersAudit($page);

    expect($page->page()->locator('[data-testid="filtros-toggle"]')->isVisible())->toBeFalse("{$label}: the mobile toggle must be hidden");
    expect($audit['primaryTops'])->not->toBeEmpty();
    expect(filtersRowCount($audit['primaryTops']))->toBe(1, "{$label}: primary controls are not on one row: ".json_encode($audit['primaryTops']));
    expect(filtersRowCount($audit['allTops']))->toBeLessThanOrEqual(2, "{$label}: the closed panel takes more than 2 rows: ".json_encode($audit['allTops']));
    expect($audit['unlabelled'])->toBe([], "{$label}: unlabelled filter controls: ".implode(', ', $audit['unlabelled']));
    expect($audit['scrollWidth'])->toBeLessThanOrEqual($audit['clientWidth'], "{$label}: horizontal overflow");

    if ($hasMoreFilters) {
        $toggle = $page->page()->locator('[data-testid="mais-filtros-toggle"]');
        $more = $page->page()->locator('#mais-filtros');

        expect($more->isVisible())->toBeFalse("{$label}: \"Mais filtros\" must start closed");

        $toggle->click();
        $more->waitFor(['state' => 'visible']);

        $page->page()->locator('#atrasoOnly')->check();
        $page->page()->locator('[data-testid="mais-filtros-toggle"]:text-is("Mais filtros (1)")')->waitFor(['state' => 'visible']);

        expect($more->isVisible())->toBeTrue("{$label}: \"Mais filtros\" closed after the re-render");
        expect($toggle->getAttribute('aria-expanded'))->toBe('true');
    } else {
        expect($page->page()->locator('[data-testid="mais-filtros-toggle"]')->count())->toBe(0);
    }

    expect($page->page()->locator('#requestedFrom')->count())->toBe(0);

    $page->page()->locator('#requestedPreset')->selectOption('personalizado');
    $page->page()->locator('#requestedFrom')->waitFor(['state' => 'visible']);

    expect($page->page()->locator('#requestedTo')->isVisible())->toBeTrue();

    $page->page()->locator('#requestedPreset')->selectOption('hoje');
    $page->page()->locator('#requestedFrom')->waitFor(['state' => 'detached']);

    expect($page->page()->locator('#requestedTo')->count())->toBe(0);

    $page->assertNoJavascriptErrors();
})->with('filter listings')->with('filter desktop viewports');

test('on mobile the filters collapse behind "Filtros (n)" with 44 px controls, no overflow and the first card above the fold', function (string $email, string $path) {
    $this->seed(DemoSeeder::class);
    $this->actingAs(User::query()->where('email', $email)->firstOrFail());

    $page = $this->visit($path);
    filtersOpenPage($page, $path, 390, 844);

    $label = "[{$path}] at 390px";
    $toggle = $page->page()->locator('[data-testid="filtros-toggle"]');
    $closed = filtersAudit($page);

    expect($toggle->isVisible())->toBeTrue("{$label}: \"Filtros\" toggle not visible");
    expect($toggle->innerText())->toBe('Filtros');
    expect($closed['visibleSelects'])->toBe(0, "{$label}: a filter select is visible on load");
    expect($closed['scrollWidth'])->toBeLessThanOrEqual($closed['clientWidth'], "{$label}: horizontal overflow with the panel closed");
    expect($closed['firstCardTop'])->not->toBeNull("{$label}: no pedido card rendered");
    expect($closed['firstCardTop'])->toBeLessThan(844, "{$label}: the first pedido card starts below the fold");

    $toggle->click();
    $page->page()->locator('#filtros-painel')->waitFor(['state' => 'visible']);

    $open = filtersAudit($page);
    $small = array_values(array_filter($open['heights'], fn (array $control): bool => $control['height'] < 44));

    expect($toggle->getAttribute('aria-expanded'))->toBe('true');
    expect($small)->toBe([], "{$label}: filter controls below 44 px: ".json_encode($small));
    expect($open['unlabelled'])->toBe([], "{$label}: unlabelled filter controls: ".implode(', ', $open['unlabelled']));
    expect($open['scrollWidth'])->toBeLessThanOrEqual($open['clientWidth'], "{$label}: horizontal overflow with the panel open");

    $statusId = Status::query()->where('slug', 'em_analise')->firstOrFail()->id;
    filtersOpenPage($page, "{$path}?statusId={$statusId}&atrasado=true", 390, 844);

    expect($page->page()->locator('[data-testid="filtros-toggle"]')->innerText())->toBe('Filtros (2)');

    $page->assertNoJavascriptErrors();
})->with('filter listings');
