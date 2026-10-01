<?php

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

use function Illuminate\Support\defer;

/**
 * T01 — RNF-03 (static part), RNF-01, RNF-05: `defer()` callbacks run in
 * the HTTP pipeline only after the response is built (the global
 * `InvokeDeferredCallbacks` middleware fires on `terminate()`, after
 * `Response::send()`), are skipped on a status ≥ 400 unless registered with
 * `always: true`, and run in console on `CommandFinished` with exit 0.
 *
 * The routes and the command exist only inside these tests: no file in
 * `app/`, `config/` or `routes/` is touched (see
 * `.spec/features/notificacoes-internas/defer-verificacao.md`).
 */

/**
 * Registers a temporary route that defers a callback and answers with the
 * given status; every step appends to `$log` in the order it happens.
 *
 * @param  list<string>  $log
 */
function registerDeferringRoute(string $uri, int $status, bool $always, array &$log): void
{
    Route::get($uri, function () use ($status, $always, &$log) {
        defer(function () use (&$log): void {
            $log[] = 'deferred';
        }, always: $always);

        $log[] = 'handler';

        return response('corpo da resposta', $status);
    });

    Event::listen(RequestHandled::class, function () use (&$log): void {
        $log[] = 'response-built';
    });
}

test('a deferred callback runs only after the response is built', function () {
    $log = [];
    registerDeferringRoute('/__teste-defer/ok', 200, false, $log);

    $this->get('/__teste-defer/ok')
        ->assertOk()
        ->assertSee('corpo da resposta');

    expect($log)->toBe(['handler', 'response-built', 'deferred']);
});

test('a deferred callback does not run on a 500 response without always', function () {
    $log = [];
    registerDeferringRoute('/__teste-defer/erro', 500, false, $log);

    $this->get('/__teste-defer/erro')->assertStatus(500);

    expect($log)->toBe(['handler', 'response-built']);
});

test('a deferred callback registered with always runs on a 500 response', function () {
    $log = [];
    registerDeferringRoute('/__teste-defer/erro-sempre', 500, true, $log);

    $this->get('/__teste-defer/erro-sempre')->assertStatus(500);

    expect($log)->toBe(['handler', 'response-built', 'deferred']);
});

test('a deferred callback does not run on a 403 response without always', function () {
    $log = [];
    registerDeferringRoute('/__teste-defer/proibido', 403, false, $log);

    $this->get('/__teste-defer/proibido')->assertForbidden();

    expect($log)->toBe(['handler', 'response-built']);
});

/**
 * `Kernel::call()` (used by `$this->artisan()`) does not reroute the Symfony
 * console events, so `CommandFinished` is dispatched here exactly as
 * `Kernel::handle()` does for a real `php artisan` run.
 */
function finishConsoleCommand(int $exitCode): void
{
    event(new CommandFinished('teste:defer', new ArrayInput([]), new NullOutput, $exitCode));
}

test('in console a deferred callback runs when the command finishes with exit 0', function () {
    $log = [];
    defer(function () use (&$log): void {
        $log[] = 'deferred';
    });

    expect($log)->toBe([]);

    finishConsoleCommand(0);

    expect($log)->toBe(['deferred']);
});

test('in console a deferred callback does not run when the command fails without always', function () {
    $log = [];
    defer(function () use (&$log): void {
        $log[] = 'deferred';
    });
    defer(function () use (&$log): void {
        $log[] = 'deferred-always';
    }, always: true);

    finishConsoleCommand(1);

    expect($log)->toBe(['deferred-always']);
});
