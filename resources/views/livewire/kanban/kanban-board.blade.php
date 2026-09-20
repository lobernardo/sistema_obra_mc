<div class="flex flex-col gap-5">
    <div>
        <h1 class="page-title">Kanban</h1>
        <p class="text-sm text-slate-500">Arraste um card entre colunas ou use o seletor "Mover para" de cada card. Toda movimentação é validada no servidor e registrada no histórico.</p>
    </div>

    @error('status_id')
        <div role="alert" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $message }}</div>
    @enderror

    <div class="kanban-board grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-5" wire:loading.class="opacity-60">
        @foreach ($columns as $column)
            <section
                class="kanban-column flex min-h-40 flex-col gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3"
                data-testid="kanban-column"
                data-column="{{ $column->slug }}"
                wire:sort="moveCard"
                wire:sort:group="kanban"
                wire:sort:group-id="{{ $column->id }}"
                aria-label="{{ $column->name }}"
            >
                <h2 class="flex items-center justify-between text-sm font-semibold text-slate-700">
                    <span>{{ $column->name }}</span>
                    <span class="badge bg-white text-slate-500 ring-1 ring-slate-200">{{ $pedidosByStatus->get($column->id, collect())->count() }}</span>
                </h2>

                @foreach ($pedidosByStatus->get($column->id, collect()) as $pedido)
                    @include('livewire.kanban.pedido-card', [
                        'pedido' => $pedido,
                        'columns' => $columns,
                        'atrasoClassifier' => $atrasoClassifier,
                        'showRoute' => 'suprimentos.pedidos.show',
                    ])
                @endforeach
            </section>
        @endforeach
    </div>
</div>
