<?php

namespace App\Services;

use App\Enums\PedidoAttachmentKind;
use App\Models\Pedido;
use finfo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Validation and private storage of pedido attachments (RF-14, RF-16,
 * RF-20, RNF-01, CT-03).
 *
 * The type is decided by sniffing the bytes (`finfo`), never by
 * `getMimeType()`/`getClientMimeType()`: Livewire's temporary upload reports
 * the declared type and Flysystem falls back to the extension, so trusting
 * them would accept a spoofed file. The detected type and the client
 * extension must both be in the kind's allow-list and match each other.
 *
 * Files live on the private `pedido_anexos` disk under a server-generated
 * name (`<pedido_id>/<160 random bits>.<ext>`): no user input ever reaches a
 * filesystem path, and the original name is kept only as sanitized display
 * metadata.
 */
class PedidoAttachmentStorage
{
    public const string DISK = 'pedido_anexos';

    public const int MAX_BYTES = 10 * 1024 * 1024;

    public const int MAX_ANEXOS_POR_PEDIDO = 10;

    public const int MAX_DISPLAY_NAME_LENGTH = 150;

    /**
     * Validates one file for `$kind`, reporting any failure on `$errorKey`
     * with a PT-BR message naming the file.
     *
     * @return array{mime: string, extension: string, size: int, display_name: string}
     *
     * @throws ValidationException
     */
    public function inspect(UploadedFile $file, PedidoAttachmentKind $kind, string $errorKey): array
    {
        $allowedTypes = $kind->allowedTypes();
        $extension = $this->normalizeExtension($file->getClientOriginalExtension());
        $displayName = $this->sanitizeDisplayName($file->getClientOriginalName(), $extension);
        $size = (int) $file->getSize();

        if ($size <= 0) {
            $this->fail($errorKey, "O arquivo «{$displayName}» está vazio.");
        }

        if ($size > self::MAX_BYTES) {
            $this->fail($errorKey, "O arquivo «{$displayName}» excede 10 MB.");
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer((string) $file->get());

        if (! array_key_exists($extension, $allowedTypes) || $allowedTypes[$extension] !== $mime) {
            $this->fail($errorKey, "O arquivo «{$displayName}» não é de um tipo permitido ({$kind->allowedTypesLabel()}).");
        }

        return [
            'mime' => $mime,
            'extension' => $extension,
            'size' => $size,
            'display_name' => $displayName,
        ];
    }

    /**
     * Keeps only the last path segment of the original name, without
     * control characters or characters outside a safe set, bounded to
     * {@see self::MAX_DISPLAY_NAME_LENGTH} characters keeping the extension.
     */
    public function sanitizeDisplayName(string $original, string $extension): string
    {
        $segments = preg_split('/[\/\\\\]/', $original) ?: [];
        $name = (string) end($segments);

        $name = (string) preg_replace('/[\p{C}]/u', '', $name);
        $name = (string) preg_replace('/[^\p{L}\p{N} ._()\-]/u', '', $name);
        $name = (string) preg_replace('/\s+/u', ' ', $name);
        $name = trim($name, ' .');

        $fallback = $extension !== '' ? "anexo.{$extension}" : 'anexo';

        if ($name === '' || $name === $extension) {
            return $fallback;
        }

        if (mb_strlen($name) <= self::MAX_DISPLAY_NAME_LENGTH) {
            return $name;
        }

        $suffix = $extension !== '' && str_ends_with(mb_strtolower($name), '.'.$extension)
            ? mb_substr($name, -(mb_strlen($extension) + 1))
            : '';

        $base = rtrim(mb_substr($name, 0, self::MAX_DISPLAY_NAME_LENGTH - mb_strlen($suffix)), ' .');

        return $base.$suffix;
    }

    /**
     * Writes the file under a server-generated name and returns its path
     * relative to the disk root.
     */
    public function store(Pedido $pedido, UploadedFile $file, string $extension): string
    {
        $path = $pedido->id.'/'.bin2hex(random_bytes(20)).'.'.$extension;

        Storage::disk(self::DISK)->put($path, (string) $file->get());

        return $path;
    }

    public function exists(string $path): bool
    {
        return Storage::disk(self::DISK)->exists($path);
    }

    /**
     * Best-effort removal of files written for a rolled-back operation
     * (RNF-02). Failures are swallowed: the caller is already handling the
     * original error, and no row references these paths.
     *
     * @param  list<string>  $paths
     */
    public function deleteQuietly(array $paths): void
    {
        foreach ($paths as $path) {
            try {
                Storage::disk(self::DISK)->delete($path);
            } catch (Throwable) {
                // Best effort only.
            }
        }
    }

    private function normalizeExtension(string $extension): string
    {
        $extension = mb_strtolower($extension);

        return $extension === 'jpeg' ? 'jpg' : $extension;
    }

    /**
     * @throws ValidationException
     */
    private function fail(string $errorKey, string $message): never
    {
        throw ValidationException::withMessages([$errorKey => $message]);
    }
}
