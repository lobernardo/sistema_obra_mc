<?php

use App\Support\LocalTime;
use Carbon\CarbonImmutable;

/**
 * RF-45..RF-47: LocalTime is the single UTC ↔ America/Sao_Paulo boundary.
 */
test('formats an instant in local time', function () {
    $instant = CarbonImmutable::parse('2026-09-25T01:30:00Z');

    expect(LocalTime::formatDateTime($instant))->toBe('24/09/2026 22:30');
    expect(LocalTime::formatDate($instant))->toBe('24/09/2026');
    expect(LocalTime::toLocal($instant)->getTimezone()->getName())->toBe(LocalTime::TIMEZONE);
});

test('formats absent values as a dash', function () {
    expect(LocalTime::formatDateTime(null))->toBe('—');
    expect(LocalTime::formatDate(null))->toBe('—');
});

test('today and its UTC window follow the local day', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-22T01:30:00Z'));

    expect(LocalTime::today()->toDateString())->toBe('2026-09-21');

    [$start, $end] = LocalTime::todayWindowUtc();

    expect($start->toIso8601ZuluString())->toBe('2026-09-21T03:00:00Z');
    expect($end->toIso8601ZuluString())->toBe('2026-09-22T03:00:00Z');
});

test('local day start is converted to UTC', function () {
    expect(LocalTime::localDayStartUtc('2026-09-22')->toIso8601ZuluString())->toBe('2026-09-22T03:00:00Z');
    expect(LocalTime::localDayStartUtc(CarbonImmutable::parse('2026-09-22 23:00', LocalTime::TIMEZONE))->toIso8601ZuluString())
        ->toBe('2026-09-22T03:00:00Z');
});

test('app.timezone stays UTC', function () {
    expect(config('app.timezone'))->toBe('UTC');
});
