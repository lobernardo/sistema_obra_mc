<?php

namespace App\Domain\Pedidos;

use App\Enums\StatusSlug;
use App\Models\Pedido;

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
}
