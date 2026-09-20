@props(['atrasado'])

@if ($atrasado)
    <span {{ $attributes->merge(['class' => 'badge bg-red-100 text-red-800']) }} data-atraso="true">Atrasado</span>
@else
    <span {{ $attributes->merge(['class' => 'badge bg-emerald-50 text-emerald-700']) }} data-atraso="false">No prazo</span>
@endif
