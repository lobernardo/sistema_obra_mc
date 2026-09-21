<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-background">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">

        <title>{{ $title ?? 'Entrar' }} - {{ config('app.name') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @livewireStyles
    </head>
    <body class="min-h-full font-sans text-text antialiased">
        {{--
            Authentication shell (§28, UI-15/UI-24): shared by login, "Esqueci minha
            senha", reset and first access. The brand name comes only from
            `config('app.name')`. The official logos (Etapa 9 — UI-17/UI-18/UI-20)
            are served from `public/images` via `asset()`: the Albuquerque mark
            (square 512×512 symbol on the #B4B4B4 brand ground, rounded corners
            baked into the asset) sits above the title with its proportion
            preserved (`w-auto` + width/height attributes), and the MC logo —
            smaller, on the muted token — is aligned with the signature in the
            footer (UI-16).
        --}}
        <main class="flex min-h-screen flex-col items-center justify-center px-4 py-12">
            <div class="w-full max-w-md">
                <div class="mb-6 flex flex-col items-center text-center">
                    <div data-brand-logo-slot class="flex w-full justify-center">
                        <img src="{{ asset('images/logo-albuquerque-simbolo.png') }}" alt="{{ config('app.name') }}" class="mx-auto mb-4 h-20 w-auto rounded-md sm:h-24" width="512" height="512">
                    </div>
                    <h1 class="text-2xl font-semibold tracking-tight text-text">{{ config('app.name') }}</h1>
                </div>

                <div class="card p-6 sm:p-8">
                    {{ $slot }}
                </div>

                <p data-technology-signature class="mt-6 flex items-center justify-center gap-2 text-xs text-text-muted">
                    <img src="{{ asset('images/logo-mc.png') }}" alt="MC Inteligência" class="h-4 w-auto" width="1305" height="200">
                    <span>Tecnologia por MC Inteligência</span>
                </p>
            </div>
        </main>

        @livewireScripts
    </body>
</html>
