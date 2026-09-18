<?php

use App\Models\User;
use App\Rules\ResponsibleMustBeSuprimentos;
use Illuminate\Support\Facades\Validator;

test('a user with the suprimentos papel passes the rule', function () {
    $suprimentos = User::factory()->suprimentos()->create();

    $validator = Validator::make(
        ['responsible_id' => $suprimentos->id],
        ['responsible_id' => [new ResponsibleMustBeSuprimentos]],
    );

    expect($validator->passes())->toBeTrue();
});

test('a user without the suprimentos papel fails the rule', function (string $role) {
    $user = User::factory()->{$role}()->create();

    $validator = Validator::make(
        ['responsible_id' => $user->id],
        ['responsible_id' => [new ResponsibleMustBeSuprimentos]],
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->first('responsible_id'))->not->toBeEmpty();
})->with(['obra', 'gestao']);

test('a non-existent user id fails the rule', function () {
    $validator = Validator::make(
        ['responsible_id' => 999999],
        ['responsible_id' => [new ResponsibleMustBeSuprimentos]],
    );

    expect($validator->fails())->toBeTrue();
});

test('the responsible-selector query only returns suprimentos users', function () {
    $suprimentos = User::factory()->suprimentos()->create();
    User::factory()->obra()->create();
    User::factory()->gestao()->create();

    $selectable = User::suprimentos()->get();

    expect($selectable)->toHaveCount(1);
    expect($selectable->first()->id)->toBe($suprimentos->id);
});
