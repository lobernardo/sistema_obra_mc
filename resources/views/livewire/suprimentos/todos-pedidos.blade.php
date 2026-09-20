<div class="flex flex-col gap-5">
    <div>
        <h1 class="page-title">Todos os Pedidos</h1>
        <p class="text-sm text-slate-500">Visão operacional de todas as obras. Abra um pedido para definir responsável, prioridade, previsão e status.</p>
    </div>

    <form wire:submit.prevent class="card grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div class="flex flex-col gap-1 xl:col-span-2">
            <label for="search" class="form-label">Busca</label>
            <input id="search" type="search" wire:model.live.debounce.300ms="search" placeholder="Código, obra ou itens" class="form-control">
        </div>

        <div class="flex items-end xl:col-span-2">
            <label for="atrasoOnly" class="inline-flex items-center gap-2 rounded-md border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700">
                <input id="atrasoOnly" type="checkbox" wire:model.live="atrasoOnly" class="h-4 w-4 rounded border-slate-300 text-sky-600 focus:ring-sky-500">
                Somente com atraso
            </label>
        </div>

        <fieldset class="flex flex-col gap-2 rounded-lg border border-slate-200 p-3 md:col-span-1 xl:col-span-2">
            <legend class="px-1 text-xs font-semibold tracking-wide text-slate-500 uppercase">Data necessária</legend>
            <div class="grid grid-cols-2 gap-3">
                <div class="flex flex-col gap-1">
                    <label for="neededAtFrom" class="text-xs text-slate-600">De</label>
                    <input id="neededAtFrom" type="date" wire:model.live="neededAtFrom" class="form-control">
                </div>
                <div class="flex flex-col gap-1">
                    <label for="neededAtTo" class="text-xs text-slate-600">Até</label>
                    <input id="neededAtTo" type="date" wire:model.live="neededAtTo" class="form-control">
                </div>
            </div>
        </fieldset>

        <fieldset class="flex flex-col gap-2 rounded-lg border border-slate-200 p-3 md:col-span-1 xl:col-span-2">
            <legend class="px-1 text-xs font-semibold tracking-wide text-slate-500 uppercase">Solicitado</legend>
            <div class="grid grid-cols-2 gap-3">
                <div class="flex flex-col gap-1">
                    <label for="requestedFrom" class="text-xs text-slate-600">A partir de</label>
                    <input id="requestedFrom" type="date" wire:model.live="requestedFrom" class="form-control">
                </div>
                <div class="flex flex-col gap-1">
                    <label for="requestedTo" class="text-xs text-slate-600">Até</label>
                    <input id="requestedTo" type="date" wire:model.live="requestedTo" class="form-control">
                </div>
            </div>
        </fieldset>
    </form>

    <div wire:loading.class="opacity-60" class="transition-opacity">
        <x-pedido-table :pedidos="$pedidos" show-route="suprimentos.pedidos.show" />
    </div>

    {{ $pedidos->links() }}
</div>
