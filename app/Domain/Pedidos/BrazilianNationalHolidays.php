<?php

namespace App\Domain\Pedidos;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Brazilian national holidays used by the Data prevista business-day rule
 * (RF-10, RNF-05): the 9 fixed national dates plus Sexta-feira da Paixão.
 *
 * Kept as a literal list and an integer-arithmetic Easter computation, with
 * no dependency and no `ext-calendar` (`easter_date()`/`easter_days()`).
 * State and municipal holidays, Carnaval and Corpus Christi are out of scope.
 */
final class BrazilianNationalHolidays
{
    /**
     * Fixed national holidays as `m-d`.
     *
     * @var list<string>
     */
    public const array FIXED = ['01-01', '04-21', '05-01', '09-07', '10-12', '11-02', '11-15', '11-20', '12-25'];

    /**
     * Easter Sunday by the anonymous Gregorian algorithm (Meeus/Jones/Butcher).
     */
    public static function easterSunday(int $year): CarbonImmutable
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;

        return CarbonImmutable::create($year, $month, $day)->startOfDay();
    }

    /**
     * Sexta-feira da Paixão: two days before Easter Sunday.
     */
    public static function goodFriday(int $year): CarbonImmutable
    {
        return self::easterSunday($year)->subDays(2);
    }

    /**
     * Whether the calendar date of the given value is a national holiday.
     */
    public static function isHoliday(CarbonInterface $date): bool
    {
        if (in_array($date->format('m-d'), self::FIXED, true)) {
            return true;
        }

        return $date->format('Y-m-d') === self::goodFriday((int) $date->format('Y'))->format('Y-m-d');
    }
}
