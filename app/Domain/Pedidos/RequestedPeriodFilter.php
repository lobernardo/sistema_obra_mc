<?php

namespace App\Domain\Pedidos;

use App\Enums\RequestedPeriodPreset;
use App\Models\Pedido;
use App\Support\LocalTime;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use LogicException;

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
 *
 * Slice 3 presets (RF-16..RF-19, NC-01): each relative
 * {@see RequestedPeriodPreset} is a closed range of whole local calendar days
 * ending on {@see LocalTime::today()}; "Personalizado" is the De/Até range as
 * local days (RF-18, router decision F-01, reversible by changing this class
 * and {@see LocalTime}). Every path ends in {@see self::applyLocalRange()}, so
 * Dashboard, drill-down and listings share one implementation.
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
     * The preset in force for the given raw state: a valid value wins; an
     * empty or unknown value with any custom date is Personalizado (RF-18);
     * an unknown value without dates is the neutral state, `null` (RF-19).
     */
    public static function effectivePreset(string $preset, string $from, string $to): ?RequestedPeriodPreset
    {
        $resolved = RequestedPeriodPreset::tryFrom($preset);

        if ($resolved !== null) {
            return $resolved;
        }

        return $from !== '' || $to !== '' ? RequestedPeriodPreset::Personalizado : null;
    }

    /**
     * The closed local calendar range (`Y-m-d`) of a relative preset, ending
     * today in the local timezone (NC-01).
     *
     * @return array{from: string, to: string}
     *
     * @throws LogicException for Personalizado, which has no relative window
     */
    public static function localRangeFor(RequestedPeriodPreset $preset): array
    {
        $daysBack = $preset->daysBack();

        if ($daysBack === null) {
            throw new LogicException('O período Personalizado não tem janela relativa.');
        }

        $today = LocalTime::today();

        return [
            'from' => $today->subDays($daysBack)->toDateString(),
            'to' => $today->toDateString(),
        ];
    }

    /**
     * UTC half-open window `[from, until)` of a relative preset, through the
     * same local-day conversion as Personalizado and the Dashboard.
     *
     * @return array{from: CarbonImmutable, until: CarbonImmutable}
     */
    public static function utcWindow(RequestedPeriodPreset $preset): array
    {
        $range = self::localRangeFor($preset);

        /** @var array{from: CarbonImmutable, until: CarbonImmutable} */
        return self::utcBoundsForLocalRange($range['from'], $range['to']);
    }

    /**
     * Applies the "Solicitado" state of a listing: neutral → unchanged;
     * relative preset → its window (custom dates ignored, RF-19);
     * Personalizado → De/Até as local days (RF-18, F-01).
     *
     * @param  Builder<Pedido>  $query
     * @return Builder<Pedido>
     */
    public static function apply(Builder $query, string $preset, string $from, string $to): Builder
    {
        $effective = self::effectivePreset($preset, $from, $to);

        if ($effective === null) {
            return $query;
        }

        if ($effective->isRelative()) {
            $range = self::localRangeFor($effective);

            return self::applyLocalRange($query, $range['from'], $range['to']);
        }

        return self::applyLocalRange($query, $from !== '' ? $from : null, $to !== '' ? $to : null);
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
