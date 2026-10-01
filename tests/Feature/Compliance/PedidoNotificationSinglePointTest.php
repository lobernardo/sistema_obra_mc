<?php

/**
 * T10 — RF-09: the history of a pedido becomes notifications through one
 * point only. Every Action in `app/Actions/Pedidos/` that writes a
 * `pedido_event` hands it to `PedidoNotificationRecorder::record()`, and no
 * other class in `app/` inserts into `internal_notifications`.
 *
 * A static scan, like `EmailNormalizationGuardTest`: `PhpToken` strips
 * comments and docblocks so a mention inside documentation never counts.
 * The scan covers only `app/` — the `DemoSeeder` writes events directly and
 * deliberately does not notify.
 */

/**
 * Every PHP file under the given directory, as an absolute path.
 *
 * @return list<string>
 */
function singlePointSources(string $directory): array
{
    $files = [];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = str_replace('\\', '/', $file->getPathname());
        }
    }

    sort($files);

    return $files;
}

/**
 * The source without whitespace, comments or docblocks.
 */
function singlePointCode(string $source): string
{
    return implode('', array_map(
        fn (PhpToken $token): string => $token->text,
        array_filter(
            PhpToken::tokenize($source),
            fn (PhpToken $token): bool => ! $token->is([T_WHITESPACE, T_COMMENT, T_DOC_COMMENT]),
        ),
    ));
}

/**
 * How many `pedido_events` writes the code has, and how many of them are
 * left without a `notificationRecorder->record(` call.
 *
 * @return array{writes: int, unrecorded: int}
 */
function singlePointEventWrites(string $source): array
{
    $code = singlePointCode($source);
    $writes = substr_count($code, 'events()->create(') + substr_count($code, 'PedidoEvent::query()->create(');
    $records = substr_count($code, '$this->notificationRecorder->record(');

    return ['writes' => $writes, 'unrecorded' => max(0, $writes - $records)];
}

/**
 * Whether the code writes rows of `internal_notifications`.
 */
function singlePointInsertsNotifications(string $source): bool
{
    $code = singlePointCode($source);

    return preg_match('/InternalNotification::(?:query\(\)->)?(?:insert|insertOrIgnore|insertGetId|upsert|create|forceCreate|firstOrCreate|updateOrCreate|createMany)\(/', $code) === 1
        || preg_match('/newInternalNotification\b/', $code) === 1
        || preg_match('/table\([\'"]internal_notifications[\'"]\)/', $code) === 1
        || preg_match('/internalNotifications\(\)->(?:create|createMany|createQuietly|save|saveMany|insert|forceCreate)\(/', $code) === 1
        || preg_match('/(?:insert|into)\s*[\'"`]?internal_notifications/i', $code) === 1;
}

/**
 * @return array<string, string>
 */
function singlePointPedidoActions(): array
{
    $actions = [];

    foreach (singlePointSources(app_path('Actions/Pedidos')) as $file) {
        $actions[str_replace(base_path().'/', '', $file)] = (string) file_get_contents($file);
    }

    return $actions;
}

test('every pedido Action that writes a pedido_event calls notificationRecorder->record() for it (RF-09)', function () {
    $writers = [];
    $violations = [];

    foreach (singlePointPedidoActions() as $file => $source) {
        ['writes' => $writes, 'unrecorded' => $unrecorded] = singlePointEventWrites($source);

        if ($writes > 0) {
            $writers[] = $file;
        }

        if ($unrecorded > 0) {
            $violations[] = "{$file}: {$unrecorded} escrita(s) de pedido_events sem notificationRecorder->record(";
        }
    }

    expect($writers)->toHaveCount(10)
        ->and($violations)->toBe([]);
});

test('the scan fails when record( is removed from any pedido Action (RF-09)', function () {
    $checked = 0;

    foreach (singlePointPedidoActions() as $file => $source) {
        if (singlePointEventWrites($source)['writes'] === 0) {
            continue;
        }

        $withoutRecord = str_replace('$this->notificationRecorder->record($event);', '', $source);

        expect($withoutRecord)->not->toBe($source, "{$file} has no `\$this->notificationRecorder->record(\$event);` to remove")
            ->and(singlePointEventWrites($withoutRecord)['unrecorded'])->toBeGreaterThan(0, "{$file} without record( must be flagged");

        $checked++;
    }

    expect($checked)->toBe(10);
});

test('a record( mentioned only in a comment does not satisfy the scan (RF-09)', function () {
    $source = <<<'PHP'
        <?php
        $event = $pedido->events()->create([]);
        // $this->notificationRecorder->record($event);
        /** $this->notificationRecorder->record($event); */
        PHP;

    expect(singlePointEventWrites($source))->toBe(['writes' => 1, 'unrecorded' => 1]);
});

test('only PedidoNotificationRecorder inserts into internal_notifications (RF-09)', function () {
    $inserters = [];

    foreach (singlePointSources(app_path()) as $file) {
        if (singlePointInsertsNotifications((string) file_get_contents($file))) {
            $inserters[] = str_replace(base_path().'/', '', $file);
        }
    }

    expect($inserters)->toBe(['app/Services/PedidoNotificationRecorder.php']);
});

test('the insert detector recognises every way of writing notification rows (RF-09)', function (string $code) {
    expect(singlePointInsertsNotifications("<?php\n".$code))->toBeTrue();
})->with([
    'batch insert' => ['InternalNotification::query()->insert([]);'],
    'static create' => ['InternalNotification::create([]);'],
    'new model' => ['(new InternalNotification([]))->save();'],
    'query builder' => ["DB::table('internal_notifications')->insert([]);"],
    'relation' => ['$user->internalNotifications()->create([]);'],
    'raw SQL' => ["DB::insert('insert into internal_notifications (id) values (1)');"],
]);
