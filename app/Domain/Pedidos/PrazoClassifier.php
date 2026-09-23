<?php

namespace App\Domain\Pedidos;

use App\Models\Pedido;
use App\Support\LocalTime;
use Carbon\CarbonImmutable;

/**
 * Single source of truth for the dashboard's "prazo" (deadline) indicator.
 * `VENCENDO_EM_BREVE_DIAS` is a RIGID, named constant — never a runtime
 * configuration value — per SPEC RF-19c.
 *
 * Days remaining are counted from the `America/Sao_Paulo` "today" of
 * {@see LocalTime::today()} (RF-45; router decision F-01, reversible). Both
 * sides are pure calendar dates built in the same timezone, so no UTC offset
 * leaks into the difference.
 */
class PrazoClassifier
{
    public const int VENCENDO_EM_BREVE_DIAS = 3;

    /**
     * @return 'atrasado'|'vencendo_em_breve'|'dentro_do_prazo'|null
     */
    public static function classificar(Pedido $pedido): ?string
    {
        if (! PendenteClassifier::isPendente($pedido)) {
            return null;
        }

        if (AtrasoClassifier::isAtrasado($pedido)) {
            return 'atrasado';
        }

        $today = CarbonImmutable::createFromFormat('!Y-m-d', LocalTime::today()->toDateString(), 'UTC');
        $neededAt = CarbonImmutable::createFromFormat('!Y-m-d', $pedido->needed_at->toDateString(), 'UTC');

        $daysRemaining = (int) $today->diffInDays($neededAt);

        if ($daysRemaining <= self::VENCENDO_EM_BREVE_DIAS) {
            return 'vencendo_em_breve';
        }

        return 'dentro_do_prazo';
    }
}
