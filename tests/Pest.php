<?php

use App\Enums\EventTypeSlug;
use App\Enums\StatusSlug;
use App\Models\EventType;
use App\Models\Status;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Unit', 'Browser');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| Lookup fixtures shared by the suites. The `finalizado` status and the
| history event types are inserted by a migration (RF-42), so every fixture
| resolves lookup rows by slug with `firstOrCreate` instead of inserting them
| blindly, which would collide with the migrated rows.
|
*/

/**
 * Ensures one `statuses` row per {@see StatusSlug} case, with
 * `sort_order` = case position + 1, reusing rows that already exist.
 *
 * @param  (Closure(StatusSlug): string)|null  $nameFor
 * @return array<string, Status>
 */
function seedWorkflowStatuses(?Closure $nameFor = null): array
{
    $statuses = [];

    foreach (StatusSlug::cases() as $index => $slug) {
        $attributes = ['slug' => $slug->value, 'sort_order' => $index + 1];

        if ($nameFor !== null) {
            $attributes['name'] = $nameFor($slug);
        }

        $statuses[$slug->value] = Status::query()->firstOrCreate(
            ['slug' => $slug->value],
            Status::factory()->raw($attributes),
        );
    }

    return $statuses;
}

/**
 * Ensures one `event_types` row per {@see EventTypeSlug} case,
 * reusing rows that already exist.
 *
 * @return array<string, EventType>
 */
function seedHistoryEventTypes(): array
{
    $eventTypes = [];

    foreach (EventTypeSlug::cases() as $slug) {
        $eventTypes[$slug->value] = EventType::query()->firstOrCreate(
            ['slug' => $slug->value],
            EventType::factory()->raw(['slug' => $slug->value]),
        );
    }

    return $eventTypes;
}

/*
|--------------------------------------------------------------------------
| Attachment fixtures
|--------------------------------------------------------------------------
|
| Real bytes for each attachment type: the storage service sniffs the
| content with `finfo`, so a fake file with a declared MIME type would not
| exercise the type detection (RF-14, RNF-01).
|
*/

/**
 * A minimal PDF, padded with trailing spaces to `$size` bytes when given.
 */
function anexoPdfBytes(?int $size = null): string
{
    $pdf = "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n";

    return $size === null ? $pdf : str_pad($pdf, $size, ' ');
}

/**
 * A 1×1 PNG (`ext-gd` is not loaded locally, so no image is generated).
 */
function anexoPngBytes(): string
{
    return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');
}

function anexoJpegBytes(): string
{
    return base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA=');
}

function anexoWebpBytes(): string
{
    return base64_decode('UklGRiIAAABXRUJQVlA4IBYAAAAwAQCdASoBAAEADsD+JaQAA3AAAAAA');
}

function anexoDocxBytes(): string
{
    return anexoOoxmlBytes('word/document.xml', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml');
}

function anexoXlsxBytes(): string
{
    return anexoOoxmlBytes('xl/workbook.xml', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml');
}

/**
 * A minimal OOXML package with `[Content_Types].xml` as its first entry,
 * which is what libmagic inspects to tell DOCX/XLSX from a plain ZIP.
 */
function anexoOoxmlBytes(string $mainPart, string $contentType): string
{
    $path = tempnam(sys_get_temp_dir(), 'ooxml');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::OVERWRITE);
    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="xml" ContentType="application/xml"/><Override PartName="/'.$mainPart.'" ContentType="'.$contentType.'"/></Types>');
    $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>');
    $zip->addFromString($mainPart, '<?xml version="1.0" encoding="UTF-8"?><root/>');
    $zip->close();

    $bytes = (string) file_get_contents($path);
    unlink($path);

    return $bytes;
}

function anexoZipBytes(): string
{
    $path = tempnam(sys_get_temp_dir(), 'zip');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::OVERWRITE);
    $zip->addFromString('leia-me.txt', 'conteúdo');
    $zip->close();

    $bytes = (string) file_get_contents($path);
    unlink($path);

    return $bytes;
}

function anexoHtmlBytes(): string
{
    return '<!DOCTYPE html><html><head><title>x</title></head><body><script>alert(1)</script></body></html>';
}

function anexoSvgBytes(): string
{
    return '<?xml version="1.0" encoding="UTF-8"?><svg xmlns="http://www.w3.org/2000/svg" width="1" height="1"><script>alert(1)</script></svg>';
}
