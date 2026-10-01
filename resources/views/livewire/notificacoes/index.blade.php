<div class="flex flex-col gap-5">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="page-title">Notificações Internas</h1>
            <p class="text-sm text-text-muted">Avisos sobre os pedidos que você acompanha.</p>
        </div>

        <button type="button" wire:click="markAllAsRead" data-testid="marcar-todas-lidas" class="btn-secondary min-h-11 lg:min-h-0">Marcar todas como lidas</button>
    </div>

    <x-filter-panel :active-count="$this->activeFilterCount()" :more-active-count="null">
        <x-slot:primary>
            <div class="flex flex-col gap-1 lg:w-40">
                <label for="readState" class="form-label">Leitura</label>
                <select id="readState" wire:model.live="readState" class="form-control">
                    <option value="">Todas</option>
                    <option value="nao">Somente não lidas</option>
                    <option value="sim">Somente lidas</option>
                </select>
            </div>

            <div class="flex flex-col gap-1 lg:w-52">
                <label for="eventType" class="form-label">Tipo</label>
                <select id="eventType" wire:model.live="eventType" class="form-control">
                    <option value="">Todos os tipos</option>
                    @foreach ($eventTypes as $slug => $name)
                        <option value="{{ $slug }}" @selected($eventType === $slug)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex flex-col gap-1 lg:w-44">
                <label for="code" class="form-label">Código do pedido</label>
                <input id="code" type="search" wire:model.live.debounce.300ms="code" placeholder="PED-…" class="form-control">
            </div>

            <button type="button" x-on:click="$wire.limparFiltros()" data-testid="limpar-filtros" class="btn-secondary min-h-11 lg:min-h-0">Limpar filtros</button>
        </x-slot:primary>

        <x-slot:secondary></x-slot:secondary>
    </x-filter-panel>

    <ul wire:loading.class="opacity-60" data-testid="notificacoes-lista" class="flex flex-col gap-3 transition-opacity">
        @forelse ($notifications as $notification)
            @php($description = $descriptions[$notification->pedido_event_id])
            <li
                wire:key="notificacao-{{ $notification->id }}"
                data-testid="notificacao"
                data-notification-id="{{ $notification->id }}"
                @class([
                    'card flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between',
                    'border-l-4 border-l-primary' => $notification->read_at === null,
                ])
            >
                <div class="flex min-w-0 flex-col gap-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" wire:click="abrir({{ $notification->id }})" data-testid="abrir-notificacao" class="font-semibold text-primary hover:underline">{{ $notification->pedido->code }}</button>
                        <span class="text-sm text-text-muted" data-field="obra">{{ $notification->pedido->obraLabel() }}</span>
                        @if ($notification->read_at === null)
                            <span class="badge badge-info" data-field="nao-lida">Não lida</span>
                        @endif
                    </div>
                    <strong class="text-sm font-semibold text-text" data-field="tipo">{{ $description['action'] }}</strong>
                    @if ($description['context'] !== null)
                        <p class="text-sm whitespace-pre-line break-words text-text" data-field="conteudo">{{ $description['context'] }}</p>
                    @endif
                    <p class="text-xs text-text-muted">
                        <time datetime="{{ $notification->event->created_at->toIso8601String() }}" data-field="data">{{ $description['at'] }}</time>@if ($description['actor'] !== null) · <span data-field="ator">{{ $description['actor'] }}</span>@endif
                    </p>
                </div>

                @if ($notification->read_at === null)
                    <button type="button" wire:click="markAsRead({{ $notification->id }})" data-testid="marcar-lida" class="btn-secondary min-h-11 shrink-0 lg:min-h-0">Marcar como lida</button>
                @endif
            </li>
        @empty
            <li class="empty-state py-6">Nenhuma notificação encontrada.</li>
        @endforelse
    </ul>

    {{ $notifications->links() }}
</div>
