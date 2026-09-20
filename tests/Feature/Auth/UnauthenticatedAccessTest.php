<?php

use App\Models\User;

test('an unauthenticated request to a protected route redirects to login', function () {
    $response = $this->get(route('home'));

    $response->assertRedirect(route('login'));
});

test('an authenticated request to a protected route is not redirected to login', function () {
    $user = User::factory()->obra()->create();

    $response = $this->actingAs($user)->get(route('home'));

    $response->assertRedirect();
    expect($response->headers->get('Location'))->not->toBe(route('login'));
});

test('the home route lands each papel on its main screen', function (string $role, string $routeName) {
    $user = User::factory()->{$role}()->create();

    $this->actingAs($user)
        ->get(route('home'))
        ->assertRedirect(route($routeName));
})->with([
    ['obra', 'obra.pedidos.index'],
    ['suprimentos', 'suprimentos.kanban'],
    ['gestao', 'gestao.dashboard'],
]);

test('the home route denies a user without a recognised papel', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('home'))
        ->assertForbidden();
});
