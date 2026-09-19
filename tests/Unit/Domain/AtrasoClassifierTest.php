<?php

use App\Domain\Pedidos\AtrasoClassifier;
use App\Models\Pedido;
use App\Models\Status;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-06-15'));
});

afterEach(function () {
    Carbon::setTestNow();
});

dataset('atraso matrix', [
    'past needed_at + active status => atrasado' => ['2026-06-10', 'solicitado', true],
    'future needed_at + active status => not atrasado' => ['2026-06-20', 'solicitado', false],
    'past needed_at + entregue status => never atrasado' => ['2026-06-10', 'entregue', false],
    'past needed_at + cancelado status => never atrasado' => ['2026-06-10', 'cancelado', false],
]);

test('isAtrasado follows the 4-combination decision table', function (string $neededAt, string $statusSlug, bool $expected) {
    $status = Status::factory()->create(['slug' => $statusSlug, 'sort_order' => rand(1000, 9999)]);
    $pedido = Pedido::factory()->create([
        'needed_at' => $neededAt,
        'status_id' => $status->id,
    ]);

    expect(AtrasoClassifier::isAtrasado($pedido))->toBe($expected);
})->with('atraso matrix');
