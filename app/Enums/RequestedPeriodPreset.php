<?php

namespace App\Enums;

use App\Domain\Pedidos\RequestedPeriodFilter;

/**
 * Options of the "Solicitado" period control of the three pedido listings
 * (RF-15, RF-16, CT-03, NC-01). The neutral state (no period restriction) is
 * the empty string, never a case.
 *
 * The enum only describes each window as a number of whole local calendar
 * days before today; it computes no bound and knows no timezone. Bounds are
 * built only by {@see RequestedPeriodFilter}.
 */
enum RequestedPeriodPreset: string
{
    case Hoje = 'hoje';
    case Ultimos3Dias = '3d';
    case Ultimos7Dias = '7d';
    case UltimoMes = 'mes';
    case Personalizado = 'personalizado';

    public const string NEUTRAL_LABEL = 'Qualquer data';

    public function label(): string
    {
        return match ($this) {
            self::Hoje => 'Hoje',
            self::Ultimos3Dias => 'Últimos 3 dias',
            self::Ultimos7Dias => 'Últimos 7 dias',
            self::UltimoMes => 'Último mês',
            self::Personalizado => 'Personalizado',
        };
    }

    /**
     * Days before today where the closed window starts (today included);
     * `null` for Personalizado, which has no relative window.
     */
    public function daysBack(): ?int
    {
        return match ($this) {
            self::Hoje => 0,
            self::Ultimos3Dias => 2,
            self::Ultimos7Dias => 6,
            self::UltimoMes => 29,
            self::Personalizado => null,
        };
    }

    public function isRelative(): bool
    {
        return $this !== self::Personalizado;
    }
}
