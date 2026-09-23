<?php

use App\Domain\Pedidos\DataPrevistaCalculator;
use App\Models\Pedido;
use Carbon\CarbonImmutable;

/**
 * RF-11 / RF-12: the Data prevista has one live rule and one presentation
 * point.
 *
 * - Business-day logic lives only in `DataPrevistaCalculator` and
 *   `BrazilianNationalHolidays` inside `app/`; the only other
 *   implementation in the repository is the frozen copy inside the backfill
 *   migration (RF-12), kept by design so a later change of the live rule
 *   never rewrites historical rows.
 * - No `easter_date()`/`easter_days()` (no `ext-calendar`).
 * - "3 dias" is never displayed as a forecast value; the only mention of the
 *   business-day count in a view is the explanatory note built from
 *   `DataPrevistaCalculator::DIAS_UTEIS`.
 * - Every display of Data prevista goes through `Pedido::dataPrevistaLabel()`
 *   or `Pedido::presentDataPrevista()`.
 *
 * PHP files are scanned without comments (PhpToken), so docblocks that name
 * the rule never count as an occurrence.
 */
const DATA_PREVISTA_RULE_FILES = [
    'app/Domain/Pedidos/DataPrevistaCalculator.php',
    'app/Domain/Pedidos/BrazilianNationalHolidays.php',
];

const DATA_PREVISTA_FROZEN_MIGRATION = 'database/migrations/2026_09_23_083524_add_outra_reference_and_data_prevista_to_pedidos.php';

const DATA_PREVISTA_BUSINESS_DAY_TOKENS = '/\b(isWeekend|isWeekday|addWeekdays|subWeekdays|addBusinessDays|DIAS_UTEIS|BrazilianNationalHolidays::)|->format\(\s*[\'"]N[\'"]\s*\)|\bdayOfWeek(Iso)?\b/';

/**
 * @return list<string> repository-relative paths
 */
function dataPrevistaFiles(string $directory, string $suffix): array
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

function dataPrevistaCodeOnly(string $relativePath): string
{
    $code = '';

    foreach (PhpToken::tokenize(file_get_contents(base_path($relativePath))) as $token) {
        if (! $token->is([T_COMMENT, T_DOC_COMMENT])) {
            $code .= $token->text;
        }
    }

    return $code;
}

function dataPrevistaBladeWithoutComments(string $relativePath): string
{
    return (string) preg_replace('/\{\{--.*?--\}\}/s', '', file_get_contents(base_path($relativePath)));
}

test('business-day logic in app/ lives only in the calculator and the holiday calendar (RF-11)', function () {
    $offenders = [];

    foreach (dataPrevistaFiles('app', '.php') as $file) {
        if (in_array($file, DATA_PREVISTA_RULE_FILES, true)) {
            continue;
        }

        if (preg_match(DATA_PREVISTA_BUSINESS_DAY_TOKENS, dataPrevistaCodeOnly($file)) === 1) {
            $offenders[] = $file;
        }
    }

    expect($offenders)->toBe([]);

    foreach (DATA_PREVISTA_RULE_FILES as $file) {
        expect(file_exists(base_path($file)))->toBeTrue();
    }

    expect(dataPrevistaCodeOnly(DATA_PREVISTA_RULE_FILES[0]))->toContain('DIAS_UTEIS');
});

test('the frozen copy in the backfill migration is the only other business-day implementation (RF-12)', function () {
    $implementations = [];

    foreach ([...dataPrevistaFiles('database', '.php'), ...dataPrevistaFiles('routes', '.php'), ...dataPrevistaFiles('config', '.php')] as $file) {
        if (preg_match(DATA_PREVISTA_BUSINESS_DAY_TOKENS, dataPrevistaCodeOnly($file)) === 1) {
            $implementations[] = $file;
        }
    }

    expect($implementations)->toBe([DATA_PREVISTA_FROZEN_MIGRATION]);
    expect(dataPrevistaCodeOnly(DATA_PREVISTA_FROZEN_MIGRATION))->not->toContain('DataPrevistaCalculator');
});

test('easter_date and easter_days appear nowhere in app/ or database/ (RF-11)', function () {
    $offenders = [];

    foreach ([...dataPrevistaFiles('app', '.php'), ...dataPrevistaFiles('database', '.php')] as $file) {
        if (preg_match('/\beaster_(date|days)\b/', dataPrevistaCodeOnly($file)) === 1) {
            $offenders[] = $file;
        }
    }

    expect($offenders)->toBe([]);
});

test('no view or app code displays "3 dias" as a forecast value (RF-11)', function () {
    $offenders = [];

    foreach (dataPrevistaFiles('resources/views', '.blade.php') as $file) {
        if (preg_match('/\b3\s+dias\b/iu', dataPrevistaBladeWithoutComments($file)) === 1) {
            $offenders[] = $file;
        }
    }

    /**
     * The "Solicitado" period preset label of slice 3 (RF-15) names a filter
     * window, not a forecast value; it is the only literal exempted.
     */
    $periodPresetLabel = "'Últimos 3 dias'";

    foreach (dataPrevistaFiles('app', '.php') as $file) {
        if (preg_match('/[\'"][^\'"]*\b3\s+dias\b[^\'"]*[\'"]/iu', str_replace($periodPresetLabel, "''", dataPrevistaCodeOnly($file))) === 1) {
            $offenders[] = $file;
        }
    }

    expect($offenders)->toBe([]);

    $notes = array_filter(
        dataPrevistaFiles('resources/views', '.blade.php'),
        fn (string $file): bool => str_contains(dataPrevistaBladeWithoutComments($file), 'dias úteis'),
    );

    foreach ($notes as $file) {
        expect(dataPrevistaBladeWithoutComments($file))->toContain('DataPrevistaCalculator::DIAS_UTEIS }} dias úteis');
    }
});

test('every Blade that shows Data prevista goes through the single presentation point (RF-11, CT-06)', function () {
    $displaying = [];
    $offenders = [];

    foreach (dataPrevistaFiles('resources/views', '.blade.php') as $file) {
        $source = dataPrevistaBladeWithoutComments($file);

        if (preg_match('/data_prevista\??->|data_prevista\b[^\n]*format\(/', $source) === 1) {
            $offenders[] = $file;
        }

        if (! str_contains($source, 'Data prevista')) {
            continue;
        }

        $displaying[] = $file;

        if (! str_contains($source, 'dataPrevistaLabel()') && ! str_contains($source, 'presentDataPrevista(')) {
            $offenders[] = $file;
        }
    }

    expect($displaying)->not->toBeEmpty();
    expect($offenders)->toBe([]);

    $appOffenders = [];

    foreach (dataPrevistaFiles('app', '.php') as $file) {
        if ($file === 'app/Models/Pedido.php') {
            continue;
        }

        if (preg_match('/data_prevista\??->\s*(format|translatedFormat|isoFormat|toDateString)\(/', dataPrevistaCodeOnly($file)) === 1) {
            $appOffenders[] = $file;
        }
    }

    expect($appOffenders)->toBe([]);
});

test('the presentation point renders the calculator result as dd/mm/aaaa (RF-11, CT-06)', function () {
    $requestedAt = CarbonImmutable::parse('2026-09-25 15:00:00', 'UTC');

    expect(Pedido::presentDataPrevista(DataPrevistaCalculator::forRequestedAt($requestedAt)))
        ->toMatch('/^\d{2}\/\d{2}\/\d{4}$/')
        ->toBe('30/09/2026');
});
