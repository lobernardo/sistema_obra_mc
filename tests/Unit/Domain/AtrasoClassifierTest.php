<?php

use App\Domain\Pedidos\AtrasoClassifier;
use App\Models\Pedido;
use App\Models\Status;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-06-15 15:00'));
});

afterEach(function () {
    Carbon::setTestNow();
});

dataset('atraso matrix', [
    'past needed_at + active status => atrasado' => ['2026-06-10', 'solicitado', true],
    'future needed_at + active status => not atrasado' => ['2026-06-20', 'solicitado', false],
    'past needed_at + entregue status => never atrasado' => ['2026-06-10', 'entregue', false],
    'past needed_at + cancelado status => never atrasado' => ['2026-06-10', 'cancelado', false],
    'past needed_at + finalizado status (migrated row) => never atrasado' => ['2026-06-10', 'finalizado', false],
]);

test('isAtrasado follows the 4-combination decision table', function (string $neededAt, string $statusSlug, bool $expected) {
    $status = Status::query()->firstWhere('slug', $statusSlug)
        ?? Status::factory()->create(['slug' => $statusSlug, 'sort_order' => rand(1000, 9999)]);
    $pedido = Pedido::factory()->create([
        'needed_at' => $neededAt,
        'status_id' => $status->id,
    ]);

    expect(AtrasoClassifier::isAtrasado($pedido))->toBe($expected);
})->with('atraso matrix');

test('scopeAtrasado excludes a finalizado pedido with a past needed_at, using the migrated row', function () {
    $finalizado = Status::query()->where('slug', 'finalizado')->firstOrFail();
    $solicitado = Status::factory()->solicitado()->create();

    $finalizadoPedido = Pedido::factory()->create(['needed_at' => '2026-06-10', 'status_id' => $finalizado->id]);
    $activePedido = Pedido::factory()->create(['needed_at' => '2026-06-10', 'status_id' => $solicitado->id]);

    $ids = AtrasoClassifier::scopeAtrasado(Pedido::query())->pluck('id')->all();

    expect($ids)->toContain($activePedido->id)->not->toContain($finalizadoPedido->id);
});

test('atraso is measured by needed_at only, never by data_prevista (RF-12)', function () {
    $solicitado = Status::factory()->solicitado()->create();

    Carbon::setTestNow(Carbon::parse('2026-06-01 12:00'));
    $futureNeededPastPrevista = Pedido::factory()->create(['needed_at' => '2026-06-20', 'status_id' => $solicitado->id]);

    Carbon::setTestNow(Carbon::parse('2026-06-12 12:00'));
    $pastNeededFuturePrevista = Pedido::factory()->create(['needed_at' => '2026-06-10', 'status_id' => $solicitado->id]);

    Carbon::setTestNow(Carbon::parse('2026-06-15 15:00'));

    expect($futureNeededPastPrevista->data_prevista->lt(Carbon::today()))->toBeTrue();
    expect($pastNeededFuturePrevista->data_prevista->gt(Carbon::today()))->toBeTrue();

    expect(AtrasoClassifier::isAtrasado($futureNeededPastPrevista))->toBeFalse();
    expect(AtrasoClassifier::isAtrasado($pastNeededFuturePrevista))->toBeTrue();

    $ids = AtrasoClassifier::scopeAtrasado(Pedido::query())->pluck('id')->all();

    expect($ids)->toContain($pastNeededFuturePrevista->id)->not->toContain($futureNeededPastPrevista->id);
});
