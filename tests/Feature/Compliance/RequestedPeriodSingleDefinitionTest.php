<?php

/**
 * RF-46 / CT-10: `App\Domain\Pedidos\RequestedPeriodFilter` is the single
 * local-day period class on `requested_at`, and `App\Support\LocalTime` the
 * single holder of the `America/Sao_Paulo` literal. Static guard over the
 * PHP token stream of `app/` (comments and whitespace ignored).
 */

/**
 * Code tokens of every PHP file under `app/`, keyed by path relative to
 * `app/`, comments and whitespace dropped.
 *
 * @return array<string, list<PhpToken>>
 */
function requestedPeriodScannedTokens(): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path(), FilesystemIterator::SKIP_DOTS));

    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $files[str_replace(app_path().DIRECTORY_SEPARATOR, '', $file->getPathname())] = array_values(array_filter(
            PhpToken::tokenize(file_get_contents($file->getPathname())),
            fn (PhpToken $token): bool => ! $token->is([T_COMMENT, T_DOC_COMMENT, T_WHITESPACE]),
        ));
    }

    ksort($files);

    return $files;
}

/**
 * The unquoted value of a string literal token, or null for any other token.
 */
function requestedPeriodStringValue(?PhpToken $token): ?string
{
    return $token !== null && $token->is(T_CONSTANT_ENCAPSED_STRING) ? substr($token->text, 1, -1) : null;
}

/**
 * Every `<method>(<first literal>, <second literal>` call site as
 * "path: method(first, second)".
 *
 * @return list<string>
 */
function requestedPeriodCallSites(string $method): array
{
    $sites = [];

    foreach (requestedPeriodScannedTokens() as $path => $tokens) {
        foreach ($tokens as $index => $token) {
            if (! $token->is(T_STRING) || $token->text !== $method || ($tokens[$index + 1]->text ?? null) !== '(') {
                continue;
            }

            $first = requestedPeriodStringValue($tokens[$index + 2] ?? null);
            $second = ($tokens[$index + 3]->text ?? null) === ',' ? requestedPeriodStringValue($tokens[$index + 4] ?? null) : null;

            $sites[] = "{$path}: {$method}({$first}, {$second})";
        }
    }

    return $sites;
}

test('no whereDate on requested_at or on a created_at timestamp remains in app/', function () {
    $offenders = array_values(array_filter(
        requestedPeriodCallSites('whereDate'),
        fn (string $site): bool => str_contains($site, 'requested_at') || str_contains($site, 'created_at'),
    ));

    expect($offenders)->toBe([]);
});

test('a where on requested_at with a range operator appears only in RequestedPeriodFilter', function () {
    $offenders = array_values(array_filter(
        requestedPeriodCallSites('where'),
        fn (string $site): bool => (bool) preg_match('/where\(([\w.]+\.)?requested_at, (>=|>|<=|<)\)$/', $site),
    ));

    expect($offenders)->toBe([]);

    $filterSource = file_get_contents(app_path('Domain/Pedidos/RequestedPeriodFilter.php'));

    expect($filterSource)->toContain("public const string COLUMN = 'requested_at';")
        ->toContain("->where(self::COLUMN, '>=', \$from)")
        ->toContain("->where(self::COLUMN, '<', \$until)");
});

test('exactly one class under app/ builds requested_at period bounds', function () {
    $builders = [];

    foreach (requestedPeriodScannedTokens() as $path => $tokens) {
        $mentionsRequestedAt = false;
        $convertsLocalDays = false;

        foreach ($tokens as $index => $token) {
            $mentionsRequestedAt = $mentionsRequestedAt || requestedPeriodStringValue($token) === 'requested_at';
            $convertsLocalDays = $convertsLocalDays
                || ($token->is(T_STRING) && in_array($token->text, ['localDayStartUtc', 'todayWindowUtc'], true) && $path !== 'Support/LocalTime.php');
        }

        if ($mentionsRequestedAt && $convertsLocalDays) {
            $builders[] = $path;
        }
    }

    expect($builders)->toBe(['Domain/Pedidos/RequestedPeriodFilter.php']);
});

test('the America/Sao_Paulo literal appears in app/ only in LocalTime', function () {
    $holders = [];

    foreach (requestedPeriodScannedTokens() as $path => $tokens) {
        foreach ($tokens as $token) {
            if (requestedPeriodStringValue($token) === 'America/Sao_Paulo') {
                $holders[] = $path;
            }
        }
    }

    expect(array_values(array_unique($holders)))->toBe(['Support/LocalTime.php']);
});
