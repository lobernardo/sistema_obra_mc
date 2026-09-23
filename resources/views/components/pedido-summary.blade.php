@props(['pedido'])

@php
    $atrasado = \App\Domain\Pedidos\AtrasoClassifier::isAtrasado($pedido);
@endphp

<dl data-testid="pedido-summary" class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2 lg:grid-cols-3">
    <div>
        <dt class="text-xs font-semibold tracking-wide text-text-muted uppercase">Obra</dt>
        <dd class="mt-0.5 text-sm text-text">{{ $pedido->obraLabel() }}</dd>
    </div>
    <div>
        <dt class="text-xs font-semibold tracking-wide text-text-muted uppercase">Solicitante</dt>
        <dd class="mt-0.5 text-sm text-text">{{ $pedido->requester?->name ?? '—' }}</dd>
    </div>
    <div>
        <dt class="text-xs font-semibold tracking-wide text-text-muted uppercase">Data da solicitação</dt>
        <dd class="mt-0.5 text-sm text-text">{{ $pedido->requested_at?->format('d/m/Y H:i') ?? '—' }}</dd>
    </div>
    <div>
        <dt class="text-xs font-semibold tracking-wide text-text-muted uppercase">Data necessária</dt>
        <dd class="mt-0.5 text-sm text-text">{{ $pedido->needed_at->format('d/m/Y') }}</dd>
    </div>
    <div>
        <dt class="text-xs font-semibold tracking-wide text-text-muted uppercase">Status</dt>
        <dd class="mt-0.5"><x-status-badge :status="$pedido->status" /></dd>
    </div>
    <div>
        <dt class="text-xs font-semibold tracking-wide text-text-muted uppercase">Prioridade</dt>
        <dd class="mt-0.5"><x-priority-badge :priority="$pedido->priority" /></dd>
    </div>
    <div>
        <dt class="text-xs font-semibold tracking-wide text-text-muted uppercase">Responsável</dt>
        <dd class="mt-0.5 text-sm text-text">{{ $pedido->responsible?->name ?? '—' }}</dd>
    </div>
    <div>
        <dt class="text-xs font-semibold tracking-wide text-text-muted uppercase">Previsão de entrega</dt>
        <dd class="mt-0.5 text-sm text-text">{{ $pedido->expected_delivery_at?->format('d/m/Y') ?? '—' }}</dd>
    </div>
    <div>
        <dt class="text-xs font-semibold tracking-wide text-text-muted uppercase">Atraso</dt>
        <dd class="mt-0.5"><x-atraso-indicator :atrasado="$atrasado" /></dd>
    </div>
    <div class="sm:col-span-2 lg:col-span-3">
        <dt class="text-xs font-semibold tracking-wide text-text-muted uppercase">Itens e quantidades</dt>
        <dd class="mt-1 rounded-md bg-background px-3 py-2 text-sm whitespace-pre-line text-text">{{ $pedido->items_description }}</dd>
    </div>
</dl>
