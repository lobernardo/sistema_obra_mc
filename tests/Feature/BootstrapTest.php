<?php

test('root route redirects to the role-scoped home', function () {
    $response = $this->get('/');

    $response->assertRedirect('/home');
});

test('the application boots with a valid app key', function () {
    expect(config('app.key'))->not->toBeEmpty();
});
