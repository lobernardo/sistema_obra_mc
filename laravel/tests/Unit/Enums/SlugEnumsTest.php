<?php

use App\Enums\EventTypeSlug;
use App\Enums\PrioritySlug;
use App\Enums\RoleSlug;
use App\Enums\StatusSlug;

test('RoleSlug matches the 3 RIGID role literals exactly', function () {
    expect(array_map(fn (RoleSlug $case) => $case->value, RoleSlug::cases()))
        ->toEqualCanonicalizing(['obra', 'suprimentos', 'gestao']);
});

test('StatusSlug matches the 6 RIGID status literals exactly', function () {
    expect(array_map(fn (StatusSlug $case) => $case->value, StatusSlug::cases()))
        ->toEqualCanonicalizing([
            'solicitado',
            'em_analise',
            'em_compra_preparacao',
            'aguardando_entrega',
            'entregue',
            'cancelado',
        ]);
});

test('StatusSlug::activeNonFinal returns the 4 active non-final statuses', function () {
    expect(array_map(fn (StatusSlug $case) => $case->value, StatusSlug::activeNonFinal()))
        ->toEqualCanonicalizing([
            'solicitado',
            'em_analise',
            'em_compra_preparacao',
            'aguardando_entrega',
        ]);
});

test('StatusSlug::isTerminal is true only for entregue and cancelado', function () {
    foreach (StatusSlug::cases() as $case) {
        $expected = in_array($case, [StatusSlug::Entregue, StatusSlug::Cancelado], true);

        expect($case->isTerminal())->toBe($expected);
    }
});

test('PrioritySlug matches the 4 RIGID priority literals exactly', function () {
    expect(array_map(fn (PrioritySlug $case) => $case->value, PrioritySlug::cases()))
        ->toEqualCanonicalizing(['baixa', 'normal', 'alta', 'urgente']);
});

test('EventTypeSlug matches the 7 RIGID event type literals exactly', function () {
    expect(array_map(fn (EventTypeSlug $case) => $case->value, EventTypeSlug::cases()))
        ->toEqualCanonicalizing([
            'criacao_pedido',
            'mudanca_status',
            'alteracao_responsavel',
            'alteracao_prioridade',
            'alteracao_previsao',
            'cancelamento',
            'entrega',
        ]);
});
