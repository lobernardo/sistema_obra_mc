{{-- $pedido, $atrasoClassifier are passed explicitly from kanban-read-only.blade.php's @include --}}
@php($atrasado = $atrasoClassifier::isAtrasado($pedido))
<article
    wire:key="pedido-{{ $pedido->id }}"
    @class([
        'pedido-atrasado border-red-200 bg-red-50' => $atrasado,
        'border-slate-200 bg-white' => ! $atrasado,
        'flex flex-col gap-2 rounded-lg border p-3 text-sm shadow-sm',
    ])
    data-testid="pedido-card"
>
    <div class="flex items-start justify-between gap-2">
        <a href="{{ route('gestao.pedidos.show', $pedido) }}" data-field="code" class="font-semibold text-sky-700 hover:underline">{{ $pedido->code }}</a>
        <x-priority-badge :priority="$pedido->priority" data-field="priority" />
    </div>
    <p data-field="obra" class="font-medium text-slate-800">{{ $pedido->obra->name }}</p>
    <p data-field="items" class="line-clamp-2 text-xs text-slate-500" title="{{ $pedido->items_description }}">{{ $pedido->items_description }}</p>
    <dl class="grid grid-cols-2 gap-x-2 gap-y-1 text-xs">
        <dt class="text-slate-500">Necessário em</dt>
        <dd data-field="needed_at" class="text-right text-slate-800">{{ $pedido->needed_at->format('d/m/Y') }}</dd>
        <dt class="text-slate-500">Responsável</dt>
        <dd data-field="responsible" class="truncate text-right text-slate-800" title="{{ $pedido->responsible?->name }}">{{ $pedido->responsible?->name ?? '—' }}</dd>
        <dt class="text-slate-500">Previsão</dt>
        <dd data-field="expected_delivery_at" class="text-right text-slate-800">{{ $pedido->expected_delivery_at?->format('d/m/Y') ?? '—' }}</dd>
    </dl>
    <div class="flex items-center justify-between gap-2" data-field="atraso">
        <x-atraso-indicator :atrasado="$atrasado" />
    </div>
</article>
