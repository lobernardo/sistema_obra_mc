<?php

/**
 * RF-01: the e-mail lower-casing rule exists exactly once in `app/`, inside
 * `App\Support\EmailNormalizer`. A second copy of `mb_strtolower(trim(...))`
 * would let limiter keys, audit e-mails and credential lookups drift apart,
 * so this guard is a static scan rather than a behavioural test.
 *
 * `PhpToken` is used instead of a plain grep so an occurrence inside a
 * comment or a string literal is never counted — the same technique as
 * `ObraVisibleToGuardTest`. Only lower-casing **applied to an e-mail** is
 * flagged: `Gestao\Usuarios\Index::users()` legitimately lower-cases a free
 * text search term, which RF-01 does not govern.
 */

/**
 * Every `app/` PHP file, as an absolute path.
 *
 * @return list<string>
 */
function normalizationGuardSources(): array
{
    $files = [];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(app_path(), FilesystemIterator::SKIP_DOTS),
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = str_replace('\\', '/', $file->getPathname());
        }
    }

    sort($files);

    return $files;
}

/**
 * Significant tokens of a source file: whitespace and comments removed, so
 * only real code is inspected.
 *
 * @return list<PhpToken>
 */
function normalizationGuardTokens(string $source): array
{
    return array_values(array_filter(
        PhpToken::tokenize($source),
        fn (PhpToken $token): bool => ! $token->is([T_WHITESPACE, T_COMMENT, T_DOC_COMMENT]),
    ));
}

/**
 * `file:line` of every `strtolower`/`mb_strtolower` call whose argument list
 * mentions an e-mail — the operation RF-01 allows in exactly one place.
 *
 * @return list<string>
 */
function emailLowerCasingOccurrences(): array
{
    $occurrences = [];

    foreach (normalizationGuardSources() as $file) {
        $tokens = normalizationGuardTokens(file_get_contents($file));

        foreach ($tokens as $index => $token) {
            if (! $token->is(T_STRING) || ! in_array(strtolower($token->text), ['strtolower', 'mb_strtolower'], true)) {
                continue;
            }

            if (! ($tokens[$index + 1] ?? null)?->is('(')) {
                continue;
            }

            $depth = 0;
            $mentionsEmail = false;

            for ($cursor = $index + 1; isset($tokens[$cursor]); $cursor++) {
                $candidate = $tokens[$cursor];

                if ($candidate->is('(')) {
                    $depth++;
                } elseif ($candidate->is(')')) {
                    $depth--;

                    if ($depth === 0) {
                        break;
                    }
                } elseif (str_contains(strtolower($candidate->text), 'email')) {
                    $mentionsEmail = true;
                }
            }

            if ($mentionsEmail) {
                $occurrences[] = str_replace(base_path().'/', '', $file).':'.$token->line;
            }
        }
    }

    return $occurrences;
}

test('lower-casing an e-mail happens exactly once in app/, inside EmailNormalizer (RF-01)', function () {
    expect(normalizationGuardSources())->not->toBeEmpty();

    $occurrences = emailLowerCasingOccurrences();

    $this->assertCount(
        1,
        $occurrences,
        'A regra de normalização de e-mail deve existir uma única vez em app/. Ocorrências: '.implode(', ', $occurrences),
    );
    expect($occurrences[0])->toStartWith('app/Support/EmailNormalizer.php:');
});

test('the canonical rule is exactly trim followed by a multibyte lower-case (RF-01)', function () {
    $source = file_get_contents(app_path('Support/EmailNormalizer.php'));

    expect($source)->toMatch('/return\s+mb_strtolower\(trim\(\$email\)\);/');
});

test('no file outside EmailNormalizer combines trim with lower-casing on an e-mail (RF-01)', function () {
    foreach (normalizationGuardSources() as $file) {
        if (str_ends_with($file, 'app/Support/EmailNormalizer.php')) {
            continue;
        }

        $this->assertDoesNotMatchRegularExpression(
            '/(?:mb_)?strtolower\s*\(\s*trim\s*\([^)]*email/i',
            file_get_contents($file),
            str_replace(base_path().'/', '', $file).' duplica a regra canônica de normalização de e-mail.',
        );
    }
});

test('every e-mail write and read path reaches the canonical rule (RF-01, RF-02, RF-03, RF-04, RF-07)', function (string $relativePath, string $expectedCall) {
    $contents = file_get_contents(base_path($relativePath));

    $this->assertStringContainsString(
        $expectedCall,
        $contents,
        "{$relativePath} não alcança a regra canônica de normalização de e-mail.",
    );
})->with([
    'rate limiter' => ['app/Services/AuthenticationRateLimiter.php', 'EmailNormalizer::normalize'],
    'create user action' => ['app/Actions/Usuarios/CreateUserAction.php', 'EmailNormalizer::normalize'],
    'update user action' => ['app/Actions/Usuarios/UpdateUserAction.php', 'CreateUserAction::withNormalizedEmail'],
    'gestao user form' => ['app/Livewire/Gestao/Usuarios/Form.php', 'EmailNormalizer::normalize'],
    'gestao bootstrap command' => ['app/Console/Commands/CreateGestaoUser.php', 'EmailNormalizer::normalize'],
    'invite and reset consumption' => ['app/Livewire/Auth/Concerns/DefinesPasswordFromToken.php', 'EmailNormalizer::normalize'],
    'diagnostic command' => ['app/Console/Commands/EmailCaseReport.php', 'EmailNormalizer::normalize'],
]);
