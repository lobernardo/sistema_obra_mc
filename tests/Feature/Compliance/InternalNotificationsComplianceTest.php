<?php

use App\Models\InternalNotification;

/**
 * notificacoes-internas T20 — RNF-05, RF-21, RF-22, UI-10: static guards
 * over the feature. Each scanner is a pure function over source text and is
 * exercised against a violating example (self-check), so a guard that stops
 * detecting anything fails on its own.
 *
 * 1. No class in `app/Notifications/` implements `ShouldQueue` and
 *    `routes/console.php`/`bootstrap/app.php` declare no schedule: there is
 *    no worker nor scheduler in production.
 * 2. `composer.json` and `package.json` carry exactly the pinned dependency
 *    lists: the feature adds no runtime or dev dependency.
 * 3. Every static `InternalNotification::query()` in
 *    `app/Livewire/Notificacoes/` and `app/Actions/Notificacoes/` opens with
 *    `->forRecipient(` (RF-21, RNF-07). The single exception is
 *    `MarkAllInternalNotificationsReadAction`, which opens with an explicit
 *    `recipient_id` (RF-19).
 * 4. Every `->update([...])` in a file of `app/` that touches
 *    `InternalNotification` writes only `read_at`, `email_status` and
 *    `email_status_at` (RF-22), and no file but `ResetDemoData` reaches
 *    `internal_notifications` through the query builder.
 * 5. The new views interpolate no class, use no `x-show` and no `<details`
 *    (UI-10).
 *
 * PhpToken-based like `AuditTrailsAppendOnlyTest`: comments and strings are
 * ignored. Limitation: only literal, static call shapes are detected — the
 * model `updating`/`deleting` guards remain the runtime barrier.
 */
const NOTIFICATIONS_COMPLIANCE_VIEWS = [
    'resources/views/livewire/notificacoes/index.blade.php',
    'resources/views/livewire/notificacoes/bell.blade.php',
];

const NOTIFICATIONS_COMPLIANCE_COMPOSER_REQUIRE = [
    'php' => '^8.4',
    'laravel/framework' => '^13.17',
    'laravel/tinker' => '^3.0',
    'livewire/livewire' => '^4.4',
    'resend/resend-php' => '^1.15',
];

const NOTIFICATIONS_COMPLIANCE_COMPOSER_REQUIRE_DEV = [
    'fakerphp/faker' => '^1.23',
    'laravel/boost' => '^2.9',
    'laravel/pail' => '^1.2.5',
    'laravel/pao' => '^1.0.6',
    'laravel/pint' => '^1.27',
    'mockery/mockery' => '^1.6',
    'nunomaduro/collision' => '^8.6',
    'pestphp/pest' => '^4.7',
    'pestphp/pest-plugin-browser' => '^4.3',
    'pestphp/pest-plugin-laravel' => '^4.1',
    'phpunit/phpunit' => '^12.5.12',
];

const NOTIFICATIONS_COMPLIANCE_NPM_DEV = [
    '@tailwindcss/vite' => '^4.0.0',
    'concurrently' => '^10.0.3',
    'laravel-vite-plugin' => '^3.1',
    'playwright' => '^1.59.1',
    'tailwindcss' => '^4.0.0',
    'vite' => '^8.0.0',
];

/**
 * @return list<PhpToken>
 */
function notificationsComplianceTokens(string $source): array
{
    return array_values(array_filter(
        PhpToken::tokenize($source),
        fn (PhpToken $token): bool => ! $token->is([T_WHITESPACE, T_COMMENT, T_DOC_COMMENT]),
    ));
}

/**
 * @return list<string> lines naming `ShouldQueue`
 */
function notificationsQueuedViolations(string $source): array
{
    $violations = [];

    foreach (notificationsComplianceTokens($source) as $token) {
        if ($token->is([T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED])
            && str_ends_with($token->text, 'ShouldQueue')) {
            $violations[] = "{$token->line}: {$token->text}";
        }
    }

    return $violations;
}

/**
 * @return list<string> lines declaring a schedule
 */
function notificationsScheduleViolations(string $source): array
{
    $violations = [];

    foreach (notificationsComplianceTokens($source) as $token) {
        if ($token->is([T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED])
            && preg_match('/(^|\\\\)(Schedule|withSchedule)$/', $token->text) === 1) {
            $violations[] = "{$token->line}: {$token->text}";
        }
    }

    return $violations;
}

/**
 * Every static `InternalNotification::query()` whose first chained call is
 * not `forRecipient` — or, when `$recipientIdAllowed`, not
 * `where('recipient_id', …)`.
 *
 * @return list<string>
 */
function notificationsUnscopedQueries(string $source, bool $recipientIdAllowed = false): array
{
    $tokens = notificationsComplianceTokens($source);
    $violations = [];

    foreach ($tokens as $index => $token) {
        if (! $token->is([T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED])
            || ! in_array(ltrim($token->text, '\\'), ['InternalNotification', 'App\\Models\\InternalNotification'], true)
            || ! ($tokens[$index + 1] ?? null)?->is(T_DOUBLE_COLON)) {
            continue;
        }

        if (strtolower($tokens[$index + 2]->text ?? '') !== 'query') {
            continue;
        }

        $method = $tokens[$index + 6] ?? null;
        $isScoped = ($tokens[$index + 5] ?? null)?->is(T_OBJECT_OPERATOR) && $method?->text === 'forRecipient';
        $isRecipientId = $recipientIdAllowed
            && $method?->text === 'where'
            && ($tokens[$index + 8] ?? null)?->is(T_CONSTANT_ENCAPSED_STRING)
            && trim($tokens[$index + 8]->text, '\'"') === 'recipient_id';

        if (! $isScoped && ! $isRecipientId) {
            $violations[] = "{$token->line}: InternalNotification::query() not opened by forRecipient";
        }
    }

    return $violations;
}

/**
 * The top-level string keys of every `->update([...])` array literal.
 *
 * @return list<string> forbidden keys, as "line: key"
 */
function notificationsForbiddenUpdateColumns(string $source): array
{
    $tokens = notificationsComplianceTokens($source);
    $violations = [];

    foreach ($tokens as $index => $token) {
        if (! $token->is(T_STRING)
            || strtolower($token->text) !== 'update'
            || ! ($tokens[$index - 1] ?? null)?->is([T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON])
            || ! ($tokens[$index + 1] ?? null)?->is('(')) {
            continue;
        }

        if (! ($tokens[$index + 2] ?? null)?->is('[')) {
            $violations[] = "{$token->line}: update() without an array literal";

            continue;
        }

        $depth = 0;

        for ($cursor = $index + 2; isset($tokens[$cursor]); $cursor++) {
            $candidate = $tokens[$cursor];

            if ($candidate->is(['[', '(', '{'])) {
                $depth++;
            } elseif ($candidate->is([']', ')', '}'])) {
                $depth--;

                if ($depth === 0) {
                    break;
                }
            } elseif ($depth === 1
                && $candidate->is(T_CONSTANT_ENCAPSED_STRING)
                && ($tokens[$cursor + 1] ?? null)?->is(T_DOUBLE_ARROW)
                && ! in_array(trim($candidate->text, '\'"'), InternalNotification::MUTABLE_COLUMNS, true)) {
                $violations[] = "{$candidate->line}: ".trim($candidate->text, '\'"');
            }
        }
    }

    return $violations;
}

/**
 * @return list<string> lines with an interpolated class, `x-show` or `<details`
 */
function notificationsViewViolations(string $source): array
{
    $source = (string) preg_replace('/\{\{--.*?--\}\}/s', '', $source);
    $violations = [];

    if (preg_match_all('/(?<![\w:-])class\s*=\s*"[^"]*(\{\{|\{!!|@php|\$)[^"]*"/', $source, $matches) > 0) {
        foreach ($matches[0] as $match) {
            $violations[] = 'interpolated class: '.$match;
        }
    }

    if (preg_match_all('/(?:x-bind:class|:class)\s*=\s*"[^"]*`[^"]*\$\{/', $source, $matches) > 0) {
        foreach ($matches[0] as $match) {
            $violations[] = 'interpolated class: '.$match;
        }
    }

    if (preg_match('/\bx-show\b/', $source) === 1) {
        $violations[] = 'x-show';
    }

    if (preg_match('/<details\b/i', $source) === 1) {
        $violations[] = '<details';
    }

    return $violations;
}

/**
 * @return list<string>
 */
function notificationsAppFilesTouchingNotifications(): array
{
    $files = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path(), FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->isFile() && $file->getExtension() === 'php'
            && preg_match('/\bInternalNotification\b|internal_notifications/', file_get_contents($file->getPathname())) === 1) {
            $files[] = $file->getPathname();
        }
    }

    sort($files);

    return $files;
}

test('no notification implements ShouldQueue and no schedule is declared (no worker nor scheduler)', function () {
    $notifications = glob(app_path('Notifications/*.php'));

    expect($notifications)->toContain(app_path('Notifications/PedidoEventNotification.php'));

    $offenders = [];

    foreach ($notifications as $file) {
        foreach (notificationsQueuedViolations(file_get_contents($file)) as $violation) {
            $offenders[] = basename($file).":{$violation}";
        }
    }

    foreach ([base_path('routes/console.php'), base_path('bootstrap/app.php')] as $file) {
        foreach (notificationsScheduleViolations(file_get_contents($file)) as $violation) {
            $offenders[] = basename($file).":{$violation}";
        }
    }

    expect($offenders)->toBe([]);
});

test('the queue and schedule scanners flag a violating example (self-check)', function () {
    expect(notificationsQueuedViolations('<?php use Illuminate\Contracts\Queue\ShouldQueue; class A extends Notification implements ShouldQueue {}'))->toHaveCount(2);
    expect(notificationsQueuedViolations('<?php // implements ShouldQueue'."\n".'class A extends Notification {}'))->toBe([]);
    expect(notificationsScheduleViolations("<?php use Illuminate\\Support\\Facades\\Schedule; Schedule::command('x')->daily();"))->not->toBeEmpty();
    expect(notificationsScheduleViolations('<?php return Application::configure()->withSchedule(fn ($s) => null);'))->toHaveCount(1);
    expect(notificationsScheduleViolations("<?php Artisan::command('inspire', fn () => null);"))->toBe([]);
});

test('composer.json and package.json carry exactly the pinned dependencies (RNF-05)', function () {
    $composer = json_decode(file_get_contents(base_path('composer.json')), true);
    $package = json_decode(file_get_contents(base_path('package.json')), true);

    expect($composer['require'])->toBe(NOTIFICATIONS_COMPLIANCE_COMPOSER_REQUIRE)
        ->and($composer['require-dev'])->toBe(NOTIFICATIONS_COMPLIANCE_COMPOSER_REQUIRE_DEV)
        ->and($package['dependencies'] ?? [])->toBe([])
        ->and($package['devDependencies'])->toBe(NOTIFICATIONS_COMPLIANCE_NPM_DEV);
});

test('every static InternalNotification query of the page, bell and actions opens with forRecipient (RF-21, RNF-07)', function () {
    $files = array_merge(glob(app_path('Livewire/Notificacoes/*.php')), glob(app_path('Actions/Notificacoes/*.php')));

    expect($files)->toContain(
        app_path('Livewire/Notificacoes/Index.php'),
        app_path('Livewire/Notificacoes/Bell.php'),
        app_path('Actions/Notificacoes/MarkInternalNotificationReadAction.php'),
        app_path('Actions/Notificacoes/MarkAllInternalNotificationsReadAction.php'),
    );

    $queries = 0;
    $offenders = [];

    foreach ($files as $file) {
        $source = file_get_contents($file);
        $queries += substr_count($source, 'InternalNotification::query()');

        foreach (notificationsUnscopedQueries($source, basename($file) === 'MarkAllInternalNotificationsReadAction.php') as $violation) {
            $offenders[] = basename($file).":{$violation}";
        }
    }

    expect($queries)->toBeGreaterThanOrEqual(5)
        ->and($offenders)->toBe([]);
});

test('the forRecipient scanner flags an unscoped query (self-check)', function () {
    expect(notificationsUnscopedQueries('<?php InternalNotification::query()->whereKey($id)->update(["read_at" => now()]);'))->toHaveCount(1);
    expect(notificationsUnscopedQueries('<?php InternalNotification::query()->where("recipient_id", $user->id)->count();'))->toHaveCount(1);
    expect(notificationsUnscopedQueries('<?php InternalNotification::query()->where("recipient_id", $user->id)->count();', recipientIdAllowed: true))->toBe([]);
    expect(notificationsUnscopedQueries('<?php InternalNotification::query()->where("pedido_id", $id)->count();', recipientIdAllowed: true))->toHaveCount(1);
    expect(notificationsUnscopedQueries('<?php InternalNotification::query()->forRecipient($user)->count();'))->toBe([]);
});

test('updates of internal_notifications in app/ touch only read_at and the e-mail state (RF-22)', function () {
    $files = notificationsAppFilesTouchingNotifications();

    expect($files)->toContain(
        app_path('Services/InternalNotificationMailer.php'),
        app_path('Actions/Notificacoes/MarkInternalNotificationReadAction.php'),
    );

    $offenders = [];

    foreach ($files as $file) {
        $source = file_get_contents($file);

        foreach (notificationsForbiddenUpdateColumns($source) as $violation) {
            $offenders[] = basename($file).":{$violation}";
        }

        if (! str_ends_with($file, 'Console/Commands/ResetDemoData.php')
            && preg_match("/DB::table\\(\\s*['\"]internal_notifications['\"]/", $source) === 1) {
            $offenders[] = basename($file).': DB::table(internal_notifications)';
        }
    }

    expect($offenders)->toBe([]);
});

test('the update scanner flags a forbidden column (self-check)', function () {
    expect(notificationsForbiddenUpdateColumns('<?php $n->update(["read_at" => now(), "event_type_slug" => "x"]);'))->toBe(['1: event_type_slug']);
    expect(notificationsForbiddenUpdateColumns('<?php InternalNotification::query()->update(["recipient_id" => 2]);'))->toBe(['1: recipient_id']);
    expect(notificationsForbiddenUpdateColumns('<?php $n->update($attributes);'))->toHaveCount(1);
    expect(notificationsForbiddenUpdateColumns('<?php $n->update(["email_status" => $this->send($n, ["k" => 1]), "email_status_at" => now()]);'))->toBe([]);
    expect(notificationsForbiddenUpdateColumns('<?php public function update(User $user): bool {}'))->toBe([]);
});

test('the new views use literal classes, data-open toggles and no x-show nor details (UI-10)', function () {
    $offenders = [];

    foreach (NOTIFICATIONS_COMPLIANCE_VIEWS as $view) {
        foreach (notificationsViewViolations(file_get_contents(base_path($view))) as $violation) {
            $offenders[] = "{$view}: {$violation}";
        }
    }

    expect($offenders)->toBe([]);
    expect(file_get_contents(base_path('resources/views/livewire/notificacoes/bell.blade.php')))->toContain('x-bind:data-open');
});

test('the view scanner flags an interpolated class, x-show and details (self-check)', function () {
    expect(notificationsViewViolations('<span class="fill-{{ $cor }} text-sm">'))->toHaveCount(1);
    expect(notificationsViewViolations('<span class="text-sm {{ $read ? \'font-normal\' : \'font-semibold\' }}">'))->toHaveCount(1);
    expect(notificationsViewViolations('<div x-bind:class="`bg-${color}`">'))->toHaveCount(1);
    expect(notificationsViewViolations('<div x-show="open">'))->toBe(['x-show']);
    expect(notificationsViewViolations('<details open><summary>x</summary></details>'))->toBe(['<details']);
    expect(notificationsViewViolations('{{-- class="{{ $x }}" x-show <details --}}<div class="hidden data-[open=true]:flex" data-open="false">'))->toBe([]);
});
