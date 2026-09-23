{{-- $pedido, $moveTargets, $atrasoClassifier, $showRoute are passed explicitly from kanban-board.blade.php's @include --}}
@php
    $atrasado = $atrasoClassifier::isAtrasado($pedido);
    $isTerminal = \App\Enums\StatusSlug::from($pedido->status->slug)->isTerminal();
@endphp
<article
    wire:key="pedido-{{ $pedido->id }}"
    @class([
        'pedido-atrasado border-y-border border-r-border' => $atrasado,
        'border-border bg-surface' => ! $atrasado,
        'flex flex-col gap-2 rounded-lg border p-3 text-sm shadow-sm',
    ])
    wire:sort:item="{{ $pedido->id }}"
    data-testid="pedido-card"
>
    <div class="flex items-start justify-between gap-2">
        <a href="{{ route($showRoute, $pedido) }}" data-field="code" class="font-semibold text-primary hover:underline">{{ $pedido->code }}</a>
        <x-priority-badge :priority="$pedido->priority" data-field="priority" />
    </div>
    <p data-field="obra" class="font-medium text-text">{{ $pedido->obraLabel() }}</p>
    <p data-field="items" class="line-clamp-2 text-xs text-text-muted" title="{{ $pedido->items_description }}">{{ $pedido->items_description }}</p>
    <dl class="grid grid-cols-2 gap-x-2 gap-y-1 text-xs">
        <dt class="text-text-muted">Preciso para</dt>
        <dd data-field="needed_at" class="text-right text-text">{{ $pedido->needed_at->format('d/m/Y') }}</dd>
        <dt class="text-text-muted">Responsável</dt>
        <dd data-field="responsible" class="truncate text-right text-text" title="{{ $pedido->responsible?->name }}">{{ $pedido->responsible?->name ?? '—' }}</dd>
        <dt class="text-text-muted">Previsão de entrega</dt>
        <dd data-field="expected_delivery_at" class="text-right text-text">{{ $pedido->expected_delivery_at?->format('d/m/Y') ?? '—' }}</dd>
    </dl>
    <div class="flex items-center justify-between gap-2" data-field="atraso">
        <x-atraso-indicator :atrasado="$atrasado" />
    </div>

    @unless ($isTerminal)
        <label class="flex flex-col gap-1 border-t border-border pt-2 text-xs text-text-muted">
            Mover para
            <select
                aria-label="Mover pedido {{ $pedido->code }} para outro status"
                wire:change="moveViaControl({{ $pedido->id }}, $event.target.value)"
                class="form-control py-1 text-xs"
            >
                @foreach ($moveTargets as $column)
                    <option value="{{ $column->id }}" @selected($column->id === $pedido->status_id)>{{ $column->name }}</option>
                @endforeach
            </select>
        </label>
    @endunless
</article>
