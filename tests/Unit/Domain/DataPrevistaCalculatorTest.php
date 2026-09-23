<?php

use App\Domain\Pedidos\DataPrevistaCalculator;
use Carbon\CarbonImmutable;

/**
 * RF-10 / RF-11: Data prevista is the 3rd business day strictly after the
 * America/Sao_Paulo calendar date of the Data da solicitação.
 */
test('the RF-10 table matches row by row', function (string $requestedAtUtc, string $expected) {
    expect(DataPrevistaCalculator::forRequestedAt(CarbonImmutable::parse($requestedAtUtc, 'UTC'))->toDateString())
        ->toBe($expected);
})->with([
    'segunda' => ['2026-09-21T12:00:00Z', '2026-09-24'],
    'sexta' => ['2026-09-25T12:00:00Z', '2026-09-30'],
    'sábado' => ['2026-09-26T12:00:00Z', '2026-09-30'],
    'domingo' => ['2026-09-27T12:00:00Z', '2026-09-30'],
    'sexta antes de 12/10' => ['2026-10-09T12:00:00Z', '2026-10-15'],
    'quinta 22:30 local' => ['2026-09-25T01:30:00Z', '2026-09-29'],
]);

test('good friday is skipped', function () {
    expect(DataPrevistaCalculator::forRequestedAt(CarbonImmutable::parse('2026-04-01T12:00:00Z'))->toDateString())
        ->toBe('2026-04-07');
});

test('the rule uses three business days and leaves app.timezone as UTC', function () {
    DataPrevistaCalculator::forRequestedAt(CarbonImmutable::parse('2026-09-25T01:30:00Z'));

    expect(DataPrevistaCalculator::DIAS_UTEIS)->toBe(3);
    expect(config('app.timezone'))->toBe('UTC');
});
