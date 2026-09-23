<?php

use Illuminate\Support\Facades\Blade;

/*
|--------------------------------------------------------------------------
| T13 — x-filter-panel, x-solicitado-filter, x-active-obras-filter
|--------------------------------------------------------------------------
*/

function renderFilterPanel(int $activeCount, ?int $moreActiveCount, bool $withMore = true): string
{
    $more = $withMore ? '<x-slot:more><span data-more-slot>mais</span></x-slot:more>' : '';

    return Blade::render(
        '<x-filter-panel :active-count="$activeCount" :more-active-count="$moreActiveCount">'
        .'<x-slot:primary><span data-primary-slot>primario</span></x-slot:primary>'
        .'<x-slot:secondary><span data-secondary-slot>secundario</span></x-slot:secondary>'
        .$more
        .'</x-filter-panel>',
        ['activeCount' => $activeCount, 'moreActiveCount' => $moreActiveCount],
    );
}

/**
 * @return array{attributes: string, text: string}
 */
function filtrosToggle(string $html): array
{
    preg_match('/<button([^>]*data-testid="filtros-toggle"[^>]*)>(.*?)<\/button>/s', $html, $match);

    expect($match)->not->toBeEmpty('filtros-toggle button not rendered');

    return ['attributes' => $match[1], 'text' => trim($match[2])];
}

test('the filter panel is a labelled form with Alpine state and no wire:click', function () {
    $html = renderFilterPanel(0, 0);

    expect($html)->toContain('<form wire:submit.prevent aria-label="Filtros" class="filter-panel card')
        ->toContain('x-data="{ filtersOpen: false, moreOpen: false }"')
        ->toContain('id="filtros-painel"')
        ->toContain('data-[open=true]:flex')
        ->toContain('data-primary-slot')
        ->toContain('data-secondary-slot')
        ->toContain('data-more-slot')
        ->not->toContain('wire:click')
        ->not->toContain('<details');
});

test('the mobile toggle reads Filtros or Filtros (N) and exposes its state', function (int $activeCount, string $expectedText) {
    $toggle = filtrosToggle(renderFilterPanel($activeCount, 0));

    expect($toggle['text'])->toBe($expectedText)
        ->and($toggle['attributes'])->toContain('type="button"')
        ->toContain('aria-expanded="false"')
        ->toContain('aria-controls="filtros-painel"')
        ->toContain('lg:hidden');
})->with([
    'none active' => [0, 'Filtros'],
    'two active' => [2, 'Filtros (2)'],
]);

test('the Mais filtros button exists only when moreActiveCount is not null', function () {
    expect(renderFilterPanel(0, 0))->toMatch('/data-testid="mais-filtros-toggle"[^>]*>Mais filtros<\/button>/')
        ->and(renderFilterPanel(1, 1))->toMatch('/data-testid="mais-filtros-toggle"[^>]*aria-expanded="false"[^>]*>Mais filtros \(1\)<\/button>/')
        ->and(renderFilterPanel(0, null, withMore: false))->not->toContain('Mais filtros')
        ->not->toContain('mais-filtros');
});

test('the solicitado control offers the neutral option and the five presets in order', function () {
    $html = Blade::render('<x-solicitado-filter :show-custom="false" />');

    preg_match_all('/<option value="([^"]*)">([^<]*)<\/option>/', $html, $options);

    expect($html)->toContain('<label for="requestedPreset" class="form-label">Solicitado</label>')
        ->toContain('<select id="requestedPreset" wire:model.live="requestedPreset" class="form-control">')
        ->and($options[1])->toBe(['', 'hoje', '3d', '7d', 'mes', 'personalizado'])
        ->and($options[2])->toBe(['Qualquer data', 'Hoje', 'Últimos 3 dias', 'Últimos 7 dias', 'Último mês', 'Personalizado']);
});

test('De and Até render only with showCustom, each with its label', function () {
    $closed = Blade::render('<x-solicitado-filter :show-custom="false" />');
    $open = Blade::render('<x-solicitado-filter :show-custom="true" />');

    expect($closed)->not->toContain('id="requestedFrom"')
        ->not->toContain('id="requestedTo"');

    expect($open)->toContain('<label for="requestedFrom" class="form-label">De</label>')
        ->toContain('<input id="requestedFrom" type="date" wire:model.live="requestedFrom"')
        ->toContain('<label for="requestedTo" class="form-label">Até</label>')
        ->toContain('<input id="requestedTo" type="date" wire:model.live="requestedTo"');
});

test('the active obras control has the exact label and a described help text', function () {
    $html = Blade::render('<x-active-obras-filter />');

    preg_match('/<label for="activeObrasOnly"[^>]*>(.*?)<\/label>/s', $html, $label);
    preg_match('/<p id="activeObrasOnly-help"[^>]*>(.*?)<\/p>/s', $html, $help);

    expect(trim(strip_tags($label[1] ?? '')))->toBe('Somente obras ativas')
        ->and($html)->toContain('id="activeObrasOnly" type="checkbox" wire:model.live="activeObrasOnly"')
        ->toContain('aria-describedby="activeObrasOnly-help"')
        ->and($help[1] ?? '')->toContain('concluídas')
        ->toContain('Outra');
});
