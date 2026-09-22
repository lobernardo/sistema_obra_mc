<?php

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

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

/**
 * RF-27 / CT-01: `GET /suprimentos/visao-geral` lives inside the
 * `can:is-suprimentos` group, so it inherits `auth` + `active` + the role gate.
 */
test('the Visão Geral route answers 200 for suprimentos and 403 for every other role', function (string $factoryState, int $expectedStatus) {
    $this->actingAs(User::factory()->{$factoryState}()->create());

    $this->get(route('suprimentos.visao-geral'))->assertStatus($expectedStatus);
})->with([
    'suprimentos' => ['suprimentos', 200],
    'obra' => ['obra', 403],
    'gestao' => ['gestao', 403],
]);

test('a guest is redirected from the Visão Geral route to the login screen', function () {
    $this->get(route('suprimentos.visao-geral'))->assertRedirect(route('login'));
});

test('the Visão Geral route carries the auth, active and role middleware', function () {
    $middleware = collect(Route::getRoutes()->getByName('suprimentos.visao-geral')->gatherMiddleware());

    expect($middleware)->toContain('auth')
        ->toContain('active')
        ->toContain('can:is-suprimentos');
});
