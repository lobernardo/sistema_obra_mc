<?php

namespace App\Enums;

/**
 * Explicit classification of a pedido attachment (CT-03, RF-30): set by the
 * upload path, never derived from the file name or content.
 */
enum PedidoAttachmentKind: string
{
    case Anexo = 'anexo';
    case Romaneio = 'romaneio';

    public function label(): string
    {
        return match ($this) {
            self::Anexo => 'Anexo',
            self::Romaneio => 'Romaneio',
        };
    }
}
