@props(['status'])

@php
    $classes = match ($status->slug) {
        \App\Enums\StatusSlug::Solicitado->value => 'bg-slate-100 text-slate-700',
        \App\Enums\StatusSlug::EmAnalise->value => 'bg-sky-100 text-sky-800',
        \App\Enums\StatusSlug::EmCompraPreparacao->value => 'bg-violet-100 text-violet-800',
        \App\Enums\StatusSlug::AguardandoEntrega->value => 'bg-amber-100 text-amber-800',
        \App\Enums\StatusSlug::Entregue->value => 'bg-emerald-100 text-emerald-800',
        \App\Enums\StatusSlug::Cancelado->value => 'bg-red-100 text-red-800',
        default => 'bg-slate-100 text-slate-700',
    };
@endphp

<span {{ $attributes->merge(['class' => 'badge '.$classes]) }} data-status="{{ $status->slug }}">{{ $status->name }}</span>
