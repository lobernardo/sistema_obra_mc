@props(['atrasado'])

@if ($atrasado)
    <span {{ $attributes->merge(['class' => 'badge badge-atraso']) }} data-atraso="true">Atrasado</span>
@else
    <span {{ $attributes->merge(['class' => 'badge badge-success']) }} data-atraso="false">No prazo</span>
@endif
