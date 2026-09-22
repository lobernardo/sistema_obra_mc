<?php

use App\Http\Middleware\Authenticate;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\AuthenticateSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        /*
         * TLS is terminated by the Railway edge proxy; trusting its
         * X-Forwarded-* headers keeps generated URLs (assets, redirects)
         * on https and lets the secure session cookie work.
         */
        $middleware->trustProxies(at: '*');

        /*
         * Invalidate every pre-existing session of a user once their
         * password is redefined (reset) or defined (first access) — RF-14.
         * The framework middleware stores the password hash in the session
         * on the first authenticated request and compares it with
         * `users.password` on every request, including `/livewire/update`
         * (it belongs to the `web` group, so it runs before `auth`/`active`).
         * R-06: sessions already open in production before this deploy
         * carry no stored hash; they receive it on their next request and
         * are NOT cut by the rollout. No `logoutOtherDevices` is added to
         * the guest reset/invite flows. `redirectGuestsTo` gives the forced
         * cut the same `/login` destination the `auth` middleware already
         * uses (JSON clients keep receiving 401).
         */
        $middleware->web(append: [AuthenticateSession::class]);

        $middleware->redirectGuestsTo(
            fn (Request $request) => $request->expectsJson() ? null : route('login'),
        );

        $middleware->alias([
            'auth' => Authenticate::class,
            'active' => EnsureUserIsActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
