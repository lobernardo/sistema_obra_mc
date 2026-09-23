<?php

namespace App\Enums;

enum StatusSlug: string
{
    case Solicitado = 'solicitado';
    case EmAnalise = 'em_analise';
    case EmCompraPreparacao = 'em_compra_preparacao';
    case AguardandoEntrega = 'aguardando_entrega';
    case Entregue = 'entregue';
    case Cancelado = 'cancelado';
    case Finalizado = 'finalizado';

    /**
     * Statuses that make up the active, non-final workflow (excludes every terminal status).
     *
     * @return array<int, self>
     */
    public static function activeNonFinal(): array
    {
        return [
            self::Solicitado,
            self::EmAnalise,
            self::EmCompraPreparacao,
            self::AguardandoEntrega,
        ];
    }

    /**
     * The single definition of a terminal status (RF-39): no operational
     * mutation, atraso or pendência applies to a pedido in one of these.
     *
     * @return array<int, self>
     */
    public static function terminal(): array
    {
        return [
            self::Entregue,
            self::Cancelado,
            self::Finalizado,
        ];
    }

    /**
     * Slug values of {@see terminal()}, for SQL predicates.
     *
     * @return array<int, string>
     */
    public static function terminalValues(): array
    {
        return array_map(fn (self $status): string => $status->value, self::terminal());
    }

    /**
     * Statuses from which a pedido may be finalized: every active status plus `entregue`.
     *
     * @return array<int, self>
     */
    public static function finalizableFrom(): array
    {
        return [...self::activeNonFinal(), self::Entregue];
    }

    public function isTerminal(): bool
    {
        return in_array($this, self::terminal(), true);
    }
}
