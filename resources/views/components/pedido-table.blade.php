@props(['pedidos', 'showRoute', 'emptyMessage' => 'Nenhum pedido encontrado.'])

@php
    /**
     * Declared once (CT-06): the header row, the rendered cells and the
     * empty-state `colspan` all derive from this list, so the three can
     * never drift apart (RF-13).
     */
    $columns = ['Código', 'Obra', 'Itens', 'Solicitado em', 'Data necessária', 'Status', 'Prioridade', 'Responsável', 'Previsão', 'Atraso'];

    /** RF-11: at most 90 visible characters (89 plus the ellipsis); `title` carries the untruncated text. */
    $itemsMaxVisibleLength = 90;
@endphp

<div>
    {{-- UI-01: the table is the at/above-`md:` rendering path. --}}
    <div class="hidden overflow-x-auto rounded-lg border border-border bg-surface shadow-sm md:block">
        <table class="data-table">
            <thead>
                <tr>
                    @foreach ($columns as $column)
                        <th>{{ $column }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($pedidos as $pedido)
                    @php($atrasado = \App\Domain\Pedidos\AtrasoClassifier::isAtrasado($pedido))
                    <tr wire:key="pedido-{{ $pedido->id }}" data-pedido-code="{{ $pedido->code }}" @class(['pedido-atrasado' => $atrasado])>
                        <td>
                            <a href="{{ route($showRoute, $pedido) }}" class="font-semibold text-primary hover:underline">{{ $pedido->code }}</a>
                        </td>
                        <td>{{ $pedido->obraLabel() }}</td>
                        <td data-field="items" title="{{ $pedido->items_description }}">{{ \Illuminate\Support\Str::limit($pedido->items_description, $itemsMaxVisibleLength - 1, '…') }}</td>
                        <td class="whitespace-nowrap">{{ $pedido->requested_at->format('d/m/Y') }}</td>
                        <td class="whitespace-nowrap">{{ $pedido->needed_at->format('d/m/Y') }}</td>
                        <td><x-status-badge :status="$pedido->status" /></td>
                        <td><x-priority-badge :priority="$pedido->priority" /></td>
                        <td>{{ $pedido->responsible?->name ?? '—' }}</td>
                        <td class="whitespace-nowrap">{{ $pedido->expected_delivery_at?->format('d/m/Y') ?? '—' }}</td>
                        <td><x-atraso-indicator :atrasado="$atrasado" /></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($columns) }}" class="p-3"><div class="empty-state">{{ $emptyMessage }}</div></td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- UI-01/UI-05: below `md:`, the same collection renders as stacked cards, reusing the existing card, badge and atraso patterns. --}}
    <div class="flex flex-col gap-3 md:hidden" data-testid="pedido-card-list">
        @forelse ($pedidos as $pedido)
            @php($atrasado = \App\Domain\Pedidos\AtrasoClassifier::isAtrasado($pedido))
            <article
                wire:key="pedido-card-{{ $pedido->id }}"
                data-pedido-code="{{ $pedido->code }}"
                data-testid="pedido-card"
                @class([
                    'pedido-atrasado border-y-border border-r-border' => $atrasado,
                    'border-border bg-surface' => ! $atrasado,
                    'flex flex-col gap-2 rounded-lg border p-3 text-sm shadow-sm',
                ])
            >
                <div class="flex items-start justify-between gap-2">
                    <a href="{{ route($showRoute, $pedido) }}" class="font-semibold text-primary hover:underline">{{ $pedido->code }}</a>
                    <x-status-badge :status="$pedido->status" />
                </div>
                <p class="font-medium text-text">{{ $pedido->obraLabel() }}</p>
                <p data-field="items" class="text-xs text-text-muted" title="{{ $pedido->items_description }}">{{ \Illuminate\Support\Str::limit($pedido->items_description, $itemsMaxVisibleLength - 1, '…') }}</p>
                <dl class="grid grid-cols-2 gap-x-2 gap-y-1 text-xs">
                    <dt class="text-text-muted">Solicitado em</dt>
                    <dd class="text-right text-text">{{ $pedido->requested_at->format('d/m/Y') }}</dd>
                    <dt class="text-text-muted">Data necessária</dt>
                    <dd class="text-right text-text">{{ $pedido->needed_at->format('d/m/Y') }}</dd>
                    <dt class="text-text-muted">Prioridade</dt>
                    <dd class="text-right"><x-priority-badge :priority="$pedido->priority" /></dd>
                    <dt class="text-text-muted">Responsável</dt>
                    <dd class="truncate text-right text-text" title="{{ $pedido->responsible?->name }}">{{ $pedido->responsible?->name ?? '—' }}</dd>
                    <dt class="text-text-muted">Previsão</dt>
                    <dd class="text-right text-text">{{ $pedido->expected_delivery_at?->format('d/m/Y') ?? '—' }}</dd>
                </dl>
                <div><x-atraso-indicator :atrasado="$atrasado" /></div>
            </article>
        @empty
            <div class="rounded-lg border border-border bg-surface p-3 shadow-sm"><div class="empty-state">{{ $emptyMessage }}</div></div>
        @endforelse
    </div>
</div>
