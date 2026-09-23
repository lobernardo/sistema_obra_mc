<?php

namespace App\Domain\Pedidos;

use App\Enums\StatusSlug;
use App\Models\Pedido;
use Illuminate\Database\Eloquent\Builder;
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

    /**
     * Query-level equivalent of {@see self::isAtrasado()}, for use as a
     * filter (e.g. the Suprimentos/Gestão "Todos os Pedidos" listing, RF-12)
     * where evaluating every row in PHP would defeat pagination. Encodes the
     * identical rule (non-terminal status AND `needed_at` before today) so
     * the formula still lives in exactly one place.
     *
     * @param  Builder<Pedido>  $query
     * @return Builder<Pedido>
     */
    public static function scopeAtrasado(Builder $query): Builder
    {
        return $query
            ->whereHas('status', fn (Builder $query) => $query->whereNotIn('slug', StatusSlug::terminalValues()))
            ->whereDate('needed_at', '<', Carbon::today());
    }
}
