<div>
    <h1>Kanban</h1>

    <div class="kanban-board">
        @foreach ($columns as $column)
            <div class="kanban-column" data-testid="kanban-column" data-column="{{ $column->slug }}">
                <h2>{{ $column->name }}</h2>

                @foreach ($pedidosByStatus->get($column->id, collect()) as $pedido)
                    @include('livewire.gestao.pedido-card-read-only', [
                        'pedido' => $pedido,
                        'atrasoClassifier' => $atrasoClassifier,
                    ])
                @endforeach
            </div>
        @endforeach
    </div>
</div>
