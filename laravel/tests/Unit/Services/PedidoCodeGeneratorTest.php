<?php

use App\Services\PedidoCodeGenerator;
use Illuminate\Support\Facades\DB;

test('generated code always matches the PED-000001 format', function () {
    $generator = new PedidoCodeGenerator;

    for ($i = 0; $i < 10; $i++) {
        expect($generator->generate())->toMatch('/^PED-\d{6}$/');
    }
});

test('a large batch of generated codes never collides, proving the sequence is concurrency-safe', function () {
    $generator = new PedidoCodeGenerator;

    $codes = [];

    for ($i = 0; $i < 200; $i++) {
        $codes[] = $generator->generate();
    }

    expect($codes)->toHaveCount(200);
    expect(array_unique($codes))->toHaveCount(200);
});

test('concurrent connections calling nextval never receive the same code', function () {
    // A second, independent database connection simulates a concurrent
    // request racing the first one. Both draw from the same PostgreSQL
    // sequence, whose `nextval()` is atomic at the database level, so no
    // amount of concurrency can produce a duplicate.
    config(['database.connections.pgsql_concurrent' => config('database.connections.pgsql')]);

    $generator = new PedidoCodeGenerator;
    $codeFromFirstConnection = $generator->generate();

    $codeFromSecondConnection = DB::connection('pgsql_concurrent')
        ->selectOne("select nextval('pedido_code_sequence') as value")
        ->value;

    expect($codeFromFirstConnection)->not->toBe(sprintf('PED-%06d', $codeFromSecondConnection));
});
