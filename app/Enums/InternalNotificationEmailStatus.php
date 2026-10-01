<?php

namespace App\Enums;

/**
 * Delivery state of the e-mail of an internal notification (CT-02):
 * `pendente` until the deferred send runs, then the last result.
 * Pinned in the database by `internal_notifications_email_status_check`.
 */
enum InternalNotificationEmailStatus: string
{
    case Pendente = 'pendente';
    case Enviado = 'enviado';
    case Falhou = 'falhou';
}
