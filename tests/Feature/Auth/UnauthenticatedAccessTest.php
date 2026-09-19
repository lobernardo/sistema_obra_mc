<?php

use App\Models\User;

test('an unauthenticated request to a protected route redirects to login', function () {
    $response = $this->get(route('home'));

    $response->assertRedirect(route('login'));
});

test('an authenticated request to a protected route is not redirected', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('home'));

    $response->assertOk();
});
