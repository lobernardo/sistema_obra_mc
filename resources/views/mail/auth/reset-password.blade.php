{{--
    Redefinição de senha (RF-20, CT-07b). Recebe `name`, `appName` e
    `validMinutes` da notificação; `actionText`/`actionUrl`/`displayableActionUrl`
    vêm do `MailMessage::action()`.
--}}
<x-mail.transactional>
# Olá, {{ $name }}!

Recebemos um pedido para redefinir a senha da sua conta no {{ $appName }}.

<x-mail::button :url="$actionUrl">
{{ $actionText }}
</x-mail::button>

Este link é válido por {{ $validMinutes }} minutos.

Se você não solicitou a redefinição, nenhuma ação é necessária: sua senha atual continua a mesma.

Atenciosamente,<br>
{{ $appName }}

<x-slot:subcopy>
Se você tiver problemas para clicar no botão "{{ $actionText }}", copie e cole a URL abaixo no seu navegador: <span class="break-all">[{{ $displayableActionUrl }}]({{ $actionUrl }})</span>
</x-slot:subcopy>
</x-mail.transactional>
