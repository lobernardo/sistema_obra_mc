<?php

namespace App\Notifications;

use App\Notifications\Concerns\BuildsAppUrl;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * PT-BR password-reset e-mail (RF-20, RF-27, CT-07b). Keeps the framework's
 * token handling, resolves the link through the named `password.reset`
 * route under `APP_URL` (RNF-12) and states the 60-minute validity read
 * from `passwords.users.expire` (RNF-01). No MC signature (UI-16); not
 * queued (RNF-08).
 */
class ResetPasswordPtBr extends ResetPassword
{
    use BuildsAppUrl;

    public function toMail($notifiable): MailMessage
    {
        $appName = config('app.name');
        $validMinutes = (int) config('auth.passwords.users.expire');

        return (new MailMessage)
            ->subject("Redefinição de senha - {$appName}")
            ->greeting("Olá, {$notifiable->name}!")
            ->line("Recebemos um pedido para redefinir a senha da sua conta no {$appName}.")
            ->action('Redefinir senha', $this->resetUrl($notifiable))
            ->line("Este link é válido por {$validMinutes} minutos.")
            ->line('Se você não solicitou a redefinição, nenhuma ação é necessária: sua senha atual continua a mesma.')
            ->salutation("Atenciosamente,\n{$appName}");
    }

    protected function resetUrl($notifiable): string
    {
        return $this->appUrlToRoute('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);
    }
}
