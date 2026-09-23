<?php

use App\Models\Pedido;
use App\Models\Status;
use Carbon\CarbonImmutable;

/**
 * RF-09, RF-12, CT-06: every insert gets `requested_at` from the server clock
 * and `data_prevista` from the single live rule; the Data prevista is never
 * recomputed afterwards.
 */
test('a new pedido gets requested_at from the clock and data_prevista from the rule', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-21 12:00:00', 'UTC'));

    $pedido = Pedido::factory()->create();

    expect($pedido->requested_at->toIso8601ZuluString())->toBe('2026-09-21T12:00:00Z');
    expect($pedido->data_prevista->toDateString())->toBe('2026-09-24');
    expect($pedido->fresh()->data_prevista->toDateString())->toBe('2026-09-24');
});

test('an explicit requested_at is converted to the local day before counting', function () {
    $pedido = Pedido::factory()->create(['requested_at' => CarbonImmutable::parse('2026-09-25T01:30:00Z')]);

    expect($pedido->fresh()->data_prevista->toDateString())->toBe('2026-09-29');
});

test('changing expected_delivery_at or status keeps data_prevista', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-21 12:00:00', 'UTC'));
    $pedido = Pedido::factory()->create();

    $pedido->update(['expected_delivery_at' => '2026-12-01']);
    $pedido->update(['status_id' => Status::factory()->emAnalise()->create()->id]);

    expect($pedido->fresh()->data_prevista->toDateString())->toBe('2026-09-24');
});

test('forcing data_prevista on update throws', function () {
    $pedido = Pedido::factory()->create();

    $pedido->data_prevista = '2030-01-01';

    expect(fn () => $pedido->save())->toThrow(LogicException::class, 'A data prevista é fixada na criação e não pode ser recalculada.');
    expect($pedido->fresh()->data_prevista->toDateString())->not->toBe('2030-01-01');
});
