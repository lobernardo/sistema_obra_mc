<?php

use App\Enums\RequestedPeriodPreset;

/**
 * RF-15 / RF-16 / CT-03 / NC-01: the "Solicitado" options, in control order,
 * with their labels and relative windows (days before today, today included).
 */
test('the presets are declared in control order with their URL values', function () {
    expect(array_map(fn (RequestedPeriodPreset $preset) => $preset->value, RequestedPeriodPreset::cases()))
        ->toBe(['hoje', '3d', '7d', 'mes', 'personalizado']);
});

test('each preset carries its PT-BR label', function () {
    expect(array_map(fn (RequestedPeriodPreset $preset) => $preset->label(), RequestedPeriodPreset::cases()))
        ->toBe(['Hoje', 'Últimos 3 dias', 'Últimos 7 dias', 'Último mês', 'Personalizado']);
});

test('the neutral state is labelled Qualquer data', function () {
    expect(RequestedPeriodPreset::NEUTRAL_LABEL)->toBe('Qualquer data');
});

test('daysBack is exact for every preset', function () {
    expect(array_map(fn (RequestedPeriodPreset $preset) => $preset->daysBack(), RequestedPeriodPreset::cases()))
        ->toBe([0, 2, 6, 29, null]);
});

test('only Personalizado is not relative', function () {
    expect(array_map(fn (RequestedPeriodPreset $preset) => $preset->isRelative(), RequestedPeriodPreset::cases()))
        ->toBe([true, true, true, true, false]);
});
