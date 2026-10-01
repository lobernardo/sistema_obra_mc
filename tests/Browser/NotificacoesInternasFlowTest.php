<?php

use App\Actions\Pedidos\UpdatePedidoStatusAction;
use App\Models\InternalNotification;
use App\Models\Pedido;
use App\Models\User;
use Pest\Browser\Api\PendingAwaitablePage;

/**
 * notificacoes-internas T17 (UI-02, UI-03, UI-05, UI-06, UI-07, UI-10): the
 * bell and the Notificações Internas page in a real browser.
 *
 * - A status change made by Suprimentos (the real `UpdatePedidoStatusAction`)
 *   reaches Gestão: the badge counts it, the page lists it, and opening it —
 *   from the page or from the bell panel — marks it as read before landing on
 *   `/gestao/pedidos/{id}`.
 * - "Marcar todas como lidas" removes the badge; Escape closes the panel and
 *   returns the focus to the bell; at 390×844 the badge sits in the top bar.
 * - `refreshIfVisible()` issues no `/livewire/update` while the tab is
 *   (simulated as) hidden and exactly one while visible, which also brings a
 *   notification created on the server into the badge.
 *
 * Each test logs in with `actingAs`; role switches would need the UI logout
 * (see `DemoRoteiroTest`).
 */
beforeEach(function () {
    $this->statuses = seedWorkflowStatuses();
    $this->eventTypes = seedHistoryEventTypes();
    $this->suprimentos = User::factory()->suprimentos()->create(['name' => 'Suprimentos Teste']);
    $this->gestao = User::factory()->gestao()->create(['name' => 'Gestão Teste']);
});

/**
 * A pedido moved by Suprimentos to "Em análise" through the real Action, so
 * Gestão receives exactly one notification for it.
 */
function notificacoesFlowStatusChange(): Pedido
{
    $pedido = Pedido::factory()->create(['status_id' => test()->statuses['solicitado']->id]);

    app(UpdatePedidoStatusAction::class)->execute(test()->suprimentos, $pedido->fresh(), test()->statuses['em_analise']->id);

    return $pedido;
}

/**
 * Polls `$condition` (a JavaScript expression) in the page until it is true,
 * for up to 10 s. `Page::waitForFunction` sends an arrow function as a plain
 * expression, which is truthy at once, so it cannot be used to wait. Each
 * poll is a `script()` call, which also lets the in-process server answer the
 * requests the page is waiting for; a navigation in the middle of a poll only
 * makes it retry.
 */
function notificacoesFlowWaitUntil(PendingAwaitablePage $page, string $condition): void
{
    $deadline = microtime(true) + 10;

    do {
        try {
            $satisfied = $page->script(<<<JS
                () => new Promise((resolve) => {
                    const started = performance.now();
                    const poll = () => {
                        let satisfied = false;
                        try { satisfied = Boolean({$condition}); } catch (error) {}
                        if (satisfied || performance.now() - started > 500) {
                            resolve(satisfied);
                            return;
                        }
                        setTimeout(poll, 25);
                    };
                    poll();
                })
                JS);
        } catch (Throwable) {
            $satisfied = false;
        }

        if ($satisfied === true) {
            return;
        }
    } while (microtime(true) < $deadline);

    throw new RuntimeException("Timed out waiting for: {$condition}");
}

/**
 * Opens `$path` and waits until Livewire and Alpine have booted the bell (a
 * click before Alpine starts is lost). Only a non-default viewport navigates
 * a second time, after the resize.
 */
function notificacoesFlowOpen(PendingAwaitablePage $page, string $path, ?int $width = null, ?int $height = null): void
{
    if ($width !== null && $height !== null) {
        $page->resize($width, $height);
        $page->page()->goto(url($path));
    }

    notificacoesFlowWaitForPath($page, $path);
}

/**
 * Waits until the browser has landed on `$path` and the new page has booted
 * (the previous page also has a bell, so the path is what tells them apart).
 */
function notificacoesFlowWaitForPath(PendingAwaitablePage $page, string $path): void
{
    notificacoesFlowWaitUntil($page, 'window.location.pathname === '.json_encode($path).' && document.readyState === "complete" && window.Livewire !== undefined && window.Alpine !== undefined && document.querySelector(\'[data-testid="notificacoes-sino"]\')?._x_dataStack !== undefined');
}

/**
 * Waits until the bell announces `$unread` unread notifications, badge
 * included (none at 0).
 */
function notificacoesFlowWaitForBadge(PendingAwaitablePage $page, int $unread): void
{
    $badge = $unread === 0 ? 'null' : json_encode($unread > 9 ? '9+' : (string) $unread);

    notificacoesFlowWaitUntil($page, 'document.querySelector(\'[data-testid="notificacoes-sino-botao"]\')?.getAttribute("aria-label") === '.json_encode("Notificações, {$unread} não lidas").' && (document.querySelector(\'[data-testid="notificacoes-badge"]\')?.textContent.trim() ?? null) === '.$badge);
}

function notificacoesFlowBadge(PendingAwaitablePage $page): ?string
{
    return $page->script('() => document.querySelector(\'[data-testid="notificacoes-badge"]\')?.textContent.trim() ?? null');
}

/**
 * Counts every `/livewire/update` request the page sends from now on.
 */
function notificacoesFlowCountUpdates(PendingAwaitablePage $page): void
{
    $page->script(<<<'JS'
        () => {
            window.__livewireUpdates = 0;
            const originalFetch = window.fetch;
            window.fetch = (...args) => {
                const target = args[0] instanceof Request ? args[0].url : String(args[0]);
                if (/livewire[^/]*\/update/.test(target)) window.__livewireUpdates++;
                return originalFetch(...args);
            };
        }
        JS);
}

/**
 * Calls the bell's `refreshIfVisible()` with `document.visibilityState`
 * forced to `$state`.
 */
function notificacoesFlowRefreshAs(PendingAwaitablePage $page, string $state): void
{
    $page->script(<<<JS
        () => {
            Object.defineProperty(document, 'visibilityState', { configurable: true, get: () => '{$state}' });
            window.Alpine.\$data(document.querySelector('[data-testid="notificacoes-sino"]')).refreshIfVisible();
        }
        JS);
}

test('a status change by Suprimentos reaches Gestão: badge, page item, and opening it from the page marks it read (UI-02, UI-05)', function () {
    $pedido = notificacoesFlowStatusChange();
    $other = notificacoesFlowStatusChange();
    $notification = InternalNotification::query()->where('recipient_id', $this->gestao->id)->where('pedido_id', $pedido->id)->firstOrFail();

    expect(InternalNotification::query()->where('recipient_id', $this->gestao->id)->count())->toBe(2);

    $this->actingAs($this->gestao);

    $page = $this->visit('/notificacoes');
    notificacoesFlowOpen($page, '/notificacoes');

    expect(notificacoesFlowBadge($page))->toBe('2');
    $page->assertAttribute('[data-testid="notificacoes-sino-botao"]', 'aria-label', 'Notificações, 2 não lidas');

    $item = $page->page()->locator('[data-testid="notificacao"][data-notification-id="'.$notification->id.'"]');

    expect($item->textContent())->toContain($pedido->code)
        ->toContain('Não lida');

    $item->locator('[data-testid="abrir-notificacao"]')->click();
    notificacoesFlowWaitForPath($page, '/gestao/pedidos/'.$pedido->id);
    notificacoesFlowWaitForBadge($page, 1);

    expect($notification->fresh()->read_at)->not->toBeNull()
        ->and($other->fresh())->not->toBeNull()
        ->and(notificacoesFlowBadge($page))->toBe('1');

    $page->page()->goto(url('/notificacoes'));
    notificacoesFlowWaitForPath($page, '/notificacoes');
    notificacoesFlowWaitForBadge($page, 1);

    expect(notificacoesFlowBadge($page))->toBe('1');
    expect($page->page()->locator('[data-testid="notificacao"][data-notification-id="'.$notification->id.'"]')->textContent())->not->toContain('Não lida');

    $page->assertNoJavascriptErrors();
});

test('opening an item of the bell panel marks it read and lands on the Gestão detail (UI-02, UI-06)', function () {
    $pedido = notificacoesFlowStatusChange();
    $notification = InternalNotification::query()->where('recipient_id', $this->gestao->id)->firstOrFail();

    $this->actingAs($this->gestao);

    $page = $this->visit('/gestao/pedidos');
    notificacoesFlowOpen($page, '/gestao/pedidos');

    expect(notificacoesFlowBadge($page))->toBe('1');

    $page->page()->locator('[data-testid="notificacoes-sino-botao"]')->click();

    $panelItem = $page->page()->locator('[data-testid="sino-notificacao"][data-notification-id="'.$notification->id.'"]');
    $panelItem->waitFor(['state' => 'visible']);

    expect($panelItem->textContent())->toContain($pedido->code);

    $panelItem->click();
    notificacoesFlowWaitForPath($page, '/gestao/pedidos/'.$pedido->id);
    notificacoesFlowWaitForBadge($page, 0);

    expect($notification->fresh()->read_at)->not->toBeNull()
        ->and(notificacoesFlowBadge($page))->toBeNull();
    $page->assertAttribute('[data-testid="notificacoes-sino-botao"]', 'aria-label', 'Notificações, 0 não lidas');

    $page->page()->locator('[data-testid="notificacoes-sino-botao"]')->click();
    $page->page()->getByText('Nenhuma notificação nova.')->waitFor(['state' => 'visible']);
    expect($page->page()->locator('[data-testid="sino-ver-todas"]')->getAttribute('href'))->toBe(url('/notificacoes'));

    $page->assertNoJavascriptErrors();
});

test('"Marcar todas como lidas" on the page removes the badge (UI-03)', function () {
    notificacoesFlowStatusChange();
    notificacoesFlowStatusChange();
    notificacoesFlowStatusChange();

    $this->actingAs($this->gestao);

    $page = $this->visit('/notificacoes');
    notificacoesFlowOpen($page, '/notificacoes');

    expect(notificacoesFlowBadge($page))->toBe('3');

    $page->page()->locator('[data-testid="marcar-todas-lidas"]')->click();
    notificacoesFlowWaitForBadge($page, 0);
    notificacoesFlowWaitUntil($page, 'document.querySelector(\'[data-field="nao-lida"]\') === null');

    expect($page->page()->locator('[data-field="nao-lida"]')->count())->toBe(0);
    $page->assertAttribute('[data-testid="notificacoes-sino-botao"]', 'aria-label', 'Notificações, 0 não lidas');
    expect(InternalNotification::query()->where('recipient_id', $this->gestao->id)->whereNull('read_at')->count())->toBe(0);

    $page->assertNoJavascriptErrors();
});

test('the bell panel opens, Escape closes it and the focus returns to the bell (UI-10)', function () {
    notificacoesFlowStatusChange();

    $this->actingAs($this->gestao);

    $page = $this->visit('/gestao/pedidos');
    notificacoesFlowOpen($page, '/gestao/pedidos');

    $panel = $page->page()->locator('[data-testid="notificacoes-painel"]');
    $bell = $page->page()->locator('[data-testid="notificacoes-sino-botao"]');

    expect($panel->isVisible())->toBeFalse();

    $bell->focus();
    $bell->press('Enter');
    $panel->waitFor(['state' => 'visible']);
    $page->page()->locator('[data-testid="sino-notificacao"]')->first()->waitFor(['state' => 'visible']);

    expect($bell->getAttribute('aria-expanded'))->toBe('true');

    $page->page()->locator('[data-testid="sino-notificacao"]')->first()->focus();
    $page->page()->locator('body')->press('Escape');
    $panel->waitFor(['state' => 'hidden']);

    $focused = $page->script(<<<'JS'
        () => new Promise((resolve) => {
            const started = performance.now();
            const poll = () => {
                const testId = document.activeElement?.getAttribute('data-testid') ?? null;
                if (testId === 'notificacoes-sino-botao' || performance.now() - started > 1000) {
                    resolve(testId);
                    return;
                }
                requestAnimationFrame(poll);
            };
            poll();
        })
        JS);

    expect($focused)->toBe('notificacoes-sino-botao')
        ->and($bell->getAttribute('aria-expanded'))->toBe('false')
        ->and($panel->getAttribute('data-open'))->not->toBe('true');

    $page->assertNoJavascriptErrors();
});

test('at 390×844 the badge is shown in the top bar, inside the viewport (UI-05)', function () {
    notificacoesFlowStatusChange();
    notificacoesFlowStatusChange();

    $this->actingAs($this->gestao);

    $page = $this->visit('/gestao/pedidos');
    notificacoesFlowOpen($page, '/gestao/pedidos', 390, 844);

    $geometry = $page->script(<<<'JS'
        () => {
            const header = document.querySelector('header').getBoundingClientRect();
            const bell = document.querySelector('[data-testid="notificacoes-sino-botao"]').getBoundingClientRect();
            const badge = document.querySelector('[data-testid="notificacoes-badge"]');
            const menu = document.querySelector('[data-testid="menu-toggle"]').getBoundingClientRect();
            const action = document.querySelector('[data-testid="topbar-nova-solicitacao"]').getBoundingClientRect();
            return {
                headerTop: header.top,
                headerBottom: header.bottom,
                bellTop: bell.top,
                bellBottom: bell.bottom,
                bellLeft: bell.left,
                bellRight: bell.right,
                badgeVisible: badge !== null && badge.getClientRects().length > 0,
                menuLeft: menu.left,
                actionRight: action.right,
                clientWidth: document.documentElement.clientWidth,
                scrollWidth: document.documentElement.scrollWidth,
            };
        }
        JS);

    expect($geometry['badgeVisible'])->toBeTrue()
        ->and(notificacoesFlowBadge($page))->toBe('2')
        ->and($geometry['bellTop'])->toBeGreaterThanOrEqual($geometry['headerTop'])
        ->and($geometry['bellBottom'])->toBeLessThanOrEqual($geometry['headerBottom'])
        ->and($geometry['bellLeft'])->toBeGreaterThanOrEqual($geometry['actionRight'])
        ->and($geometry['bellRight'])->toBeLessThanOrEqual($geometry['menuLeft'])
        ->and($geometry['scrollWidth'])->toBeLessThanOrEqual($geometry['clientWidth']);

    $page->assertNoJavascriptErrors();
});

test('refreshIfVisible sends nothing while hidden, one update while visible, and brings a new notification into the badge (UI-07)', function () {
    notificacoesFlowStatusChange();

    $this->actingAs($this->gestao);

    $page = $this->visit('/gestao/pedidos');
    notificacoesFlowOpen($page, '/gestao/pedidos');

    expect(notificacoesFlowBadge($page))->toBe('1');

    notificacoesFlowCountUpdates($page);

    notificacoesFlowRefreshAs($page, 'hidden');
    $page->wait(1);

    expect($page->script('() => window.__livewireUpdates'))->toBe(0);

    notificacoesFlowStatusChange();

    expect(InternalNotification::query()->where('recipient_id', $this->gestao->id)->whereNull('read_at')->count())->toBe(2)
        ->and(notificacoesFlowBadge($page))->toBe('1');

    notificacoesFlowRefreshAs($page, 'visible');
    notificacoesFlowWaitForBadge($page, 2);

    expect($page->script('() => window.__livewireUpdates'))->toBe(1);
    $page->assertAttribute('[data-testid="notificacoes-sino-botao"]', 'aria-label', 'Notificações, 2 não lidas');

    $page->assertNoJavascriptErrors();
});
