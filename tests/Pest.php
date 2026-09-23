<?php

use App\Enums\EventTypeSlug;
use App\Enums\StatusSlug;
use App\Models\EventType;
use App\Models\Status;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Pest\Browser\Api\AwaitableWebpage;
use Pest\Browser\Api\PendingAwaitablePage;
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

/*
|--------------------------------------------------------------------------
| Browser helpers
|--------------------------------------------------------------------------
|
| Shared by the `tests/Browser` suite: the responsive/accessibility audit
| (moved verbatim from `ResponsiveIdentityTest`) and the sidebar helpers of
| navegacao-sidebar-listagens (below `lg` the sidebar is a drawer behind the
| "Menu" button of the top bar).
|
*/

/**
 * JS audit run inside the page: overflow, primary control bounds, labels and
 * focus visibility. `el.focus()` after a keyboard Tab keeps the browser in
 * keyboard modality, so `:focus-visible` rules apply exactly as for a user
 * tabbing through the screen.
 */
const RESPONSIVE_AUDIT_SCRIPT = <<<'JS'
    ((primarySelector) => {
        const root = document.documentElement;
        const isRendered = (el) => el.getClientRects().length > 0 && getComputedStyle(el).visibility !== 'hidden';
        const describe = (el) => `<${el.tagName.toLowerCase()}${el.id ? '#' + el.id : ''}${el.className ? '.' + String(el.className).trim().split(/\s+/).join('.') : ''}>`;

        const primary = document.querySelector(primarySelector);
        const primaryRect = primary ? primary.getBoundingClientRect() : null;

        const unlabelled = [];
        for (const el of document.querySelectorAll('input:not([type="hidden"])')) {
            if (!isRendered(el)) continue;
            const hasLabelFor = el.id !== '' && document.querySelector(`label[for="${CSS.escape(el.id)}"]`) !== null;
            if (!hasLabelFor) unlabelled.push(describe(el));
        }
        for (const el of document.querySelectorAll('select, textarea')) {
            if (!isRendered(el)) continue;
            if (el.labels.length === 0 && !el.getAttribute('aria-label')) unlabelled.push(describe(el));
        }

        const ringWidth = (boxShadow) => {
            let max = 0;
            for (const match of boxShadow.matchAll(/(?:^|,)\s*((?:rgba?|oklab|oklch|color)\([^)]*\)|#[0-9a-f]+|[a-z]+)\s+0px 0px 0px (\d+(?:\.\d+)?)px/gi)) {
                const color = match[1];
                const width = parseFloat(match[2]);
                if (/^rgba\(0, 0, 0, 0\)$/.test(color) || color === 'transparent') continue;
                max = Math.max(max, width);
            }
            return max;
        };

        const withoutFocus = [];
        for (const el of document.querySelectorAll('a[href], button, input:not([type="hidden"]), select, textarea')) {
            if (!isRendered(el) || el.disabled) continue;
            el.focus();
            if (document.activeElement !== el) continue;
            const cs = getComputedStyle(el);
            const outline = cs.outlineStyle !== 'none' ? parseFloat(cs.outlineWidth) : 0;
            const ring = ringWidth(cs.boxShadow);
            if (Math.max(outline, ring) < 2) {
                withoutFocus.push(`${describe(el)} outline=${cs.outlineStyle} ${cs.outlineWidth} box-shadow=${cs.boxShadow}`);
            }
        }
        if (document.activeElement instanceof HTMLElement) document.activeElement.blur();

        return {
            scrollWidth: root.scrollWidth,
            clientWidth: root.clientWidth,
            primaryFound: primary !== null,
            primaryRendered: primary !== null && isRendered(primary),
            primaryLeft: primaryRect ? Math.round(primaryRect.left) : null,
            primaryRight: primaryRect ? Math.round(primaryRect.right) : null,
            unlabelled,
            withoutFocus,
        };
    })
    JS;

/**
 * Renders the given screen at the viewport and asserts the UI-21/UI-22 rules.
 */
function assertResponsiveAndAccessible(PendingAwaitablePage $page, string $path, int $width, int $height, string $primarySelector): void
{
    $page->resize($width, $height);
    $page->page()->goto(url($path));
    $page->page()->waitForFunction('() => window.Livewire !== undefined');
    $page->page()->locator('body')->press('Tab');

    $audit = $page->script(RESPONSIVE_AUDIT_SCRIPT."('".addslashes($primarySelector)."')");

    expect($audit['scrollWidth'])
        ->toBeLessThanOrEqual($audit['clientWidth'], "[{$path}] at {$width}px overflows horizontally: scrollWidth {$audit['scrollWidth']} > clientWidth {$audit['clientWidth']}");

    expect($audit['primaryFound'])->toBeTrue("[{$path}] at {$width}px: primary control [{$primarySelector}] not found");
    expect($audit['primaryRendered'])->toBeTrue("[{$path}] at {$width}px: primary control [{$primarySelector}] is not rendered");
    expect($audit['primaryLeft'])->toBeGreaterThanOrEqual(0, "[{$path}] at {$width}px: primary control starts outside the viewport");
    expect($audit['primaryRight'])->toBeLessThanOrEqual($audit['clientWidth'], "[{$path}] at {$width}px: primary control ends outside the viewport");

    expect($audit['unlabelled'])->toBe([], "[{$path}] at {$width}px: form controls without an associated label: ".implode(', ', $audit['unlabelled']));
    expect($audit['withoutFocus'])->toBe([], "[{$path}] at {$width}px: focusable controls without a visible focus indicator ≥ 2px: ".implode(' | ', $audit['withoutFocus']));

    $page->assertNoJavascriptErrors();
}

/**
 * Opens the sidebar drawer when the viewport collapses it (below `lg`), by
 * clicking `[data-testid="menu-toggle"]`; a no-op on desktop, where the
 * sidebar is always visible, and when the drawer is already open. Waits for
 * Alpine to own the layout first: a click that lands before the `x-on:click`
 * listener exists is lost after a full-page navigation.
 */
function openSidebarIfCollapsed(PendingAwaitablePage|AwaitableWebpage $page): PendingAwaitablePage|AwaitableWebpage
{
    $page->page()->waitForFunction('() => document.readyState === "complete" && document.body._x_dataStack !== undefined');

    $toggle = $page->page()->locator('[data-testid="menu-toggle"]');

    if (! $page->page()->locator('#sidebar nav')->isVisible() && $toggle->isVisible()) {
        $toggle->click();
        $page->page()->locator('#sidebar nav')->waitFor(['state' => 'visible']);
    }

    return $page;
}

/**
 * Logs out through the "Sair" button of the sidebar, opening the drawer
 * first on narrow viewports, and asserts the landing on `/login`.
 */
function logoutThroughSidebar(PendingAwaitablePage|AwaitableWebpage $page): PendingAwaitablePage|AwaitableWebpage
{
    openSidebarIfCollapsed($page);

    $page->page()->locator('#sidebar form[action$="/logout"] button[type="submit"]')->click();

    return $page->assertPathIs('/login');
}
