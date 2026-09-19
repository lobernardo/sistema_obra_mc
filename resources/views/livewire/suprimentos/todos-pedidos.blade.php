<div>
    <h1>Todos os Pedidos</h1>

    <form wire:submit.prevent>
        <div>
            <label for="search">Busca</label>
            <input id="search" type="search" wire:model.live.debounce.300ms="search" placeholder="Código, obra ou itens">
        </div>

        <div>
            <label for="atrasoOnly">
                <input id="atrasoOnly" type="checkbox" wire:model.live="atrasoOnly">
                Atraso
            </label>
        </div>

        <fieldset>
            <legend>Data necessária</legend>
            <label for="neededAtFrom">De</label>
            <input id="neededAtFrom" type="date" wire:model.live="neededAtFrom">
            <label for="neededAtTo">Até</label>
            <input id="neededAtTo" type="date" wire:model.live="neededAtTo">
        </fieldset>

        <fieldset>
            <legend>Solicitado</legend>
            <label for="requestedFrom">A partir de</label>
            <input id="requestedFrom" type="date" wire:model.live="requestedFrom">
            <label for="requestedTo">Até</label>
            <input id="requestedTo" type="date" wire:model.live="requestedTo">
        </fieldset>
    </form>

    <table>
        <thead>
            <tr>
                <th>Código</th>
                <th>Obra</th>
                <th>Status</th>
                <th>Prioridade</th>
                <th>Responsável</th>
                <th>Previsão</th>
                <th>Atraso</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($pedidos as $pedido)
                <tr wire:key="pedido-{{ $pedido->id }}" @class(['pedido-atrasado' => $atrasoClassifier::isAtrasado($pedido)])>
                    <td>
                        <a href="{{ route('suprimentos.pedidos.show', $pedido) }}">{{ $pedido->code }}</a>
                    </td>
                    <td>{{ $pedido->obra->name }}</td>
                    <td>{{ $pedido->status->name }}</td>
                    <td>{{ $pedido->priority?->name ?? '—' }}</td>
                    <td>{{ $pedido->responsible?->name ?? '—' }}</td>
                    <td>{{ $pedido->expected_delivery_at?->format('d/m/Y') ?? '—' }}</td>
                    <td>
                        @if ($atrasoClassifier::isAtrasado($pedido))
                            <span data-atraso="true">Atrasado</span>
                        @else
                            <span data-atraso="false">No prazo</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">Nenhum pedido encontrado.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{ $pedidos->links() }}
</div>
