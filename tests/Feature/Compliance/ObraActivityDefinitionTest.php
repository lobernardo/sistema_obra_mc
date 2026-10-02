<?php

use App\Enums\ObraStatus;
use App\Models\Obra;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/**
 * RF-03 (`obras-ativacao-exclusao`) / RF-06: static guards over the PHP
 * token stream (comments and whitespace ignored) of `app/`, `database/` and
 * `routes/`.
 *
 * (a) "obra ativa" has one single definition — `is_active = true`, read only
 *     by `Obra::isActive()` and its SQL twin `Obra::scopeActive()`. Status is
 *     descriptive: nothing compares it with Concluído to decide activity,
 *     and `ObraStatus` has no `isActive()`. The migrations up to and
 *     including the status conversion migration are historical and
 *     excluded; the `is_active` migration is the one place that derives the
 *     flag from the status (its one-time backfill).
 * (b) no route, Livewire method or Action deletes an obra; the single
 *     exemption is `demo:reset` (`ResetDemoData`), which removes only
 *     `is_demo` data.
 */
const OBRA_STATUS_CONVERSION_MIGRATION = '2026_09_23_040313_convert_obras_activity_to_status.php';

const OBRA_ACTIVITY_FLAG_MIGRATION = '2026_10_02_022750_add_is_active_to_obras_and_relax_obra_admin_events_fks.php';

/**
 * The only files allowed to read or write `obras.is_active` directly.
 */
const OBRA_ACTIVITY_COLUMN_ALLOWLIST = [
    'app/Models/Obra.php',
    'app/Actions/Obras/SetObraActiveAction.php',
    'database/factories/ObraFactory.php',
    'database/migrations/'.OBRA_ACTIVITY_FLAG_MIGRATION,
];

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

/**
 * Whether a statement touches the `is_active` column. Two forms are not a
 * read of the column: a `const` list of key names (the audit whitelist) and
 * an audit payload key whose value is `$obra->isActive()` — the definition
 * itself.
 */
function obraActivityReadsIsActive(string $code): bool
{
    if (preg_match('/^(?:(?:public|protected|private|final)\s+)*const\b/', $code)) {
        return false;
    }

    $code = preg_replace('/[\'"]is_active[\'"]\s*=>\s*\$\w+->isActive\(\)/', '', $code);

    return str_contains($code, 'is_active');
}

test('only the allowlisted files read is_active in an obra context (RF-03)', function () {
    $violations = [];

    foreach (obraActivityScannedFiles(['app', 'database']) as $path) {
        $relative = obraActivityRelativePath($path);

        if (in_array($relative, OBRA_ACTIVITY_COLUMN_ALLOWLIST, true)) {
            continue;
        }

        $isObraFile = (bool) preg_match('/Obra(s)?[^\/]*\.php$|\/Obras\/|\/Associacoes\//', $path)
            && ! str_contains($path, 'User');

        foreach (obraActivityStatements($path) as $statement) {
            if (! obraActivityReadsIsActive($statement['code'])) {
                continue;
            }

            if ($isObraFile || obraActivityIsObraContext($statement['code'])) {
                $violations[] = $relative.':'.$statement['line'].' → '.$statement['code'];
            }
        }
    }

    expect($violations)->toBe([]);
});

test('the scanner flags an obra-context is_active read and ignores the isActive() payload (self-check)', function () {
    $probe = tempnam(sys_get_temp_dir(), 'obra-activity').'.php';
    file_put_contents($probe, "<?php\n\$ids = Obra::query()\n    ->where('is_active', true)\n    ->pluck('id');\n\$flag = \$obra->is_active;\n\$payload = ['is_active' => \$obra->isActive()];\n");

    $flagged = array_filter(
        obraActivityStatements($probe),
        fn (array $statement): bool => obraActivityReadsIsActive($statement['code']) && obraActivityIsObraContext($statement['code']),
    );

    unlink($probe);

    $flaggedCode = array_column(array_values($flagged), 'code');

    expect($flaggedCode)->toHaveCount(2);
    expect($flaggedCode[0])->toEndWith("\$ids = Obra::query() ->where('is_active', true) ->pluck('id')");
    expect($flaggedCode[1])->toBe('$flag = $obra->is_active');
});

test('Concluído is referenced only by the ObraStatus enum, the factory and the one-time backfill (RF-03 b)', function () {
    $references = [];

    foreach (obraActivityScannedFiles(['app', 'database']) as $path) {
        foreach (obraActivityStatements($path) as $statement) {
            if (preg_match('/\b(?:ObraStatus|self)::Concluido\b|[\'"]concluido[\'"]|\'concluido\'/', $statement['code'])) {
                $references[obraActivityRelativePath($path)][] = $statement['code'];
            }
        }
    }

    expect(array_keys($references))->toEqualCanonicalizing([
        'app/Enums/ObraStatus.php',
        'database/factories/ObraFactory.php',
        'database/migrations/'.OBRA_ACTIVITY_FLAG_MIGRATION,
    ]);

    // In app/, only the enum case and its label — never a comparison.
    $enumReferences = $references['app/Enums/ObraStatus.php'];

    expect($enumReferences)->toHaveCount(2);
    expect($enumReferences[0])->toBe("case Concluido = 'concluido'");
    expect($enumReferences[1])->toEndWith("self::Concluido => 'Concluído',");

    $comparison = '/(?:!==?|===?|<>)\s*(?:ObraStatus|self)::Concluido|(?:ObraStatus|self)::Concluido(?:->value)?\s*(?:!==?|===?)/';

    foreach ($enumReferences as $code) {
        expect($code)->not->toMatch($comparison);
    }

    expect($references['database/factories/ObraFactory.php'])->toHaveCount(1);
    expect($references['database/migrations/'.OBRA_ACTIVITY_FLAG_MIGRATION])->toBe([
        "DB::statement(\"update obras set is_active = (status <> 'concluido')\")",
    ]);
});

test('ObraStatus has no isActive method (RF-03 a)', function () {
    expect(method_exists(ObraStatus::class, 'isActive'))->toBeFalse();
});

test('Obra::active() returns exactly the is_active rows, whatever the status, and isActive() agrees (RF-03 c)', function () {
    $obras = collect();

    foreach (ObraStatus::cases() as $status) {
        foreach ([true, false] as $isActive) {
            $obras->push(Obra::factory()->create(['status' => $status, 'is_active' => $isActive]));
        }
    }

    $expected = $obras->filter(fn (Obra $obra): bool => $obra->is_active)->pluck('id')->sort()->values()->all();

    expect($expected)->toHaveCount(3);
    expect(Obra::query()->active()->orderBy('id')->pluck('id')->all())->toBe($expected);

    foreach ($obras as $obra) {
        expect($obra->fresh()->isActive())->toBe((bool) DB::table('obras')->where('id', $obra->id)->value('is_active'));
    }
});

test('the obra activity consumers delegate to active()/isActive() and never to the status (RF-03)', function (string $file, string $delegation) {
    $code = implode("\n", array_column(obraActivityStatements(base_path($file)), 'code'));

    expect($code)->toContain($delegation)
        ->not->toContain('ObraStatus')
        ->not->toMatch('/where\([\'"]status[\'"]/')
        ->not->toMatch('/obra\??->status\b/');
})->with([
    ['app/Livewire/Pedidos/NovaSolicitacao.php', '->active()'],
    ['app/Actions/Pedidos/CreatePedidoAction.php', '->active()'],
    ['app/Actions/Obras/GenerateObraInvitationAction.php', '->isActive()'],
    ['app/Models/ObraInvitation.php', '->isActive()'],
    ['app/Models/ObraInvitation.php', '->active()'],
]);

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
