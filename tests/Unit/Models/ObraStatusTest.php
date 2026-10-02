<?php

use App\Enums\ObraStatus;

test('ObraStatus no longer defines obra activity: it has no isActive method (RF-03 a)', function () {
    expect(method_exists(ObraStatus::class, 'isActive'))->toBeFalse();
});

test('each obra status carries its exact PT-BR label (CT-01)', function (ObraStatus $status, string $label) {
    expect($status->label())->toBe($label);
})->with([
    'a_iniciar' => [ObraStatus::AIniciar, 'A iniciar'],
    'em_andamento' => [ObraStatus::EmAndamento, 'Em andamento'],
    'concluido' => [ObraStatus::Concluido, 'Concluído'],
]);
