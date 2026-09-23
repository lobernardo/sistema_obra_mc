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
        <dd class="mt-0.5 text-sm text-text">{{ \App\Support\LocalTime::formatDateTime($pedido->requested_at) }}</dd>
    </div>
    <div>
        <dt class="text-xs font-semibold tracking-wide text-text-muted uppercase">Preciso para</dt>
        <dd class="mt-0.5 text-sm text-text">{{ $pedido->needed_at->format('d/m/Y') }}</dd>
    </div>
    <div>
        <dt class="text-xs font-semibold tracking-wide text-text-muted uppercase">Data prevista</dt>
        <dd class="mt-0.5 text-sm text-text">{{ $pedido->dataPrevistaLabel() }}</dd>
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
        <dt class="text-xs font-semibold tracking-wide text-text-muted uppercase">Descrição</dt>
        <dd class="mt-1 rounded-md bg-background px-3 py-2 text-sm whitespace-pre-line text-text">{{ $pedido->items_description }}</dd>
    </div>
    <div class="sm:col-span-2 lg:col-span-3">
        <dt class="text-xs font-semibold tracking-wide text-text-muted uppercase">Anexos</dt>
        <dd class="mt-1">
            @if ($pedido->attachments->isEmpty())
                <p class="text-sm text-text-muted">Nenhum anexo.</p>
            @else
                <ul data-testid="pedido-attachments" class="flex flex-col divide-y divide-border rounded-md border border-border">
                    @foreach ($pedido->attachments as $attachment)
                        @php
                            $sizeLabel = $attachment->size_bytes >= 1048576
                                ? number_format($attachment->size_bytes / 1048576, 1, ',', '.').' MB'
                                : number_format(max(1, (int) ceil($attachment->size_bytes / 1024)), 0, ',', '.').' KB';
                        @endphp
                        <li wire:key="pedido-attachment-{{ $attachment->id }}" data-testid="pedido-attachment" class="flex flex-wrap items-center gap-x-3 gap-y-1 px-3 py-2 text-sm">
                            <a href="{{ route('pedidos.anexos.download', [$pedido, $attachment]) }}" class="font-medium break-all text-primary hover:underline">{{ $attachment->original_name }}</a>
                            @if ($attachment->kind === \App\Enums\PedidoAttachmentKind::Romaneio)
                                <span class="badge badge-info" data-attachment-kind="romaneio">Romaneio</span>
                            @endif
                            <span class="text-xs text-text-muted">{{ $sizeLabel }} · {{ $attachment->uploader?->name ?? '—' }} · {{ \App\Support\LocalTime::formatDateTime($attachment->created_at) }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </dd>
    </div>
</dl>
