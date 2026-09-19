<div>
    <h1>Acompanhamento</h1>

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
                        <a href="{{ route('obra.pedidos.show', $pedido) }}">{{ $pedido->code }}</a>
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
                    <td colspan="7">Nenhum pedido encontrado para as suas obras.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{ $pedidos->links() }}
</div>
