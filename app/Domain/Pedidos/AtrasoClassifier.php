<?php

namespace App\Domain\Pedidos;

use App\Enums\StatusSlug;
use App\Models\Pedido;
use App\Support\LocalTime;
use Illuminate\Database\Eloquent\Builder;

/**
 * Single source of truth for the "atraso" (overdue) rule. Every consumer
 * (Kanban, listings, filters, dashboard) must call this instead of
 * re-deriving the formula.
 *
 * "Today" is the current calendar day in `America/Sao_Paulo`, taken only
 * from {@see LocalTime::today()} (RF-45; router decision F-01, reversible).
 * `needed_at` is a `date` column: it is compared as a calendar date and
 * never timezone-shifted.
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

        return $pedido->needed_at->toDateString() < LocalTime::today()->toDateString();
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
            ->where('needed_at', '<', LocalTime::today()->toDateString());
    }
}
