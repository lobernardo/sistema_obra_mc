<?php

test('root route responds successfully', function () {
    $response = $this->get('/');

    $response->assertOk();
});

test('the application boots with a valid app key', function () {
    expect(config('app.key'))->not->toBeEmpty();
});
