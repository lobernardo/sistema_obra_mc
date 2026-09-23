<?php

use App\Domain\Pedidos\RequestedPeriodFilter;
use App\Enums\RequestedPeriodPreset;
use App\Models\Pedido;
use App\Support\LocalTime;
use Carbon\CarbonImmutable;

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

/**
 * Slice 3 (RF-16..RF-19, NC-01, F-01): presets added to the same class. The
 * clock is 2026-09-22 12:00 in the local timezone unless stated otherwise.
 */
function travelToLocal(string $localDateTime): void
{
    test()->travelTo(CarbonImmutable::parse($localDateTime, LocalTime::TIMEZONE));
}

test('utcWindow of each relative preset ends at the next local midnight', function (RequestedPeriodPreset $preset, string $expectedFrom) {
    travelToLocal('2026-09-22 12:00');

    $window = RequestedPeriodFilter::utcWindow($preset);

    expect($window['from']->toIso8601ZuluString())->toBe($expectedFrom)
        ->and($window['until']->toIso8601ZuluString())->toBe('2026-09-23T03:00:00Z');
})->with([
    'Hoje' => [RequestedPeriodPreset::Hoje, '2026-09-22T03:00:00Z'],
    'Últimos 3 dias' => [RequestedPeriodPreset::Ultimos3Dias, '2026-09-20T03:00:00Z'],
    'Últimos 7 dias' => [RequestedPeriodPreset::Ultimos7Dias, '2026-09-16T03:00:00Z'],
    'Último mês' => [RequestedPeriodPreset::UltimoMes, '2026-08-24T03:00:00Z'],
]);

test('a late-evening local instant belongs to Hoje only on its local day', function () {
    $instant = CarbonImmutable::parse('2026-09-22T02:30:00Z');
    $inside = fn (array $window): bool => $instant->greaterThanOrEqualTo($window['from']) && $instant->lessThan($window['until']);

    travelToLocal('2026-09-22 12:00');
    expect($inside(RequestedPeriodFilter::utcWindow(RequestedPeriodPreset::Hoje)))->toBeFalse();

    travelToLocal('2026-09-21 20:00');
    expect($inside(RequestedPeriodFilter::utcWindow(RequestedPeriodPreset::Hoje)))->toBeTrue();
});

test('localRangeFor returns the closed local range ending today', function () {
    travelToLocal('2026-09-22 12:00');

    expect(RequestedPeriodFilter::localRangeFor(RequestedPeriodPreset::Ultimos7Dias))
        ->toBe(['from' => '2026-09-16', 'to' => '2026-09-22']);
});

test('effectivePreset resolves forged, empty and dated states', function (string $preset, string $from, string $to, ?RequestedPeriodPreset $expected) {
    expect(RequestedPeriodFilter::effectivePreset($preset, $from, $to))->toBe($expected);
})->with([
    'unknown without dates' => ['xyz', '', '', null],
    'empty without dates' => ['', '', '', null],
    'relative wins over dates' => ['hoje', '2020-01-01', '', RequestedPeriodPreset::Hoje],
    'empty with dates' => ['', '2026-06-01', '2026-06-30', RequestedPeriodPreset::Personalizado],
    'unknown with a date' => ['xyz', '2026-06-01', '', RequestedPeriodPreset::Personalizado],
]);

test('Personalizado applies exactly the local-range bounds', function () {
    $applied = RequestedPeriodFilter::apply(Pedido::query(), 'personalizado', '2026-09-22', '2026-09-22')->toRawSql();
    $expected = RequestedPeriodFilter::applyLocalRange(Pedido::query(), '2026-09-22', '2026-09-22')->toRawSql();

    expect($applied)->toBe($expected)
        ->toContain('2026-09-22 03:00:00')
        ->toContain('2026-09-23 03:00:00');
});

test('a relative preset ignores custom dates and uses its own window', function () {
    travelToLocal('2026-09-22 12:00');

    $applied = RequestedPeriodFilter::apply(Pedido::query(), 'hoje', '2020-01-01', '2020-01-31')->toRawSql();

    expect($applied)->toBe(RequestedPeriodFilter::applyLocalRange(Pedido::query(), '2026-09-22', '2026-09-22')->toRawSql())
        ->not->toContain('2020-01');
});

test('the neutral state adds no where clause', function () {
    expect(RequestedPeriodFilter::apply(Pedido::query(), 'xyz', '', '')->toRawSql())
        ->toBe(Pedido::query()->toRawSql());
});

test('localRangeFor refuses Personalizado', function () {
    RequestedPeriodFilter::localRangeFor(RequestedPeriodPreset::Personalizado);
})->throws(LogicException::class);

test('the application timezone stays UTC after travelling the local clock', function () {
    travelToLocal('2026-09-22 12:00');
    RequestedPeriodFilter::utcWindow(RequestedPeriodPreset::Hoje);

    expect(config('app.timezone'))->toBe('UTC');
});
