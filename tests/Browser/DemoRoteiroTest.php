<?php

use App\Enums\EventTypeSlug;
use App\Enums\StatusSlug;
use App\Models\Obra;
use App\Models\Pedido;
use App\Models\PedidoEvent;
use App\Models\Priority;
use App\Models\Status;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Str;

/**
 * Automates the 19-step official demo roteiro from brief §31 (RF-24, AC-28)
 * against the demo dataset (T46), driving the real Blade/Livewire UI through
 * a headless browser (pestphp/pest-plugin-browser) rather than simulating
 * requests, so drag-free workflow moves, wire:submit round-trips and
 * cross-role visibility are exercised exactly as a demo operator would.
 *
 * Role switches go through the UI logout ("Sair", in the sidebar) followed by a fresh login,
 * exactly as the demo operator does. This is also required by the harness:
 * the plugin serves every request from one in-process Laravel application,
 * so the session guard keeps the previous user resolved across browser
 * contexts until `logout()` clears it — a new `visit('/login')` alone would
 * be redirected away as "already authenticated".
 */
test('the official 19-step demo roteiro completes with persisted state visible on every screen', function () {
    $this->seed(DemoSeeder::class);

    $obraUser = User::query()->where('email', 'obra.demo@example.com')->firstOrFail();
    $obra = $obraUser->obras()->firstOrFail();
    $itemsDescription = 'Cimento CP-II 50kg e areia média — roteiro E2E '.Str::random(6);
    $neededAt = now()->addDays(7)->toDateString();

    $login = function ($page, string $email, string $expectedPath) {
        $page->assertPathIs('/login');

        // The login form is a Livewire component: `wire:model` syncs each input
        // from the (empty) component state in a deferred Alpine effect after
        // boot, so text typed before that flush is wiped. Wait for the binding
        // on the e-mail input and let the pending effect run before typing.
        $page->page()->waitForFunction('() => document.getElementById("email")?._x_model !== undefined');
        $page->page()->evaluate('() => new Promise((resolve) => setTimeout(resolve, 50))');

        return $page
            ->type('email', $email)
            ->type('password', 'password')
            ->press('Entrar')
            ->assertPathIs($expectedPath);
    };

    $logout = fn ($page) => logoutThroughSidebar($page);

    // Step 1: autenticar como Obra (lands on Acompanhamento, the obra home).
    $page = $login($this->visit('/login'), 'obra.demo@example.com', '/obra/pedidos');

    // Step 2: criar uma nova solicitação.
    $page->page()->goto(route('obra.nova-solicitacao'));
    $page->select('obra_selection', (string) $obra->id)
        ->type('needed_at', $neededAt)
        ->type('descricao', $itemsDescription)
        ->press('Enviar solicitação')
        ->assertSee('Solicitação criada com sucesso!');

    // Step 3: confirmar persistência (UI-visible generated code matches the persisted row).
    $code = $page->text('[role="status"] strong');
    $pedido = Pedido::query()->where('code', $code)->firstOrFail();
    expect($pedido->obra_id)->toBe($obra->id);
    expect($pedido->items_description)->toBe($itemsDescription);
    expect($pedido->status->slug)->toBe(StatusSlug::Solicitado->value);

    // Step 4: acompanhar pedido. Scoped selectors must be explicit CSS
    // (`[attr=...]`, `#id`, `.class`, `@data-testid`): the plugin treats a bare
    // word such as `table` or `dl` as *text* to look for, not as a tag. The
    // status assertion is also scoped to the new pedido's own row, because the
    // demo dataset already lists other `Solicitado` pedidos for this obra.
    $page->page()->goto(route('obra.pedidos.index'));
    $page->assertSee($pedido->code)
        ->assertSee($obra->name)
        ->assertSeeIn('tr[data-pedido-code="'.$pedido->code.'"]', 'Solicitado');

    // Step 5: sair e autenticar como Suprimentos (lands on Pedidos, the suprimentos
    // home since navegacao-sidebar-listagens RF-09, then opens the Kanban).
    $suprimentosUser = User::query()->where('email', 'suprimentos.demo@example.com')->firstOrFail();
    $sup = $login($logout($page), 'suprimentos.demo@example.com', '/suprimentos/pedidos');
    $sup->page()->goto(route('suprimentos.kanban'));

    // Step 6: localizar pedido no Kanban.
    $sup->assertSee($pedido->code)
        ->assertSeeIn('[data-column="solicitado"]', $pedido->code);

    $sup->page()->goto(route('suprimentos.pedidos.show', $pedido));

    // Step 7: definir responsável.
    $sup->select('responsible_id', (string) $suprimentosUser->id)
        ->press('Salvar responsável')
        ->assertSeeIn('@pedido-summary', $suprimentosUser->name);

    // Step 8: definir prioridade.
    $urgente = Priority::query()->where('slug', 'urgente')->firstOrFail();
    $sup->select('priority_id', (string) $urgente->id)
        ->press('Salvar prioridade')
        ->assertSeeIn('@pedido-summary', $urgente->name);

    // Step 9: informar previsão.
    $expectedDeliveryAt = now()->addDays(3)->toDateString();
    $sup->type('expected_delivery_at', $expectedDeliveryAt)
        ->press('Salvar previsão')
        ->assertSeeIn('@pedido-summary', now()->addDays(3)->format('d/m/Y'));

    // Step 10: mover pelo workflow (non-terminal → non-terminal, RF-13).
    $aguardandoEntrega = Status::query()->where('slug', StatusSlug::AguardandoEntrega->value)->firstOrFail();
    $sup->select('status_id', (string) $aguardandoEntrega->id)
        ->press('Mover status')
        ->assertSeeIn('@pedido-summary', $aguardandoEntrega->name);

    // Step 11: verificar histórico.
    $sup->assertSee('Histórico');
    // criacao_pedido + responsável + prioridade + previsão + status (RF-18: 1 event per mutation).
    expect($pedido->fresh()->events()->count())->toBe(5);
    expect(
        PedidoEvent::query()->where('pedido_id', $pedido->id)
            ->whereHas('eventType', fn ($query) => $query->where('slug', EventTypeSlug::MudancaStatus->value))
            ->exists()
    )->toBeTrue();

    // Step 12: sair, autenticar novamente como Obra e confirmar atualização.
    $obraAgain = $login($logout($sup), 'obra.demo@example.com', '/obra/pedidos');

    $obraAgain->page()->goto(route('obra.pedidos.show', $pedido));
    $obraAgain->assertSeeIn('@pedido-summary', $aguardandoEntrega->name)
        ->assertSeeIn('@pedido-summary', $suprimentosUser->name)
        ->assertSeeIn('@pedido-summary', $urgente->name)
        ->assertDontSee('editar');

    // Step 13: sair e autenticar como Gestão (lands on Pedidos, the gestao home
    // since navegacao-sidebar-listagens RF-09, then opens the Dashboard).
    $gestao = $login($logout($obraAgain), 'gestao.demo@example.com', '/gestao/pedidos');
    $gestao->page()->goto(route('gestao.dashboard'));

    // Step 14: verificar dashboard.
    $gestao->assertSee('Dashboard')
        ->assertSeeAnythingIn('[data-testid="indicator-volume-total"] [data-value]');
    $pendentesBeforeDelivery = (int) trim($gestao->text('[data-testid="indicator-pendentes"] [data-value]'));

    // Step 15: verificar Kanban read-only.
    $gestao->page()->goto(route('gestao.kanban'));
    $gestao->assertSee($pedido->code)
        ->assertNotPresent('[aria-label^="Mover pedido"]');

    // Step 16: sair e retornar como Suprimentos.
    $supAgain = $login($logout($gestao), 'suprimentos.demo@example.com', '/suprimentos/pedidos');
    $supAgain->page()->goto(route('suprimentos.kanban'));

    $supAgain->page()->goto(route('suprimentos.pedidos.show', $pedido));

    // Step 17: marcar como Entregue.
    $entregue = Status::query()->where('slug', StatusSlug::Entregue->value)->firstOrFail();
    $supAgain->select('status_id', (string) $entregue->id)
        ->press('Mover status')
        ->assertSeeIn('@pedido-summary', $entregue->name);

    expect($pedido->fresh()->status->slug)->toBe(StatusSlug::Entregue->value);

    // Step 18: confirmar histórico (delivery is recorded as `entrega`, not `mudanca_status`).
    $supAgain->assertSee('Histórico');
    expect(
        PedidoEvent::query()->where('pedido_id', $pedido->id)
            ->whereHas('eventType', fn ($query) => $query->where('slug', EventTypeSlug::Entrega->value))
            ->exists()
    )->toBeTrue();
    expect($pedido->fresh()->events()->count())->toBe(6);

    // Step 19: sair, voltar como Gestão e confirmar atualização dos indicadores.
    $gestaoAgain = $login($logout($supAgain), 'gestao.demo@example.com', '/gestao/pedidos');
    $gestaoAgain->page()->goto(route('gestao.dashboard'));
    $pendentesAfterDelivery = (int) trim($gestaoAgain->text('[data-testid="indicator-pendentes"] [data-value]'));

    expect($pendentesAfterDelivery)->toBe($pendentesBeforeDelivery - 1);
});
