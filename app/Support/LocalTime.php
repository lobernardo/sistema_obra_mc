<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The single boundary conversion between stored UTC instants and the
 * `America/Sao_Paulo` calendar (RF-10, RF-45, RF-46, RF-47, CT-10).
 *
 * Timestamps stay stored in UTC and `config('app.timezone')` stays `UTC`;
 * every "which local day is this" decision (Data prevista, "today" for
 * atraso/prazo/`entreguesHoje`, the period filter) and every date/time
 * display goes through this class, so the local calendar lives in exactly
 * one place.
 */
final class LocalTime
{
    public const string TIMEZONE = 'America/Sao_Paulo';

    /**
     * The given instant expressed in the local timezone.
     */
    public static function toLocal(CarbonInterface $utc): CarbonImmutable
    {
        return CarbonImmutable::instance($utc)->setTimezone(self::TIMEZONE);
    }

    /**
     * Local `d/m/Y H:i` rendering of an instant; `—` when absent.
     */
    public static function formatDateTime(?CarbonInterface $utc): string
    {
        return $utc === null ? '—' : self::toLocal($utc)->format('d/m/Y H:i');
    }

    /**
     * Local `d/m/Y` rendering of the calendar day of an instant; `—` when absent.
     */
    public static function formatDate(?CarbonInterface $utc): string
    {
        return $utc === null ? '—' : self::toLocal($utc)->format('d/m/Y');
    }

    /**
     * The current local calendar date, at 00:00 in {@see self::TIMEZONE}.
     */
    public static function today(): CarbonImmutable
    {
        return CarbonImmutable::now(self::TIMEZONE)->startOfDay();
    }

    /**
     * The UTC instant at which the given local calendar date begins. A
     * `Y-m-d` string is read as a local date; a Carbon value contributes
     * only its calendar date.
     */
    public static function localDayStartUtc(CarbonInterface|string $localDate): CarbonImmutable
    {
        $date = $localDate instanceof CarbonInterface ? $localDate->format('Y-m-d') : $localDate;

        return CarbonImmutable::createFromFormat('!Y-m-d', $date, self::TIMEZONE)->utc();
    }

    /**
     * The half-open UTC interval `[start, end)` covering the current local day.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function todayWindowUtc(): array
    {
        $today = self::today();

        return [self::localDayStartUtc($today), self::localDayStartUtc($today->addDay())];
    }
}
