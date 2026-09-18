<?php

namespace App\Domain\Pedidos;

use App\Models\Pedido;
use Illuminate\Support\Carbon;

/**
 * Single source of truth for the dashboard's "prazo" (deadline) indicator.
 * `VENCENDO_EM_BREVE_DIAS` is a RIGID, named constant — never a runtime
 * configuration value — per SPEC RF-19c.
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

        $today = Carbon::today();
        $neededAt = Carbon::parse($pedido->needed_at)->startOfDay();

        $daysRemaining = $today->diffInDays($neededAt);

        if ($daysRemaining <= self::VENCENDO_EM_BREVE_DIAS) {
            return 'vencendo_em_breve';
        }

        return 'dentro_do_prazo';
    }
}
