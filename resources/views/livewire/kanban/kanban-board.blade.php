<div class="flex flex-col gap-5">
    <div>
        <h1 class="page-title">Kanban</h1>
        <p class="text-sm text-text-muted">Arraste um card entre colunas ou use o seletor "Mover para" de cada card. Toda movimentação é validada no servidor e registrada no histórico.</p>
    </div>

    @error('status_id')
        <div role="alert" class="alert-error">{{ $message }}</div>
    @enderror

    <div class="kanban-board grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-6" wire:loading.class="opacity-60">
        @foreach ($columns as $column)
            <section
                class="kanban-column flex min-h-40 flex-col gap-3 rounded-lg border border-border bg-background p-3"
                data-testid="kanban-column"
                data-column="{{ $column->slug }}"
                wire:sort="moveCard"
                wire:sort:group="kanban"
                wire:sort:group-id="{{ $column->id }}"
                aria-label="{{ $column->name }}"
            >
                <h2 class="flex items-center justify-between border-b border-border pb-2 text-sm font-semibold text-text">
                    <span>{{ $column->name }}</span>
                    <span class="badge badge-neutral bg-surface">{{ $pedidosByStatus->get($column->id, collect())->count() }}</span>
                </h2>

                @foreach ($pedidosByStatus->get($column->id, collect()) as $pedido)
                    @include('livewire.kanban.pedido-card', [
                        'pedido' => $pedido,
                        'moveTargets' => $moveTargets,
                        'atrasoClassifier' => $atrasoClassifier,
                        'showRoute' => 'suprimentos.pedidos.show',
                    ])
                @endforeach
            </section>
        @endforeach
    </div>
</div>
