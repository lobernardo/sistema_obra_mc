<?php

use Symfony\Component\Finder\Finder;

/**
 * Identity compliance gate (UI-01, UI-04, UI-07, UI-09, UI-13, UI-15, UI-16,
 * UI-25, RF-25). Like `NoCommittedSecretsTest`, this is a grep-style gate over
 * the source tree rather than a behavioral test: the design system is applied
 * globally, so any hardcoded blue palette, dark surface, gradient, heavy
 * shadow, animation, hardcoded brand string or misplaced MC signature is a
 * regression regardless of which screen introduced it.
 */

/**
 * Repository-relative path => file contents for every file under the given
 * directories (relative to the repository root).
 *
 * @param  list<string>  $directories
 * @return array<string, string>
 */
function identitySourceFiles(array $directories, string $namePattern = '*'): array
{
    $existing = array_values(array_filter($directories, fn (string $dir) => is_dir(base_path($dir))));

    if ($existing === []) {
        return [];
    }

    $finder = (new Finder)
        ->files()
        ->in(array_map(fn (string $dir) => base_path($dir), $existing))
        ->name($namePattern)
        ->sortByName();

    $files = [];

    foreach ($finder as $file) {
        $relative = str_replace('\\', '/', substr($file->getRealPath(), strlen(base_path()) + 1));
        $files[$relative] = $file->getContents();
    }

    return $files;
}

/**
 * "path:line" for every line of every given file that matches the pattern.
 *
 * @param  array<string, string>  $files
 * @param  (callable(string): bool)|null  $ignoreLine
 * @return list<string>
 */
function identityOffenders(array $files, string $pattern, ?callable $ignoreLine = null): array
{
    $offenders = [];

    foreach ($files as $path => $contents) {
        foreach (preg_split('/\R/', $contents) as $index => $line) {
            if ($ignoreLine !== null && $ignoreLine($line)) {
                continue;
            }

            if (preg_match($pattern, $line)) {
                $offenders[] = $path.':'.($index + 1).': '.trim($line);
            }
        }
    }

    return $offenders;
}

/**
 * Body of every `.btn-*` rule declared in the stylesheet.
 *
 * @return array<string, string> selector => declarations
 */
function buttonComponentRules(): array
{
    preg_match_all('/(\.btn-[a-z-]+)\s*\{(.*?)\}/s', file_get_contents(resource_path('css/app.css')), $matches, PREG_SET_ORDER);

    $rules = [];

    foreach ($matches as $match) {
        $rules[$match[1]] = $match[2];
    }

    return $rules;
}

test('(a) no sky palette utility survives in views or stylesheets (UI-01)', function () {
    $files = identitySourceFiles(['resources/views', 'resources/css']);

    expect($files)->not->toBeEmpty();
    expect(identityOffenders($files, '/(bg|text|border|ring|from|to)-sky-[0-9]+/'))->toBe([]);
});

test('(b) layouts and the auth shell have no dark slate surface (UI-04)', function () {
    $files = identitySourceFiles(['resources/views/layouts', 'resources/views/auth']);

    expect(array_keys($files))->toContain('resources/views/layouts/app.blade.php')
        ->toContain('resources/views/auth/login.blade.php');
    expect(identityOffenders($files, '/bg-slate-(800|900)/'))->toBe([]);
});

test('(c) views use no gradients, heavy shadows or animations beyond Livewire loading states (UI-13)', function () {
    $files = identitySourceFiles(['resources/views']);

    expect(identityOffenders($files, '/bg-gradient-|shadow-xl|shadow-2xl/'))->toBe([]);
    expect(identityOffenders($files, '/animate-/', fn (string $line) => str_contains($line, 'wire:loading')))->toBe([]);
});

test('(d) the MC Inteligência signature appears only in the auth layout (UI-16, UI-25)', function () {
    $files = identitySourceFiles(['resources/views']);

    $filesWithSignature = array_keys(array_filter($files, fn (string $contents) => str_contains($contents, 'MC Inteligência')));

    expect($filesWithSignature)->toBe(['resources/views/auth/login.blade.php']);
    expect(substr_count($files['resources/views/auth/login.blade.php'], 'Tecnologia por MC Inteligência'))->toBe(1);
});

test('(e) the brand name is never hardcoded in views or application code — only config(app.name) (UI-15)', function () {
    $files = identitySourceFiles(['resources/views', 'app']);

    expect(identityOffenders($files, '/Albuquerque Engenharia/'))->toBe([]);
    expect($files['resources/views/layouts/app.blade.php'])->toContain("config('app.name')");
    expect($files['resources/views/auth/login.blade.php'])->toContain("config('app.name')");
});

test('(f) buttons are not pill-shaped and only the app layout carries a sidebar (UI-09, navegacao-sidebar-listagens UI-04)', function () {
    $rules = buttonComponentRules();

    expect(array_keys($rules))->toContain('.btn-primary')->toContain('.btn-secondary');

    foreach ($rules as $selector => $declarations) {
        expect($declarations)->not->toContain('rounded-full');
    }

    $layouts = identitySourceFiles(['resources/views/layouts', 'resources/views/auth']);

    expect(substr_count($layouts['resources/views/layouts/app.blade.php'], '<aside'))->toBe(1);
    expect(substr_count($layouts['resources/views/auth/login.blade.php'], '<aside'))->toBe(0);

    $otherLayouts = array_diff_key($layouts, ['resources/views/layouts/app.blade.php' => true]);

    expect(identityOffenders($otherLayouts, '/<aside/i'))->toBe([]);
});

test('(g) no view outputs a password attribute (RF-25)', function () {
    $files = identitySourceFiles(['resources/views']);

    expect(identityOffenders($files, '/->password\b/'))->toBe([]);
});
