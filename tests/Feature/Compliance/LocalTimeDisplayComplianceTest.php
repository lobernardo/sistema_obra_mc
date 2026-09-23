<?php

/**
 * RF-47 / F-12 / RF-48: static guards over the views owned by slices 1 and 2
 * and over the migrations of slice 2.
 *
 * - In the pedido summary and history timeline, the three detail screens,
 *   the Nova Solicitação, the Kanban boards and cards, the Gestão Dashboard,
 *   the Suprimentos Visão Geral, the Obras and Associações areas and the
 *   convite/cadastro pages, no `->format(` whose pattern carries `H:i` may
 *   appear (only `App\Support\LocalTime` formats a time), and no timestamp
 *   attribute (`requested_at`, `created_at`, `expires_at`, `revoked_at`,
 *   `used_at`) is formatted directly: it goes through
 *   `LocalTime::formatDateTime()`/`formatDate()`. The machine-readable
 *   `<time datetime="…">` value (`toIso8601String()`, which carries the UTC
 *   offset) is the only allowed direct serialization.
 * - `x-pedido-table` is excluded: slice 3 owns its "Solicitado em" column.
 * - No migration of slice 2 writes `obra_profile` (RF-48): associations are
 *   made by people in `/associacoes`, never by a deploy.
 */
const LOCAL_TIME_VIEW_GLOBS = [
    'resources/views/components/pedido-summary.blade.php',
    'resources/views/components/pedido-history-timeline.blade.php',
    'resources/views/livewire/pedidos/*.blade.php',
    'resources/views/livewire/obra/pedido-detalhe.blade.php',
    'resources/views/livewire/suprimentos/pedido-detalhe.blade.php',
    'resources/views/livewire/suprimentos/visao-geral.blade.php',
    'resources/views/livewire/gestao/pedido-detalhe.blade.php',
    'resources/views/livewire/gestao/dashboard.blade.php',
    'resources/views/livewire/gestao/pedido-card-read-only.blade.php',
    'resources/views/livewire/gestao/kanban-read-only.blade.php',
    'resources/views/livewire/kanban/*.blade.php',
    'resources/views/livewire/obras/*.blade.php',
    'resources/views/livewire/associacoes/*.blade.php',
    'resources/views/livewire/auth/obra-invitation-page.blade.php',
    'resources/views/livewire/auth/register.blade.php',
];

const LOCAL_TIME_EXCLUDED_VIEWS = [
    'resources/views/components/pedido-table.blade.php',
];

const LOCAL_TIME_TIMESTAMP_FORMAT = '/\b(requested_at|created_at|expires_at|revoked_at|used_at)\s*\??->\s*(format|translatedFormat|isoFormat|toDateString|toDateTimeString|toTimeString|toFormattedDateString|toDayDateTimeString|diffForHumans|setTimezone|timezone|tz)\s*\(/';

const LOCAL_TIME_HOUR_FORMAT = '/->\s*(format|translatedFormat)\(\s*[\'"][^\'"]*H:i[^\'"]*[\'"]\s*\)|\bdate\(\s*[\'"][^\'"]*H:i/';

/**
 * First slice-2 migration: every migration from this one on belongs to
 * this slice (or a later one) and must not write `obra_profile`.
 */
const LOCAL_TIME_SLICE_2_FIRST_MIGRATION = '2026_09_23_083524_add_outra_reference_and_data_prevista_to_pedidos.php';

/**
 * @return list<string> repository-relative paths
 */
function localTimeScannedViews(): array
{
    $files = [];

    foreach (LOCAL_TIME_VIEW_GLOBS as $pattern) {
        $matches = glob(base_path($pattern)) ?: [];

        expect($matches)->not->toBeEmpty("The glob {$pattern} matched no view.");

        foreach ($matches as $match) {
            $files[] = ltrim(str_replace(base_path(), '', $match), DIRECTORY_SEPARATOR);
        }
    }

    $files = array_values(array_unique(array_diff($files, LOCAL_TIME_EXCLUDED_VIEWS)));
    sort($files);

    return $files;
}

function localTimeBladeSource(string $relativePath): string
{
    return (string) preg_replace('/\{\{--.*?--\}\}/s', '', file_get_contents(base_path($relativePath)));
}

/**
 * @return list<string>
 */
function localTimeViolations(string $source): array
{
    $violations = [];

    foreach (preg_split('/\R/', $source) as $number => $line) {
        if (preg_match(LOCAL_TIME_HOUR_FORMAT, $line) === 1 || preg_match(LOCAL_TIME_TIMESTAMP_FORMAT, $line) === 1) {
            $violations[] = ($number + 1).': '.trim($line);
        }
    }

    return $violations;
}

test('the slice 1 and 2 views never format a time or a timestamp outside LocalTime (RF-47, F-12)', function () {
    $views = localTimeScannedViews();
    $offenders = [];

    expect($views)->not->toContain('resources/views/components/pedido-table.blade.php');

    foreach ($views as $view) {
        foreach (localTimeViolations(localTimeBladeSource($view)) as $violation) {
            $offenders[] = "{$view}:{$violation}";
        }
    }

    expect($offenders)->toBe([]);
});

test('the views that show a timestamp route it through LocalTime (RF-47)', function () {
    foreach ([
        'resources/views/components/pedido-summary.blade.php' => 'LocalTime::formatDateTime($pedido->requested_at)',
        'resources/views/livewire/obras/form.blade.php' => 'LocalTime::formatDateTime($invitation->expires_at)',
    ] as $view => $call) {
        expect(localTimeBladeSource($view))->toContain($call);
    }

    $timeline = localTimeBladeSource('resources/views/components/pedido-history-timeline.blade.php');

    expect($timeline)->toContain("\$description['at']");
    expect(file_get_contents(app_path('Services/PedidoEventValuePresenter.php')))->toContain('LocalTime::formatDateTime($event->created_at)');
});

test('the scanner flags a direct timestamp or hour format (self-check)', function () {
    expect(localTimeViolations("{{ \$pedido->requested_at->format('d/m/Y') }}"))->toHaveCount(1);
    expect(localTimeViolations("{{ \$invitation->expires_at?->format('d/m/Y') }}"))->toHaveCount(1);
    expect(localTimeViolations("{{ \$now->format('d/m/Y H:i') }}"))->toHaveCount(1);
    expect(localTimeViolations('{{ $event->created_at->diffForHumans() }}'))->toHaveCount(1);
    expect(localTimeViolations('{{ \\App\\Support\\LocalTime::formatDateTime($pedido->requested_at) }}'))->toBe([]);
    expect(localTimeViolations("{{ \$pedido->needed_at->format('d/m/Y') }}"))->toBe([]);
    expect(localTimeViolations('<time datetime="{{ $event->created_at->toIso8601String() }}">'))->toBe([]);
});

test('no migration of this slice writes obra_profile (RF-48)', function () {
    $migrations = array_values(array_filter(
        glob(database_path('migrations/*.php')) ?: [],
        fn (string $path): bool => strcmp(basename($path), LOCAL_TIME_SLICE_2_FIRST_MIGRATION) >= 0,
    ));

    expect($migrations)->not->toBeEmpty();

    $offenders = [];

    foreach ($migrations as $migration) {
        $code = '';

        foreach (PhpToken::tokenize(file_get_contents($migration)) as $token) {
            if (! $token->is([T_COMMENT, T_DOC_COMMENT])) {
                $code .= $token->text;
            }
        }

        if (preg_match('/obra_profile|->\s*(users|obras)\(\)\s*->\s*(attach|sync|syncWithoutDetaching|toggle|detach)\(/', $code) === 1) {
            $offenders[] = basename($migration);
        }
    }

    expect($offenders)->toBe([]);
});
