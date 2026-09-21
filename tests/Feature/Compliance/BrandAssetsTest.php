<?php

use Symfony\Component\Finder\Finder;

/**
 * T27 — Official brand assets (UI-17, UI-18, UI-19, UI-20, CT-06, IH-02). The
 * The two horizontal logos are byte-for-byte copies of the owner-provided originals
 * (`logo_Albuquerque.png` 1063×345 RGBA 209281 B, `logo_MC.png` 1305×200 RGBA
 * 23177 B), versioned under `public/images/` and served under `APP_URL`; the
 * application never depends on the Windows path they were copied from.
 */

/**
 * @return array<string, array{width: int, height: int, bytes: int, sha256: string}> repository-relative path => expected fingerprint
 */
function expectedBrandAssets(): array
{
    return [
        'public/images/logo-albuquerque.png' => [
            'width' => 1063,
            'height' => 345,
            'bytes' => 209281,
            'sha256' => '64e210022d3dcc398690ba8850233afa52960999f301d4f84d033f5b05f77b4d',
        ],
        'public/images/logo-mc.png' => [
            'width' => 1305,
            'height' => 200,
            'bytes' => 23177,
            'sha256' => '798e27b03956aac3d9572fc35b64a7ab81fe6d6336b23eba0a1c3c8276309b36',
        ],
        'public/images/logo-albuquerque-simbolo.png' => [
            'width' => 512,
            'height' => 512,
            'bytes' => 53251,
            'sha256' => '4e6562eb3ee15b940bda243f53b03aad2bdccd97ed5a10ea135db54660794053',
        ],
    ];
}

/**
 * @return list<string> repository-relative paths
 */
function filesReferencingWindowsPaths(array $directories): array
{
    $existing = array_values(array_filter($directories, fn (string $dir) => is_dir(base_path($dir))));

    $finder = (new Finder)
        ->files()
        ->in(array_map(fn (string $dir) => base_path($dir), $existing))
        ->exclude('build')
        ->notName('*.png')
        ->filter(fn (SplFileInfo $file) => preg_match('~/mnt/c/|C:\\\\Users~', $file->getContents()) === 1);

    return array_values(array_map(
        fn (SplFileInfo $file) => str_replace('\\', '/', substr($file->getRealPath(), strlen(base_path()) + 1)),
        iterator_to_array($finder, false),
    ));
}

test('both official logos exist under public/images as PNG files (CT-06)', function () {
    foreach (expectedBrandAssets() as $path => $expected) {
        expect(is_file(base_path($path)))->toBeTrue("{$path} is missing");
        expect(mime_content_type(base_path($path)))->toBe('image/png', "{$path} is not a PNG");
    }
});

test('the brand assets keep their declared dimensions (UI-17, UI-18)', function () {
    foreach (expectedBrandAssets() as $path => $expected) {
        $info = getimagesize(base_path($path));

        expect($info)->not->toBeFalse("{$path} is not a readable image");
        expect($info[0])->toBe($expected['width'], "{$path} width");
        expect($info[1])->toBe($expected['height'], "{$path} height");
        expect($info['mime'])->toBe('image/png', "{$path} mime");
    }
});

test('the brand assets match their recorded fingerprint — same size and sha256 (UI-19, IH-02)', function () {
    foreach (expectedBrandAssets() as $path => $expected) {
        expect(filesize(base_path($path)))->toBe($expected['bytes'], "{$path} size");
        expect(hash_file('sha256', base_path($path)))->toBe($expected['sha256'], "{$path} checksum");
    }
});

test('the logos are tracked by Git and not ignored (UI-19, CT-06)', function () {
    $tracked = trim((string) shell_exec('git -C '.escapeshellarg(base_path()).' ls-files public/images'));

    expect(preg_split('/\R/', $tracked))->toContain('public/images/logo-albuquerque.png')
        ->toContain('public/images/logo-mc.png')
        ->toContain('public/images/logo-albuquerque-simbolo.png');

    expect(file_get_contents(base_path('.gitignore')))->not->toMatch('~^/?public/images~m')
        ->not->toMatch('~^\*\.png$~m');
});

test('no application file references the Windows source path (UI-19)', function () {
    expect(filesReferencingWindowsPaths(['app', 'resources', 'public', 'config']))->toBe([]);
});

test('the assets resolve to public URLs under APP_URL through asset() (CT-06)', function () {
    expect(asset('images/logo-albuquerque.png'))->toBe(rtrim(config('app.url'), '/').'/images/logo-albuquerque.png');
    expect(asset('images/logo-mc.png'))->toBe(rtrim(config('app.url'), '/').'/images/logo-mc.png');
    expect(asset('images/logo-albuquerque-simbolo.png'))->toBe(rtrim(config('app.url'), '/').'/images/logo-albuquerque-simbolo.png');
});
