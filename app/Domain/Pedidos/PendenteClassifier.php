<?php

namespace App\Domain\Pedidos;

use App\Enums\StatusSlug;
use App\Models\Pedido;
use Illuminate\Database\Eloquent\Builder;

/**
 * Single source of truth for the "pendente" (pending) rule. Every consumer
 * (Kanban, listings, dashboard) must call this instead of re-deriving the
 * formula.
 */
class PendenteClassifier
{
    public static function isPendente(Pedido $pedido): bool
    {
        /** @var StatusSlug $statusSlug */
        $statusSlug = StatusSlug::from($pedido->status->slug);

        return ! $statusSlug->isTerminal();
    }

    /**
     * Query-level equivalent of {@see self::isPendente()}, for use as a
     * filter (e.g. the Gestão dashboard's "pendentes" indicator drill-down,
     * RF-22) where evaluating every row in PHP would defeat pagination.
     *
     * @param  Builder<Pedido>  $query
     * @return Builder<Pedido>
     */
    public static function scopePendente(Builder $query, bool $pendente = true): Builder
    {
        $terminalSlugs = StatusSlug::terminalValues();

        return $pendente
            ? $query->whereHas('status', fn (Builder $query) => $query->whereNotIn('slug', $terminalSlugs))
            : $query->whereHas('status', fn (Builder $query) => $query->whereIn('slug', $terminalSlugs));
    }
}
