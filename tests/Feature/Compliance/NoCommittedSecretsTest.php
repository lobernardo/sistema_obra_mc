<?php

use Illuminate\Support\Facades\Process;
use Symfony\Component\Finder\Finder;

/**
 * RNF-05 / RNF-06: no real `.env` file may be versioned, `.gitignore` must
 * cover it, and no file committed to the repository may contain a value
 * shaped like a real secret. Only placeholders/examples are allowed
 * (empty `APP_KEY=`, demo credentials, Railway `${{Service.VAR}}` references).
 *
 * This is a grep-based compliance gate rather than a behavioral test.
 */

/**
 * Secret-shaped patterns that must never appear in a versioned file.
 *
 * @return array<string, string>
 */
function committedSecretPatterns(): array
{
    return [
        'Laravel APP_KEY' => '/APP_KEY\s*=\s*["\']?base64:[A-Za-z0-9+\/=]{40,}/',
        'AWS access key id' => '/\bAKIA[0-9A-Z]{16}\b/',
        'PEM private key' => '/-----BEGIN (?:RSA |EC |DSA |OPENSSH |PGP )?PRIVATE KEY(?: BLOCK)?-----/',
        'JWT' => '/\beyJ[A-Za-z0-9_-]{10,}\.[A-Za-z0-9_-]{10,}\.[A-Za-z0-9_-]{10,}\b/',
        'GitHub token' => '/\b(?:gh[pousr]_[A-Za-z0-9]{36,}|github_pat_[A-Za-z0-9_]{20,})\b/',
        'Slack token' => '/\bxox[baprs]-[A-Za-z0-9-]{10,}\b/',
        'Stripe live key' => '/\b[sr]k_live_[A-Za-z0-9]{16,}\b/',
        'Supabase key' => '/\bsb_(?:secret|publishable)_[A-Za-z0-9_-]{10,}\b/',
        'Database URL with embedded password' => '/\b(?:postgres(?:ql)?|pgsql|mysql):\/\/[^:\/\s]+:[^@\s]{8,}@/i',
    ];
}

/**
 * Generic `KEY=value` / `key: value` / `$var = 'value'` assignments whose key
 * name suggests a credential. A match is only reported when the value looks
 * like real entropy (>= 20 chars mixing letters and digits) and is not an
 * obvious placeholder.
 */
function genericCredentialPattern(): string
{
    return '/\b(?:password|passwd|secret|token|api[_-]?key|private[_-]?key|access[_-]?key)\b\s*[=:]\s*["\']?([A-Za-z0-9+\/_\-]{20,})/i';
}

function isPlaceholderValue(string $value): bool
{
    $lowercase = strtolower($value);

    foreach (['example', 'placeholder', 'changeme', 'your_', 'your-', 'dummy', 'fake', 'testing', 'sample', 'xxx'] as $marker) {
        if (str_contains($lowercase, $marker)) {
            return true;
        }
    }

    return ! (preg_match('/[A-Za-z]/', $value) && preg_match('/\d/', $value));
}

/**
 * Paths (relative to the repository root) tracked by Git. Falls back to a
 * `.gitignore`-aware filesystem walk when Git is not available.
 *
 * @return list<string>
 */
function versionedRepositoryFiles(): array
{
    $result = Process::path(base_path())->run(['git', 'ls-files', '-z']);

    if ($result->successful()) {
        return array_values(array_filter(explode("\0", $result->output()), fn (string $path) => $path !== ''));
    }

    $finder = (new Finder)
        ->files()
        ->in(base_path())
        ->ignoreDotFiles(false)
        ->ignoreVCS(true)
        ->ignoreVCSIgnored(true)
        ->exclude(['vendor', 'node_modules']);

    return array_map(
        fn (SplFileInfo $file) => str_replace('\\', '/', $file->getRelativePathname()),
        iterator_to_array($finder, false),
    );
}

function isTextFile(string $contents): bool
{
    return ! str_contains(substr($contents, 0, 8192), "\0");
}

test('.gitignore covers every real .env file while keeping .env.example versioned', function () {
    $rules = array_map('trim', file(base_path('.gitignore'), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));

    expect($rules)
        ->toContain('.env')
        ->toContain('.env.*')
        ->toContain('!.env.example');
});

test('git ignores real .env variants and does not ignore .env.example', function () {
    if (! Process::path(base_path())->run(['git', 'rev-parse', '--is-inside-work-tree'])->successful()) {
        $this->markTestSkipped('Git is not available; ignore rules are asserted structurally in the previous test.');
    }

    $isIgnored = fn (string $path): bool => Process::path(base_path())
        ->run(['git', 'check-ignore', '-q', $path])
        ->successful();

    foreach (['.env', '.env.production', '.env.backup', '.env.local', '.env.staging'] as $path) {
        expect($isIgnored($path))->toBeTrue("{$path} is not ignored by git");
    }

    expect($isIgnored('.env.example'))->toBeFalse('.env.example must stay versioned');
});

test('no real .env file is versioned', function () {
    $envFiles = array_filter(
        versionedRepositoryFiles(),
        fn (string $path) => str_starts_with(basename($path), '.env'),
    );

    expect(array_values($envFiles))->toBe(['.env.example']);
});

test('.env.example only contains placeholders for sensitive values', function () {
    $contents = file_get_contents(base_path('.env.example'));

    expect($contents)
        ->toMatch('/^APP_KEY=\s*$/m')
        ->toMatch('/^DB_PASSWORD=\s*$/m')
        ->not->toMatch(committedSecretPatterns()['Laravel APP_KEY']);
});

test('.env.example declares every variable required for Railway', function () {
    $contents = file_get_contents(base_path('.env.example'));

    foreach (['APP_NAME', 'APP_ENV', 'APP_KEY', 'APP_DEBUG', 'APP_URL', 'DB_CONNECTION', 'DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD'] as $variable) {
        expect($contents)->toMatch('/^'.preg_quote($variable, '/').'=/m', "{$variable} is missing from .env.example");
    }

    expect($contents)->toMatch('/^DB_CONNECTION=pgsql$/m');
});

test('README documents the production environment variables and deploy procedure', function () {
    $readme = file_get_contents(base_path('README.md'));

    foreach (['APP_NAME', 'APP_ENV', 'APP_KEY', 'APP_DEBUG', 'APP_URL', 'DB_CONNECTION', 'DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD'] as $variable) {
        expect($readme)->toContain("`{$variable}`");
    }

    expect($readme)
        ->toContain('`production`')
        ->toContain('`false`')
        ->toContain('composer install --no-dev')
        ->toContain('npm run build')
        ->toContain('php artisan migrate --force')
        ->toContain('config:cache')
        ->toContain('route:cache')
        ->toContain('view:cache')
        ->toContain('php artisan serve --host=0.0.0.0 --port=$PORT');
});

test('no versioned file contains a secret-shaped value', function () {
    $selfPath = str_replace('\\', '/', substr(__FILE__, strlen(base_path()) + 1));

    $offenders = [];

    foreach (versionedRepositoryFiles() as $path) {
        if ($path === $selfPath || ! is_file(base_path($path))) {
            continue;
        }

        $contents = file_get_contents(base_path($path));

        if (! isTextFile($contents)) {
            continue;
        }

        foreach (committedSecretPatterns() as $label => $pattern) {
            if (preg_match($pattern, $contents)) {
                $offenders[] = "{$path}: {$label}";
            }
        }

        preg_match_all(genericCredentialPattern(), $contents, $matches);

        foreach ($matches[1] as $value) {
            if (! isPlaceholderValue($value)) {
                $offenders[] = "{$path}: credential assignment with value {$value}";
            }
        }
    }

    expect($offenders)->toBeEmpty();
});
