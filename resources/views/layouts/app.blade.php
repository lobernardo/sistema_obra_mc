<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-100">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ?? config('app.name') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @livewireStyles
    </head>
    <body class="min-h-full flex flex-col font-sans text-slate-900 antialiased">
        @php
            $currentUser = auth()->user();
            $roleSlug = $currentUser?->role?->slug;
            $navItems = match ($roleSlug) {
                \App\Enums\RoleSlug::Obra->value => [
                    ['label' => 'Acompanhamento', 'route' => 'obra.pedidos.index', 'active' => 'obra.pedidos.*'],
                    ['label' => '+ Nova Solicitação', 'route' => 'obra.nova-solicitacao', 'active' => 'obra.nova-solicitacao'],
                ],
                \App\Enums\RoleSlug::Suprimentos->value => [
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

        <header class="bg-slate-900 text-white shadow">
            <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 px-4 py-3 sm:px-6">
                <a href="{{ route('home') }}" class="text-base font-semibold tracking-tight sm:text-lg">
                    {{ config('app.name') }}
                </a>

                @if ($navItems !== [])
                    <nav aria-label="Navegação principal" class="order-last flex w-full gap-1 overflow-x-auto sm:order-none sm:w-auto">
                        @foreach ($navItems as $item)
                            <a
                                href="{{ route($item['route']) }}"
                                @class([
                                    'rounded-md px-3 py-1.5 text-sm font-medium whitespace-nowrap transition',
                                    'bg-white/15 text-white' => request()->routeIs($item['active']),
                                    'text-slate-300 hover:bg-white/10 hover:text-white' => ! request()->routeIs($item['active']),
                                ])
                                @if (request()->routeIs($item['active'])) aria-current="page" @endif
                            >{{ $item['label'] }}</a>
                        @endforeach
                    </nav>
                @endif

                @auth
                    <div class="flex items-center gap-3 text-sm">
                        <span class="hidden text-slate-300 sm:inline">{{ $currentUser->name }}</span>
                        @if ($currentUser->role)
                            <span class="rounded-full bg-sky-500/20 px-2.5 py-0.5 text-xs font-semibold uppercase tracking-wide text-sky-200">
                                {{ $currentUser->role->name }}
                            </span>
                        @endif
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="rounded-md border border-slate-600 px-3 py-1.5 text-sm font-medium text-slate-200 hover:bg-white/10 hover:text-white">
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
