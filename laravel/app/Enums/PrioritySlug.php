<?php

namespace App\Enums;

enum PrioritySlug: string
{
    case Baixa = 'baixa';
    case Normal = 'normal';
    case Alta = 'alta';
    case Urgente = 'urgente';
}
