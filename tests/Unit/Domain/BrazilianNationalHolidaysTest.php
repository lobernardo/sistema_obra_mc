<?php

use App\Domain\Pedidos\BrazilianNationalHolidays;
use Carbon\CarbonImmutable;

/**
 * RF-10 / RNF-05: the national holiday list and the integer-arithmetic
 * Easter computation, pinned by known Easter dates.
 */
test('easter sunday matches the known dates', function (int $year, string $expected) {
    expect(BrazilianNationalHolidays::easterSunday($year)->toDateString())->toBe($expected);
})->with([
    [2024, '2024-03-31'],
    [2025, '2025-04-20'],
    [2026, '2026-04-05'],
    [2027, '2027-03-28'],
    [2030, '2030-04-21'],
    [2038, '2038-04-25'],
]);

test('good friday is two days before easter', function () {
    expect(BrazilianNationalHolidays::goodFriday(2026)->toDateString())->toBe('2026-04-03');
    expect(BrazilianNationalHolidays::isHoliday(CarbonImmutable::parse('2026-04-03')))->toBeTrue();
});

test('every fixed national date is a holiday in 2026', function (string $monthDay) {
    expect(BrazilianNationalHolidays::isHoliday(CarbonImmutable::parse("2026-{$monthDay}")))->toBeTrue();
})->with(BrazilianNationalHolidays::FIXED);

test('ordinary days and carnaval are not holidays', function (string $date) {
    expect(BrazilianNationalHolidays::isHoliday(CarbonImmutable::parse($date)))->toBeFalse();
})->with(['2026-10-13', '2026-02-17']);
