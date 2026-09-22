<?php

use App\Models\Pedido;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Symfony\Component\Finder\Finder;

/**
 * RNF-03, RF-26 (catalog), RF-30 — architecture compliance assertions of
 * the adversarial suite (decision D-12). They pin the settled decisions
 * that visibility is enforced in the application layer only:
 *
 * - no Global Scope on `Pedido` (`addGlobalScope` / `ScopedBy` absent from
 *   `app/Models/Pedido.php`, and none registered at runtime) — the only
 *   mechanism is the local scope `Pedido::visibleTo()` (RF-01);
 * - no PostgreSQL Row Level Security anywhere under `database/`
 *   (`CREATE POLICY` / `ROW LEVEL SECURITY`);
 * - the removed `password_changed` slug never reappears under `app/`
 *   (RF-26: no authenticated change-password flow exists).
 *
 * Grep-style scans over the repository sources, so a regression fails the
 * suite before it ever reaches a database.
 */

/**
 * @return array<string, string> relative path => contents
 */
function adversarialSourcesUnder(string $directory, string $pattern = '*.php'): array
{
    $files = [];

    foreach (Finder::create()->files()->in(base_path($directory))->name($pattern) as $file) {
        $files[$file->getRelativePathname()] = (string) file_get_contents($file->getRealPath());
    }

    ksort($files);

    return $files;
}

test('RNF-03 app/Models/Pedido.php declares no Global Scope (addGlobalScope / ScopedBy) and none is registered at runtime', function () {
    $source = (string) file_get_contents(app_path('Models/Pedido.php'));

    expect(preg_match_all('/addGlobalScope|ScopedBy/', $source))->toBe(0);
    expect((new ReflectionClass(Pedido::class))->getAttributes(ScopedBy::class))->toBe([]);
    expect((new Pedido)->getGlobalScopes())->toBe([]);
    $visibleTo = new ReflectionMethod(Pedido::class, 'visibleTo');

    expect($visibleTo->getAttributes(Scope::class))->toHaveCount(1);
});

test('RNF-03 no file under database/ enables Row Level Security or creates a policy', function () {
    $offenders = [];

    foreach (adversarialSourcesUnder('database', '*') as $path => $contents) {
        if (preg_match('/ROW\s+LEVEL\s+SECURITY|CREATE\s+POLICY/i', $contents) === 1) {
            $offenders[] = $path;
        }
    }

    expect($offenders)->toBe([]);
});

test('RF-26 no file under app/ references the removed password_changed slug', function () {
    $offenders = [];

    foreach (adversarialSourcesUnder('app') as $path => $contents) {
        if (stripos($contents, 'password_changed') !== false) {
            $offenders[] = $path;
        }
    }

    expect($offenders)->toBe([]);
});
