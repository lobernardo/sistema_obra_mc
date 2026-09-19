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
