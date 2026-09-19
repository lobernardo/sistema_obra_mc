@props(['events'])

<ol data-testid="pedido-history-timeline">
    @forelse ($events as $event)
        <li wire:key="pedido-event-{{ $event->id }}">
            <strong>{{ $event->eventType->name }}</strong>
            <time datetime="{{ $event->created_at->toIso8601String() }}">
                {{ $event->created_at->format('d/m/Y H:i') }}
            </time>
            @if ($event->actor)
                <span>por {{ $event->actor->name }}</span>
            @endif
            @if (! is_null($event->previous_value) || ! is_null($event->new_value))
                <span>{{ $event->previous_value ?? '—' }} → {{ $event->new_value ?? '—' }}</span>
            @endif
        </li>
    @empty
        <li>Nenhum evento registrado.</li>
    @endforelse
</ol>
