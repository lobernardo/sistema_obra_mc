@props(['pedidos', 'showRoute', 'emptyMessage' => 'Nenhum pedido encontrado.'])

<div class="overflow-x-auto rounded-lg border border-border bg-surface shadow-sm">
    <table class="data-table">
        <thead>
            <tr>
                <th>Código</th>
                <th>Obra</th>
                <th>Data necessária</th>
                <th>Status</th>
                <th>Prioridade</th>
                <th>Responsável</th>
                <th>Previsão</th>
                <th>Atraso</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($pedidos as $pedido)
                @php($atrasado = \App\Domain\Pedidos\AtrasoClassifier::isAtrasado($pedido))
                <tr wire:key="pedido-{{ $pedido->id }}" data-pedido-code="{{ $pedido->code }}" @class(['pedido-atrasado' => $atrasado])>
                    <td>
                        <a href="{{ route($showRoute, $pedido) }}" class="font-semibold text-primary hover:underline">{{ $pedido->code }}</a>
                    </td>
                    <td>{{ $pedido->obra->name }}</td>
                    <td class="whitespace-nowrap">{{ $pedido->needed_at->format('d/m/Y') }}</td>
                    <td><x-status-badge :status="$pedido->status" /></td>
                    <td><x-priority-badge :priority="$pedido->priority" /></td>
                    <td>{{ $pedido->responsible?->name ?? '—' }}</td>
                    <td class="whitespace-nowrap">{{ $pedido->expected_delivery_at?->format('d/m/Y') ?? '—' }}</td>
                    <td><x-atraso-indicator :atrasado="$atrasado" /></td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="p-3"><div class="empty-state">{{ $emptyMessage }}</div></td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
