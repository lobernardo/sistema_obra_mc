<div>
    @if ($enabled)
        {{--
            notificacoes-internas UI-05..UI-07, UI-10: a single bell, fixed in the mobile top bar and next to
            the brand at the top of the desktop sidebar. The panel toggles only through the data-open attribute
            and literal Tailwind variants, which survive the Livewire morph; Escape closes it and returns focus to
            the bell. The timer refreshes the counter every 60 s and only while the tab is visible.
        --}}
        <div
            data-testid="notificacoes-sino"
            class="fixed top-2 right-[4.25rem] z-30 lg:top-2 lg:right-auto lg:left-[11.25rem]"
            x-data="{
                open: false,
                timer: null,
                init() {
                    this.timer = setInterval(() => this.refreshIfVisible(), 60000);
                },
                destroy() {
                    clearInterval(this.timer);
                },
                refreshIfVisible() {
                    if (document.visibilityState === 'visible') {
                        this.$wire.$refresh();
                    }
                },
                toggle() {
                    this.open = ! this.open;

                    if (this.open) {
                        this.$wire.loadPanel();
                    }
                },
                close() {
                    if (! this.open) {
                        return;
                    }

                    this.open = false;
                    this.$nextTick(() => this.$refs.bellButton.focus());
                },
            }"
            x-on:keydown.escape.window="close()"
            x-on:click.outside="open = false"
        >
            <button
                type="button"
                x-ref="bellButton"
                data-testid="notificacoes-sino-botao"
                aria-label="Notificações, {{ $unreadCount }} não lidas"
                aria-controls="notificacoes-painel"
                aria-expanded="false"
                x-bind:aria-expanded="open.toString()"
                x-on:click="toggle()"
                class="relative inline-flex min-h-11 min-w-11 items-center justify-center rounded-md border border-border bg-surface text-text hover:bg-background focus:outline-none focus-visible:ring-2 focus-visible:ring-focus/40"
            >
                <svg aria-hidden="true" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 1 0-12 0v3.2a2 2 0 0 1-.6 1.4L4 17h5m6 0a3 3 0 1 1-6 0m6 0H9" />
                </svg>

                @if ($unreadCount > 0)
                    <span aria-hidden="true" data-testid="notificacoes-badge" class="absolute -top-1.5 -right-1.5 inline-flex min-w-5 items-center justify-center rounded-full bg-primary px-1 text-xs leading-5 font-semibold text-surface">{{ $badge }}</span>
                @endif
            </button>

            <div
                id="notificacoes-painel"
                data-testid="notificacoes-painel"
                data-open="false"
                x-bind:data-open="open"
                class="fixed inset-x-4 top-16 hidden max-h-[70vh] flex-col overflow-y-auto rounded-md border border-border bg-surface shadow-lg data-[open=true]:flex lg:absolute lg:inset-x-auto lg:top-12 lg:left-0 lg:w-96"
            >
                <div class="flex items-center justify-between gap-2 border-b border-border px-4 py-3">
                    <p class="text-sm font-semibold text-text">Notificações não lidas</p>

                    @if ($unreadCount > 0)
                        <button type="button" wire:click="markAllAsRead" data-testid="sino-marcar-todas-lidas" class="text-sm text-primary hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-focus/40">Marcar todas como lidas</button>
                    @endif
                </div>

                <ul class="flex flex-col divide-y divide-border" data-testid="notificacoes-painel-lista">
                    @if ($unreadCount === 0 || ($panelLoaded && $panelNotifications->isEmpty()))
                        <li class="empty-state px-4 py-6">Nenhuma notificação nova.</li>
                    @elseif (! $panelLoaded)
                        <li class="px-4 py-6 text-sm text-text-muted">Carregando…</li>
                    @else
                        @foreach ($panelNotifications as $notification)
                            @php($description = $descriptions[$notification->pedido_event_id])
                            <li wire:key="sino-notificacao-{{ $notification->id }}">
                                <button
                                    type="button"
                                    wire:click="abrir({{ $notification->id }})"
                                    data-testid="sino-notificacao"
                                    data-notification-id="{{ $notification->id }}"
                                    class="flex w-full flex-col gap-0.5 px-4 py-3 text-left hover:bg-background focus:outline-none focus-visible:ring-2 focus-visible:ring-focus/40"
                                >
                                    <span class="text-sm font-semibold text-primary">{{ $notification->pedido->code }}</span>
                                    <span class="text-sm text-text">{{ $description['action'] }}</span>
                                    <time datetime="{{ $notification->event->created_at->toIso8601String() }}" class="text-xs text-text-muted">{{ $description['at'] }}</time>
                                </button>
                            </li>
                        @endforeach
                    @endif
                </ul>

                <div class="border-t border-border px-4 py-3">
                    <a href="{{ route('notificacoes.index') }}" data-testid="sino-ver-todas" class="text-sm font-semibold text-primary hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-focus/40">Ver todas</a>
                </div>
            </div>
        </div>
    @endif
</div>
