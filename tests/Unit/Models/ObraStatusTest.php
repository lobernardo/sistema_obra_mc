<?php

use App\Enums\ObraStatus;

test('only A iniciar and Em andamento count as an active obra (RF-03)', function (ObraStatus $status, bool $expected) {
    expect($status->isActive())->toBe($expected);
})->with([
    'a_iniciar' => [ObraStatus::AIniciar, true],
    'em_andamento' => [ObraStatus::EmAndamento, true],
    'concluido' => [ObraStatus::Concluido, false],
]);

test('each obra status carries its exact PT-BR label (CT-01)', function (ObraStatus $status, string $label) {
    expect($status->label())->toBe($label);
})->with([
    'a_iniciar' => [ObraStatus::AIniciar, 'A iniciar'],
    'em_andamento' => [ObraStatus::EmAndamento, 'Em andamento'],
    'concluido' => [ObraStatus::Concluido, 'Concluído'],
]);
