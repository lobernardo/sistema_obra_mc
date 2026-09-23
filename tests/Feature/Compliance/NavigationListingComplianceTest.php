<?php

/**
 * navegacao-sidebar-listagens T17 (RF-11, RF-12, RF-17, RF-18, RF-20, RF-23,
 * RNF-04): static guards over the slice-3 surface — the shared pedido table,
 * the sidebar layout, the three pedido listings, the "Solicitado" period
 * trait and enum, and the listing views.
 *
 * PHP files are scanned without comments, so a docblock may describe a
 * forbidden construct; Blade files are scanned as written.
 */
const NAVIGATION_LISTING_COMPONENTS = [
    'app/Livewire/Obra/Acompanhamento.php',
    'app/Livewire/Suprimentos/TodosPedidos.php',
    'app/Livewire/Gestao/TodosPedidos.php',
];

const NAVIGATION_LISTING_TODOS_PEDIDOS = [
    'app/Livewire/Suprimentos/TodosPedidos.php',
    'app/Livewire/Gestao/TodosPedidos.php',
];

const NAVIGATION_LISTING_PERIOD_TRAIT = 'app/Livewire/Concerns/FiltersByRequestedPeriod.php';

/**
 * Code tokens of a PHP file, comments and whitespace dropped.
 *
 * @return list<PhpToken>
 */
function navigationListingTokens(string $relativePath): array
{
    return array_values(array_filter(
        PhpToken::tokenize(file_get_contents(base_path($relativePath))),
        fn (PhpToken $token): bool => ! $token->is([T_COMMENT, T_DOC_COMMENT, T_WHITESPACE]),
    ));
}

/**
 * The code of a PHP file with comments removed and whitespace collapsed, so
 * substring checks never match documentation.
 */
function navigationListingCode(string $relativePath): string
{
    return implode(' ', array_map(fn (PhpToken $token): string => $token->text, navigationListingTokens($relativePath)));
}

/**
 * The statements of a PHP file (token runs up to each `;`).
 *
 * @return list<list<PhpToken>>
 */
function navigationListingStatements(string $relativePath): array
{
    $statements = [];
    $current = [];

    foreach (navigationListingTokens($relativePath) as $token) {
        if ($token->is(';')) {
            $statements[] = $current;
            $current = [];

            continue;
        }

        $current[] = $token;
    }

    return $statements;
}

/**
 * Whether the statement calls `$method` (case-sensitive) with `$firstArgument`
 * as its first string literal argument; `null` matches any argument.
 *
 * @param  list<PhpToken>  $statement
 */
function navigationListingCalls(array $statement, string $method, ?string $firstArgument = null): bool
{
    foreach ($statement as $index => $token) {
        if (! $token->is(T_STRING) || $token->text !== $method || ! ($statement[$index + 1] ?? null)?->is('(')) {
            continue;
        }

        if ($firstArgument === null) {
            return true;
        }

        $argument = $statement[$index + 2] ?? null;

        if ($argument !== null && $argument->is(T_CONSTANT_ENCAPSED_STRING) && substr($argument->text, 1, -1) === $firstArgument) {
            return true;
        }
    }

    return false;
}

test('the shared pedido table has no previsão de entrega, "Itens" or "Data necessária" (RF-11, RF-12)', function () {
    $table = file_get_contents(resource_path('views/components/pedido-table.blade.php'));

    expect($table)
        ->not->toContain('expected_delivery_at')
        ->not->toContain('>Itens<')
        ->not->toContain('Data necessária');
});

test('the app layout builds the sidebar from the catalogue, without a role match, nav-link, wire:click or raw output', function () {
    $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));

    expect($layout)
        ->toContain('SidebarNavigation::for(')
        ->not->toContain('match (')
        ->not->toContain('nav-link')
        ->not->toContain('wire:click')
        ->not->toContain('{!!');
});

test('the Suprimentos and Gestão listings use the single obra activity rule and keep pedidos "Outra" (RF-20, NC-02)', function () {
    foreach (NAVIGATION_LISTING_TODOS_PEDIDOS as $component) {
        $code = navigationListingCode($component);

        expect($code)
            ->not->toContain('ObraStatus ::')
            ->not->toContain('ObraStatus::')
            ->not->toContain("'concluido'");

        $restrictsActiveObras = false;

        foreach (navigationListingStatements($component) as $statement) {
            $filtersByObraActivity = navigationListingCalls($statement, 'whereHas', 'obra')
                || (navigationListingCalls($statement, 'orWhereHas', 'obra') && navigationListingCalls($statement, 'active'));

            if (! $filtersByObraActivity) {
                continue;
            }

            $restrictsActiveObras = true;

            expect(navigationListingCalls($statement, 'whereNull', 'obra_id'))
                ->toBeTrue("{$component}: an obra activity filter must keep pedidos \"Outra\" with whereNull('obra_id') in the same statement");
        }

        expect($restrictsActiveObras)->toBeTrue("{$component} must filter by Obra::active()");
    }
});

test('the listings apply the "Solicitado" period only through the single period class (RF-18, F-01)', function () {
    $forbidden = ["whereDate ( 'requested_at'", "where ( 'requested_at'", 'localDayStartUtc', 'utcBoundsForLocalRange', 'America/Sao_Paulo'];

    foreach ([...NAVIGATION_LISTING_COMPONENTS, NAVIGATION_LISTING_PERIOD_TRAIT] as $file) {
        $code = navigationListingCode($file);

        foreach ($forbidden as $needle) {
            expect($code)->not->toContain($needle, "{$file} must not contain {$needle}");
        }
    }

    foreach (NAVIGATION_LISTING_COMPONENTS as $component) {
        $code = navigationListingCode($component);

        expect($code)
            ->toContain('$this -> applyRequestedPeriod (')
            ->not->toContain('RequestedPeriodFilter')
            ->not->toContain('applyLocalRange');
    }

    $trait = navigationListingCode(NAVIGATION_LISTING_PERIOD_TRAIT);

    expect($trait)
        ->toContain('RequestedPeriodFilter :: apply (')
        ->not->toContain('applyLocalRange')
        ->not->toContain('utcWindow')
        ->not->toContain('localRangeFor');

    $enum = navigationListingCode('app/Enums/RequestedPeriodPreset.php');

    expect($enum)
        ->not->toContain('LocalTime')
        ->not->toContain('Carbon');

    $periodFilters = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path(), FilesystemIterator::SKIP_DOTS));

    foreach ($iterator as $file) {
        if (str_ends_with($file->getFilename(), 'PeriodFilter.php')) {
            $periodFilters[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname());
        }
    }

    expect($periodFilters)->toBe(['app/Domain/Pedidos/RequestedPeriodFilter.php']);
});

test('no listing view says "A partir de" or "data necessária", in any case (RF-12, RF-17, N-06)', function () {
    $views = [
        ...glob(resource_path('views/livewire/{obra,suprimentos,gestao}/*todos-pedidos*'), GLOB_BRACE),
        ...glob(resource_path('views/livewire/obra/acompanhamento*')),
    ];

    expect($views)->toHaveCount(3);

    foreach ($views as $view) {
        $source = file_get_contents($view);

        foreach (['A partir de', 'data necessária'] as $needle) {
            expect(mb_stripos($source, $needle))->toBeFalse("{$view} must not contain \"{$needle}\" (case-insensitive)");
        }
    }
});
