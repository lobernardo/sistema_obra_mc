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

    /**
     * Statuses that make up the active, non-final workflow (excludes `entregue` and `cancelado`).
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

    public function isTerminal(): bool
    {
        return $this === self::Entregue || $this === self::Cancelado;
    }
}
