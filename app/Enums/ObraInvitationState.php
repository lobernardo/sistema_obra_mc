<?php

namespace App\Enums;

/**
 * Derived state of a convite (RF-26), computed at read time by
 * `ObraInvitation::state()` and never persisted.
 */
enum ObraInvitationState: string
{
    case Pendente = 'pendente';
    case Utilizado = 'utilizado';
    case Expirado = 'expirado';
    case Revogado = 'revogado';

    public function label(): string
    {
        return match ($this) {
            self::Pendente => 'Pendente',
            self::Utilizado => 'Utilizado',
            self::Expirado => 'Expirado',
            self::Revogado => 'Revogado',
        };
    }
}
