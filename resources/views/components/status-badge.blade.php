@props(['status'])

{{-- One semantic badge variant per workflow status (§23, UI-11): institutional red is never applied to every state. --}}
@php
    $variant = match ($status->slug) {
        \App\Enums\StatusSlug::Solicitado->value => 'badge-neutral',
        \App\Enums\StatusSlug::EmAnalise->value => 'badge-info',
        \App\Enums\StatusSlug::EmCompraPreparacao->value => 'badge-secondary',
        \App\Enums\StatusSlug::AguardandoEntrega->value => 'badge-warning',
        \App\Enums\StatusSlug::Entregue->value => 'badge-concluido',
        \App\Enums\StatusSlug::Cancelado->value => 'badge-error',
        default => 'badge-neutral',
    };
@endphp

<span {{ $attributes->merge(['class' => 'badge '.$variant]) }} data-status="{{ $status->slug }}">{{ $status->name }}</span>
