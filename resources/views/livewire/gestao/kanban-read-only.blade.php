<div class="flex flex-col gap-5">
    <div>
        <h1 class="page-title">Kanban</h1>
        <p class="text-sm text-text-muted">Visão somente leitura do fluxo operacional de Suprimentos.</p>
    </div>

    <div class="kanban-board grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-6">
        @foreach ($columns as $column)
            <section class="kanban-column flex min-h-40 flex-col gap-3 rounded-lg border border-border bg-background p-3" data-testid="kanban-column" data-column="{{ $column->slug }}" aria-label="{{ $column->name }}">
                <h2 class="flex items-center justify-between border-b border-border pb-2 text-sm font-semibold text-text">
                    <span>{{ $column->name }}</span>
                    <span class="badge badge-neutral bg-surface">{{ $pedidosByStatus->get($column->id, collect())->count() }}</span>
                </h2>

                @foreach ($pedidosByStatus->get($column->id, collect()) as $pedido)
                    @include('livewire.gestao.pedido-card-read-only', [
                        'pedido' => $pedido,
                        'atrasoClassifier' => $atrasoClassifier,
                    ])
                @endforeach
            </section>
        @endforeach
    </div>
</div>
