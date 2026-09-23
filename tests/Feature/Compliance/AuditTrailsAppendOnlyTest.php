<?php

use Illuminate\Support\Facades\Route;

/**
 * RF-23, RF-27, D-11 — mechanical guard over the two append-only trails
 * (`UserAdminEvent`, `AuthenticationEvent`): no Eloquent statement in
 * `app/` updates or deletes them, the single exemption (`demo:reset`)
 * touches the tables only through `DB::table(...)`, and no registered
 * route exposes either trail.
 *
 * PhpToken-based like `ObraVisibleToGuardTest`: comments and strings are
 * ignored; a statement is the token run from a static `Model::` reference
 * to its next `;`. Limitation: only static entry points are detected (no
 * aliases, dynamic class names or relation writes) — the model-level
 * `updating`/`deleting` guards remain the runtime barrier.
 */
const AUDIT_MODELS = ['UserAdminEvent', 'AuthenticationEvent', 'App\\Models\\UserAdminEvent', 'App\\Models\\AuthenticationEvent', 'ObraAdminEvent', 'AccountRegistrationEvent', 'App\\Models\\ObraAdminEvent', 'App\\Models\\AccountRegistrationEvent', 'PedidoAttachment', 'App\\Models\\PedidoAttachment'];

const AUDIT_FORBIDDEN_CALLS = ['update', 'delete', 'forcedelete', 'destroy', 'truncate', 'updateorcreate', 'upsert', 'increment', 'decrement'];

/**
 * @return list<PhpToken>
 */
function auditTrailTokens(string $source): array
{
    return array_values(array_filter(
        PhpToken::tokenize($source),
        fn (PhpToken $token): bool => ! $token->is([T_WHITESPACE, T_COMMENT, T_DOC_COMMENT]),
    ));
}

/**
 * @return list<string>
 */
function appPhpFiles(): array
{
    $files = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path(), FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }

    sort($files);

    return $files;
}

test('no statement starting from an audit model static reference updates or deletes it (RF-23, RF-27)', function () {
    $files = appPhpFiles();
    $staticReferences = 0;
    $violations = [];

    expect($files)->not->toBeEmpty();

    foreach ($files as $file) {
        $tokens = auditTrailTokens(file_get_contents($file));

        foreach ($tokens as $index => $token) {
            if (! $token->is([T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED])
                || ! in_array(ltrim($token->text, '\\'), AUDIT_MODELS, true)
                || ! ($tokens[$index + 1] ?? null)?->is(T_DOUBLE_COLON)) {
                continue;
            }

            $staticReferences++;

            for ($cursor = $index + 2; isset($tokens[$cursor]) && ! $tokens[$cursor]->is(';'); $cursor++) {
                $candidate = $tokens[$cursor];
                $isMethodCall = $candidate->is(T_STRING)
                    && $tokens[$cursor - 1]->is([T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_NULLSAFE_OBJECT_OPERATOR])
                    && ($tokens[$cursor + 1] ?? null)?->is('(');

                if ($isMethodCall && in_array(strtolower($candidate->text), AUDIT_FORBIDDEN_CALLS, true)) {
                    $violations[] = "{$file}:{$candidate->line} {$candidate->text}()";
                }
            }
        }
    }

    expect($staticReferences)->toBeGreaterThan(0, 'The recorders must reference the audit models statically.');
    expect($violations)->toBe([]);
});

test('ResetDemoData reaches the two trails only through DB::table and never through the models (D-11)', function () {
    $source = file_get_contents(app_path('Console/Commands/ResetDemoData.php'));
    $tokens = auditTrailTokens($source);

    expect(substr_count($source, "DB::table('user_admin_events')"))->toBe(1);
    expect(substr_count($source, "DB::table('authentication_events')"))->toBe(1);

    $modelReferences = array_filter(
        $tokens,
        fn (PhpToken $token): bool => $token->is([T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED])
            && in_array(ltrim($token->text, '\\'), AUDIT_MODELS, true),
    );

    expect($modelReferences)->toBe([]);

    $userDeletePosition = strpos($source, "User::query()->where('is_demo', true)->delete()");
    expect($userDeletePosition)->not->toBeFalse();
    expect(strpos($source, "DB::table('user_admin_events')"))->toBeLessThan($userDeletePosition);
    expect(strpos($source, "DB::table('authentication_events')"))->toBeLessThan($userDeletePosition);

    foreach (['obra_invitations', 'obra_admin_events', 'account_registration_events'] as $table) {
        expect(substr_count($source, "DB::table('{$table}')"))->toBe(1);
        expect(strpos($source, "DB::table('{$table}')"))->toBeLessThan($userDeletePosition);
    }
});

test('no other file in app/ touches the audit tables through the query builder (D-11)', function () {
    $offenders = [];

    foreach (appPhpFiles() as $file) {
        if (str_ends_with($file, 'Console/Commands/ResetDemoData.php')) {
            continue;
        }

        $source = file_get_contents($file);

        if (preg_match("/DB::table\\(\\s*['\"](user_admin_events|authentication_events|obra_admin_events|account_registration_events|pedido_attachments)['\"]/", $source) === 1) {
            $offenders[] = $file;
        }
    }

    expect($offenders)->toBe([]);
});

test('no registered route exposes an audit trail (RF-23, RF-27)', function () {
    $offenders = [];

    foreach (Route::getRoutes() as $route) {
        $haystack = strtolower($route->uri().' '.(string) $route->getName());

        if (preg_match('/audit|events|trilha/', $haystack) === 1) {
            $offenders[] = $route->uri().' ('.$route->getName().')';
        }
    }

    expect($offenders)->toBe([]);
});

test('ResetDemoData reads pedido_attachments only through DB::table, before the demo pedidos go, and never writes it (RF-19, RF-43)', function () {
    $source = implode('', array_map(
        fn (PhpToken $token): string => $token->text,
        auditTrailTokens(file_get_contents(app_path('Console/Commands/ResetDemoData.php'))),
    ));

    expect(substr_count($source, "DB::table('pedido_attachments')"))->toBe(1);
    expect(preg_match("/DB::table\\('pedido_attachments'\\)[^;]*->(update|delete|truncate|insert|upsert)\\(/", $source))->toBe(0);

    $pedidoDeletePosition = strpos($source, "Pedido::query()->where('is_demo',true)->delete()");
    expect($pedidoDeletePosition)->not->toBeFalse();
    expect(strpos($source, "DB::table('pedido_attachments')"))->toBeLessThan($pedidoDeletePosition);

    $commitPosition = strrpos($source, '});');
    expect(strpos($source, 'deleteQuietly('))->toBeGreaterThan($commitPosition);
});

test('no registered route exposes an update or delete of attachments (RF-19)', function () {
    $offenders = [];

    foreach (Route::getRoutes() as $route) {
        if (! str_contains($route->uri(), 'anexos')) {
            continue;
        }

        $writeMethods = array_diff($route->methods(), ['GET', 'HEAD']);

        if ($writeMethods !== []) {
            $offenders[] = implode('|', $route->methods()).' '.$route->uri();
        }
    }

    expect($offenders)->toBe([]);
});
