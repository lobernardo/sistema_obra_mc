{{--
    Convite de primeiro acesso (RF-15, CT-07a). Recebe `name`, `appName` e
    `validHours` da notificação; `actionText`/`actionUrl`/`displayableActionUrl`
    vêm do `MailMessage::action()`. Nunca contém senha (RF-18).
--}}
<x-mail.transactional>
# Olá, {{ $name }}!

Um acesso ao {{ $appName }} foi criado para você com este e-mail.

Para começar, defina a sua senha pelo botão abaixo.

<x-mail::button :url="$actionUrl">
{{ $actionText }}
</x-mail::button>

Este link é válido por {{ $validHours }} horas. Depois disso, peça um novo convite à Gestão.

Se você não esperava este e-mail, nenhuma ação é necessária.

Atenciosamente,<br>
{{ $appName }}

<x-slot:subcopy>
Se você tiver problemas para clicar no botão "{{ $actionText }}", copie e cole a URL abaixo no seu navegador: <span class="break-all">[{{ $displayableActionUrl }}]({{ $actionUrl }})</span>
</x-slot:subcopy>
</x-mail.transactional>
