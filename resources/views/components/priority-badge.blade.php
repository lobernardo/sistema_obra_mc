@props(['priority'])

@if ($priority)
    @php
        $classes = match ($priority->slug) {
            \App\Enums\PrioritySlug::Baixa->value => 'bg-slate-100 text-slate-600',
            \App\Enums\PrioritySlug::Normal->value => 'bg-sky-100 text-sky-800',
            \App\Enums\PrioritySlug::Alta->value => 'bg-orange-100 text-orange-800',
            \App\Enums\PrioritySlug::Urgente->value => 'bg-red-600 text-white',
            default => 'bg-slate-100 text-slate-600',
        };
    @endphp

    <span {{ $attributes->merge(['class' => 'badge '.$classes]) }} data-priority="{{ $priority->slug }}">{{ $priority->name }}</span>
@else
    <span {{ $attributes->merge(['class' => 'text-slate-400']) }}>—</span>
@endif
