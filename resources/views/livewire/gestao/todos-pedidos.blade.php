<div class="flex flex-col gap-5">
    <div>
        <h1 class="page-title">Todos os Pedidos</h1>
        <p class="text-sm text-text-muted">Consulta de todos os pedidos, somente leitura.</p>
    </div>

    <form wire:submit.prevent class="card grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
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

        <fieldset class="flex flex-col gap-2 rounded-lg border border-border p-3 md:col-span-1 xl:col-span-2">
            <legend class="px-1 text-xs font-semibold tracking-wide text-text-muted uppercase">Data necessária</legend>
            <div class="grid grid-cols-2 gap-3">
                <div class="flex flex-col gap-1">
                    <label for="neededAtFrom" class="text-xs text-text-muted">De</label>
                    <input id="neededAtFrom" type="date" wire:model.live="neededAtFrom" class="form-control">
                </div>
                <div class="flex flex-col gap-1">
                    <label for="neededAtTo" class="text-xs text-text-muted">Até</label>
                    <input id="neededAtTo" type="date" wire:model.live="neededAtTo" class="form-control">
                </div>
            </div>
        </fieldset>

        <fieldset class="flex flex-col gap-2 rounded-lg border border-border p-3 md:col-span-1 xl:col-span-2">
            <legend class="px-1 text-xs font-semibold tracking-wide text-text-muted uppercase">Solicitado</legend>
            <div class="grid grid-cols-2 gap-3">
                <div class="flex flex-col gap-1">
                    <label for="requestedFrom" class="text-xs text-text-muted">A partir de</label>
                    <input id="requestedFrom" type="date" wire:model.live="requestedFrom" class="form-control">
                </div>
                <div class="flex flex-col gap-1">
                    <label for="requestedTo" class="text-xs text-text-muted">Até</label>
                    <input id="requestedTo" type="date" wire:model.live="requestedTo" class="form-control">
                </div>
            </div>
        </fieldset>
    </form>

    <div wire:loading.class="opacity-60" class="transition-opacity">
        <x-pedido-table :pedidos="$pedidos" show-route="gestao.pedidos.show" />
    </div>

    {{ $pedidos->links() }}
</div>
