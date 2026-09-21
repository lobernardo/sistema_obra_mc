@props(['priority'])

@if ($priority)
    {{-- Distinct semantic variant per priority (§23, UI-11); only "urgente" is solid. --}}
    @php
        $variant = match ($priority->slug) {
            \App\Enums\PrioritySlug::Baixa->value => 'badge-neutral',
            \App\Enums\PrioritySlug::Normal->value => 'badge-info',
            \App\Enums\PrioritySlug::Alta->value => 'badge-warning',
            \App\Enums\PrioritySlug::Urgente->value => 'bg-error text-white',
            default => 'badge-neutral',
        };
    @endphp

    <span {{ $attributes->merge(['class' => 'badge '.$variant]) }} data-priority="{{ $priority->slug }}">{{ $priority->name }}</span>
@else
    <span {{ $attributes->merge(['class' => 'text-text-muted']) }}>—</span>
@endif
