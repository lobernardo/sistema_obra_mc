@props(['showCustom' => false])

{{--
    "Solicitado" period control (RF-15): neutral option + the presets of
    RequestedPeriodPreset in order; De/Até only while Personalizado.
--}}
<div class="flex flex-col gap-1 lg:w-36">
    <label for="requestedPreset" class="form-label">Solicitado</label>
    <select id="requestedPreset" wire:model.live="requestedPreset" class="form-control">
        <option value="">{{ \App\Enums\RequestedPeriodPreset::NEUTRAL_LABEL }}</option>
        @foreach (\App\Enums\RequestedPeriodPreset::cases() as $preset)
            <option value="{{ $preset->value }}">{{ $preset->label() }}</option>
        @endforeach
    </select>
</div>

@if ($showCustom)
    <div class="flex flex-col gap-1 lg:w-40">
        <label for="requestedFrom" class="form-label">De</label>
        <input id="requestedFrom" type="date" wire:model.live="requestedFrom" class="form-control">
    </div>
    <div class="flex flex-col gap-1 lg:w-40">
        <label for="requestedTo" class="form-label">Até</label>
        <input id="requestedTo" type="date" wire:model.live="requestedTo" class="form-control">
    </div>
@endif
