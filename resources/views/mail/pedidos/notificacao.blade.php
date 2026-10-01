{{--
    Notificação interna de pedido (RF-14, CT-03). Recebe `name`, `code`,
    `obraLabel`, `typeLabel`, `content`, `actor`, `at` e `appName` da
    notificação; `obraLabel`, `content` e `actor` chegam já escapados para
    markdown. `actionText`/`actionUrl`/`displayableActionUrl` vêm do
    `MailMessage::action()`. Data/hora local formatada por `LocalTime`.
    Nunca contém senha, token nem anexo.
--}}
<x-mail.transactional>
# Olá, {{ $name }}!

Há uma atualização no pedido **{{ $code }}**.

**Pedido:** {{ $code }}<br>
**Obra:** {{ $obraLabel }}<br>
**Tipo:** {{ $typeLabel }}<br>
**Por:** {{ $actor ?? '—' }}<br>
**Data/hora:** {{ $at }}

@if ($content !== null)
**Conteúdo:**

{{ $content }}
@endif

<x-mail::button :url="$actionUrl">
{{ $actionText }}
</x-mail::button>

Você recebeu este e-mail porque acompanha este pedido no {{ $appName }}.

Atenciosamente,<br>
{{ $appName }}

<x-slot:subcopy>
Se você tiver problemas para clicar no botão "{{ $actionText }}", copie e cole a URL abaixo no seu navegador: <span class="break-all">[{{ $displayableActionUrl }}]({{ $actionUrl }})</span>
</x-slot:subcopy>
</x-mail.transactional>
