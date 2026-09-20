<?php

namespace App\Notifications;

use App\Notifications\Concerns\BuildsAppUrl;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * First-access invite (RF-15, CT-03, CT-07a). Carries a temporary token
 * issued by the `passwords.invites` broker (72 h) and builds its link from
 * `APP_URL` through the named `invite.show` route (RF-27, RNF-12). The body
 * never contains a password (RF-18) nor the MC Inteligência signature
 * (UI-16). Deliberately NOT `ShouldQueue`: no worker exists (RNF-08).
 */
class FirstAccessInvite extends Notification
{
    use BuildsAppUrl;

    public function __construct(#[\SensitiveParameter] public readonly string $token) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $appName = config('app.name');
        $validHours = intdiv((int) config('auth.passwords.invites.expire'), 60);

        return (new MailMessage)
            ->subject("Seu acesso ao {$appName}")
            ->greeting("Olá, {$notifiable->name}!")
            ->line("Um acesso ao {$appName} foi criado para você com este e-mail.")
            ->line('Para começar, defina a sua senha pelo botão abaixo.')
            ->action('Definir minha senha', $this->inviteUrl($notifiable))
            ->line("Este link é válido por {$validHours} horas. Depois disso, peça um novo convite à Gestão.")
            ->line('Se você não esperava este e-mail, nenhuma ação é necessária.')
            ->salutation("Atenciosamente,\n{$appName}");
    }

    public function inviteUrl(object $notifiable): string
    {
        return $this->appUrlToRoute('invite.show', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);
    }
}
