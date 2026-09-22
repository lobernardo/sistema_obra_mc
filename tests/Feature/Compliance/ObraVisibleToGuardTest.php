<?php

/**
 * RF-04: PhpToken checks static Pedido:: entry points in
 * Acompanhamento::pedidos() and PedidoDetalhe::mount(), ignoring comments
 * and strings. Each statement must call visibleTo before its next semicolon.
 *
 * Limitation: only static Pedido:: entry points are detected, not aliases,
 * dynamic class names or relation reads. Relation reads are pedido-scoped
 * after the mount check and complemented by behavioural G-01..G-03 coverage.
 * PedidoPolicyTest fixes the independent second barrier: the RF-03 mutation
 * removing the scope fails this guard; neutralizing the policy fails that test.
 */

/**
 * @return list<PhpToken>
 */
function obraVisibilityTokens(string $source): array
{
    return array_values(array_filter(
        PhpToken::tokenize($source),
        fn (PhpToken $token): bool => ! $token->is([T_WHITESPACE, T_COMMENT, T_DOC_COMMENT]),
    ));
}

test('every static Obra pedido query uses visibleTo in the same statement', function () {
    $files = glob(app_path('Livewire/Obra/*.php'));

    expect($files)->not->toBeEmpty();

    foreach ($files as $file) {
        $tokens = obraVisibilityTokens(file_get_contents($file));
        $staticReferences = 0;

        foreach ($tokens as $index => $token) {
            if (! $token->is([T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED])
                || ! in_array(ltrim($token->text, '\\'), ['Pedido', 'App\\Models\\Pedido'], true)
                || ! ($tokens[$index + 1] ?? null)?->is(T_DOUBLE_COLON)) {
                continue;
            }

            $staticReferences++;
            $hasScope = false;

            for ($cursor = $index + 2; isset($tokens[$cursor]) && ! $tokens[$cursor]->is(';'); $cursor++) {
                $candidate = $tokens[$cursor];
                $isMethodCall = $candidate->is(T_STRING)
                    && $tokens[$cursor - 1]->is([T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_NULLSAFE_OBJECT_OPERATOR])
                    && ($tokens[$cursor + 1] ?? null)?->is('(');

                if (! $isMethodCall) {
                    continue;
                }

                expect(strtolower($candidate->text))
                    ->not->toBeIn(['find', 'findorfail', 'firstorfail'], "{$file}:{$candidate->line}");

                if (strtolower($candidate->text) === 'visibleto') {
                    $hasScope = true;
                }
            }

            expect($hasScope)->toBeTrue("{$file}:{$token->line} must call visibleTo in the same statement");
        }

        if (basename($file) === 'NovaSolicitacao.php') {
            expect($staticReferences)->toBe(0, 'NovaSolicitacao must delegate creation to its Action');
        }
    }
});

test('Obra components do not duplicate the obra_id visibility filter', function () {
    $violations = [];

    foreach (glob(app_path('Livewire/Obra/*.php')) as $file) {
        $tokens = obraVisibilityTokens(file_get_contents($file));

        foreach ($tokens as $index => $token) {
            if ($token->is(T_STRING) && strtolower($token->text) === 'wherein'
                && ($tokens[$index + 1] ?? null)?->is('(')
                && ($tokens[$index + 2] ?? null)?->is(T_CONSTANT_ENCAPSED_STRING)) {
                if (trim($tokens[$index + 2]->text, "'\"") === 'obra_id') {
                    $violations[] = "{$file}:{$token->line} must use visibleTo";
                }
            }
        }
    }
    expect($violations)->toBe([]);
});

test('Pedido has no global visibility scope (RNF-03)', function () {
    expect(file_get_contents(app_path('Models/Pedido.php')))
        ->not->toMatch('/addGlobalScope|ScopedBy/');
});
