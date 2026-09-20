<?php

namespace App\Notifications;

use App\Notifications\Concerns\BuildsAppUrl;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * PT-BR password-reset e-mail (RF-20, RF-27, CT-07b). Keeps the framework's
 * token handling, resolves the link through the named `password.reset`
 * route under `APP_URL` (RNF-12) and states the 60-minute validity read
 * from `passwords.users.expire` (RNF-01). The copy lives in the PT-BR
 * markdown template `mail.auth.reset-password`; the sender is whatever
 * `config('mail.from')` resolves (RF-27). No MC signature (UI-16); not
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
            ->action('Redefinir senha', $this->resetUrl($notifiable))
            ->markdown('mail.auth.reset-password', [
                'name' => $notifiable->name,
                'appName' => $appName,
                'validMinutes' => $validMinutes,
            ]);
    }

    protected function resetUrl($notifiable): string
    {
        return $this->appUrlToRoute('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);
    }
}
