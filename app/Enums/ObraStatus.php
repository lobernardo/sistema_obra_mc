<?php

namespace App\Enums;

/**
 * Lifecycle status of an obra (CT-01). `isActive()` is the single definition
 * of "obra ativa" (RF-03): every obra that is not Concluído.
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

    public function isActive(): bool
    {
        return $this !== self::Concluido;
    }
}
