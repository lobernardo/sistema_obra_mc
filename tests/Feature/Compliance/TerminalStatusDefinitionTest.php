<?php

use App\Enums\StatusSlug;

/**
 * RF-39 / CT-08: "terminal status" has one single definition —
 * `StatusSlug::terminal()` (with `terminalValues()` and `isTerminal()`
 * derived from it). Static guard over the PHP token stream of `app/`
 * (comments and whitespace ignored): no array literal lists both
 * `entregue` and `cancelado` (as enum cases or string values) outside the
 * body of `StatusSlug::terminal()`.
 *
 * RF-12: the atraso / pendência / prazo classifiers keep measuring
 * `needed_at`; `data_prevista` never appears in them.
 */

/**
 * @return list<string>
 */
function terminalDefinitionScannedFiles(): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path(), FilesystemIterator::SKIP_DOTS));

    foreach ($iterator as $file) {
        if ($file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }

    sort($files);

    return $files;
}

/**
 * Code tokens of a PHP file, comments and whitespace dropped.
 *
 * @return list<PhpToken>
 */
function terminalDefinitionTokens(string $path): array
{
    return array_values(array_filter(
        PhpToken::tokenize(file_get_contents($path)),
        fn (PhpToken $token): bool => ! $token->is([T_COMMENT, T_DOC_COMMENT, T_WHITESPACE]),
    ));
}

/**
 * Token index range [start, end] of the body of `function terminal()`, or
 * null when the file declares no such method.
 *
 * @param  list<PhpToken>  $tokens
 * @return array{0: int, 1: int}|null
 */
function terminalMethodBodyRange(array $tokens): ?array
{
    foreach ($tokens as $index => $token) {
        if (! $token->is(T_FUNCTION) || ($tokens[$index + 1]->text ?? null) !== 'terminal') {
            continue;
        }

        for ($open = $index; $tokens[$open]->text !== '{'; $open++);

        $depth = 0;

        for ($close = $open; $close < count($tokens); $close++) {
            $depth += match ($tokens[$close]->text) {
                '{' => 1,
                '}' => -1,
                default => 0,
            };

            if ($depth === 0) {
                return [$open, $close];
            }
        }
    }

    return null;
}

/**
 * Every bracket array literal of the token stream, as its start index and
 * its concatenated code.
 *
 * @param  list<PhpToken>  $tokens
 * @return list<array{start: int, line: int, code: string}>
 */
function terminalDefinitionArrayLiterals(array $tokens): array
{
    $literals = [];
    $stack = [];

    foreach ($tokens as $index => $token) {
        if ($token->text === '[') {
            $stack[] = $index;
        } elseif ($token->text === ']' && $stack !== []) {
            $start = array_pop($stack);

            $literals[] = [
                'start' => $start,
                'line' => $tokens[$start]->line,
                'code' => implode('', array_map(
                    fn (PhpToken $token): string => $token->text,
                    array_slice($tokens, $start, $index - $start + 1),
                )),
            ];
        }
    }

    return $literals;
}

function listsEntregueAndCancelado(string $code): bool
{
    $mentions = fn (string $case, string $value): bool => str_contains($code, 'StatusSlug::'.$case)
        || str_contains($code, 'self::'.$case)
        || str_contains($code, "'{$value}'")
        || str_contains($code, '"'.$value.'"');

    return $mentions('Entregue', 'entregue') && $mentions('Cancelado', 'cancelado');
}

test('the terminal set is exactly entregue, cancelado and finalizado', function () {
    expect(StatusSlug::terminalValues())->toBe(['entregue', 'cancelado', 'finalizado']);

    foreach (StatusSlug::cases() as $case) {
        expect($case->isTerminal())->toBe(in_array($case, StatusSlug::terminal(), true));
    }
});

test('no terminal status list is hard-coded outside StatusSlug::terminal()', function () {
    $violations = [];

    foreach (terminalDefinitionScannedFiles() as $path) {
        $tokens = terminalDefinitionTokens($path);
        $allowedRange = $path === app_path('Enums/StatusSlug.php') ? terminalMethodBodyRange($tokens) : null;

        foreach (terminalDefinitionArrayLiterals($tokens) as $literal) {
            if (! listsEntregueAndCancelado($literal['code'])) {
                continue;
            }

            if ($allowedRange !== null && $literal['start'] > $allowedRange[0] && $literal['start'] < $allowedRange[1]) {
                continue;
            }

            $violations[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $path).':'.$literal['line'].' '.$literal['code'];
        }
    }

    expect($violations)->toBe([]);
});

test('the scan recognises the single allowed definition inside StatusSlug::terminal()', function () {
    $tokens = terminalDefinitionTokens(app_path('Enums/StatusSlug.php'));
    $range = terminalMethodBodyRange($tokens);

    expect($range)->not->toBeNull();

    $inside = array_filter(
        terminalDefinitionArrayLiterals($tokens),
        fn (array $literal): bool => listsEntregueAndCancelado($literal['code'])
            && $literal['start'] > $range[0] && $literal['start'] < $range[1],
    );

    expect($inside)->toHaveCount(1);
});

test('the atraso, pendente and prazo classifiers never reference data_prevista (RF-12)', function () {
    foreach (['AtrasoClassifier', 'PendenteClassifier', 'PrazoClassifier'] as $classifier) {
        expect(file_get_contents(app_path("Domain/Pedidos/{$classifier}.php")))->not->toContain('data_prevista');
    }
});
