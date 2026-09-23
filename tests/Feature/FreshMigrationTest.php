<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

test('migrate:fresh runs from zero and creates every expected table', function () {
    $exitCode = Artisan::call('migrate:fresh', ['--force' => true]);

    expect($exitCode)->toBe(0);

    $expectedTables = [
        'migrations',
        'cache',
        'jobs',
        'users',
        'roles',
        'statuses',
        'priorities',
        'event_types',
        'obras',
        'obra_profile',
        'pedidos',
        'pedido_events',
        'pedido_attachments',
    ];

    foreach ($expectedTables as $table) {
        expect(Schema::hasTable($table))->toBeTrue("Expected table [{$table}] to exist after migrate:fresh.");
    }
});
