<?php

/**
 * RF-25 (T23): the only gate standing between the donut and a black chart in
 * production. Tailwind emits a utility solely when it finds the complete class
 * name in a scanned source file, and this project declares no safelist
 * (`resources/css/app.css` holds two `@source` lines only), so an interpolated
 * `fill-…` class would pass every PHP assertion and still render unpainted in
 * the built stylesheet.
 *
 * The check therefore reads the artefact `npm run build` produced, resolved
 * through the Vite manifest exactly as `@vite` resolves it at runtime.
 */
function builtStylesheet(): string
{
    $manifestPath = public_path('build/manifest.json');

    expect(is_file($manifestPath))->toBeTrue(
        "O manifesto do Vite não existe em {$manifestPath}. Rode `npm run build` antes da suíte: ".
        'o layout autenticado chama @vite e `public/build` não é versionado.'
    );

    $manifest = json_decode(file_get_contents($manifestPath), true);

    expect(is_array($manifest) && isset($manifest['resources/css/app.css']['file']))->toBeTrue(
        'O manifesto do Vite não tem a entrada `resources/css/app.css`. Rode `npm run build`.'
    );

    $stylesheetPath = public_path('build/'.$manifest['resources/css/app.css']['file']);

    expect(is_file($stylesheetPath))->toBeTrue(
        "A folha de estilo {$stylesheetPath} anunciada pelo manifesto não existe. Rode `npm run build`."
    );

    return file_get_contents($stylesheetPath);
}

test('the built stylesheet is resolved through the Vite manifest', function () {
    expect(builtStylesheet())->not->toBeEmpty();
});

test('the three donut fill utilities survive the production build', function (string $utility, string $token) {
    $css = builtStylesheet();

    expect(str_contains($css, ".{$utility}"))->toBeTrue(
        "O utilitário .{$utility} não está na saída do build. Rode `npm run build` depois de editar o donut; ".
        'se ele continuar ausente, a classe foi interpolada em vez de escrita por extenso.'
    );

    expect(str_contains($css, "--color-{$token}"))->toBeTrue("O token --color-{$token} não chegou à folha construída.");
})->with([
    'dentro do prazo' => ['fill-success', 'success'],
    'vencendo em breve' => ['fill-warning', 'warning'],
    'atrasado' => ['fill-atraso', 'atraso'],
]);
