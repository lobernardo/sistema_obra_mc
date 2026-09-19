<?php

/**
 * RNF-04: the final executable application must not depend on Next.js or
 * React as its main framework. Laravel is the sole application served from
 * the repository root (T54); `package.json` is kept only for the Vite asset
 * pipeline, so it must declare no `next`/`react` runtime dependency and no
 * script may invoke the retired `next build`/`next start` commands.
 */
test('root package.json declares no next/react dependency', function () {
    $package = json_decode(file_get_contents(base_path('package.json')), true);

    $declared = array_keys(array_merge(
        $package['dependencies'] ?? [],
        $package['devDependencies'] ?? [],
        $package['optionalDependencies'] ?? [],
    ));

    $nextOrReact = array_filter(
        $declared,
        fn (string $name) => $name === 'next' || $name === 'react' || $name === 'react-dom',
    );

    expect($nextOrReact)->toBeEmpty();
});

test('no script in package.json or composer.json invokes next build or next start', function () {
    $package = json_decode(file_get_contents(base_path('package.json')), true);
    $composer = json_decode(file_get_contents(base_path('composer.json')), true);

    $scripts = array_merge(
        array_values($package['scripts'] ?? []),
        collect($composer['scripts'] ?? [])->flatten()->all(),
    );

    foreach ($scripts as $script) {
        expect($script)
            ->not->toContain('next build')
            ->not->toContain('next start')
            ->not->toContain('next dev');
    }
});

test('the Next.js App Router directory no longer exists at the repository root', function () {
    expect(base_path('next.config.ts'))->not->toBeFile();
    expect(base_path('next.config.js'))->not->toBeFile();
    expect(base_path('app/layout.tsx'))->not->toBeFile();
    expect(base_path('app/page.tsx'))->not->toBeFile();
});

test('the Supabase CLI project no longer exists at the repository root', function () {
    expect(base_path('supabase'))->not->toBeDirectory();
});
