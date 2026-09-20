{{--
    PT-BR shell shared by every transactional e-mail (RF-27, CT-07). It
    mirrors the framework's `mail::message` component but writes the header
    and footer in Portuguese, so no theme string leaks in English whatever
    `APP_LOCALE` is. Brand and links come only from `config('app.name')` and
    `config('app.url')`; no technology-provider signature is added here (UI-16).
--}}
<x-mail::layout>
<x-slot:header>
<x-mail::header :url="config('app.url')">
{{ config('app.name') }}
</x-mail::header>
</x-slot:header>

{{ $slot }}

@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{{ $subcopy }}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

<x-slot:footer>
<x-mail::footer>
© {{ date('Y') }} {{ config('app.name') }}. Todos os direitos reservados.
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
