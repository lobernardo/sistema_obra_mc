<?php

use App\Models\User;

/**
 * RNF-08: every mutating request must go through Laravel's native CSRF
 * protection. Laravel's `PreventRequestForgery` middleware short-circuits its
 * own check while `app()->runningUnitTests()` is true (the default in this
 * test environment), so each test below flips the container's `env` binding
 * to `production` first — the only way to exercise the real token
 * verification path instead of the test-mode bypass.
 */
test('a mutating request without a CSRF token is rejected with 419', function () {
    $this->app['env'] = 'production';

    $actor = User::factory()->obra()->create();
    $this->actingAs($actor);

    $this->post(route('logout'))->assertStatus(419);
});

test('a mutating request with a valid CSRF token is not rejected', function () {
    $this->app['env'] = 'production';

    $actor = User::factory()->obra()->create();
    $this->actingAs($actor);

    $token = 'a-valid-testing-token';
    $this->withSession(['_token' => $token]);

    $this->post(route('logout'), ['_token' => $token])
        ->assertRedirect(route('login'));
});

test('the Livewire update endpoint shared by every mutating component form rejects requests without a CSRF token', function () {
    $this->app['env'] = 'production';

    $actor = User::factory()->suprimentos()->create();
    $this->actingAs($actor);

    $this->post(route('default-livewire.update'), [])->assertStatus(419);
});

test('the Livewire update endpoint accepts requests carrying a valid CSRF token', function () {
    $this->app['env'] = 'production';

    $actor = User::factory()->suprimentos()->create();
    $this->actingAs($actor);

    $token = 'a-valid-testing-token';
    $this->withSession(['_token' => $token]);

    $response = $this->post(route('default-livewire.update'), [], ['X-CSRF-TOKEN' => $token]);

    expect($response->status())->not->toBe(419);
});

/*
 * The public authentication forms (recovery request, reset, first-access
 * invite) submit through the same Livewire endpoint as a visitor — RNF-03.
 */
test('a visitor posting to the Livewire update endpoint without a CSRF token is rejected with 419', function () {
    $this->app['env'] = 'production';

    $this->post(route('default-livewire.update'), [])->assertStatus(419);
});

test('a visitor posting to the Livewire update endpoint with a valid CSRF token is not rejected', function () {
    $this->app['env'] = 'production';

    $token = 'a-valid-testing-token';
    $this->withSession(['_token' => $token]);

    $response = $this->post(route('default-livewire.update'), [], ['X-CSRF-TOKEN' => $token]);

    expect($response->status())->not->toBe(419);
});

test('the public authentication pages render the csrf-token meta used by their forms', function (string $url) {
    $this->get($url)
        ->assertOk()
        ->assertSeeHtml('<meta name="csrf-token" content="');
})->with([
    'password.request' => fn () => route('password.request'),
    'password.reset' => fn () => route('password.reset', ['token' => 'x', 'email' => 'x@example.com']),
    'invite.show' => fn () => route('invite.show', ['token' => 'x', 'email' => 'x@example.com']),
]);
