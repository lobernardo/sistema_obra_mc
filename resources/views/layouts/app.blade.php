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
    <body
        class="min-h-full font-sans text-text antialiased"
        x-data="{ sidebarOpen: false }"
        x-on:keydown.escape.window="if (sidebarOpen) { sidebarOpen = false; $nextTick(() => $refs.menuButton.focus()) }"
    >
        {{--
            Sidebar (navegacao-sidebar-listagens RF-01..RF-08, UI-01..UI-03): the only primary navigation,
            sticky on the left from lg up and an overlay drawer behind "Menu" below it. White surface; the
            brand red only on the active item, the "+ Nova Solicitação" action and details (ajustes-finais-albuquerque
            UI-07); no gradient, no MC signature (UI-16/UI-25). Presentation only: the items come from
            SidebarNavigation, which mirrors each route's own abilities and is never an authorization layer.
        --}}
        @php
            $currentUser = auth()->user();
            $sidebarItems = \App\Support\SidebarNavigation::for($currentUser);
            $highlightedItem = collect($sidebarItems)->firstWhere('highlight', true);
            $sectionItems = array_values(array_filter($sidebarItems, fn (array $item): bool => ! $item['highlight']));
        @endphp

        <header class="sticky top-0 z-30 flex items-center gap-2 border-b border-border bg-surface px-4 py-2 lg:hidden">
            <a href="{{ route('home') }}" class="min-w-0 grow truncate text-base font-semibold tracking-tight text-text hover:text-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-focus/40">
                {{ config('app.name') }}
            </a>

            @if ($highlightedItem !== null)
                <a href="{{ route($highlightedItem['route']) }}" data-testid="topbar-nova-solicitacao" class="btn-primary shrink-0">
                    {{ $highlightedItem['label'] }}
                </a>
            @endif

            <button
                type="button"
                x-ref="menuButton"
                data-testid="menu-toggle"
                aria-controls="sidebar"
                aria-expanded="false"
                x-bind:aria-expanded="sidebarOpen.toString()"
                x-on:click="sidebarOpen = true"
                class="inline-flex min-h-11 min-w-11 shrink-0 items-center justify-center rounded-md border border-border text-text hover:bg-background focus:outline-none focus-visible:ring-2 focus-visible:ring-focus/40"
            >
                <svg aria-hidden="true" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
                <span class="sr-only">Menu</span>
            </button>
        </header>

        <div
            data-open="false"
            x-bind:data-open="sidebarOpen"
            x-on:click="sidebarOpen = false"
            aria-hidden="true"
            class="fixed inset-0 z-40 hidden bg-text/40 data-[open=true]:block lg:data-[open=true]:hidden"
        ></div>

        <div class="lg:flex">
            <aside
                id="sidebar"
                data-open="false"
                x-bind:data-open="sidebarOpen"
                class="fixed inset-y-0 left-0 z-50 hidden w-60 flex-col overflow-y-auto border-r border-border bg-surface data-[open=true]:flex lg:sticky lg:top-0 lg:z-auto lg:flex lg:h-screen lg:shrink-0"
            >
                <div class="flex items-center justify-between gap-2 px-4 py-4">
                    <a href="{{ route('home') }}" class="min-w-0 truncate text-base font-semibold tracking-tight text-text hover:text-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-focus/40 sm:text-lg">
                        {{ config('app.name') }}
                    </a>

                    <button
                        type="button"
                        aria-label="Fechar menu"
                        x-on:click="sidebarOpen = false; $nextTick(() => $refs.menuButton.focus())"
                        class="inline-flex min-h-11 min-w-11 shrink-0 items-center justify-center rounded-md text-text-muted hover:bg-background hover:text-text focus:outline-none focus-visible:ring-2 focus-visible:ring-focus/40 lg:hidden"
                    >
                        <svg aria-hidden="true" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18" />
                        </svg>
                    </button>
                </div>

                <nav aria-label="Navegação principal" class="flex grow flex-col gap-1 px-3 pb-4">
                    @if ($highlightedItem !== null)
                        <a
                            href="{{ route($highlightedItem['route']) }}"
                            data-testid="sidebar-nova-solicitacao"
                            @class([
                                'btn-primary mb-2 w-full',
                                'ring-2 ring-focus/40 ring-offset-2' => request()->routeIs($highlightedItem['active']),
                            ])
                            @if (request()->routeIs($highlightedItem['active'])) aria-current="page" @endif
                            x-on:click="sidebarOpen = false"
                        >{{ $highlightedItem['label'] }}</a>
                    @endif

                    @foreach ($sectionItems as $index => $item)
                        @if ($item['group'] !== null && ($index === 0 || $sectionItems[$index - 1]['group'] !== $item['group']))
                            <p class="sidebar-group-label">{{ $item['group'] }}</p>
                        @endif

                        <a
                            href="{{ route($item['route']) }}"
                            @class([
                                'sidebar-link',
                                'sidebar-link-active' => request()->routeIs($item['active']),
                            ])
                            @if (request()->routeIs($item['active'])) aria-current="page" @endif
                            x-on:click="sidebarOpen = false"
                        >{{ $item['label'] }}</a>
                    @endforeach
                </nav>

                @auth
                    <div class="flex flex-col gap-3 border-t border-border px-4 py-4 text-sm">
                        <div class="flex min-w-0 flex-col gap-1">
                            <span class="truncate text-text-muted">{{ $currentUser->name }}</span>
                            @if ($currentUser->role)
                                <span class="badge badge-neutral self-start uppercase tracking-wide">
                                    {{ $currentUser->role->name }}
                                </span>
                            @endif
                        </div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="btn-secondary w-full">
                                Sair
                            </button>
                        </form>
                    </div>
                @endauth
            </aside>

            <main class="min-w-0 flex-1 px-4 py-6 sm:px-6">
                <div class="mx-auto w-full max-w-7xl">
                    {{ $slot }}
                </div>
            </main>
        </div>

        @livewireScripts
    </body>
</html>
