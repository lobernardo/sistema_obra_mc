<?php

use App\Livewire\Gestao\TodosPedidos as GestaoTodosPedidos;
use App\Livewire\Obra\Acompanhamento;
use App\Livewire\Suprimentos\TodosPedidos as SuprimentosTodosPedidos;
use Livewire\Attributes\Url;

/**
 * RF-20 / CT-02: the filter state of the three listings is addressable by
 * URL through `#[Url]`, and by nothing else. Two mechanical rules protect
 * the contract:
 *
 * 1. every filter property carries `#[Url]` whose `except:` equals the
 *    property's declared default, so a default-valued filter never appears
 *    in the query string and "Limpar filtros" (RF-19) leaves a clean URL;
 * 2. no component in the three listing namespaces reads a request parameter
 *    manually — `mount()` does not re-run on a Livewire update, so a
 *    parameter read there would survive the reset and come back on reload.
 */

/**
 * The filter properties of each listing, with the query-string name each one
 * must keep. The four legacy drill-down names (`atrasado`, `pendente`,
 * `requestedFrom`, `requestedTo`) are pinned here so a rename breaks the
 * build rather than a production link, and so are the slice-3 names
 * `solicitado` (the "Solicitado" preset) and `obrasAtivas`
 * (navegacao-sidebar-listagens RF-15, RF-20).
 *
 * @return array<string, array<string, string>>
 */
function filterPropertyUrlNames(): array
{
    $shared = [
        'search' => 'search',
        'atrasoOnly' => 'atrasado',
        'obraId' => 'obraId',
        'statusId' => 'statusId',
        'priorityId' => 'priorityId',
        'responsibleId' => 'responsibleId',
        'neededAtFrom' => 'neededAtFrom',
        'neededAtTo' => 'neededAtTo',
        'requestedFrom' => 'requestedFrom',
        'requestedTo' => 'requestedTo',
        'requestedPreset' => 'solicitado',
        'activeObrasOnly' => 'obrasAtivas',
    ];

    return [
        SuprimentosTodosPedidos::class => $shared,
        GestaoTodosPedidos::class => $shared + ['pendenteOnly' => 'pendente'],
        Acompanhamento::class => [
            'search' => 'search',
            'obraId' => 'obraId',
            'statusId' => 'statusId',
            'atrasoOnly' => 'atrasado',
            'requestedPreset' => 'solicitado',
            'requestedFrom' => 'requestedFrom',
            'requestedTo' => 'requestedTo',
        ],
    ];
}

test('every listing filter property is Url-bound under its contract name', function () {
    foreach (filterPropertyUrlNames() as $component => $properties) {
        $reflection = new ReflectionClass($component);

        foreach ($properties as $property => $urlName) {
            expect($reflection->hasProperty($property))->toBeTrue("{$component}::\${$property}");

            $attributes = $reflection->getProperty($property)->getAttributes(Url::class);

            expect($attributes)->toHaveCount(1, "{$component}::\${$property} must carry exactly one #[Url]");

            $url = $attributes[0]->newInstance();

            expect($url->as ?? $property)->toBe($urlName, "{$component}::\${$property} url name");
        }
    }
});

test('each Url except value equals the property default so a default filter never reaches the URL', function () {
    foreach (filterPropertyUrlNames() as $component => $properties) {
        $reflection = new ReflectionClass($component);
        $defaults = $reflection->getDefaultProperties();

        foreach ($properties as $property => $urlName) {
            $url = $reflection->getProperty($property)->getAttributes(Url::class)[0]->newInstance();

            expect($url->except)->toBe($defaults[$property], "{$component}::\${$property} except");
        }
    }
});

test('no listing component reads a filter parameter from the request', function () {
    $files = array_merge(
        glob(app_path('Livewire/Obra/*.php')),
        glob(app_path('Livewire/Suprimentos/*.php')),
        glob(app_path('Livewire/Gestao/*.php')),
        glob(app_path('Livewire/Gestao/*/*.php')),
        glob(app_path('Livewire/Concerns/*.php')),
    );

    expect($files)->not->toBeEmpty();

    $violations = [];

    foreach ($files as $file) {
        $tokens = array_values(array_filter(
            PhpToken::tokenize(file_get_contents($file)),
            fn (PhpToken $token): bool => ! $token->is([T_WHITESPACE, T_COMMENT, T_DOC_COMMENT]),
        ));

        foreach ($tokens as $index => $token) {
            if (! $token->is(T_STRING) || strtolower($token->text) !== 'request') {
                continue;
            }

            if (($tokens[$index + 1] ?? null)?->is('(') && ($tokens[$index + 3] ?? null)?->is(T_OBJECT_OPERATOR)) {
                $violations[] = "{$file}:{$token->line}";
            }
        }
    }

    expect($violations)->toBe([]);
});
