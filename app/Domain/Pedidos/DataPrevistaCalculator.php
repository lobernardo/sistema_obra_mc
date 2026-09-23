<?php

namespace App\Domain\Pedidos;

use App\Support\LocalTime;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The single live definition of the Data prevista rule (RF-10, RF-11): the
 * 3rd business day strictly after the `America/Sao_Paulo` calendar date of
 * the Data da solicitação. Never "+72 h" and never "+3 calendar days".
 *
 * Business day = Monday–Friday that is not a national holiday
 * ({@see BrazilianNationalHolidays}). The local date comes from
 * {@see LocalTime} (RF-45), so `app.timezone` stays `UTC`.
 *
 * The backfill migration of `pedidos.data_prevista` holds a deliberately
 * frozen copy of this rule (RF-12); a parity test keeps both visible.
 */
final class DataPrevistaCalculator
{
    public const int DIAS_UTEIS = 3;

    public static function forRequestedAt(CarbonInterface $requestedAt): CarbonImmutable
    {
        $local = LocalTime::toLocal($requestedAt);
        $date = CarbonImmutable::create((int) $local->format('Y'), (int) $local->format('m'), (int) $local->format('d'))->startOfDay();

        $counted = 0;

        while ($counted < self::DIAS_UTEIS) {
            $date = $date->addDay();

            if (! $date->isWeekend() && ! BrazilianNationalHolidays::isHoliday($date)) {
                $counted++;
            }
        }

        return $date;
    }
}
