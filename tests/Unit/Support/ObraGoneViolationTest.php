<?php

use App\Exceptions\Obras\ObraNotFoundException;
use App\Models\User;
use App\Support\ObraGoneViolation;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

const OBRA_GONE_CONSTRAINTS = [
    'pedidos_obra_id_foreign',
    'obra_invitations_obra_id_foreign',
    'obra_profile_obra_id_foreign',
    'obra_admin_events_obra_id_foreign',
];

function obraGoneQueryException(string $sqlState, string $driverMessage): QueryException
{
    $pdoException = new PDOException("SQLSTATE[{$sqlState}]: {$driverMessage}");
    $pdoException->errorInfo = [$sqlState, 7, $driverMessage];

    return new QueryException('pgsql', 'insert into pedidos (obra_id) values (?)', [1], $pdoException);
}

test('23503 naming a listed obra FK matches (RNF-01)', function (string $constraint) {
    $exception = obraGoneQueryException(
        '23503',
        "ERROR: insert or update on table violates foreign key constraint \"{$constraint}\"",
    );

    expect(ObraGoneViolation::matches($exception, OBRA_GONE_CONSTRAINTS))->toBeTrue();
})->with(OBRA_GONE_CONSTRAINTS);

test('23503 naming an unrelated FK does not match (RNF-01)', function () {
    $exception = obraGoneQueryException(
        '23503',
        'ERROR: insert or update on table "pedidos" violates foreign key constraint "pedidos_status_id_foreign"',
    );

    expect(ObraGoneViolation::matches($exception, OBRA_GONE_CONSTRAINTS))->toBeFalse();
});

test('23503 on a listed FK does not match when that FK is not among the given names', function () {
    $exception = obraGoneQueryException(
        '23503',
        'ERROR: insert or update on table "pedidos" violates foreign key constraint "pedidos_obra_id_foreign"',
    );

    expect(ObraGoneViolation::matches($exception, ['obra_profile_obra_id_foreign']))->toBeFalse();
});

test('40P01 deadlock matches whatever the constraint list (RNF-01, D-1)', function () {
    $exception = obraGoneQueryException('40P01', 'ERROR: deadlock detected');

    expect(ObraGoneViolation::matches($exception, OBRA_GONE_CONSTRAINTS))->toBeTrue();
    expect(ObraGoneViolation::matches($exception, []))->toBeTrue();
});

test('other SQLSTATEs never match', function (string $sqlState) {
    $exception = obraGoneQueryException($sqlState, 'ERROR: pedidos_obra_id_foreign');

    expect(ObraGoneViolation::matches($exception, OBRA_GONE_CONSTRAINTS))->toBeFalse();
})->with(['23505', '23502', '40001', '42P01']);

test('exception() is a 422 carrying the exact message on the given field', function () {
    $exception = ObraGoneViolation::exception('obra_id');

    expect($exception)->toBeInstanceOf(ValidationException::class);
    expect($exception->status)->toBe(422);
    expect($exception->errors())->toBe(['obra_id' => ['A obra informada não foi encontrada.']]);
});

test('ObraNotFoundException is a RuntimeException with the same message', function () {
    $exception = ObraNotFoundException::make();

    expect($exception)->toBeInstanceOf(RuntimeException::class);
    expect($exception->getMessage())->toBe('A obra informada não foi encontrada.');
    expect(ObraNotFoundException::MESSAGE)->toBe(ObraGoneViolation::MESSAGE);
});

test('a real PostgreSQL FK violation on obra_profile.obra_id matches, and is not mistaken for another FK', function () {
    $user = User::factory()->obra()->create();

    try {
        DB::transaction(fn () => DB::table('obra_profile')->insert([
            'obra_id' => 987654321,
            'user_id' => $user->id,
            'created_at' => now(),
        ]));

        $this->fail('Expected a QueryException.');
    } catch (QueryException $exception) {
        expect(ObraGoneViolation::matches($exception, ['obra_profile_obra_id_foreign']))->toBeTrue();
        expect(ObraGoneViolation::matches($exception, ['pedidos_obra_id_foreign']))->toBeFalse();
    }
});
