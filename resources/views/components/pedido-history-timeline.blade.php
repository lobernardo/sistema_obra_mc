@props(['events', 'pedido'])

@php
    $descriptions = app(\App\Services\PedidoEventValuePresenter::class)->describeAll($events, $pedido);
@endphp

<ol data-testid="pedido-history-timeline" class="relative flex flex-col gap-4 border-l-2 border-border pl-5">
    @forelse ($events as $event)
        @php($description = $descriptions[$event->id])
        <li wire:key="pedido-event-{{ $event->id }}" data-testid="pedido-history-event" class="relative">
            {{-- The institutional accent marks only the most recent event (UI-05); older events stay neutral. --}}
            <span @class([
                'absolute -left-[27px] top-1.5 h-3 w-3 rounded-full border-2 border-surface ring-1 ring-border',
                'bg-primary' => $loop->last,
                'bg-border' => ! $loop->last,
            ])></span>
            <strong class="block text-sm font-semibold text-text">{{ $description['action'] }}</strong>
            @if ($description['context'] !== null)
                <p class="mt-0.5 text-sm whitespace-pre-line break-words text-text">{{ $description['context'] }}</p>
            @endif
            <p class="mt-0.5 text-xs text-text-muted">
                <time datetime="{{ $event->created_at->toIso8601String() }}">{{ $description['at'] }}</time>@if ($description['actor'] !== null) · {{ $description['actor'] }}@endif
            </p>
        </li>
    @empty
        <li class="empty-state py-6">Nenhum evento registrado.</li>
    @endforelse
</ol>
