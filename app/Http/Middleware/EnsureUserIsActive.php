<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cuts the live session of a deactivated user on its next authenticated
 * request (RF-31): logs out, invalidates the session, regenerates the CSRF
 * token and redirects to the login screen with a PT-BR message. Aliased
 * as `active` in `bootstrap/app.php` and applied next to `auth`; also
 * persisted by Livewire so `/livewire/update` calls are covered. Only a
 * strict `false` triggers it — `null` (guest) never locks anyone out.
 * `sessions` rows are never deleted here (Q-06).
 */
class EnsureUserIsActive
{
    public const string DEACTIVATED_MESSAGE = 'Sua conta foi desativada. Fale com a Gestão.';

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->is_active === false) {
            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('status', self::DEACTIVATED_MESSAGE);
        }

        return $next($request);
    }
}
