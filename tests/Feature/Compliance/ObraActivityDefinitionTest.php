<?php

use App\Enums\ObraStatus;
use App\Models\Obra;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * RF-03 / RF-36 / RF-06: static guards over the PHP token stream (comments
 * and whitespace ignored) of `app/`, `database/` and `routes/`.
 *
 * (a) "obra ativa" has one single definition — `ObraStatus::isActive()` and
 *     its SQL twin `Obra::scopeActive()` — and nothing reads the dropped
 *     `obras.is_active` column any more. The migrations up to and including
 *     the conversion migration (T01) are historical and excluded: T01 is the
 *     one place that must read `is_active` to derive the status.
 * (b) no route, Livewire method or Action deletes an obra; the single
 *     exemption is `demo:reset` (`ResetDemoData`), which removes only
 *     `is_demo` data.
 */
const OBRA_STATUS_CONVERSION_MIGRATION = '2026_09_23_040313_convert_obras_activity_to_status.php';

/**
 * @return list<string>
 */
function obraActivityScannedFiles(array $directories): array
{
    $files = [];

    foreach ($directories as $directory) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($directory), FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $path = $file->getPathname();

            if (str_contains($path, DIRECTORY_SEPARATOR.'migrations'.DIRECTORY_SEPARATOR)
                && strcmp(basename($path), OBRA_STATUS_CONVERSION_MIGRATION) <= 0) {
                continue;
            }

            $files[] = $path;
        }
    }

    sort($files);

    return $files;
}

/**
 * Code-only statements of a PHP file: comments and whitespace dropped,
 * split on `;`, `{` and `}`.
 *
 * @return list<array{line: int, code: string}>
 */
function obraActivityStatements(string $path): array
{
    $statements = [];
    $buffer = '';
    $line = null;

    foreach (PhpToken::tokenize(file_get_contents($path)) as $token) {
        if ($token->is([T_COMMENT, T_DOC_COMMENT])) {
            continue;
        }

        if ($token->is([';', '{', '}'])) {
            if (trim($buffer) !== '') {
                $statements[] = ['line' => $line, 'code' => trim($buffer)];
            }

            $buffer = '';
            $line = null;

            continue;
        }

        if ($token->is(T_WHITESPACE)) {
            $buffer .= ' ';

            continue;
        }

        $line ??= $token->line;
        $buffer .= $token->text;
    }

    if (trim($buffer) !== '') {
        $statements[] = ['line' => $line ?? 0, 'code' => trim($buffer)];
    }

    return $statements;
}

function obraActivityIsObraContext(string $code): bool
{
    return (bool) preg_match('/\bObra::|\bObra\b\s*\(|->obras\b|->obra\b|\$obras?\b|[\'"]obras[\'"]|\bObraFactory\b/', $code);
}

function obraActivityRelativePath(string $path): string
{
    return ltrim(str_replace(base_path(), '', $path), DIRECTORY_SEPARATOR);
}

test('no code in app/ or database/ reads is_active in an obra context (RF-03, RF-36)', function () {
    $violations = [];

    foreach (obraActivityScannedFiles(['app', 'database']) as $path) {
        $isObraFile = (bool) preg_match('/Obra(s)?[^\/]*\.php$|\/Obras\/|\/Associacoes\//', $path)
            && ! str_contains($path, 'User');

        foreach (obraActivityStatements($path) as $statement) {
            if (! str_contains($statement['code'], 'is_active')) {
                continue;
            }

            if ($isObraFile || obraActivityIsObraContext($statement['code'])) {
                $violations[] = obraActivityRelativePath($path).':'.$statement['line'].' → '.$statement['code'];
            }
        }
    }

    expect($violations)->toBe([]);
});

test('the scanner flags an obra-context is_active read (self-check)', function () {
    $probe = tempnam(sys_get_temp_dir(), 'obra-activity').'.php';
    file_put_contents($probe, "<?php\n\$ids = Obra::query()\n    ->where('is_active', true)\n    ->pluck('id');\n");

    $flagged = array_filter(
        obraActivityStatements($probe),
        fn (array $statement): bool => str_contains($statement['code'], 'is_active') && obraActivityIsObraContext($statement['code']),
    );

    unlink($probe);

    expect($flagged)->toHaveCount(1);
});

test('ObraStatus::Concluido is referenced only by the single activity definition and its non-deciding consumers (RF-03)', function () {
    $references = [];

    foreach (obraActivityScannedFiles(['app', 'database']) as $path) {
        foreach (obraActivityStatements($path) as $statement) {
            $count = preg_match_all('/\b(?:ObraStatus|self)::Concluido\b|[\'"]concluido[\'"]/', $statement['code']);

            if ($count > 0) {
                $references[obraActivityRelativePath($path)][] = $statement['code'];
            }
        }
    }

    /*
     * Allowed:
     *  - the enum itself: case, label and `isActive()` — the definition;
     *  - `Obra::scopeActive()` — the SQL form of `isActive()`;
     *  - `Obras\Form::isConcluidoSelected()` — decides only whether the
     *    UI-04 notice is shown for the value being typed, not activity;
     *  - `ObraFactory::concluida()` — a fixture state.
     */
    expect(array_keys($references))->toEqualCanonicalizing([
        'app/Enums/ObraStatus.php',
        'app/Models/Obra.php',
        'app/Livewire/Obras/Form.php',
        'database/factories/ObraFactory.php',
    ]);

    $comparison = '/(?:!==?|===?|<>|[\'"](?:!=|<>|=)[\'"]\s*,)\s*(?:ObraStatus|self)::Concluido|(?:ObraStatus|self)::Concluido(?:->value)?\s*(?:!==?|===?)/';

    $activityDecisions = [];

    foreach ($references as $file => $statements) {
        foreach ($statements as $code) {
            if (preg_match($comparison, $code) && $file !== 'app/Livewire/Obras/Form.php') {
                $activityDecisions[] = "{$file}: {$code}";
            }
        }
    }

    expect($activityDecisions)->toHaveCount(2);
    expect(implode("\n", $activityDecisions))
        ->toContain('app/Enums/ObraStatus.php: return $this !== self::Concluido')
        ->toContain("app/Models/Obra.php: return \$query->where('status', '!=', ObraStatus::Concluido->value)");

    expect($references['app/Models/Obra.php'])->toHaveCount(1);
    expect($references['app/Livewire/Obras/Form.php'])->toHaveCount(1);
    expect($references['database/factories/ObraFactory.php'])->toHaveCount(1);
});

test('the obra activity consumers delegate to the active() scope instead of reading the status (RF-03)', function () {
    foreach (['app/Livewire/Pedidos/NovaSolicitacao.php', 'app/Actions/Pedidos/CreatePedidoAction.php'] as $file) {
        $code = implode("\n", array_column(obraActivityStatements(base_path($file)), 'code'));

        expect($code)->toContain('->active()')
            ->not->toContain('ObraStatus')
            ->not->toMatch('/where\([\'"]status[\'"]/');
    }
});

test('Obra::active() and ObraStatus::isActive() agree on every status (RF-03)', function () {
    $obrasByStatus = collect(ObraStatus::cases())
        ->mapWithKeys(fn (ObraStatus $status) => [$status->value => Obra::factory()->create(['status' => $status])]);

    $expected = $obrasByStatus
        ->filter(fn (Obra $obra) => $obra->status->isActive())
        ->map(fn (Obra $obra) => $obra->id)
        ->sort()
        ->values()
        ->all();

    expect(Obra::query()->active()->orderBy('id')->pluck('id')->all())->toBe($expected);
    expect($expected)->not->toContain($obrasByStatus[ObraStatus::Concluido->value]->id);
});

test('no statement in app/ or routes/ deletes an obra, except ResetDemoData (RF-06)', function () {
    $violations = [];

    foreach (obraActivityScannedFiles(['app', 'routes']) as $path) {
        if (basename($path) === 'ResetDemoData.php') {
            continue;
        }

        foreach (obraActivityStatements($path) as $statement) {
            $code = $statement['code'];
            $deletes = preg_match('/->(?:delete|forceDelete|forceDeleteQuietly|deleteQuietly)\s*\(|::destroy\s*\(|->truncate\s*\(/', $code);

            if ($deletes && (obraActivityIsObraContext($code) || preg_match('/table\(\s*[\'"]obras[\'"]\s*\)/', $code))) {
                $violations[] = obraActivityRelativePath($path).':'.$statement['line'].' → '.$code;
            }
        }
    }

    expect($violations)->toBe([]);
});

test('no route, Livewire method or Action is an obra deletion entry point (RF-06)', function () {
    foreach (Route::getRoutes()->getRoutes() as $route) {
        $touchesObras = str_contains($route->uri(), 'obras') || str_starts_with((string) $route->getName(), 'obras.');

        if ($touchesObras) {
            expect($route->methods())->not->toContain('DELETE', $route->uri());
            expect((string) $route->getName())->not->toMatch('/destroy|delete|excluir/i');
        }
    }

    foreach (glob(app_path('Livewire/Obras/*.php')) as $file) {
        $class = 'App\\Livewire\\Obras\\'.basename($file, '.php');

        foreach ((new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            expect($method->getName())->not->toMatch('/delete|destroy|excluir|apagar/i', "{$class}::{$method->getName()}");
        }
    }

    foreach (glob(app_path('Actions/*/*.php')) as $file) {
        expect(basename($file))->not->toMatch('/^(Delete|Destroy|Remove|Excluir)Obra(Action)?\.php$/');
    }

    $obra = Obra::factory()->create();

    foreach (['gestao', 'suprimentos', 'obra'] as $role) {
        expect(User::factory()->{$role}()->create()->can('delete', $obra))->toBeFalse();
    }
});
