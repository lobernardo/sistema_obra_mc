<?php

namespace App\Domain\Pedidos;

use App\Models\Pedido;
use App\Support\LocalTime;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * The single local-day period class on `requested_at` (RF-46, CT-10).
 *
 * A period is a range of `America/Sao_Paulo` calendar dates (De and/or Até,
 * inclusive, either side optional). It becomes a UTC half-open interval on
 * the stored UTC timestamp: De → local 00:00 of De in UTC (inclusive); Até →
 * local 00:00 of the day after Até in UTC (exclusive). The conversion goes
 * through {@see LocalTime}, so Dashboard, drill-down and listings agree on
 * which day a pedido belongs to.
 *
 * Slice 3 (`navegacao-sidebar-listagens`) adds its presets to **this** class
 * and routes "Personalizado" through {@see self::applyLocalRange()}; there is
 * never a second period class, and never a `whereDate` on `requested_at`.
 */
final class RequestedPeriodFilter
{
    public const string COLUMN = 'requested_at';

    /**
     * UTC bounds of a local calendar date range; an empty or unparseable
     * side yields `null` (no restriction on that side).
     *
     * @return array{from: ?CarbonImmutable, until: ?CarbonImmutable}
     */
    public static function utcBoundsForLocalRange(?string $localFrom, ?string $localTo): array
    {
        $fromDate = self::parseLocalDate($localFrom);
        $toDate = self::parseLocalDate($localTo);

        return [
            'from' => $fromDate === null ? null : LocalTime::localDayStartUtc($fromDate),
            'until' => $toDate === null ? null : LocalTime::localDayStartUtc($toDate->addDay()),
        ];
    }

    /**
     * Restricts the query to the local calendar date range, as `>= from` and
     * `< until` on {@see self::COLUMN}.
     *
     * @param  Builder<Pedido>  $query
     * @return Builder<Pedido>
     */
    public static function applyLocalRange(Builder $query, ?string $localFrom, ?string $localTo): Builder
    {
        ['from' => $from, 'until' => $until] = self::utcBoundsForLocalRange($localFrom, $localTo);

        if ($from !== null) {
            $query->where(self::COLUMN, '>=', $from);
        }

        if ($until !== null) {
            $query->where(self::COLUMN, '<', $until);
        }

        return $query;
    }

    /**
     * A strict `Y-m-d` calendar date (the time zone of the returned value
     * carries no meaning: only its date is used), or `null`.
     */
    private static function parseLocalDate(?string $date): ?CarbonImmutable
    {
        if ($date === null || preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $parts) !== 1) {
            return null;
        }

        if (! checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
            return null;
        }

        return CarbonImmutable::createFromFormat('!Y-m-d', $date, 'UTC');
    }
}
