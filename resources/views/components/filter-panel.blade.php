@props(['activeCount' => 0, 'moreActiveCount' => null])

{{--
    Compact filter panel of the pedido listings (UI-06, UI-07, NC-05).
    The open/closed state lives in Alpine, never in `<details>`: the Livewire
    morph drops attributes absent from the server HTML, so a native `open`
    would close on every filter change, while Alpine data survives morphs.
    No `wire:click` here, so the read-only Gestão listing stays free of it.
--}}
<form wire:submit.prevent aria-label="Filtros" class="filter-panel card flex flex-col gap-3" x-data="{ filtersOpen: false, moreOpen: false }">
    <button type="button" data-testid="filtros-toggle" class="btn-secondary min-h-11 w-full lg:hidden" aria-controls="filtros-painel" aria-expanded="false" x-bind:aria-expanded="filtersOpen.toString()" x-on:click="filtersOpen = ! filtersOpen">{{ $activeCount > 0 ? 'Filtros ('.$activeCount.')' : 'Filtros' }}</button>

    <div id="filtros-painel" data-open="false" x-bind:data-open="filtersOpen" class="hidden flex-col gap-3 data-[open=true]:flex lg:flex">
        <div class="flex flex-col gap-3 lg:flex-row lg:flex-wrap lg:items-end lg:gap-2">
            {{ $primary }}
        </div>

        <div class="flex flex-col gap-3 lg:flex-row lg:flex-wrap lg:items-end lg:gap-2">
            {{ $secondary }}

            @if ($moreActiveCount !== null)
                <button type="button" data-testid="mais-filtros-toggle" class="btn-secondary hidden lg:inline-flex" aria-controls="mais-filtros" aria-expanded="false" x-bind:aria-expanded="moreOpen.toString()" x-on:click="moreOpen = ! moreOpen">{{ $moreActiveCount > 0 ? 'Mais filtros ('.$moreActiveCount.')' : 'Mais filtros' }}</button>
            @endif
        </div>

        @isset($more)
            <div id="mais-filtros" data-open="false" x-bind:data-open="moreOpen" class="flex flex-col gap-3 lg:hidden lg:flex-row lg:flex-wrap lg:items-end lg:gap-2 lg:data-[open=true]:flex">
                {{ $more }}
            </div>
        @endisset
    </div>
</form>
