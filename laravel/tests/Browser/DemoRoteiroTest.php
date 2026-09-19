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
 */
test('the official 19-step demo roteiro completes with persisted state visible on every screen', function () {
    $this->seed(DemoSeeder::class);

    $obraUser = User::query()->where('email', 'obra.demo@example.com')->firstOrFail();
    $obra = $obraUser->obras()->firstOrFail();
    $itemsDescription = 'Cimento CP-II 50kg e areia média — roteiro E2E '.Str::random(6);
    $neededAt = now()->addDays(7)->toDateString();

    // Step 1: autenticar como Obra.
    $page = $this->visit('/login')
        ->type('email', 'obra.demo@example.com')
        ->type('password', 'password')
        ->press('Entrar')
        ->assertPathIs('/home');

    // Step 2: criar uma nova solicitação.
    $page->page()->goto(route('obra.nova-solicitacao'));
    $page->select('obra_id', (string) $obra->id)
        ->type('needed_at', $neededAt)
        ->type('items_description', $itemsDescription)
        ->press('Enviar solicitação')
        ->assertSee('Solicitação criada com sucesso!');

    // Step 3: confirmar persistência (UI-visible generated code matches the persisted row).
    $code = $page->text('[role="status"] strong');
    $pedido = Pedido::query()->where('code', $code)->firstOrFail();
    expect($pedido->obra_id)->toBe($obra->id);
    expect($pedido->items_description)->toBe($itemsDescription);
    expect($pedido->status->slug)->toBe(StatusSlug::Solicitado->value);

    // Step 4: acompanhar pedido.
    $page->page()->goto(route('obra.pedidos.index'));
    $page->assertSee($pedido->code)
        ->assertSee($obra->name)
        ->assertSeeIn('table', 'Solicitado');

    // Step 5: autenticar como Suprimentos (fresh browser context — new session).
    $suprimentosUser = User::query()->where('email', 'suprimentos.demo@example.com')->firstOrFail();
    $sup = $this->visit('/login')
        ->type('email', 'suprimentos.demo@example.com')
        ->type('password', 'password')
        ->press('Entrar')
        ->assertPathIs('/home');

    // Step 6: localizar pedido no Kanban.
    $sup->page()->goto(route('suprimentos.kanban'));
    $sup->assertSee($pedido->code)
        ->assertSeeIn('[data-column="solicitado"]', $pedido->code);

    $sup->page()->goto(route('suprimentos.pedidos.show', $pedido));

    // Step 7: definir responsável.
    $sup->select('responsible_id', (string) $suprimentosUser->id)
        ->press('Salvar responsável')
        ->assertSeeIn('dl', $suprimentosUser->name);

    // Step 8: definir prioridade.
    $urgente = Priority::query()->where('slug', 'urgente')->firstOrFail();
    $sup->select('priority_id', (string) $urgente->id)
        ->press('Salvar prioridade')
        ->assertSeeIn('dl', $urgente->name);

    // Step 9: informar previsão.
    $expectedDeliveryAt = now()->addDays(3)->toDateString();
    $sup->type('expected_delivery_at', $expectedDeliveryAt)
        ->press('Salvar previsão')
        ->assertSeeIn('dl', now()->addDays(3)->format('d/m/Y'));

    // Step 10: mover pelo workflow (non-terminal → non-terminal, RF-13).
    $aguardandoEntrega = Status::query()->where('slug', StatusSlug::AguardandoEntrega->value)->firstOrFail();
    $sup->select('status_id', (string) $aguardandoEntrega->id)
        ->press('Mover status')
        ->assertSeeIn('dl', $aguardandoEntrega->name);

    // Step 11: verificar histórico.
    $sup->assertSee('Histórico');
    expect($pedido->fresh()->events()->count())->toBe(4); // criacao_pedido + 3 alterações
    expect(
        PedidoEvent::query()->where('pedido_id', $pedido->id)
            ->whereHas('eventType', fn ($query) => $query->where('slug', EventTypeSlug::MudancaStatus->value))
            ->exists()
    )->toBeTrue();

    // Step 12: autenticar novamente como Obra e confirmar atualização (fresh session).
    $obraAgain = $this->visit('/login')
        ->type('email', 'obra.demo@example.com')
        ->type('password', 'password')
        ->press('Entrar')
        ->assertPathIs('/home');

    $obraAgain->page()->goto(route('obra.pedidos.show', $pedido));
    $obraAgain->assertSeeIn('dl', $aguardandoEntrega->name)
        ->assertSeeIn('dl', $suprimentosUser->name)
        ->assertSeeIn('dl', $urgente->name)
        ->assertDontSee('editar');

    // Step 13: autenticar como Gestão (fresh session).
    $gestaoUser = User::query()->where('email', 'gestao.demo@example.com')->firstOrFail();
    $gestao = $this->visit('/login')
        ->type('email', 'gestao.demo@example.com')
        ->type('password', 'password')
        ->press('Entrar')
        ->assertPathIs('/home');

    // Step 14: verificar dashboard.
    $gestao->page()->goto(route('gestao.dashboard'));
    $gestao->assertSee('Dashboard')
        ->assertSeeAnythingIn('[data-testid="indicator-volume-total"] [data-value]');
    $pendentesBeforeDelivery = (int) trim($gestao->text('[data-testid="indicator-pendentes"] [data-value]'));

    // Step 15: verificar Kanban read-only.
    $gestao->page()->goto(route('gestao.kanban'));
    $gestao->assertSee($pedido->code)
        ->assertNotPresent('[aria-label^="Mover pedido"]');

    // Step 16: retornar como Suprimentos (fresh session).
    $supAgain = $this->visit('/login')
        ->type('email', 'suprimentos.demo@example.com')
        ->type('password', 'password')
        ->press('Entrar')
        ->assertPathIs('/home');

    $supAgain->page()->goto(route('suprimentos.pedidos.show', $pedido));

    // Step 17: marcar como Entregue.
    $entregue = Status::query()->where('slug', StatusSlug::Entregue->value)->firstOrFail();
    $supAgain->select('status_id', (string) $entregue->id)
        ->press('Mover status')
        ->assertSeeIn('dl', $entregue->name);

    expect($pedido->fresh()->status->slug)->toBe(StatusSlug::Entregue->value);

    // Step 18: confirmar histórico (delivery is recorded as `entrega`, not `mudanca_status`).
    $supAgain->assertSee('Histórico');
    expect(
        PedidoEvent::query()->where('pedido_id', $pedido->id)
            ->whereHas('eventType', fn ($query) => $query->where('slug', EventTypeSlug::Entrega->value))
            ->exists()
    )->toBeTrue();
    expect($pedido->fresh()->events()->count())->toBe(5);

    // Step 19: confirmar atualização dos indicadores.
    $gestaoAgain = $this->visit('/login')
        ->type('email', 'gestao.demo@example.com')
        ->type('password', 'password')
        ->press('Entrar')
        ->assertPathIs('/home');

    $gestaoAgain->page()->goto(route('gestao.dashboard'));
    $pendentesAfterDelivery = (int) trim($gestaoAgain->text('[data-testid="indicator-pendentes"] [data-value]'));

    expect($pendentesAfterDelivery)->toBe($pendentesBeforeDelivery - 1);
});
