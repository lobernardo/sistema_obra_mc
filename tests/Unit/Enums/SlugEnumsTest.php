<?php

use App\Enums\EventTypeSlug;
use App\Enums\ObraStatus;
use App\Enums\PrioritySlug;
use App\Enums\RoleSlug;
use App\Enums\StatusSlug;

test('RoleSlug matches the 3 RIGID role literals exactly', function () {
    expect(array_map(fn (RoleSlug $case) => $case->value, RoleSlug::cases()))
        ->toEqualCanonicalizing(['obra', 'suprimentos', 'gestao']);
});

test('StatusSlug matches the 7 RIGID status literals exactly', function () {
    expect(array_map(fn (StatusSlug $case) => $case->value, StatusSlug::cases()))
        ->toEqualCanonicalizing([
            'solicitado',
            'em_analise',
            'em_compra_preparacao',
            'aguardando_entrega',
            'entregue',
            'cancelado',
            'finalizado',
        ]);
});

test('StatusSlug::Finalizado is the last case so fixtures derive sort_order 7', function () {
    expect(StatusSlug::cases()[6])->toBe(StatusSlug::Finalizado);
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

test('StatusSlug::isTerminal is true only for entregue, cancelado and finalizado', function () {
    foreach (StatusSlug::cases() as $case) {
        $expected = in_array($case->value, ['entregue', 'cancelado', 'finalizado'], true);

        expect($case->isTerminal())->toBe($expected);
    }
});

test('StatusSlug::terminal and terminalValues list exactly entregue, cancelado and finalizado', function () {
    expect(StatusSlug::terminal())->toBe([StatusSlug::Entregue, StatusSlug::Cancelado, StatusSlug::Finalizado]);
    expect(StatusSlug::terminalValues())->toBe(['entregue', 'cancelado', 'finalizado']);
});

test('StatusSlug::finalizableFrom lists the 4 active statuses plus entregue', function () {
    expect(array_map(fn (StatusSlug $case) => $case->value, StatusSlug::finalizableFrom()))
        ->toBe([
            'solicitado',
            'em_analise',
            'em_compra_preparacao',
            'aguardando_entrega',
            'entregue',
        ]);
});

test('PrioritySlug matches the 4 RIGID priority literals exactly', function () {
    expect(array_map(fn (PrioritySlug $case) => $case->value, PrioritySlug::cases()))
        ->toEqualCanonicalizing(['baixa', 'normal', 'alta', 'urgente']);
});

test('EventTypeSlug matches the 10 RIGID event type literals exactly', function () {
    expect(array_map(fn (EventTypeSlug $case) => $case->value, EventTypeSlug::cases()))
        ->toEqualCanonicalizing([
            'criacao_pedido',
            'mudanca_status',
            'alteracao_responsavel',
            'alteracao_prioridade',
            'alteracao_previsao',
            'cancelamento',
            'entrega',
            'observacao',
            'romaneio_anexado',
            'finalizacao',
        ]);
});

test('ObraStatus matches the 3 RIGID obra status literals exactly with TitleCase cases', function () {
    expect(array_map(fn (ObraStatus $case) => $case->value, ObraStatus::cases()))
        ->toEqualCanonicalizing(['a_iniciar', 'em_andamento', 'concluido']);

    expect(array_map(fn (ObraStatus $case) => $case->name, ObraStatus::cases()))
        ->toEqualCanonicalizing(['AIniciar', 'EmAndamento', 'Concluido']);
});
