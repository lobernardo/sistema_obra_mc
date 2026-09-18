<?php

use Illuminate\Support\Facades\DB;

test('the default database connection is pgsql sourced entirely from env vars', function () {
    expect(config('database.default'))->toBe('pgsql');

    $connection = config('database.connections.pgsql');

    expect($connection['driver'])->toBe('pgsql')
        ->and($connection['host'])->toBe(env('DB_HOST'))
        ->and((string) $connection['port'])->toBe((string) env('DB_PORT'))
        ->and($connection['database'])->toBe(env('DB_DATABASE'))
        ->and($connection['username'])->toBe(env('DB_USERNAME'))
        ->and($connection['password'])->toBe(env('DB_PASSWORD'));
});

test('the application connects to postgres and executes a trivial query', function () {
    expect(DB::connection()->getDriverName())->toBe('pgsql');

    $result = DB::selectOne('select 1 as ok');

    expect($result->ok)->toBe(1);
});
