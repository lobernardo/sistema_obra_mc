<?php

namespace App\Domain\Pedidos;

use App\Enums\StatusSlug;
use App\Models\Pedido;
use Illuminate\Support\Carbon;

/**
 * Single source of truth for the "atraso" (overdue) rule. Every consumer
 * (Kanban, listings, filters, dashboard) must call this instead of
 * re-deriving the formula.
 */
class AtrasoClassifier
{
    public static function isAtrasado(Pedido $pedido): bool
    {
        /** @var StatusSlug $statusSlug */
        $statusSlug = StatusSlug::from($pedido->status->slug);

        if ($statusSlug->isTerminal()) {
            return false;
        }

        return Carbon::parse($pedido->needed_at)->startOfDay()->lt(Carbon::today());
    }
}
