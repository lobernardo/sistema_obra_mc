<div class="flex flex-col gap-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="page-title">Acompanhamento</h1>
            <p class="text-sm text-text-muted">Solicitações das obras às quais você está associado.</p>
        </div>
        <a href="{{ route('obra.nova-solicitacao') }}" class="btn-primary">+ Nova Solicitação</a>
    </div>

    <form wire:submit.prevent class="card grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4" aria-label="Filtros">
        <div class="flex flex-col gap-1 xl:col-span-2">
            <label for="search" class="form-label">Busca</label>
            <input id="search" type="search" wire:model.live.debounce.300ms="search" placeholder="Código, obra ou itens" class="form-control">
        </div>

        <div class="flex items-end xl:col-span-2">
            <label for="atrasoOnly" class="inline-flex items-center gap-2 rounded-md border border-border px-3 py-2 text-sm font-medium text-text">
                <input id="atrasoOnly" type="checkbox" wire:model.live="atrasoOnly" class="h-4 w-4 rounded border-border accent-primary focus:ring-2 focus:ring-focus/40">
                Somente com atraso
            </label>
        </div>

        <div class="flex flex-col gap-1">
            <label for="obraId" class="form-label">Obra</label>
            <select id="obraId" wire:model.live="obraId" class="form-control">
                <option value="">Todas as obras</option>
                @foreach ($obras as $obra)
                    <option value="{{ $obra->id }}" @selected($obraId === $obra->id)>{{ $obra->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="flex flex-col gap-1">
            <label for="statusId" class="form-label">Status</label>
            <select id="statusId" wire:model.live="statusId" class="form-control">
                <option value="">Todos os status</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->id }}" @selected($statusId === $status->id)>{{ $status->name }}</option>
                @endforeach
            </select>
        </div>

        {{-- RF-19: dispatched through Alpine's `$wire`, mirroring the two other listings. --}}
        <div class="flex justify-end md:col-span-2 xl:col-span-2">
            <button type="button" x-on:click="$wire.limparFiltros()" data-testid="limpar-filtros" class="btn-secondary">Limpar filtros</button>
        </div>
    </form>

    <div wire:loading.class="opacity-60" class="transition-opacity">
        <x-pedido-table :pedidos="$pedidos" show-route="obra.pedidos.show" empty-message="Nenhum pedido encontrado para as suas obras." />
    </div>

    {{ $pedidos->links() }}
</div>
