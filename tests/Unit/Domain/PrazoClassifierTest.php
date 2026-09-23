<?php

use App\Domain\Pedidos\PrazoClassifier;
use App\Models\Pedido;
use App\Models\Status;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-06-15 15:00'));
});

afterEach(function () {
    Carbon::setTestNow();
});

test('VENCENDO_EM_BREVE_DIAS is the RIGID constant value 3', function () {
    expect(PrazoClassifier::VENCENDO_EM_BREVE_DIAS)->toBe(3);
});

dataset('prazo matrix', [
    'more than 3 days out + active status => dentro_do_prazo' => ['2026-06-20', 'solicitado', 'dentro_do_prazo'],
    'exactly 3 days out + active status => vencendo_em_breve' => ['2026-06-18', 'solicitado', 'vencendo_em_breve'],
    'today (0 days out) + active status => vencendo_em_breve' => ['2026-06-15', 'solicitado', 'vencendo_em_breve'],
    'past needed_at + active status => atrasado' => ['2026-06-10', 'solicitado', 'atrasado'],
    'non-pendente (entregue) regardless of date => null' => ['2026-06-10', 'entregue', null],
    'non-pendente (cancelado) regardless of date => null' => ['2026-06-20', 'cancelado', null],
    'non-pendente (finalizado, migrated row) with past needed_at => null' => ['2026-06-10', 'finalizado', null],
]);

test('classificar follows the RIGID 3-day window decision table', function (string $neededAt, string $statusSlug, ?string $expected) {
    $status = Status::query()->firstWhere('slug', $statusSlug)
        ?? Status::factory()->create(['slug' => $statusSlug, 'sort_order' => rand(1000, 9999)]);
    $pedido = Pedido::factory()->create([
        'needed_at' => $neededAt,
        'status_id' => $status->id,
    ]);

    expect(PrazoClassifier::classificar($pedido))->toBe($expected);
})->with('prazo matrix');

test('4 days out is above the window and classifies as dentro_do_prazo', function () {
    $status = Status::factory()->create(['slug' => 'solicitado', 'sort_order' => rand(1000, 9999)]);
    $pedido = Pedido::factory()->create([
        'needed_at' => '2026-06-19',
        'status_id' => $status->id,
    ]);

    expect(PrazoClassifier::classificar($pedido))->toBe('dentro_do_prazo');
});
