<?php

use App\Models\User;
use Database\Seeders\DemoSeeder;
use Pest\Browser\Api\PendingAwaitablePage;

/**
 * UI-21 / UI-22 (§34, §35): the Albuquerque identity works at desktop,
 * tablet and mobile widths without breaking the existing responsiveness or
 * accessibility. Every screen is rendered in a real headless browser at the
 * three reference viewports and checked for: no horizontal overflow of the
 * document, the primary control reachable inside the viewport width, no
 * JavaScript errors, every `<input>` associated to a `<label for>` (selects
 * and textareas labelled by `for`, wrapping label or `aria-label`), and a
 * visible focus indicator ≥ 2px (token ring via box-shadow or outline) on
 * every focusable control.
 *
 * Contrast figures (WCAG 2.1, recorded for the §46 review — `visual-review.md`):
 * `#FFFFFF` on `#9E0128` = 8.4:1; `#202124` on `#FFFFFF` = 16.1:1;
 * `#6B7280` on `#FFFFFF` = 4.8:1 — all ≥ 4.5:1 (AA).
 */
dataset('viewports', [
    'desktop ≥1280px (1440×900)' => [1440, 900],
    'tablet 768–1024px (820×1180)' => [820, 1180],
    'mobile ≤414px (390×844)' => [390, 844],
]);

/**
 * JS audit run inside the page: overflow, primary control bounds, labels and
 * focus visibility. `el.focus()` after a keyboard Tab keeps the browser in
 * keyboard modality, so `:focus-visible` rules apply exactly as for a user
 * tabbing through the screen.
 */
const RESPONSIVE_AUDIT_SCRIPT = <<<'JS'
    ((primarySelector) => {
        const root = document.documentElement;
        const isRendered = (el) => el.getClientRects().length > 0 && getComputedStyle(el).visibility !== 'hidden';
        const describe = (el) => `<${el.tagName.toLowerCase()}${el.id ? '#' + el.id : ''}${el.className ? '.' + String(el.className).trim().split(/\s+/).join('.') : ''}>`;

        const primary = document.querySelector(primarySelector);
        const primaryRect = primary ? primary.getBoundingClientRect() : null;

        const unlabelled = [];
        for (const el of document.querySelectorAll('input:not([type="hidden"])')) {
            if (!isRendered(el)) continue;
            const hasLabelFor = el.id !== '' && document.querySelector(`label[for="${CSS.escape(el.id)}"]`) !== null;
            if (!hasLabelFor) unlabelled.push(describe(el));
        }
        for (const el of document.querySelectorAll('select, textarea')) {
            if (!isRendered(el)) continue;
            if (el.labels.length === 0 && !el.getAttribute('aria-label')) unlabelled.push(describe(el));
        }

        const ringWidth = (boxShadow) => {
            let max = 0;
            for (const match of boxShadow.matchAll(/(?:^|,)\s*((?:rgba?|oklab|oklch|color)\([^)]*\)|#[0-9a-f]+|[a-z]+)\s+0px 0px 0px (\d+(?:\.\d+)?)px/gi)) {
                const color = match[1];
                const width = parseFloat(match[2]);
                if (/^rgba\(0, 0, 0, 0\)$/.test(color) || color === 'transparent') continue;
                max = Math.max(max, width);
            }
            return max;
        };

        const withoutFocus = [];
        for (const el of document.querySelectorAll('a[href], button, input:not([type="hidden"]), select, textarea')) {
            if (!isRendered(el) || el.disabled) continue;
            el.focus();
            if (document.activeElement !== el) continue;
            const cs = getComputedStyle(el);
            const outline = cs.outlineStyle !== 'none' ? parseFloat(cs.outlineWidth) : 0;
            const ring = ringWidth(cs.boxShadow);
            if (Math.max(outline, ring) < 2) {
                withoutFocus.push(`${describe(el)} outline=${cs.outlineStyle} ${cs.outlineWidth} box-shadow=${cs.boxShadow}`);
            }
        }
        if (document.activeElement instanceof HTMLElement) document.activeElement.blur();

        return {
            scrollWidth: root.scrollWidth,
            clientWidth: root.clientWidth,
            primaryFound: primary !== null,
            primaryRendered: primary !== null && isRendered(primary),
            primaryLeft: primaryRect ? Math.round(primaryRect.left) : null,
            primaryRight: primaryRect ? Math.round(primaryRect.right) : null,
            unlabelled,
            withoutFocus,
        };
    })
    JS;

/**
 * Renders the given screen at the viewport and asserts the UI-21/UI-22 rules.
 */
function assertResponsiveAndAccessible(PendingAwaitablePage $page, string $path, int $width, int $height, string $primarySelector): void
{
    $page->resize($width, $height);
    $page->page()->goto(url($path));
    $page->page()->waitForFunction('() => window.Livewire !== undefined');
    $page->page()->locator('body')->press('Tab');

    $audit = $page->script(RESPONSIVE_AUDIT_SCRIPT."('".addslashes($primarySelector)."')");

    expect($audit['scrollWidth'])
        ->toBeLessThanOrEqual($audit['clientWidth'], "[{$path}] at {$width}px overflows horizontally: scrollWidth {$audit['scrollWidth']} > clientWidth {$audit['clientWidth']}");

    expect($audit['primaryFound'])->toBeTrue("[{$path}] at {$width}px: primary control [{$primarySelector}] not found");
    expect($audit['primaryRendered'])->toBeTrue("[{$path}] at {$width}px: primary control [{$primarySelector}] is not rendered");
    expect($audit['primaryLeft'])->toBeGreaterThanOrEqual(0, "[{$path}] at {$width}px: primary control starts outside the viewport");
    expect($audit['primaryRight'])->toBeLessThanOrEqual($audit['clientWidth'], "[{$path}] at {$width}px: primary control ends outside the viewport");

    expect($audit['unlabelled'])->toBe([], "[{$path}] at {$width}px: form controls without an associated label: ".implode(', ', $audit['unlabelled']));
    expect($audit['withoutFocus'])->toBe([], "[{$path}] at {$width}px: focusable controls without a visible focus indicator ≥ 2px: ".implode(' | ', $audit['withoutFocus']));

    $page->assertNoJavascriptErrors();
}

test('login and esqueci-senha fit the viewport with labelled inputs and visible focus', function (int $width, int $height) {
    $page = $this->visit('/login');

    assertResponsiveAndAccessible($page, '/login', $width, $height, 'button[type="submit"]');
    $page->assertSee('Entrar no sistema')->assertSee('Esqueci minha senha');

    assertResponsiveAndAccessible($page, '/esqueci-senha', $width, $height, 'button[type="submit"]');
    $page->assertSee('Esqueci minha senha')->assertSee('Voltar ao login');
})->with('viewports');

test('the suprimentos kanban fits the viewport with reachable move controls', function (int $width, int $height) {
    $this->seed(DemoSeeder::class);
    $this->actingAs(User::query()->where('email', 'suprimentos.demo@example.com')->firstOrFail());

    $page = $this->visit('/suprimentos/kanban');

    assertResponsiveAndAccessible($page, '/suprimentos/kanban', $width, $height, '[data-column="solicitado"] [aria-label^="Mover pedido"]');
    $page->assertSee('Kanban')->assertPresent('[data-testid="kanban-column"]');
})->with('viewports');

test('the gestao dashboard, users listing and user form fit the viewport with labelled controls', function (int $width, int $height) {
    $this->seed(DemoSeeder::class);
    $this->actingAs(User::query()->where('email', 'gestao.demo@example.com')->firstOrFail());

    // 15 per page: extra users make the listing paginate, so the pagination
    // controls (§27 "paginação") go through the same focus/label audit.
    User::factory()->count(15)->suprimentos()->create();

    $page = $this->visit('/gestao/dashboard');

    assertResponsiveAndAccessible($page, '/gestao/dashboard', $width, $height, '[data-testid="indicator-pendentes"] a');
    $page->assertSee('Dashboard')->assertSeeAnythingIn('[data-testid="indicator-volume-total"] [data-value]');

    assertResponsiveAndAccessible($page, '/gestao/usuarios', $width, $height, 'a[href$="/gestao/usuarios/novo"]');
    $page->assertSee('Usuários')->assertSee('Novo usuário')->assertPresent('nav[aria-label="Paginação"]');

    assertResponsiveAndAccessible($page, '/gestao/usuarios/novo', $width, $height, 'button[type="submit"]');
    $page->assertSee('Novo usuário')->assertSee('Criar usuário');
})->with('viewports');
