<?php

namespace Tests\Browser\Support;

use Closure;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Symfony\Component\HttpFoundation\Response;

/**
 * Test-only shim for the Browser suite.
 *
 * pest-plugin-browser's in-process `LaravelHttpServer` builds every request
 * from the raw body and never fills `$_FILES` (its source carries
 * `[], // @TODO files...`), so a real browser upload — Livewire's
 * `multipart/form-data` POST to `/livewire/upload-file` — reaches the app
 * without any file. This middleware parses that raw multipart body into
 * `UploadedFile` instances (test mode, so `is_uploaded_file()` is not
 * required), exactly what PHP would have done in front of a real server.
 * It never runs outside the tests that register it through {@see register()}.
 */
class ParsesMultipartUploads
{
    /**
     * Also fakes Livewire's temporary upload disk: under tests Livewire uses
     * `tmp-for-tests`, which it fakes lazily only on the `Livewire::test()`
     * path, never in the upload controller a real browser hits.
     */
    public static function register(): void
    {
        Storage::fake(FileUploadConfiguration::disk());

        app(Kernel::class)->prependMiddleware(self::class);
    }

    public function handle(Request $request, Closure $next): Response
    {
        $contentType = (string) $request->headers->get('content-type', '');

        if ($request->files->count() === 0
            && preg_match('/^multipart\/form-data;.*boundary="?([^";]+)"?/i', $contentType, $matches) === 1) {
            [$fields, $files] = $this->parse((string) $request->getContent(), $matches[1]);

            $request->request->add($fields);
            $request->files->add($files);
        }

        return $next($request);
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function parse(string $body, string $boundary): array
    {
        $fields = [];
        $files = [];

        foreach (explode('--'.$boundary, $body) as $part) {
            $part = ltrim($part, "\r\n");

            if ($part === '' || str_starts_with($part, '--')) {
                continue;
            }

            [$rawHeaders, $content] = array_pad(explode("\r\n\r\n", $part, 2), 2, '');
            $content = substr($content, 0, -2);

            if (preg_match('/name="([^"]*)"/i', $rawHeaders, $name) !== 1) {
                continue;
            }

            if (preg_match('/filename="([^"]*)"/i', $rawHeaders, $filename) === 1) {
                preg_match('/Content-Type:\s*([^\r\n]+)/i', $rawHeaders, $type);

                $path = tempnam(sys_get_temp_dir(), 'browser-upload');
                file_put_contents($path, $content);

                $this->assign($files, $name[1], new UploadedFile($path, $filename[1], $type[1] ?? null, UPLOAD_ERR_OK, true));

                continue;
            }

            $this->assign($fields, $name[1], $content);
        }

        return [$fields, $files];
    }

    /**
     * Assigns `name` or `name[]` the way PHP builds `$_POST`/`$_FILES`.
     *
     * @param  array<string, mixed>  $target
     */
    private function assign(array &$target, string $name, mixed $value): void
    {
        if (str_ends_with($name, '[]')) {
            $target[substr($name, 0, -2)][] = $value;

            return;
        }

        $target[$name] = $value;
    }
}
