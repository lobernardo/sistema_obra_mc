<?php

namespace App\Notifications\Concerns;

/**
 * Absolute links for outbound e-mails are anchored on `APP_URL` (RNF-12,
 * RF-27) instead of the root of the request that triggered the send, so a
 * message issued from any host — or from the console — always points at
 * the configured public domain and follows a future domain change.
 */
trait BuildsAppUrl
{
    /**
     * @param  array<string, mixed>  $parameters
     */
    protected function appUrlToRoute(string $name, array $parameters): string
    {
        return rtrim((string) config('app.url'), '/').route($name, $parameters, absolute: false);
    }
}
