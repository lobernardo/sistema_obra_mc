{{--
    Página 403 própria (mensagem-auto-rebaixamento UI-01..UI-03). Autônoma — não estende o layout da aplicação,
    então funciona sem usuário autenticado e também dentro do modal de erro do Livewire, que exibe o HTML da
    resposta num iframe (por isso "Voltar" usa target="_top"). Uma mensagem específica da negação é exibida
    escapada; vazia ou a padrão do framework vira o texto genérico em PT-BR.
--}}
@php
    $message = trim((string) (isset($exception) ? $exception->getMessage() : ''));

    if ($message === '' || $message === 'This action is unauthorized.') {
        $message = 'Você não tem permissão para acessar esta página.';
    }
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-surface">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <title>Acesso negado — {{ config('app.name') }}</title>

        @vite(['resources/css/app.css'])
    </head>
    <body class="min-h-full bg-surface font-sans text-text antialiased">
        <main class="flex min-h-screen items-center justify-center px-4">
            <div class="max-w-md text-center">
                <p class="text-sm font-semibold text-primary">403</p>
                <h1 class="mt-2 text-xl font-semibold text-text">Acesso negado</h1>
                <p class="mt-4 text-base text-text-muted">{{ $message }}</p>
                <div class="mt-6">
                    <a href="{{ route('home') }}" target="_top" class="btn-primary">Voltar</a>
                </div>
            </div>
        </main>
    </body>
</html>
