<?php

use App\Services\PedidoAttachmentStorage;
use Illuminate\Support\Facades\Route;

/**
 * RF-14 / RF-16 / RF-19: static and runtime guards over the attachment
 * surface.
 *
 * - a general attachment (`PedidoAttachmentKind::Anexo`) is written only by
 *   `CreatePedidoAction`, a romaneio only by `AttachRomaneioAction`;
 * - no code updates or deletes an attachment row; `ResetDemoData` only
 *   reads `pedido_attachments` (rows go by the cascade FK);
 * - attachments never touch the `public` disk nor `storage/app/public`;
 * - `pedidos.anexos.download` is the only route under
 *   `/pedidos/{pedido}/anexos` and carries `auth` + `active`;
 * - no Blade or app code builds a `temporaryUrl()`.
 *
 * PHP sources are scanned without comments (PhpToken), so docblocks never
 * count as an occurrence. Item 13 of `ProductionConfigTest` keeps file
 * contents out of logs.
 */
const PEDIDO_ATTACHMENT_WRITERS = [
    'Anexo' => 'app/Actions/Pedidos/CreatePedidoAction.php',
    'Romaneio' => 'app/Actions/Pedidos/AttachRomaneioAction.php',
];

/**
 * @return list<string> repository-relative paths
 */
function pedidoAttachmentFiles(string $directory, string $suffix): array
{
    $files = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($directory), FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->isFile() && str_ends_with($file->getFilename(), $suffix)) {
            $files[] = ltrim(str_replace(base_path(), '', $file->getPathname()), DIRECTORY_SEPARATOR);
        }
    }

    sort($files);

    return $files;
}

/**
 * Code-only source with whitespace collapsed to single spaces.
 */
function pedidoAttachmentCode(string $relativePath): string
{
    $code = '';

    foreach (PhpToken::tokenize(file_get_contents(base_path($relativePath))) as $token) {
        if ($token->is([T_COMMENT, T_DOC_COMMENT])) {
            continue;
        }

        $code .= $token->is(T_WHITESPACE) ? ' ' : $token->text;
    }

    return $code;
}

test('each attachment kind is written by exactly one Action (RF-14, RF-16)', function () {
    $writers = ['Anexo' => [], 'Romaneio' => []];
    $creators = [];

    foreach (pedidoAttachmentFiles('app', '.php') as $file) {
        $code = pedidoAttachmentCode($file);

        foreach (array_keys($writers) as $kind) {
            if (preg_match("/'kind'\\s*=>\\s*PedidoAttachmentKind::{$kind}\\b/", $code) === 1) {
                $writers[$kind][] = $file;
            }
        }

        if (preg_match('/PedidoAttachment::(query\(\)\s*->\s*)?(create|forceCreate|insert|make)\(|->\s*(attachments|romaneios)\(\)\s*->\s*(create|createMany|save|saveMany)\(/', $code) === 1) {
            $creators[] = $file;
        }
    }

    expect($writers['Anexo'])->toBe([PEDIDO_ATTACHMENT_WRITERS['Anexo']]);
    expect($writers['Romaneio'])->toBe([PEDIDO_ATTACHMENT_WRITERS['Romaneio']]);

    $expectedCreators = array_values(PEDIDO_ATTACHMENT_WRITERS);
    sort($expectedCreators);

    expect($creators)->toBe($expectedCreators);
});

test('no code in app/ or routes/ updates or deletes an attachment row (RF-19)', function () {
    $offenders = [];
    $forbidden = '(update|delete|forceDelete|destroy|truncate|upsert|updateOrCreate|increment|decrement|save|push|touch)';

    foreach ([...pedidoAttachmentFiles('app', '.php'), ...pedidoAttachmentFiles('routes', '.php')] as $file) {
        $code = pedidoAttachmentCode($file);

        foreach (explode(';', $code) as $statement) {
            $touchesAttachments = preg_match('/\bPedidoAttachment::|->\s*(attachments|romaneios)\(\)|\$(attachment|romaneio|anexo)s?\b/', $statement) === 1;

            if ($touchesAttachments && preg_match("/->\\s*{$forbidden}\\(|::{$forbidden}\\(/", $statement) === 1) {
                $offenders[] = $file.': '.trim($statement);
            }
        }

        if (preg_match("/DB::table\\(\\s*'pedido_attachments'\\s*\\)/", $code) === 1
            && $file !== 'app/Console/Commands/ResetDemoData.php') {
            $offenders[] = $file.': DB::table(pedido_attachments)';
        }
    }

    expect($offenders)->toBe([]);

    $resetCode = pedidoAttachmentCode('app/Console/Commands/ResetDemoData.php');
    preg_match_all("/DB::table\\(\\s*'pedido_attachments'\\s*\\)[^;]*/", $resetCode, $matches);

    expect($matches[0])->toHaveCount(1);
    expect($matches[0][0])->toContain('->pluck(');
    expect($matches[0][0])->not->toMatch("/->\\s*{$forbidden}\\(/");
});

test('attachments never use the public disk nor storage/app/public (RNF-01, RF-16)', function () {
    $offenders = [];

    foreach ([...pedidoAttachmentFiles('app', '.php'), ...pedidoAttachmentFiles('routes', '.php'), ...pedidoAttachmentFiles('resources/views', '.blade.php')] as $file) {
        $code = str_ends_with($file, '.blade.php') ? file_get_contents(base_path($file)) : pedidoAttachmentCode($file);

        if (preg_match("/disk\\(\\s*['\"]public['\"]\\s*\\)|storage_path\\(\\s*['\"]app\\/public|public_path\\(|asset\\(\\s*['\"]storage/", $code) === 1) {
            $offenders[] = $file;
        }
    }

    expect($offenders)->toBe([]);

    $disk = config('filesystems.disks.'.PedidoAttachmentStorage::DISK);
    $root = realpath($disk['root']) ?: $disk['root'];

    expect($disk['driver'])->toBe('local');
    expect($disk['visibility'])->toBe('private');
    expect($disk)->not->toHaveKey('url');
    expect(str_starts_with($root, public_path()))->toBeFalse();
    expect(str_starts_with($root, storage_path('app/public')))->toBeFalse();
});

test('the download route is the only route under /pedidos/{pedido}/anexos and carries auth + active (RF-17, RF-18)', function () {
    $routes = array_values(array_filter(
        iterator_to_array(Route::getRoutes()),
        fn ($route): bool => str_contains($route->uri(), 'anexos'),
    ));

    expect($routes)->toHaveCount(1);

    $route = $routes[0];
    $middleware = $route->gatherMiddleware();

    expect($route->getName())->toBe('pedidos.anexos.download');
    expect($route->uri())->toBe('pedidos/{pedido}/anexos/{attachment}');
    expect($route->methods())->toBe(['GET', 'HEAD']);
    expect($middleware)->toContain('auth');
    expect($middleware)->toContain('active');
});

test('no Blade or app code builds a temporary URL for a file (RF-17)', function () {
    $offenders = [];

    foreach (pedidoAttachmentFiles('resources/views', '.blade.php') as $file) {
        if (str_contains(file_get_contents(base_path($file)), 'temporaryUrl')) {
            $offenders[] = $file;
        }
    }

    foreach (pedidoAttachmentFiles('app', '.php') as $file) {
        if (preg_match('/\b(temporaryUrl|temporaryUploadUrl)\s*\(/', pedidoAttachmentCode($file)) === 1) {
            $offenders[] = $file;
        }
    }

    expect($offenders)->toBe([]);
});
