<?php

use App\Services\PedidoAttachmentStorage;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;

/**
 * Converts a php.ini shorthand size ("12M", "512K", "1G", "2048") to bytes.
 */
function iniSizeToBytes(string $value): int
{
    $value = trim($value);
    $number = (int) $value;

    return match (strtoupper(substr($value, -1))) {
        'G' => $number * 1024 * 1024 * 1024,
        'M' => $number * 1024 * 1024,
        'K' => $number * 1024,
        default => $number,
    };
}

/**
 * @return array<string, string>
 */
function versionedUploadLimits(): array
{
    $path = config_path('php/uploads.ini');

    expect(file_exists($path))->toBeTrue();

    return array_map('strval', parse_ini_file($path, false, INI_SCANNER_RAW));
}

test('the versioned ini keeps every upload limit above the 10 MB attachment limit (RNF-07)', function () {
    $limits = versionedUploadLimits();

    expect($limits)->toHaveKeys(['upload_max_filesize', 'post_max_size', 'max_file_uploads']);

    $uploadMax = iniSizeToBytes($limits['upload_max_filesize']);
    $postMax = iniSizeToBytes($limits['post_max_size']);

    expect($uploadMax)->toBeGreaterThanOrEqual(iniSizeToBytes('10M'));
    expect($postMax)->toBeGreaterThan($uploadMax);
    expect($uploadMax)->toBeGreaterThanOrEqual(PedidoAttachmentStorage::MAX_BYTES);
    expect($postMax)->toBeGreaterThanOrEqual(PedidoAttachmentStorage::MAX_BYTES);
    expect((int) $limits['max_file_uploads'])->toBeGreaterThanOrEqual(1);
});

test('the Livewire temporary upload rule accepts a 10 MB file (RNF-07)', function () {
    $maxRule = collect(FileUploadConfiguration::rules())
        ->first(fn ($rule): bool => is_string($rule) && str_starts_with($rule, 'max:'));

    expect($maxRule)->not->toBeNull();
    expect((int) substr($maxRule, 4))->toBeGreaterThanOrEqual(intdiv(PedidoAttachmentStorage::MAX_BYTES, 1024));
});

test('config/livewire.php does not narrow the temporary upload rule', function () {
    $path = config_path('livewire.php');

    if (! file_exists($path)) {
        expect(config('livewire.temporary_file_upload.rules'))->toBeNull();

        return;
    }

    $rules = config('livewire.temporary_file_upload.rules');

    if ($rules === null) {
        expect(true)->toBeTrue();

        return;
    }

    $rules = is_array($rules) ? $rules : explode('|', $rules);
    $max = collect($rules)->first(fn ($rule): bool => is_string($rule) && str_starts_with($rule, 'max:'));

    expect($max)->not->toBeNull();
    expect((int) substr($max, 4))->toBeGreaterThanOrEqual(intdiv(PedidoAttachmentStorage::MAX_BYTES, 1024));
});

test('no deploy configuration file was added at the repository root (RNF-07)', function (string $file) {
    expect(file_exists(base_path($file)))->toBeFalse();
})->with(['Caddyfile', 'railpack.json', 'Dockerfile']);
