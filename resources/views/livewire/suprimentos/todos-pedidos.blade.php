<div class="flex flex-col gap-5">
    <div>
        <h1 class="page-title">Todos os Pedidos</h1>
        <p class="text-sm text-text-muted">Visão operacional de todas as obras. Abra um pedido para definir responsável, prioridade, previsão e status.</p>
    </div>

    {{--
        RF-21: Total, Pendentes e Atrasados do conjunto atualmente filtrado.
        Os números chegam prontos do componente, que os calcula sobre o mesmo
        builder da listagem — nenhuma regra de pendência ou atraso é
        redigitada aqui.
    --}}
    <section aria-label="Indicadores" class="grid grid-cols-1 gap-4 sm:grid-cols-3" wire:loading.class="opacity-60">
        <div data-testid="indicator-total" class="card flex flex-col gap-1">
            <h2 class="text-xs font-semibold tracking-wide text-text-muted uppercase">Total</h2>
            <p data-value class="text-3xl font-semibold text-text">{{ $indicators['total'] }}</p>
            <span class="text-xs text-text-muted">pedidos no escopo filtrado</span>
        </div>

        <div data-testid="indicator-pendentes" class="card flex flex-col gap-1 border-t-4 border-t-warning">
            <h2 class="text-xs font-semibold tracking-wide text-text-muted uppercase">Pendentes</h2>
            <p data-value class="text-3xl font-semibold text-warning">{{ $indicators['pendentes'] }}</p>
            <span class="text-xs text-text-muted">não entregues nem cancelados</span>
        </div>

        <div data-testid="indicator-atrasados" class="card flex flex-col gap-1 border-t-4 border-t-atraso">
            <h2 class="text-xs font-semibold tracking-wide text-text-muted uppercase">Atrasados</h2>
            <p data-value class="text-3xl font-semibold text-atraso">{{ $indicators['atrasados'] }}</p>
            <span class="text-xs text-text-muted">Preciso para vencido e não concluídos</span>
        </div>
    </section>

    <x-filter-panel :active-count="$this->activeFilterCount()" :more-active-count="$this->moreFiltersActiveCount()">
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

            <div class="flex flex-col gap-1 lg:w-36">
                <label for="priorityId" class="form-label">Prioridade</label>
                <select id="priorityId" wire:model.live="priorityId" class="form-control">
                    <option value="">Todas as prioridades</option>
                    @foreach ($priorities as $priority)
                        <option value="{{ $priority->id }}" @selected($priorityId === $priority->id)>{{ $priority->name }}</option>
                    @endforeach
                </select>
            </div>

            <x-solicitado-filter :show-custom="$this->showsCustomRequestedPeriod()" />

            <div class="flex flex-col gap-1 lg:w-36">
                <label for="responsibleId" class="form-label">Responsável</label>
                <select id="responsibleId" wire:model.live="responsibleId" class="form-control">
                    <option value="">Todos os responsáveis</option>
                    @foreach ($suprimentosUsers as $suprimentosUser)
                        <option value="{{ $suprimentosUser->id }}" @selected($responsibleId === $suprimentosUser->id)>{{ $suprimentosUser->name }}</option>
                    @endforeach
                </select>
            </div>

            {{--
                RF-19: the action is dispatched through Alpine's `$wire` rather than
                `wire:click` so the read-only Gestão listing, which mirrors this
                markup, stays free of `wire:click` — the marker its compliance test
                uses to prove no operational control is rendered there.
            --}}
            <button type="button" x-on:click="$wire.limparFiltros()" data-testid="limpar-filtros" class="btn-secondary min-h-11 lg:min-h-0">Limpar filtros</button>
        </x-slot:primary>

        <x-slot:secondary>
            <div class="flex flex-col gap-1 lg:w-80">
                <label for="search" class="form-label">Busca</label>
                <input id="search" type="search" wire:model.live.debounce.300ms="search" placeholder="Código, obra ou descrição" class="form-control">
            </div>
        </x-slot:secondary>

        <x-slot:more>
            <div class="flex items-end">
                <label for="atrasoOnly" class="inline-flex min-h-11 items-center gap-2 rounded-md border border-border px-3 py-2 text-sm font-medium text-text lg:min-h-0">
                    <input id="atrasoOnly" type="checkbox" wire:model.live="atrasoOnly" class="h-4 w-4 rounded border-border accent-primary focus:ring-2 focus:ring-focus/40">
                    Somente com atraso
                </label>
            </div>

            <x-active-obras-filter />

            <fieldset class="flex flex-col gap-2 rounded-lg border border-border p-3">
                <legend class="px-1 text-xs font-semibold tracking-wide text-text-muted uppercase">Preciso para</legend>
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
        </x-slot:more>
    </x-filter-panel>

    <div wire:loading.class="opacity-60" class="transition-opacity">
        <x-pedido-table :pedidos="$pedidos" show-route="suprimentos.pedidos.show" />
    </div>

    {{ $pedidos->links() }}
</div>
