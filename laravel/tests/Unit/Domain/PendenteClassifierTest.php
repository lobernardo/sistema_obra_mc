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
]);

test('isPendente is true unless the status is entregue or cancelado', function (string $statusSlug, bool $expected) {
    $status = Status::factory()->create(['slug' => $statusSlug, 'sort_order' => rand(1000, 9999)]);
    $pedido = Pedido::factory()->create(['status_id' => $status->id]);

    expect(PendenteClassifier::isPendente($pedido))->toBe($expected);
})->with('status pendente matrix');
