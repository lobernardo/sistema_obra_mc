<div class="flex flex-col gap-5">
    @if ($showRegistrationNotice)
        <p role="status" class="alert-info" data-registration-notice>Conta criada. O acesso às obras depende de associação feita pela Gestão ou por Suprimentos.</p>
    @endif

    <div>
        <h1 class="page-title">Acompanhamento</h1>
        <p class="text-sm text-text-muted">Solicitações das obras às quais você está associado.</p>
    </div>

    <x-filter-panel :active-count="$this->activeFilterCount()" :more-active-count="null">
        <x-slot:primary>
            <div class="flex flex-col gap-1 lg:w-36">
                <label for="obraId" class="form-label">Obra</label>
                <select id="obraId" wire:model.live="obraId" class="form-control">
                    <option value="">Todas as obras</option>
                    @foreach ($obras as $obra)
                        <option value="{{ $obra->id }}" @selected($obraId === $obra->id)>{{ $obra->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex flex-col gap-1 lg:w-36">
                <label for="statusId" class="form-label">Status</label>
                <select id="statusId" wire:model.live="statusId" class="form-control">
                    <option value="">Todos os status</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->id }}" @selected($statusId === $status->id)>{{ $status->name }}</option>
                    @endforeach
                </select>
            </div>

            <x-solicitado-filter :show-custom="$this->showsCustomRequestedPeriod()" />

            {{-- RF-19: dispatched through Alpine's `$wire`, mirroring the two other listings. --}}
            <button type="button" x-on:click="$wire.limparFiltros()" data-testid="limpar-filtros" class="btn-secondary min-h-11 lg:min-h-0">Limpar filtros</button>
        </x-slot:primary>

        <x-slot:secondary>
            <div class="flex flex-col gap-1 lg:w-80">
                <label for="search" class="form-label">Busca</label>
                <input id="search" type="search" wire:model.live.debounce.300ms="search" placeholder="Código, obra ou descrição" class="form-control">
            </div>

            <div class="flex items-end">
                <label for="atrasoOnly" class="inline-flex min-h-11 items-center gap-2 rounded-md border border-border px-3 py-2 text-sm font-medium text-text lg:min-h-0">
                    <input id="atrasoOnly" type="checkbox" wire:model.live="atrasoOnly" class="h-4 w-4 rounded border-border accent-primary focus:ring-2 focus:ring-focus/40">
                    Somente com atraso
                </label>
            </div>
        </x-slot:secondary>
    </x-filter-panel>

    <div wire:loading.class="opacity-60" class="transition-opacity">
        <x-pedido-table :pedidos="$pedidos" show-route="obra.pedidos.show" empty-message="Nenhum pedido encontrado para as suas obras." />
    </div>

    {{ $pedidos->links() }}
</div>
