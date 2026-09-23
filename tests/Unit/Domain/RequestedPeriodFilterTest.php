<?php

use App\Domain\Pedidos\RequestedPeriodFilter;

/**
 * RF-46 / CT-10: a local calendar range becomes UTC half-open bounds on
 * `requested_at` (De inclusive at local 00:00; Até exclusive at local 00:00
 * of the next day).
 */
test('the period column is requested_at', function () {
    expect(RequestedPeriodFilter::COLUMN)->toBe('requested_at');
});

test('De = Até = 22/09/2026 maps to [2026-09-22T03:00Z, 2026-09-23T03:00Z)', function () {
    $bounds = RequestedPeriodFilter::utcBoundsForLocalRange('2026-09-22', '2026-09-22');

    expect($bounds['from']->toIso8601ZuluString())->toBe('2026-09-22T03:00:00Z')
        ->and($bounds['until']->toIso8601ZuluString())->toBe('2026-09-23T03:00:00Z');
});

test('only De leaves until open and only Até leaves from open', function () {
    $onlyFrom = RequestedPeriodFilter::utcBoundsForLocalRange('2026-09-22', null);
    $onlyTo = RequestedPeriodFilter::utcBoundsForLocalRange(null, '2026-09-22');

    expect($onlyFrom['from']->toIso8601ZuluString())->toBe('2026-09-22T03:00:00Z')
        ->and($onlyFrom['until'])->toBeNull()
        ->and($onlyTo['from'])->toBeNull()
        ->and($onlyTo['until']->toIso8601ZuluString())->toBe('2026-09-23T03:00:00Z');
});

test('an empty, null or unparseable side yields a null bound', function (?string $value) {
    expect(RequestedPeriodFilter::utcBoundsForLocalRange($value, $value))->toBe(['from' => null, 'until' => null]);
})->with([
    'empty' => [''],
    'null' => [null],
    'garbage' => ['xyz'],
    'impossible date' => ['2026-02-30'],
    'wrong format' => ['22/09/2026'],
]);

test('the application timezone stays UTC', function () {
    expect(config('app.timezone'))->toBe('UTC');
});
