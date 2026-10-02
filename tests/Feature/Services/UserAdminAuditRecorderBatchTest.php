<?php

use App\Enums\UserAdminAction;
use App\Models\User;
use App\Models\UserAdminEvent;
use App\Services\UserAdminAuditRecorder;
use Illuminate\Support\Facades\DB;

/**
 * RNF-03 and CT-03: `UserAdminAuditRecorder::recordMany()` writes N
 * `user_admin_events` rows with a single INSERT and keeps the whitelist.
 */
test('recordMany writes 50 rows with exactly one INSERT and the correct JSON payloads (RNF-03, CT-03)', function () {
    $actor = User::factory()->gestao()->create();
    $targets = User::factory()->obra()->count(50)->create();

    $rows = $targets->values()->map(fn (User $target, int $index): array => [
        'target_id' => $target->id,
        'before' => ['obra_ids' => [7, $index + 100]],
        'after' => ['obra_ids' => [$index + 100]],
    ])->all();

    $inserts = 0;
    DB::listen(function ($query) use (&$inserts): void {
        if (str_starts_with(strtolower(ltrim($query->sql)), 'insert')) {
            $inserts++;
        }
    });

    $written = app(UserAdminAuditRecorder::class)->recordMany($actor, UserAdminAction::ObraAccessChanged, $rows);

    expect($inserts)->toBe(1);
    expect($written)->toBe(50);

    $stored = UserAdminEvent::query()->orderBy('id')->get();

    expect($stored)->toHaveCount(50);

    foreach ($stored->values() as $index => $event) {
        expect($event->actor_id)->toBe($actor->id);
        expect($event->target_id)->toBe($targets[$index]->id);
        expect($event->action)->toBe(UserAdminAction::ObraAccessChanged);
        expect($event->before)->toBe(['obra_ids' => [7, $index + 100]]);
        expect($event->after)->toBe(['obra_ids' => [$index + 100]]);
        expect($event->created_at)->not->toBeNull();
    }
});

test('recordMany with no rows issues no query', function () {
    $actor = User::factory()->gestao()->create();

    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    expect(app(UserAdminAuditRecorder::class)->recordMany($actor, UserAdminAction::ObraAccessChanged, []))->toBe(0);
    expect($queries)->toBe(0);
});

test('a key outside the whitelist throws before any query and writes nothing (CT-03)', function (string $side) {
    $actor = User::factory()->gestao()->create();
    $target = User::factory()->obra()->create();
    $bad = ['obra_ids' => [1], 'password' => 'segredo'];

    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    expect(fn () => app(UserAdminAuditRecorder::class)->recordMany($actor, UserAdminAction::ObraAccessChanged, [
        ['target_id' => $target->id, 'before' => ['obra_ids' => [1]], 'after' => ['obra_ids' => []]],
        [
            'target_id' => $target->id,
            'before' => $side === 'before' ? $bad : null,
            'after' => $side === 'after' ? $bad : null,
        ],
    ]))->toThrow(LogicException::class);

    expect($queries)->toBe(0);
    expect(UserAdminEvent::query()->count())->toBe(0);
})->with(['before', 'after']);
