<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-background">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">

        <title>{{ $title ?? config('app.name') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @livewireStyles
    </head>
    <body class="min-h-full flex flex-col font-sans text-text antialiased">
        @php
            $currentUser = auth()->user();
            $roleSlug = $currentUser?->role?->slug;
            $navItems = match ($roleSlug) {
                \App\Enums\RoleSlug::Obra->value => [
                    ['label' => 'Acompanhamento', 'route' => 'obra.pedidos.index', 'active' => 'obra.pedidos.*'],
                    ['label' => '+ Nova Solicitação', 'route' => 'obra.nova-solicitacao', 'active' => 'obra.nova-solicitacao'],
                ],
                \App\Enums\RoleSlug::Suprimentos->value => [
                    ['label' => 'Visão Geral', 'route' => 'suprimentos.visao-geral', 'active' => 'suprimentos.visao-geral'],
                    ['label' => 'Kanban', 'route' => 'suprimentos.kanban', 'active' => 'suprimentos.kanban'],
                    ['label' => 'Todos os Pedidos', 'route' => 'suprimentos.pedidos.index', 'active' => 'suprimentos.pedidos.*'],
                ],
                \App\Enums\RoleSlug::Gestao->value => [
                    ['label' => 'Dashboard', 'route' => 'gestao.dashboard', 'active' => 'gestao.dashboard'],
                    ['label' => 'Kanban', 'route' => 'gestao.kanban', 'active' => 'gestao.kanban'],
                    ['label' => 'Todos os Pedidos', 'route' => 'gestao.pedidos.index', 'active' => 'gestao.pedidos.*'],
                    ['label' => 'Usuários', 'route' => 'gestao.usuarios.index', 'active' => 'gestao.usuarios.*'],
                ],
                default => [],
            };
        @endphp

        {{-- Topbar (§20, UI-08): white surface, discreet border, no sidebar (UI-07), no MC signature (UI-16/UI-25). --}}
        <header class="bg-surface border-b border-border">
            <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-x-6 gap-y-0 px-4 sm:px-6">
                <a href="{{ route('home') }}" class="py-3 text-base font-semibold tracking-tight text-text hover:text-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-focus/40 sm:text-lg">
                    {{ config('app.name') }}
                </a>

                @if ($navItems !== [])
                    <nav aria-label="Navegação principal" class="order-last flex w-full gap-5 overflow-x-auto sm:order-none sm:w-auto">
                        @foreach ($navItems as $item)
                            <a
                                href="{{ route($item['route']) }}"
                                @class([
                                    'nav-link whitespace-nowrap',
                                    'nav-link-active' => request()->routeIs($item['active']),
                                ])
                                @if (request()->routeIs($item['active'])) aria-current="page" @endif
                            >{{ $item['label'] }}</a>
                        @endforeach
                    </nav>
                @endif

                @auth
                    <div class="flex items-center gap-3 py-3 text-sm">
                        <span class="hidden text-text-muted sm:inline">{{ $currentUser->name }}</span>
                        @if ($currentUser->role)
                            <span class="badge badge-neutral uppercase tracking-wide">
                                {{ $currentUser->role->name }}
                            </span>
                        @endif
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="btn-secondary px-3 py-1.5">
                                Sair
                            </button>
                        </form>
                    </div>
                @endauth
            </div>
        </header>

        <main class="mx-auto w-full max-w-7xl grow px-4 py-6 sm:px-6">
            {{ $slot }}
        </main>

        @livewireScripts
    </body>
</html>
