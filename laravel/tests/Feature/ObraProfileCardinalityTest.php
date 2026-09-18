<?php

use App\Models\Obra;
use App\Models\User;
use Illuminate\Database\QueryException;

test('a user can be associated with multiple obras and vice versa', function () {
    $user = User::factory()->obra()->create();
    $obraOne = Obra::factory()->create();
    $obraTwo = Obra::factory()->create();

    $user->obras()->attach([$obraOne->id, $obraTwo->id]);

    expect($user->obras()->pluck('obras.id')->all())->toEqualCanonicalizing([$obraOne->id, $obraTwo->id]);

    $otherUser = User::factory()->obra()->create();
    $obraOne->users()->attach($otherUser->id);

    expect($obraOne->users()->pluck('users.id')->all())->toEqualCanonicalizing([$user->id, $otherUser->id]);
});

test('obra_profile enforces a composite primary key preventing duplicate pairs', function () {
    $user = User::factory()->obra()->create();
    $obra = Obra::factory()->create();

    $user->obras()->attach($obra->id);

    expect(fn () => $user->obras()->attach($obra->id))
        ->toThrow(QueryException::class);
});
