<?php

use App\Models\Pedido;
use App\Models\Status;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Pest\Browser\Api\PendingAwaitablePage;

/**
 * navegacao-sidebar-listagens T18 (UI-01, UI-02, UI-03, UI-07, RF-05, RF-07,
 * RNF-02): the sidebar in a real browser, per papel, on its Pedidos landing.
 *
 * - From `lg` up (1440×900) the sidebar is always visible, the "Menu" button
 *   is hidden, the surface is white and "+ Nova Solicitação" stays reachable
 *   at the bottom of a long listing (the sidebar is sticky).
 * - Below `lg` (390×844) the sidebar is a drawer behind "Menu": the top bar
 *   carries "+ Nova Solicitação", Enter opens, Escape and "Fechar menu"
 *   close and return focus to "Menu", following a link closes it.
 * - No horizontal overflow at the three reference viewports, drawer open or
 *   closed, and every sidebar link shows a focus ring.
 *
 * Each dataset row is a fresh test, logged in with `actingAs`; role switches
 * inside a test would need the UI logout (see `DemoRoteiroTest`).
 */
dataset('sidebar papéis', [
    'Obra' => ['obra.demo@example.com', '/obra/pedidos', true],
    'Suprimentos' => ['suprimentos.demo@example.com', '/suprimentos/pedidos', true],
    'Gestão' => ['gestao.demo@example.com', '/gestao/pedidos', false],
]);

dataset('sidebar viewports', [
    'desktop (1440×900)' => [1440, 900],
    'tablet (820×1180)' => [820, 1180],
    'mobile (390×844)' => [390, 844],
]);

/**
 * Resizes, navigates to `$path` and waits until Livewire and Alpine have
 * booted (the drawer state lives in the `x-data` of `<body>`).
 */
function sidebarOpenPage(PendingAwaitablePage $page, string $path, int $width, int $height): void
{
    $page->resize($width, $height);
    $page->page()->goto(url($path));
    $page->page()->waitForFunction('() => document.readyState === "complete" && window.Livewire !== undefined && document.body._x_dataStack !== undefined');
}

/**
 * Overflow of the document and focus ring of every sidebar link that is
 * rendered right now.
 *
 * @return array{scrollWidth: int, clientWidth: int, links: int, withoutFocus: list<string>}
 */
function sidebarAudit(PendingAwaitablePage $page): array
{
    $page->page()->locator('body')->press('Tab');

    return $page->script(<<<'JS'
        () => {
            const root = document.documentElement;
            const withoutFocus = [];
            let links = 0;
            for (const el of document.querySelectorAll('#sidebar a[href], #sidebar button')) {
                if (el.getClientRects().length === 0) continue;
                links++;
                el.focus();
                if (document.activeElement !== el) continue;
                const cs = getComputedStyle(el);
                const outline = cs.outlineStyle !== 'none' ? parseFloat(cs.outlineWidth) : 0;
                let ring = 0;
                for (const match of cs.boxShadow.matchAll(/((?:rgba?|oklab|oklch|color)\([^)]*\)|#[0-9a-f]+|[a-z]+)\s+0px 0px 0px (\d+(?:\.\d+)?)px/gi)) {
                    if (/^rgba\(0, 0, 0, 0\)$/.test(match[1]) || match[1] === 'transparent') continue;
                    ring = Math.max(ring, parseFloat(match[2]));
                }
                if (Math.max(outline, ring) < 2) withoutFocus.push(`${el.textContent.trim()} box-shadow=${cs.boxShadow}`);
            }
            if (document.activeElement instanceof HTMLElement) document.activeElement.blur();
            return { scrollWidth: root.scrollWidth, clientWidth: root.clientWidth, links, withoutFocus };
        }
        JS);
}

/**
 * `data-testid` of the focused element once the focus leaves both `<body>`
 * and the drawer; the drawer returns the focus to "Menu" on Alpine's next
 * tick (right after a click the close button still holds it), so the check
 * polls for up to one second instead of reading it once.
 */
function sidebarFocusedTestId(PendingAwaitablePage $page): ?string
{
    return $page->script(<<<'JS'
        () => new Promise((resolve) => {
            const started = performance.now();
            const poll = () => {
                const focused = document.activeElement;
                const settled = focused && focused !== document.body && ! document.getElementById('sidebar')?.contains(focused);
                if (settled || performance.now() - started > 1000) {
                    resolve(focused?.getAttribute('data-testid') ?? null);
                    return;
                }
                requestAnimationFrame(poll);
            };
            poll();
        })
        JS);
}

test('at 1440×900 the sidebar is visible without interaction, white, and "Sair" leads to /login', function (string $email, string $landing, bool $hasNovaSolicitacao) {
    $this->seed(DemoSeeder::class);
    $this->actingAs(User::query()->where('email', $email)->firstOrFail());

    $page = $this->visit($landing);
    sidebarOpenPage($page, $landing, 1440, 900);

    expect($page->page()->locator('#sidebar nav')->isVisible())->toBeTrue();
    expect($page->page()->locator('[data-testid="menu-toggle"]')->isVisible())->toBeFalse();
    expect($page->script("() => getComputedStyle(document.getElementById('sidebar')).backgroundColor"))->toBe('rgb(255, 255, 255)');
    expect($page->page()->locator('#sidebar [data-testid="sidebar-nova-solicitacao"]')->isVisible())->toBe($hasNovaSolicitacao);

    logoutThroughSidebar($page);
    $page->assertNoJavascriptErrors();
})->with('sidebar papéis');

test('at 1440×900 "+ Nova Solicitação" stays in the viewport at the bottom of a long Suprimentos listing and opens the form', function () {
    $this->seed(DemoSeeder::class);
    $suprimentos = User::query()->where('email', 'suprimentos.demo@example.com')->firstOrFail();
    $this->actingAs($suprimentos);

    Pedido::factory()->count(12)->create([
        'status_id' => Status::query()->where('slug', 'solicitado')->firstOrFail()->id,
    ]);

    expect(Pedido::query()->count())->toBeGreaterThan(10);

    $page = $this->visit('/suprimentos/pedidos');
    sidebarOpenPage($page, '/suprimentos/pedidos', 1440, 900);

    $geometry = $page->script(<<<'JS'
        () => {
            window.scrollTo(0, document.body.scrollHeight);
            const rect = document.querySelector('[data-testid="sidebar-nova-solicitacao"]').getBoundingClientRect();
            return { scrollY: window.scrollY, top: rect.top, bottom: rect.bottom, left: rect.left, right: rect.right, height: window.innerHeight, width: window.innerWidth };
        }
        JS);

    expect($geometry['scrollY'])->toBeGreaterThan(0, 'the listing must be taller than the viewport');
    expect($geometry['bottom'] > 0 && $geometry['top'] < $geometry['height'] && $geometry['right'] > 0 && $geometry['left'] < $geometry['width'])
        ->toBeTrue('"+ Nova Solicitação" must intersect the viewport after scrolling to the end: '.json_encode($geometry));

    $page->page()->locator('[data-testid="sidebar-nova-solicitacao"]')->click();

    $page->assertPathIs('/suprimentos/nova-solicitacao');
    $page->page()->locator('main form #descricao')->waitFor(['state' => 'visible']);
    $page->assertNoJavascriptErrors();
});

test('at 390×844 the sidebar is a keyboard-operable drawer and the top bar carries "+ Nova Solicitação"', function (string $email, string $landing, bool $hasNovaSolicitacao) {
    $this->seed(DemoSeeder::class);
    $this->actingAs(User::query()->where('email', $email)->firstOrFail());

    $page = $this->visit($landing);
    sidebarOpenPage($page, $landing, 390, 844);

    $toggle = $page->page()->locator('[data-testid="menu-toggle"]');
    $nav = $page->page()->locator('#sidebar nav');

    expect($nav->isVisible())->toBeFalse();
    expect($toggle->isVisible())->toBeTrue();

    if ($hasNovaSolicitacao) {
        $topBarAction = $page->page()->locator('[data-testid="topbar-nova-solicitacao"]');

        expect($topBarAction->isVisible())->toBeTrue();

        $box = $topBarAction->boundingBox();

        expect($box['x'])->toBeGreaterThanOrEqual(0);
        expect($box['x'] + $box['width'])->toBeLessThanOrEqual(390);
        expect($box['y'] + $box['height'])->toBeLessThanOrEqual(844);
    } else {
        expect($page->page()->locator('[data-testid="topbar-nova-solicitacao"]')->count())->toBe(0);
    }

    // Enter on "Menu" opens the drawer; Escape closes it and returns the focus.
    $toggle->focus();
    $toggle->press('Enter');
    $nav->waitFor(['state' => 'visible']);

    expect($toggle->getAttribute('aria-expanded'))->toBe('true');

    $toggle->press('Escape');
    $nav->waitFor(['state' => 'hidden']);

    expect($toggle->getAttribute('aria-expanded'))->toBe('false');
    expect(sidebarFocusedTestId($page))->toBe('menu-toggle');

    // "Fechar menu" closes it and returns the focus too.
    openSidebarIfCollapsed($page);
    $page->page()->locator('#sidebar button[aria-label="Fechar menu"]')->click();
    $nav->waitFor(['state' => 'hidden']);

    expect($toggle->getAttribute('aria-expanded'))->toBe('false');
    expect(sidebarFocusedTestId($page))->toBe('menu-toggle');

    // Following a link navigates, and the new page starts with the drawer closed.
    openSidebarIfCollapsed($page);
    $link = $page->page()->locator('#sidebar nav a.sidebar-link')->last();
    $target = (string) parse_url((string) $link->getAttribute('href'), PHP_URL_PATH);
    $link->click();

    $page->assertPathIs($target);
    $page->page()->locator('#sidebar nav')->waitFor(['state' => 'hidden']);
    $page->page()->waitForFunction('() => document.readyState === "complete" && window.Livewire !== undefined && document.body._x_dataStack !== undefined');

    expect($page->page()->locator('#sidebar nav')->isVisible())->toBeFalse();
    expect($page->page()->locator('[data-testid="menu-toggle"]')->getAttribute('aria-expanded'))->toBe('false');

    // The top-bar "+ Nova Solicitação" opens the form.
    if ($hasNovaSolicitacao) {
        $page->page()->locator('[data-testid="topbar-nova-solicitacao"]')->click();
        $page->assertPathIs(str_replace('/pedidos', '/nova-solicitacao', $landing));
        $page->page()->locator('main form #descricao')->waitFor(['state' => 'visible']);
    }

    // "Sair" is reachable once the drawer is open: openSidebarIfCollapsed()
    // waits for it to be visible before the logout clicks it.
    logoutThroughSidebar($page);
    $page->assertNoJavascriptErrors();
})->with('sidebar papéis');

test('the layout never overflows horizontally, drawer open or closed, and every sidebar link shows a focus ring', function (int $width, int $height) {
    $this->seed(DemoSeeder::class);

    $landing = '/suprimentos/pedidos';
    $this->actingAs(User::query()->where('email', 'suprimentos.demo@example.com')->firstOrFail());

    $page = $this->visit($landing);
    sidebarOpenPage($page, $landing, $width, $height);

    $closed = sidebarAudit($page);

    expect($closed['scrollWidth'])->toBeLessThanOrEqual($closed['clientWidth'], "[{$landing}] at {$width}px, menu closed: scrollWidth {$closed['scrollWidth']} > clientWidth {$closed['clientWidth']}");

    openSidebarIfCollapsed($page);

    $open = sidebarAudit($page);

    expect($open['scrollWidth'])->toBeLessThanOrEqual($open['clientWidth'], "[{$landing}] at {$width}px, menu open: scrollWidth {$open['scrollWidth']} > clientWidth {$open['clientWidth']}");
    expect($open['links'])->toBeGreaterThanOrEqual(7, "[{$landing}] at {$width}px: sidebar links not rendered");
    expect($open['withoutFocus'])->toBe([], "[{$landing}] at {$width}px: sidebar controls without a visible focus ring ≥ 2px: ".implode(' | ', $open['withoutFocus']));

    $page->assertNoJavascriptErrors();
})->with('sidebar viewports');
