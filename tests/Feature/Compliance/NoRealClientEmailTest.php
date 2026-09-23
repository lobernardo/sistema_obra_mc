<?php

use Illuminate\Support\Facades\Process;

/**
 * RF-10: no real client e-mail address may appear in a file this feature
 * adds or modifies, and the diagnostic command must write addresses to
 * stdout only.
 *
 * **Scope decision.** The scan covers the *code* this feature adds or
 * modifies — source, tests, migrations, views, configuration and scripts —
 * and deliberately excludes the prose deliverables of the `/plan` pipeline
 * (`.spec/**`, `claude/**`, `.phases/**`, `docs/**` and any Markdown file).
 * Those documents were authored before this phase, quote the reported defect
 * verbatim in order to specify it, and ship nothing: RF-10's own wording
 * targets application logs, test fixtures, the diagnostic command's output
 * and the files that carry them. Narrowing the scan here is the only way the
 * gate can be green at all, because the SPEC and the PLAN that define RF-10
 * both quote the address RF-10 forbids.
 */

/**
 * Commit this feature branched from, or `null` when it cannot be resolved.
 *
 * Anchored first on the parent of the commit that introduced
 * `app/Support/EmailNormalizer.php`, so the scope survives the feature being
 * merged into the base branch; the branch merge base is only a fallback.
 */
function featureMergeBase(): ?string
{
    $introducingCommit = Process::path(base_path())->run(
        ['git', 'log', '--diff-filter=A', '--format=%H', '--', 'app/Support/EmailNormalizer.php'],
    );

    if ($introducingCommit->successful()) {
        $commits = preg_split('/\R/', trim($introducingCommit->output()));
        $firstCommit = end($commits);

        if (is_string($firstCommit) && $firstCommit !== '') {
            $parent = Process::path(base_path())->run(['git', 'rev-parse', "{$firstCommit}^"]);

            if ($parent->successful() && trim($parent->output()) !== '') {
                return trim($parent->output());
            }
        }
    }

    foreach (['build/v0-demo-laravel', 'origin/build/v0-demo-laravel', 'build/v0-demo', 'origin/build/v0-demo'] as $base) {
        $result = Process::path(base_path())->run(['git', 'merge-base', 'HEAD', $base]);

        if ($result->successful() && trim($result->output()) !== '') {
            return trim($result->output());
        }
    }

    return null;
}

/**
 * Repository-relative paths of every code file this feature added or
 * modified: committed changes since the merge base plus the current working
 * tree, including files not yet staged.
 *
 * @return list<string>
 */
function featureTouchedCodeFiles(): array
{
    $mergeBase = featureMergeBase();

    if ($mergeBase === null) {
        return [];
    }

    $paths = [];

    foreach ([
        ['git', 'diff', '--name-only', '--diff-filter=ACMR', $mergeBase, 'HEAD'],
        ['git', 'diff', '--name-only', '--diff-filter=ACMR', 'HEAD'],
        ['git', 'ls-files', '--others', '--exclude-standard'],
    ] as $command) {
        $result = Process::path(base_path())->run($command);

        if (! $result->successful()) {
            continue;
        }

        foreach (preg_split('/\R/', $result->output()) as $path) {
            if ($path !== '') {
                $paths[$path] = true;
            }
        }
    }

    return array_values(array_filter(
        array_keys($paths),
        fn (string $path): bool => isFeatureCodeFile($path),
    ));
}

/**
 * A path is in scope when it is an existing, non-prose file of the
 * application tree.
 */
function isFeatureCodeFile(string $path): bool
{
    if (! is_file(base_path($path))) {
        return false;
    }

    foreach (['.spec/', 'claude/', '.phases/', 'docs/', 'public/build/', 'vendor/', 'node_modules/'] as $excludedPrefix) {
        if (str_starts_with($path, $excludedPrefix)) {
            return false;
        }
    }

    return (bool) preg_match('/\.(php|blade\.php|js|mjs|ts|css|json|ya?ml|sh|sql|xml)$/', $path);
}

/**
 * The host of the configured application URL, or `null` when it is a local
 * development value with nothing to protect.
 */
function configuredProductionHost(): ?string
{
    $host = parse_url((string) config('app.url'), PHP_URL_HOST);

    if (! is_string($host) || $host === '') {
        return null;
    }

    return in_array($host, ['localhost', '127.0.0.1', '0.0.0.0', '::1'], true) ? null : $host;
}

/**
 * Domains allowed in an e-mail literal: the IANA reserved example domains
 * and reserved TLDs (RFC 2606 / RFC 6761).
 *
 * @return list<string>
 */
function allowedTestEmailDomains(): array
{
    return ['example.com', 'example.org', 'example.net', 'example.edu'];
}

function isAllowedTestEmailDomain(string $domain): bool
{
    $domain = strtolower(rtrim($domain, '.'));

    if (in_array($domain, allowedTestEmailDomains(), true)) {
        return true;
    }

    foreach (['.example', '.invalid', '.test', '.localhost'] as $reservedTld) {
        if (str_ends_with($domain, $reservedTld)) {
            return true;
        }
    }

    return false;
}

beforeEach(function () {
    $this->touchedFiles = featureTouchedCodeFiles();

    if ($this->touchedFiles === []) {
        $this->markTestSkipped('Não foi possível resolver a base do branch via git; o escopo do diff é indeterminado.');
    }
});

test('the feature touched code files at all, so the scan is not vacuous (RF-10)', function () {
    expect($this->touchedFiles)->not->toBeEmpty();
    expect($this->touchedFiles)->toContain('app/Support/EmailNormalizer.php');
    expect($this->touchedFiles)->toContain('app/Console/Commands/EmailCaseReport.php');
});

test('no code file added or modified by this feature mentions the client e-mail domain (RF-10)', function () {
    $offenders = [];

    foreach ($this->touchedFiles as $path) {
        if ($path === 'tests/Feature/Compliance/NoRealClientEmailTest.php') {
            continue;
        }

        if (preg_match('/@albuquerque\./i', file_get_contents(base_path($path)))) {
            $offenders[] = $path;
        }
    }

    expect($offenders)->toBe([], 'Endereço do cliente encontrado em: '.implode(', ', $offenders));
});

test('no code file added or modified by this feature mentions the configured production domain (RF-10)', function () {
    $host = configuredProductionHost();

    if ($host === null) {
        expect(config('app.url'))->toBeString();

        return;
    }

    $offenders = [];

    foreach ($this->touchedFiles as $path) {
        if ($path === 'tests/Feature/Compliance/NoRealClientEmailTest.php') {
            continue;
        }

        if (str_contains(strtolower(file_get_contents(base_path($path))), strtolower($host))) {
            $offenders[] = $path;
        }
    }

    expect($offenders)->toBe([], "Domínio de produção {$host} encontrado em: ".implode(', ', $offenders));
});

test('every e-mail literal added or modified by this feature uses a reserved example domain (RF-10)', function () {
    $offenders = [];

    foreach ($this->touchedFiles as $path) {
        if ($path === 'tests/Feature/Compliance/NoRealClientEmailTest.php') {
            continue;
        }

        preg_match_all(
            '/[A-Za-z0-9._%+-]+@([A-Za-z0-9.-]+\.[A-Za-z]{2,})/',
            file_get_contents(base_path($path)),
            $matches,
        );

        foreach ($matches[1] ?? [] as $domain) {
            if (! isAllowedTestEmailDomain($domain)) {
                $offenders[] = "{$path} → {$domain}";
            }
        }
    }

    expect(array_values(array_unique($offenders)))
        ->toBe([], 'Domínio de e-mail não reservado: '.implode(', ', array_unique($offenders)));
});

test('the diagnostic command reaches no log channel, so no address can leak into a log (RF-10)', function () {
    $source = file_get_contents(app_path('Console/Commands/EmailCaseReport.php'));

    // `$this->info()`/`warn()`/`error()` are console styles on stdout, not log
    // channels; only genuine logging and file writes are prohibited.
    foreach (['Log::', 'logger(', 'error_log(', 'report(', 'file_put_contents(', 'fwrite('] as $forbidden) {
        $this->assertStringNotContainsString(
            $forbidden,
            $source,
            "O comando de diagnóstico não pode escrever fora do stdout: {$forbidden}.",
        );
    }

    expect($source)->toContain('$this->table(')->toContain('$this->line(');
});
