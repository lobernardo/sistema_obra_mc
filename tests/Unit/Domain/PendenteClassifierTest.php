<?php

use App\Domain\Pedidos\PendenteClassifier;
use App\Models\Pedido;
use App\Models\Status;

dataset('status pendente matrix', [
    'solicitado' => ['solicitado', true],
    'em_analise' => ['em_analise', true],
    'em_compra_preparacao' => ['em_compra_preparacao', true],
    'aguardando_entrega' => ['aguardando_entrega', true],
    'entregue' => ['entregue', false],
    'cancelado' => ['cancelado', false],
    'finalizado (migrated row)' => ['finalizado', false],
]);

test('isPendente is true unless the status is entregue, cancelado or finalizado', function (string $statusSlug, bool $expected) {
    $status = Status::query()->firstWhere('slug', $statusSlug)
        ?? Status::factory()->create(['slug' => $statusSlug, 'sort_order' => rand(1000, 9999)]);
    $pedido = Pedido::factory()->create(['status_id' => $status->id]);

    expect(PendenteClassifier::isPendente($pedido))->toBe($expected);
})->with('status pendente matrix');

test('scopePendente treats a finalizado pedido as terminal, using the migrated row', function () {
    $finalizado = Status::query()->where('slug', 'finalizado')->firstOrFail();
    $solicitado = Status::factory()->solicitado()->create();

    $finalizadoPedido = Pedido::factory()->create(['status_id' => $finalizado->id]);
    $activePedido = Pedido::factory()->create(['status_id' => $solicitado->id]);

    $pendentes = PendenteClassifier::scopePendente(Pedido::query(), true)->pluck('id')->all();
    $naoPendentes = PendenteClassifier::scopePendente(Pedido::query(), false)->pluck('id')->all();

    expect($pendentes)->toContain($activePedido->id)->not->toContain($finalizadoPedido->id);
    expect($naoPendentes)->toContain($finalizadoPedido->id)->not->toContain($activePedido->id);
});
