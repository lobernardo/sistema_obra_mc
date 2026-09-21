<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-background">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ?? 'Entrar' }} - {{ config('app.name') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @livewireStyles
    </head>
    <body class="min-h-full font-sans text-text antialiased">
        {{--
            Authentication shell (§28, UI-15/UI-24): shared by login, "Esqueci minha
            senha", reset and first access. The brand name comes only from
            `config('app.name')`; the Albuquerque logo slot above the title and the
            MC logo beside the signature are filled in Etapa 9 (UI-20).
        --}}
        <main class="flex min-h-screen flex-col items-center justify-center px-4 py-12">
            <div class="w-full max-w-md">
                <div class="mb-6 flex flex-col items-center gap-4 text-center">
                    <div data-brand-logo-slot class="flex justify-center"></div>
                    <h1 class="text-2xl font-semibold tracking-tight text-text">{{ config('app.name') }}</h1>
                </div>

                <div class="card p-6 sm:p-8">
                    {{ $slot }}
                </div>

                <p data-technology-signature class="mt-6 flex items-center justify-center gap-2 text-xs text-text-muted">Tecnologia por MC Inteligência</p>
            </div>
        </main>

        @livewireScripts
    </body>
</html>
