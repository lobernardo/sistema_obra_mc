<?php

/**
 * T21 — Production configuration review in code (RF-31, AC-N10, AC-N11).
 *
 * Every assertion reads only versioned sources (`config/session.php`,
 * `config/auth.php`, `bootstrap/app.php`, `.env.example`, `app/`) — never
 * the real `.env`, never a Railway value — so the suite proves what the
 * repository promises production without touching production (AC-N11) and
 * without any secret entering the test output (AC-N10).
 *
 * Items checked (mirror of the "Configuration review" section of the final
 * report — each test name is one row, its expectation is the verdict):
 *
 *  1. `config/session.php` — `driver` defaults to `database` via `SESSION_DRIVER`.
 *  2. `config/session.php` — `lifetime` defaults to `120` via `SESSION_LIFETIME`.
 *  3. `config/session.php` — `secure` is driven only by `SESSION_SECURE_COOKIE`
 *     (no hardcoded value; production sets it `true` under HTTPS).
 *  4. `config/session.php` — `http_only` defaults to `true` via `SESSION_HTTP_ONLY`.
 *  5. `config/session.php` — `same_site` defaults to `lax` via `SESSION_SAME_SITE`.
 *  6. `config/auth.php` — broker `users`: expire 60 min / throttle 60 s.
 *  7. `config/auth.php` — broker `invites`: expire 4320 min (72 h) / throttle 60 s.
 *  8. `bootstrap/app.php` — `trustProxies(at: '*')` kept (R-01 accepted, D-01).
 *  9. `bootstrap/app.php` — `active` alias bound to `EnsureUserIsActive`.
 * 10. `bootstrap/app.php` — `AuthenticateSession` appended to the `web` group (RF-14).
 * 11. `.env.example` header — documents `APP_ENV=production`, `APP_DEBUG=false`
 *     and `SESSION_SECURE_COOKIE=true` "(sob HTTPS)" as comment lines only.
 * 12. `.env.example` local values — `APP_ENV=local` and `APP_DEBUG=true` remain
 *     the committed defaults (exactly once each, uncommented).
 * 13. `app/` — no `Log::*()`, `logger()` or `report()` call whose argument list
 *     mentions `password`, `token`, `->all()` or a `$request` payload.
 *
 * Item 13 is tokenised with `PhpToken` (comments and doc-comments ignored):
 * for each call the tokens between the opening `(` and its matching `)` are
 * inspected — identifiers, variables and string literals are matched
 * case-insensitively against the forbidden words, and the sequence
 * `->all()` is detected structurally.
 */
function productionConfigSource(string $relativePath): string
{
    return file_get_contents(base_path($relativePath));
}

/**
 * @return list<PhpToken>
 */
function productionConfigTokens(string $source): array
{
    return array_values(array_filter(
        PhpToken::tokenize($source),
        fn (PhpToken $token): bool => ! $token->is([T_WHITESPACE, T_COMMENT, T_DOC_COMMENT]),
    ));
}

/**
 * Returns the tokens of every `Log::*(...)`, `logger(...)` and `report(...)`
 * argument list found in the source, keyed by "file:line" of the call.
 *
 * @param  list<PhpToken>  $tokens
 * @return array<string, list<PhpToken>>
 */
function productionConfigLoggingCalls(string $file, array $tokens): array
{
    $calls = [];

    foreach ($tokens as $index => $token) {
        $isFacadeCall = $token->is([T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED])
            && in_array(ltrim($token->text, '\\'), ['Log', 'Illuminate\\Support\\Facades\\Log'], true)
            && ($tokens[$index + 1] ?? null)?->is(T_DOUBLE_COLON)
            && ($tokens[$index + 2] ?? null)?->is(T_STRING)
            && ($tokens[$index + 3] ?? null)?->is('(');

        $isHelperCall = $token->is(T_STRING)
            && in_array(strtolower($token->text), ['logger', 'report'], true)
            && ! ($tokens[$index - 1] ?? null)?->is([T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION])
            && ($tokens[$index + 1] ?? null)?->is('(');

        if (! $isFacadeCall && ! $isHelperCall) {
            continue;
        }

        $open = $isFacadeCall ? $index + 3 : $index + 1;
        $arguments = [];

        /*
         * Collect the argument list of the call and of every chained
         * `->method(...)` that follows it, so `logger()->debug(...)` and
         * `Log::channel('x')->info(...)` are inspected as one call.
         */
        while (isset($tokens[$open]) && $tokens[$open]->is('(')) {
            $depth = 0;

            for ($cursor = $open; isset($tokens[$cursor]); $cursor++) {
                if ($tokens[$cursor]->is('(')) {
                    $depth++;
                } elseif ($tokens[$cursor]->is(')')) {
                    $depth--;

                    if ($depth === 0) {
                        break;
                    }
                }

                if ($cursor > $open) {
                    $arguments[] = $tokens[$cursor];
                }
            }

            $isChained = ($tokens[$cursor + 1] ?? null)?->is([T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR])
                && ($tokens[$cursor + 2] ?? null)?->is(T_STRING)
                && ($tokens[$cursor + 3] ?? null)?->is('(');

            $open = $isChained ? $cursor + 3 : $cursor + 1;
        }

        $calls["{$file}:{$token->line}"] = $arguments;
    }

    return $calls;
}

test('config/session.php keeps driver, lifetime, secure, http_only and same_site keyed on env with the expected defaults (items 1-5)', function () {
    $source = productionConfigSource('config/session.php');

    expect($source)
        ->toMatch("/^\s*'driver' => env\('SESSION_DRIVER', 'database'\),\s*$/m")
        ->toMatch("/^\s*'lifetime' => \(int\) env\('SESSION_LIFETIME', 120\),\s*$/m")
        ->toMatch("/^\s*'secure' => env\('SESSION_SECURE_COOKIE'\),\s*$/m")
        ->toMatch("/^\s*'http_only' => env\('SESSION_HTTP_ONLY', true\),\s*$/m")
        ->toMatch("/^\s*'same_site' => env\('SESSION_SAME_SITE', 'lax'\),\s*$/m");
});

test('config/auth.php password broker users expires in 60 minutes with 60 s throttle (item 6)', function () {
    expect(config('auth.passwords.users.expire'))->toBe(60)
        ->and(config('auth.passwords.users.throttle'))->toBe(60)
        ->and(config('auth.passwords.users.provider'))->toBe('users');
});

test('config/auth.php password broker invites expires in 4320 minutes (72 h) with 60 s throttle (item 7)', function () {
    expect(config('auth.passwords.invites.expire'))->toBe(4320)
        ->and(config('auth.passwords.invites.throttle'))->toBe(60)
        ->and(config('auth.passwords.invites.provider'))->toBe('users')
        ->and(config('auth.passwords.invites.table'))->toBe(config('auth.passwords.users.table'));
});

test('bootstrap/app.php trusts the Railway edge proxy headers (item 8)', function () {
    expect(productionConfigSource('bootstrap/app.php'))
        ->toMatch("/\\\$middleware->trustProxies\(at: '\*'\);/");
});

test('bootstrap/app.php binds the active alias to EnsureUserIsActive (item 9)', function () {
    $source = productionConfigSource('bootstrap/app.php');

    expect($source)
        ->toContain('use App\Http\Middleware\EnsureUserIsActive;')
        ->toMatch("/'active' => EnsureUserIsActive::class,/");
});

test('bootstrap/app.php appends AuthenticateSession to the web group (item 10)', function () {
    $source = productionConfigSource('bootstrap/app.php');

    expect($source)
        ->toContain('use Illuminate\Session\Middleware\AuthenticateSession;')
        ->toMatch('/\$middleware->web\(append: \[AuthenticateSession::class\]\);/');
});

test('.env.example header documents the production expectations as comment lines only (item 11)', function () {
    $contents = productionConfigSource('.env.example');

    expect($contents)
        ->toMatch('/^#\s+APP_ENV=production\s*$/m')
        ->toMatch('/^#\s+APP_DEBUG=false\b/m')
        ->toMatch('/^#.*\bSESSION_SECURE_COOKIE=true\s+\(sob HTTPS\)/m');

    expect($contents)
        ->not->toMatch('/^APP_ENV=production$/m')
        ->not->toMatch('/^APP_DEBUG=false$/m')
        ->not->toMatch('/^SESSION_SECURE_COOKIE=/m');
});

test('.env.example keeps APP_ENV=local and APP_DEBUG=true as the committed local values (item 12)', function () {
    $contents = productionConfigSource('.env.example');

    expect(preg_match_all('/^APP_ENV=local$/m', $contents))->toBe(1)
        ->and(preg_match_all('/^APP_DEBUG=true$/m', $contents))->toBe(1)
        ->and(preg_match_all('/^APP_ENV=/m', $contents))->toBe(1)
        ->and(preg_match_all('/^APP_DEBUG=/m', $contents))->toBe(1);
});

test('no Log, logger or report call in app/ receives a password, token, ->all() or $request payload (item 13)', function () {
    $files = iterator_to_array(new RegexIterator(
        new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path(), FilesystemIterator::SKIP_DOTS)),
        '/\.php$/',
    ));

    expect($files)->not->toBeEmpty();

    $forbiddenWords = ['password', 'token', 'request'];
    $inspectedCalls = 0;

    foreach ($files as $file) {
        $relative = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname());
        $tokens = productionConfigTokens(file_get_contents($file->getPathname()));

        foreach (productionConfigLoggingCalls($relative, $tokens) as $location => $arguments) {
            $inspectedCalls++;

            foreach ($arguments as $position => $argument) {
                if ($argument->is([T_STRING, T_VARIABLE, T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE])) {
                    foreach ($forbiddenWords as $word) {
                        expect(stripos($argument->text, $word))->toBeFalse(
                            "{$location}: logging call mentions `{$word}` in its arguments ({$argument->text}).",
                        );
                    }
                }

                $isAllCall = $argument->is(T_STRING)
                    && strtolower($argument->text) === 'all'
                    && ($arguments[$position - 1] ?? null)?->is([T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR])
                    && ($arguments[$position + 1] ?? null)?->is('(');

                expect($isAllCall)->toBeFalse("{$location}: logging call passes a full `->all()` payload.");
            }
        }
    }

    expect($inspectedCalls)->toBeGreaterThanOrEqual(1);
});
