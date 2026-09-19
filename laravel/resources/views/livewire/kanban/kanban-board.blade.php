<div>
    <h1>Kanban</h1>

    <div class="kanban-board">
        @foreach ($columns as $column)
            <div
                class="kanban-column"
                data-testid="kanban-column"
                data-column="{{ $column->slug }}"
                wire:sort="moveCard"
                wire:sort:group="kanban"
                wire:sort:group-id="{{ $column->id }}"
            >
                <h2>{{ $column->name }}</h2>

                @foreach ($pedidosByStatus->get($column->id, collect()) as $pedido)
                    @include('livewire.kanban.pedido-card', [
                        'pedido' => $pedido,
                        'columns' => $columns,
                        'atrasoClassifier' => $atrasoClassifier,
                    ])
                @endforeach
            </div>
        @endforeach
    </div>
</div>
