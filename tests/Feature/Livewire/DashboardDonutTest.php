<?php

use App\Enums\StatusSlug;
use App\Livewire\Gestao\Dashboard;
use App\Models\Pedido;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Process;

/**
 * RF-25 (T22): the "Prazos" card renders a donut as inline SVG, with three
 * wedges sized proportionally to `indicators['prazos']`, painted exclusively
 * through complete literal `fill-*` utilities, and keeping the numeric list
 * below it untouched as the textual alternative.
 */
beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-23 15:00:00', 'UTC'));

    $this->statuses = seedWorkflowStatuses(fn (StatusSlug $slug): string => ucfirst($slug->value));

    $this->actingAs(User::factory()->gestao()->create());
});

/**
 * Extracts the donut `<svg>` markup from the prazos card.
 */
function donutMarkup(string $html): string
{
    preg_match('/data-testid="indicator-prazos".*?(<svg\b.*?<\/svg>)/s', $html, $svg);

    expect($svg)->not->toBeEmpty('no <svg> found inside [data-testid="indicator-prazos"]');

    return $svg[1];
}

/**
 * Angular sweep of each wedge, recomputed from the `d` attribute coordinates
 * themselves (not from any declared attribute), keyed by situação.
 *
 * @return array<string, float> situação => sweep in degrees
 */
function donutSweeps(string $svg): array
{
    preg_match_all('/<path\b[^>]*data-fatia="([^"]+)"[^>]*\bd="([^"]+)"/s', $svg, $paths, PREG_SET_ORDER);

    $angleAt = function (float $x, float $y): float {
        $angle = rad2deg(atan2($x - 50.0, 50.0 - $y));

        return fmod($angle + 360.0, 360.0);
    };

    $sweeps = [];

    foreach ($paths as [, $situacao, $d]) {
        expect($d)->not->toContain('NAN')->not->toContain('nan')->not->toContain('INF');

        preg_match_all('/-?\d+(?:\.\d+)?/', $d, $numbers);
        $numbers = array_map('floatval', $numbers[0]);

        // M x1 y1 A rx ry rotation large-arc sweep x2 y2 …
        $start = $angleAt($numbers[0], $numbers[1]);
        $end = $angleAt($numbers[7], $numbers[8]);

        $sweeps[$situacao] = fmod($end - $start + 360.0, 360.0);
    }

    return $sweeps;
}

test('the prazos card renders exactly one inline svg whose three wedges are proportional to the three counts', function () {
    // 5 dentro do prazo, 3 vencendo em breve, 2 atrasados = 10 pendentes.
    Pedido::factory()->count(5)->create(['status_id' => $this->statuses['solicitado']->id, 'needed_at' => now()->addDays(20)]);
    Pedido::factory()->count(3)->create(['status_id' => $this->statuses['em_analise']->id, 'needed_at' => now()->addDays(2)]);
    Pedido::factory()->count(2)->create(['status_id' => $this->statuses['aguardando_entrega']->id, 'needed_at' => now()->subDays(4)]);
    // Terminal pedidos never count as pendentes, so they must not move a wedge.
    Pedido::factory()->create(['status_id' => $this->statuses['entregue']->id, 'needed_at' => now()->subDays(9)]);

    $html = Livewire::test(Dashboard::class)->html();
    $card = preg_split('/data-testid="indicator-prazos"/', $html);

    expect(substr_count($html, 'data-testid="donut-prazos"'))->toBe(1);
    expect(substr_count($card[1] ?? '', '<svg'))->toBe(1, 'the prazos card must hold exactly one <svg>');

    $sweeps = donutSweeps(donutMarkup($html));

    expect(array_keys($sweeps))->toBe(['dentro_do_prazo', 'vencendo_em_breve', 'atrasado']);

    foreach (['dentro_do_prazo' => 5, 'vencendo_em_breve' => 3, 'atrasado' => 2] as $situacao => $count) {
        $expectedShare = $count / 10;
        $renderedShare = $sweeps[$situacao] / 360;

        expect(abs($renderedShare - $expectedShare))
            ->toBeLessThanOrEqual(0.01, "wedge [{$situacao}] renders {$renderedShare} of the donut, expected {$expectedShare}");
    }
});

test('each wedge carries its own complete literal fill utility', function () {
    Pedido::factory()->create(['status_id' => $this->statuses['solicitado']->id, 'needed_at' => now()->addDays(20)]);
    Pedido::factory()->create(['status_id' => $this->statuses['solicitado']->id, 'needed_at' => now()->addDays(2)]);
    Pedido::factory()->create(['status_id' => $this->statuses['solicitado']->id, 'needed_at' => now()->subDay()]);

    $svg = donutMarkup(Livewire::test(Dashboard::class)->html());

    foreach ([
        'dentro_do_prazo' => 'fill-success',
        'vencendo_em_breve' => 'fill-warning',
        'atrasado' => 'fill-atraso',
    ] as $situacao => $utility) {
        expect($svg)->toMatch('/<path\b[^>]*data-fatia="'.$situacao.'"[^>]*class="[^"]*\b'.$utility.'\b[^"]*"/s');
    }
});

test('the numeric prazos list survives the donut unchanged', function () {
    Pedido::factory()->count(4)->create(['status_id' => $this->statuses['solicitado']->id, 'needed_at' => now()->addDays(20)]);
    Pedido::factory()->count(2)->create(['status_id' => $this->statuses['solicitado']->id, 'needed_at' => now()->subDays(3)]);

    $html = Livewire::test(Dashboard::class)
        ->assertSeeText('Dentro do prazo')
        ->assertSeeText('Atrasados')
        ->html();

    foreach (['dentro_do_prazo', 'vencendo_em_breve', 'atrasado'] as $situacao) {
        expect($html)->toContain('data-situacao="'.$situacao.'"');
    }

    preg_match('/data-situacao="dentro_do_prazo".*?<\/li>/s', $html, $dentroDoPrazo);
    preg_match('/data-situacao="atrasado".*?<\/li>/s', $html, $atrasado);

    expect($dentroDoPrazo[0])->toContain('>4<')->toContain('bg-success');
    expect($atrasado[0])->toContain('>2<')->toContain('bg-atraso');
});

test('an empty pendentes set still renders the donut without NaN coordinates', function () {
    Pedido::factory()->create(['status_id' => $this->statuses['entregue']->id, 'needed_at' => now()->subDays(5)]);
    Pedido::factory()->create(['status_id' => $this->statuses['cancelado']->id]);

    $svg = donutMarkup(Livewire::test(Dashboard::class)->html());

    expect(donutSweeps($svg))->toBe([
        'dentro_do_prazo' => 0.0,
        'vencendo_em_breve' => 0.0,
        'atrasado' => 0.0,
    ]);
});

test('the donut markup never interpolates a class name', function () {
    $blade = file_get_contents(resource_path('views/livewire/gestao/dashboard.blade.php'));

    expect($blade)->not->toContain('fill-{{')
        ->not->toContain('stroke-{{')
        ->not->toContain("fill-'.")
        ->not->toContain("stroke-'.");
});

/**
 * RF-25: no view or stylesheet this feature adds or touches may carry a color
 * of its own — every color arrives through an `@theme` token utility.
 */
test('no resources file added or modified by this feature holds a literal color', function () {
    $mergeBase = Process::path(base_path())->run(['git', 'merge-base', 'HEAD', 'build/v0-demo-laravel']);

    $featureFiles = collect([
        'resources/views/livewire/gestao/dashboard.blade.php',
        'resources/views/livewire/suprimentos/visao-geral.blade.php',
        'resources/views/components/pedido-table.blade.php',
        'resources/views/layouts/app.blade.php',
    ]);

    $diffFiles = $mergeBase->successful()
        ? collect(preg_split('/\R/', Process::path(base_path())
            ->run(['git', 'diff', '--name-only', trim($mergeBase->output()), '--', 'resources/views', 'resources/css'])
            ->output(), -1, PREG_SPLIT_NO_EMPTY))
        : collect();

    // Once the feature is merged into the base branch the diff is empty, so the
    // scan falls back to the files the feature is known to have touched.
    // The theme stylesheet is the single place where tokens are bound to hex
    // values (pinned by ThemeTokensTest), so any uncommitted edit to it would
    // otherwise surface its `@theme` block here.
    $diffFiles = $diffFiles->reject(fn (string $file): bool => $file === 'resources/css/app.css')->values();

    $files = $diffFiles->isEmpty() ? $featureFiles : $diffFiles;

    expect($files)->not->toBeEmpty('the feature diff lists no resources file — the scan would be vacuous');

    $offenders = [];

    foreach ($files as $file) {
        $path = base_path($file);

        if (! is_file($path)) {
            continue;
        }

        foreach (preg_split('/\R/', file_get_contents($path)) as $number => $line) {
            $isOffender = preg_match('/#[0-9a-fA-F]{3,8}\b/', $line)
                || preg_match('/\b(?:rgb|rgba|hsl|hsla)\(/', $line)
                || preg_match('/\b(?:bg|text|border|fill|stroke|ring|from|to|via)-(?:slate|gray|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose)-\d{2,3}\b/', $line);

            if ($isOffender) {
                $offenders[] = $file.':'.($number + 1).' '.trim($line);
            }
        }
    }

    expect($offenders)->toBe([]);
});

test('the donut adds no JavaScript to the dashboard', function () {
    $blade = file_get_contents(resource_path('views/livewire/gestao/dashboard.blade.php'));

    expect($blade)->not->toContain('<script')
        ->not->toContain('x-data')
        ->not->toContain('chart.js');
});
