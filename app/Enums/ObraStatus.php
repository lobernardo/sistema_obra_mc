<?php

namespace App\Enums;

/**
 * Lifecycle status of an obra (CT-01). Purely descriptive: it never decides
 * whether an obra is "ativa" — that is `Obra::isActive()` / `Obra::active()`
 * over `obras.is_active` (RF-02, RF-03).
 */
enum ObraStatus: string
{
    case AIniciar = 'a_iniciar';
    case EmAndamento = 'em_andamento';
    case Concluido = 'concluido';

    public function label(): string
    {
        return match ($this) {
            self::AIniciar => 'A iniciar',
            self::EmAndamento => 'Em andamento',
            self::Concluido => 'Concluído',
        };
    }
}
