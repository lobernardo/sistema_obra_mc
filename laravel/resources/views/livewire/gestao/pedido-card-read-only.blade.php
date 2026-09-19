{{-- $pedido, $atrasoClassifier are passed explicitly from kanban-read-only.blade.php's @include --}}
<div
    wire:key="pedido-{{ $pedido->id }}"
    data-testid="pedido-card"
    @class(['pedido-atrasado' => $atrasoClassifier::isAtrasado($pedido)])
>
    <p data-field="code">{{ $pedido->code }}</p>
    <p data-field="obra">{{ $pedido->obra->name }}</p>
    <p data-field="needed_at">{{ $pedido->needed_at->format('d/m/Y') }}</p>
    <p data-field="priority">{{ $pedido->priority?->name ?? '—' }}</p>
    <p data-field="responsible">{{ $pedido->responsible?->name ?? '—' }}</p>
    <p data-field="expected_delivery_at">{{ $pedido->expected_delivery_at?->format('d/m/Y') ?? '—' }}</p>
    <p data-field="atraso">
        @if ($atrasoClassifier::isAtrasado($pedido))
            <span data-atraso="true">Atrasado</span>
        @else
            <span data-atraso="false">No prazo</span>
        @endif
    </p>
</div>
