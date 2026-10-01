<?php

namespace App\Notifications;

use App\Models\PedidoEvent;
use App\Support\LocalTime;
use App\Support\PedidoDetailRoute;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * E-mail of an internal notification (RF-14, RF-15, CT-03): one message
 * per recipient of a history event. Subject `[<código>] <rótulo do tipo> —
 * <obraLabel>`; the PT-BR markdown template `mail.pedidos.notificacao`
 * carries the code, obra label, type label, content, actor and local
 * date/time ({@see LocalTime::formatDateTime()}) plus a "Ver pedido" button
 * to the detail route of the recipient's papel, anchored on `APP_URL`
 * ({@see PedidoDetailRoute::absoluteUrlFor()}). It never carries a password,
 * token nor attachment; the sender is `config('mail.from')`.
 *
 * User-written content (observação, nome do romaneio) is escaped for
 * markdown, so it is shown as typed and never becomes a link or heading.
 *
 * Deliberately NOT `ShouldQueue`: no worker exists. It is sent after the
 * response by `InternalNotificationMailer` (RNF-01).
 */
class PedidoEventNotification extends Notification
{
    /**
     * @param  array{action: string, context: ?string, at: string, actor: ?string}  $description  the event as rendered by `PedidoEventValuePresenter`
     */
    public function __construct(
        public readonly PedidoEvent $event,
        public readonly array $description,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $pedido = $this->event->pedido;
        $obraLabel = $pedido->obraLabel();
        $action = $this->description['action'];

        return (new MailMessage)
            ->subject("[{$pedido->code}] {$action} — {$obraLabel}")
            ->action('Ver pedido', (string) PedidoDetailRoute::absoluteUrlFor($notifiable, $pedido))
            ->markdown('mail.pedidos.notificacao', [
                'name' => $notifiable->name,
                'code' => $pedido->code,
                'obraLabel' => $this->escapeMarkdown($obraLabel),
                'typeLabel' => $action,
                'content' => $this->description['context'] === null ? null : $this->escapeMarkdown($this->description['context']),
                'actor' => $this->description['actor'] === null ? null : $this->escapeMarkdown($this->description['actor']),
                'at' => LocalTime::formatDateTime($this->event->created_at),
                'appName' => config('app.name'),
            ]);
    }

    /**
     * Backslash-escapes markdown punctuation and keeps line breaks as hard
     * breaks. `&`, `<` and `>` are left to Blade's HTML escaping.
     */
    private function escapeMarkdown(string $text): string
    {
        $escaped = preg_replace('/([\\\\`*_{}\[\]()#+\-.!|~])/', '\\\\$1', str_replace("\r\n", "\n", $text));

        return str_replace("\n", "  \n", (string) $escaped);
    }
}
