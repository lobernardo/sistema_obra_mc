<?php

namespace App\Enums;

/**
 * Delivery state of the e-mail of an internal notification (CT-02):
 * `pendente` until the deferred send runs, then the last result.
 * `ignorado` means the notification was not eligible for e-mail and the
 * transport was never called (CT-03).
 * Pinned in the database by `internal_notifications_email_status_check`.
 */
enum InternalNotificationEmailStatus: string
{
    case Pendente = 'pendente';
    case Enviado = 'enviado';
    case Falhou = 'falhou';
    case Ignorado = 'ignorado';
}
