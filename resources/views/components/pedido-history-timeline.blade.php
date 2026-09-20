@props(['events'])

@php
    $labels = app(\App\Services\PedidoEventValuePresenter::class)->labelsFor($events);
@endphp

<ol data-testid="pedido-history-timeline" class="relative flex flex-col gap-4 border-l-2 border-slate-200 pl-5">
    @forelse ($events as $event)
        @php($eventLabels = $labels[$event->id] ?? ['previous' => null, 'new' => null])
        <li wire:key="pedido-event-{{ $event->id }}" class="relative">
            <span class="absolute -left-[27px] top-1.5 h-3 w-3 rounded-full border-2 border-white bg-sky-500 ring-1 ring-slate-200"></span>
            <div class="flex flex-wrap items-baseline gap-x-3 gap-y-0.5">
                <strong class="text-sm font-semibold text-slate-800">{{ $event->eventType->name }}</strong>
                <time datetime="{{ $event->created_at->toIso8601String() }}" class="text-xs text-slate-500">
                    {{ $event->created_at->format('d/m/Y H:i') }}
                </time>
                @if ($event->actor)
                    <span class="text-xs text-slate-500">por {{ $event->actor->name }}</span>
                @endif
            </div>
            @if ($eventLabels['previous'] !== null || $eventLabels['new'] !== null)
                <p class="mt-0.5 text-sm text-slate-600">
                    <span class="text-slate-400">{{ $eventLabels['previous'] ?? '—' }}</span>
                    <span aria-hidden="true">→</span>
                    <span class="font-medium text-slate-800">{{ $eventLabels['new'] ?? '—' }}</span>
                </p>
            @endif
        </li>
    @empty
        <li class="text-sm text-slate-500">Nenhum evento registrado.</li>
    @endforelse
</ol>
