<?php

use App\Models\User;
use Illuminate\Support\Facades\Gate;

dataset('role gates', [
    'obra' => ['obra', 'is-obra', true],
    'obra denied is-suprimentos' => ['obra', 'is-suprimentos', false],
    'obra denied is-gestao' => ['obra', 'is-gestao', false],
    'suprimentos' => ['suprimentos', 'is-suprimentos', true],
    'suprimentos denied is-obra' => ['suprimentos', 'is-obra', false],
    'suprimentos denied is-gestao' => ['suprimentos', 'is-gestao', false],
    'gestao' => ['gestao', 'is-gestao', true],
    'gestao denied is-obra' => ['gestao', 'is-obra', false],
    'gestao denied is-suprimentos' => ['gestao', 'is-suprimentos', false],
]);

test('a role gate returns the expected result for each of the 3 papéis', function (string $factoryState, string $gate, bool $expected) {
    $user = User::factory()->{$factoryState}()->create();

    expect(Gate::forUser($user)->allows($gate))->toBe($expected);
})->with('role gates');

dataset('manage-users gate', [
    'obra' => ['obra', false],
    'suprimentos' => ['suprimentos', false],
    'gestao' => ['gestao', true],
]);

test('manage-users is granted only to gestao', function (string $factoryState, bool $expected) {
    $user = User::factory()->{$factoryState}()->create();

    expect(Gate::forUser($user)->allows('manage-users'))->toBe($expected);
})->with('manage-users gate');
