@props(['obra'])

{{-- "INATIVA" badge (obras-ativacao-exclusao UI-01, UI-07): the text is the signal, not the colour. Renders nothing for an active obra. --}}
@if (! $obra->isActive())
    <span {{ $attributes->merge(['class' => 'badge badge-neutral']) }} data-obra-inativa>INATIVA</span>
@endif
