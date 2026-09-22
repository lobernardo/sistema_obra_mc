<?php

use Illuminate\Support\Facades\Schema;

function columnNames(string $table): array
{
    return array_column(Schema::getColumns($table), 'name');
}

function foreignKeyFor(string $table, string $column): ?array
{
    foreach (Schema::getForeignKeys($table) as $foreignKey) {
        if (in_array($column, $foreignKey['columns'], true)) {
            return $foreignKey;
        }
    }

    return null;
}

function hasUniqueIndexOn(string $table, array $columns): bool
{
    foreach (Schema::getIndexes($table) as $index) {
        if ($index['unique'] && $index['columns'] === $columns) {
            return true;
        }
    }

    return false;
}

function hasIndexOn(string $table, array $columns): bool
{
    foreach (Schema::getIndexes($table) as $index) {
        if ($index['columns'] === $columns) {
            return true;
        }
    }

    return false;
}

describe('lookup tables', function () {
    test('roles table has the expected columns and unique slug', function () {
        expect(Schema::hasTable('roles'))->toBeTrue();
        expect(columnNames('roles'))->toContain('id', 'name', 'slug', 'description', 'is_active', 'created_at', 'updated_at');
        expect(hasUniqueIndexOn('roles', ['slug']))->toBeTrue();
    });

    test('statuses table has sort_order and unique slug', function () {
        expect(Schema::hasTable('statuses'))->toBeTrue();
        expect(columnNames('statuses'))->toContain('id', 'name', 'slug', 'sort_order', 'is_active', 'created_at', 'updated_at');
        expect(hasUniqueIndexOn('statuses', ['slug']))->toBeTrue();
        expect(hasUniqueIndexOn('statuses', ['sort_order']))->toBeTrue();
    });

    test('priorities table has sort_order and unique slug', function () {
        expect(Schema::hasTable('priorities'))->toBeTrue();
        expect(columnNames('priorities'))->toContain('id', 'name', 'slug', 'sort_order', 'is_active', 'created_at', 'updated_at');
        expect(hasUniqueIndexOn('priorities', ['slug']))->toBeTrue();
        expect(hasUniqueIndexOn('priorities', ['sort_order']))->toBeTrue();
    });

    test('event_types table has the expected columns and unique slug', function () {
        expect(Schema::hasTable('event_types'))->toBeTrue();
        expect(columnNames('event_types'))->toContain('id', 'name', 'slug', 'description', 'is_active', 'created_at', 'updated_at');
        expect(hasUniqueIndexOn('event_types', ['slug']))->toBeTrue();
    });
});

describe('users identity', function () {
    test('users table has role_id foreign key, is_active and is_demo', function () {
        expect(columnNames('users'))->toContain('role_id', 'is_active', 'is_demo');

        $foreignKey = foreignKeyFor('users', 'role_id');

        expect($foreignKey)->not->toBeNull();
        expect($foreignKey['foreign_table'])->toBe('roles');
    });
});

describe('obras and obra_profile', function () {
    test('obras table has the expected columns', function () {
        expect(Schema::hasTable('obras'))->toBeTrue();
        expect(columnNames('obras'))->toContain('id', 'name', 'is_active', 'is_demo', 'created_at', 'updated_at');
    });

    test('obra_profile has a composite primary key and both foreign keys', function () {
        expect(Schema::hasTable('obra_profile'))->toBeTrue();
        expect(columnNames('obra_profile'))->toContain('obra_id', 'user_id', 'created_at');

        $primaryIndex = collect(Schema::getIndexes('obra_profile'))->firstWhere('primary', true);

        expect($primaryIndex)->not->toBeNull();
        expect($primaryIndex['columns'])->toEqualCanonicalizing(['obra_id', 'user_id']);

        $obraForeignKey = foreignKeyFor('obra_profile', 'obra_id');
        $userForeignKey = foreignKeyFor('obra_profile', 'user_id');

        expect($obraForeignKey)->not->toBeNull()->and($obraForeignKey['foreign_table'])->toBe('obras');
        expect($userForeignKey)->not->toBeNull()->and($userForeignKey['foreign_table'])->toBe('users');
    });
});

describe('pedidos and pedido_events', function () {
    test('pedidos table has the complete column set and foreign keys', function () {
        expect(Schema::hasTable('pedidos'))->toBeTrue();

        expect(columnNames('pedidos'))->toContain(
            'id', 'code', 'obra_id', 'requester_id', 'requested_at', 'needed_at',
            'items_description', 'status_id', 'priority_id', 'responsible_id',
            'expected_delivery_at', 'is_demo', 'created_at', 'updated_at',
        );

        expect(hasUniqueIndexOn('pedidos', ['code']))->toBeTrue();

        expect(foreignKeyFor('pedidos', 'obra_id')['foreign_table'])->toBe('obras');
        expect(foreignKeyFor('pedidos', 'requester_id')['foreign_table'])->toBe('users');
        expect(foreignKeyFor('pedidos', 'status_id')['foreign_table'])->toBe('statuses');
        expect(foreignKeyFor('pedidos', 'priority_id')['foreign_table'])->toBe('priorities');
        expect(foreignKeyFor('pedidos', 'responsible_id')['foreign_table'])->toBe('users');
    });

    test('pedidos table has the query indexes required by RNF-07', function () {
        expect(hasIndexOn('pedidos', ['obra_id', 'status_id']))->toBeTrue();
        expect(hasIndexOn('pedidos', ['needed_at']))->toBeTrue();
    });

    test('pedido_events table is append-only with no update-capable column', function () {
        expect(Schema::hasTable('pedido_events'))->toBeTrue();

        $columns = columnNames('pedido_events');

        expect($columns)->toContain(
            'id', 'pedido_id', 'event_type_id', 'previous_value', 'new_value', 'actor_id', 'created_at',
        );
        expect($columns)->not->toContain('updated_at');

        expect(foreignKeyFor('pedido_events', 'pedido_id')['foreign_table'])->toBe('pedidos');
        expect(foreignKeyFor('pedido_events', 'event_type_id')['foreign_table'])->toBe('event_types');
        expect(foreignKeyFor('pedido_events', 'actor_id')['foreign_table'])->toBe('users');
    });

    test('pedido_events has the query index required for the history timeline', function () {
        expect(hasIndexOn('pedido_events', ['pedido_id', 'created_at']))->toBeTrue();
    });
});

describe('user_admin_events', function () {
    test('user_admin_events table is append-only with the CT-02 columns and restrict FKs (RF-23, RF-24)', function () {
        expect(Schema::hasTable('user_admin_events'))->toBeTrue();

        $columns = columnNames('user_admin_events');

        expect($columns)->toContain('id', 'actor_id', 'target_id', 'action', 'before', 'after', 'created_at');
        expect($columns)->not->toContain('updated_at');

        $actorForeignKey = foreignKeyFor('user_admin_events', 'actor_id');
        $targetForeignKey = foreignKeyFor('user_admin_events', 'target_id');

        expect($actorForeignKey)->not->toBeNull()->and($actorForeignKey['foreign_table'])->toBe('users');
        expect($actorForeignKey['on_delete'])->toBe('restrict');
        expect($targetForeignKey)->not->toBeNull()->and($targetForeignKey['foreign_table'])->toBe('users');
        expect($targetForeignKey['on_delete'])->toBe('restrict');
    });

    test('user_admin_events has the two chronological indexes required by RF-25', function () {
        expect(hasIndexOn('user_admin_events', ['target_id', 'created_at']))->toBeTrue();
        expect(hasIndexOn('user_admin_events', ['actor_id', 'created_at']))->toBeTrue();
    });

    test('user_admin_events has no column able to hold a secret (RF-22)', function () {
        $forbidden = '/^(password|password_hash|remember_token|token|secret|api_key|session_id|cookie|authorization)$/i';

        foreach (columnNames('user_admin_events') as $column) {
            expect(preg_match($forbidden, $column))->toBe(0, "Coluna proibida em user_admin_events: {$column}");
        }
    });
});

describe('authentication_events', function () {
    test('authentication_events table is append-only with the CT-03 columns and a nullable restrict FK (RF-27, RF-28)', function () {
        expect(Schema::hasTable('authentication_events'))->toBeTrue();

        $columns = columnNames('authentication_events');

        expect($columns)->toContain('id', 'event', 'user_id', 'email', 'ip', 'user_agent', 'created_at');
        expect($columns)->not->toContain('updated_at');

        $userIdColumn = collect(Schema::getColumns('authentication_events'))->firstWhere('name', 'user_id');
        expect($userIdColumn['nullable'])->toBeTrue();

        $userForeignKey = foreignKeyFor('authentication_events', 'user_id');

        expect($userForeignKey)->not->toBeNull()->and($userForeignKey['foreign_table'])->toBe('users');
        expect($userForeignKey['on_delete'])->toBe('restrict');
    });

    test('authentication_events has the two chronological indexes required by CT-03', function () {
        expect(hasIndexOn('authentication_events', ['user_id', 'created_at']))->toBeTrue();
        expect(hasIndexOn('authentication_events', ['email', 'created_at']))->toBeTrue();
    });

    test('authentication_events has no column able to hold a secret (RF-22)', function () {
        $forbidden = '/^(password|password_hash|remember_token|token|secret|api_key|session_id|cookie|authorization)$/i';

        foreach (columnNames('authentication_events') as $column) {
            expect(preg_match($forbidden, $column))->toBe(0, "Coluna proibida em authentication_events: {$column}");
        }
    });
});
