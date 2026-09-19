<?php

use Symfony\Component\Finder\Finder;

/**
 * RNF-03: the Laravel application must not import/depend on any Supabase
 * SDK or API (`@supabase/supabase-js`, `@supabase/ssr`, Supabase Auth) in
 * its executable runtime. This is a grep-based compliance gate rather than
 * a behavioral test, since the requirement is the absence of a dependency.
 */
test('composer.json does not require any Supabase package', function () {
    $composer = json_decode(file_get_contents(base_path('composer.json')), true);

    $declared = array_keys(array_merge(
        $composer['require'] ?? [],
        $composer['require-dev'] ?? [],
    ));

    $supabasePackages = array_filter(
        $declared,
        fn (string $package) => str_contains(strtolower($package), 'supabase'),
    );

    expect($supabasePackages)->toBeEmpty();
});

test('.env.example does not declare any Supabase variable', function () {
    $contents = file_get_contents(base_path('.env.example'));

    expect(strtolower($contents))->not->toContain('supabase');
});

test('no application source file references Supabase', function () {
    $finder = (new Finder)
        ->files()
        ->in([
            base_path('app'),
            base_path('bootstrap'),
            base_path('config'),
            base_path('database'),
            base_path('resources'),
            base_path('routes'),
            base_path('tests'),
        ])
        ->exclude(['vendor', 'node_modules'])
        ->notPath('Feature/Compliance');

    $offenders = [];

    foreach ($finder as $file) {
        if (str_contains(strtolower($file->getContents()), 'supabase')) {
            $offenders[] = $file->getRelativePathname();
        }
    }

    expect($offenders)->toBeEmpty();
});
