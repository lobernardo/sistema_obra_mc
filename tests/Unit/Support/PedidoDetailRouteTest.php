<?php

use App\Models\Pedido;
use App\Models\User;
use App\Support\PedidoDetailRoute;

/**
 * T05 — RF-15, UI-02: the pedido detail route of each papel and its
 * absolute link anchored on `APP_URL`.
 */
beforeEach(function () {
    $this->status = seedWorkflowStatuses()['solicitado'];
});

test('each papel maps to its own pedido detail route; an unknown papel has none', function (string $papel, ?string $route) {
    expect(PedidoDetailRoute::nameFor(userForPapel($papel)))->toBe($route);
})->with([
    'obra' => ['obra', 'obra.pedidos.show'],
    'suprimentos' => ['suprimentos', 'suprimentos.pedidos.show'],
    'gestao' => ['gestao', 'gestao.pedidos.show'],
    'sem papel' => ['sem papel', null],
]);

test('the absolute link is anchored on APP_URL with the route of the recipient papel (RF-15)', function () {
    config(['app.url' => 'https://exemplo.test']);
    $pedido = Pedido::factory()->create(['status_id' => $this->status->id]);

    expect(PedidoDetailRoute::absoluteUrlFor(User::factory()->obra()->create(), $pedido))
        ->toBe("https://exemplo.test/obra/pedidos/{$pedido->id}");
    expect(PedidoDetailRoute::absoluteUrlFor(User::factory()->suprimentos()->create(), $pedido))
        ->toBe("https://exemplo.test/suprimentos/pedidos/{$pedido->id}");
    expect(PedidoDetailRoute::absoluteUrlFor(User::factory()->gestao()->create(), $pedido))
        ->toBe("https://exemplo.test/gestao/pedidos/{$pedido->id}");
});

test('a trailing slash in APP_URL does not double the separator', function () {
    config(['app.url' => 'https://exemplo.test/']);
    $pedido = Pedido::factory()->create(['status_id' => $this->status->id]);

    expect(PedidoDetailRoute::absoluteUrlFor(User::factory()->gestao()->create(), $pedido))
        ->toBe("https://exemplo.test/gestao/pedidos/{$pedido->id}");
});

test('an unknown papel gets no link', function () {
    expect(PedidoDetailRoute::absoluteUrlFor(userForPapel('sem papel'), Pedido::factory()->create(['status_id' => $this->status->id])))->toBeNull();
});
