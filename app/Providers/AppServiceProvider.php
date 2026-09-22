<?php

namespace App\Providers;

use App\Enums\RoleSlug;
use App\Http\Middleware\EnsureUserIsActive;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * Coarse role gates (RF-08, RF-08b, RF-08c) reused by Policies and
     * Livewire components to check an actor's papel without repeating the
     * `role?->slug === ...` comparison everywhere.
     *
     * `manage-users` is the distinct administrative ability (RNF-11): the
     * users area and `UserPolicy` consume it, so a future `Admin` papel can
     * take it over by editing only this definition.
     */
    public function boot(): void
    {
        Gate::define('is-obra', fn (User $user): bool => $user->role?->slug === RoleSlug::Obra->value);
        Gate::define('is-suprimentos', fn (User $user): bool => $user->role?->slug === RoleSlug::Suprimentos->value);
        Gate::define('is-gestao', fn (User $user): bool => $user->role?->slug === RoleSlug::Gestao->value);
        Gate::define('manage-users', fn (User $user): bool => $user->role?->slug === RoleSlug::Gestao->value);

        /*
         * Re-apply the active-account check on `/livewire/update` requests
         * issued from a page that was loaded under `active` (RF-31), so a
         * deactivated user's already-open screen is cut on its next call.
         */
        Livewire::addPersistentMiddleware([EnsureUserIsActive::class]);

        $this->configureRateLimiting();
    }

    /**
     * Named limiters for the guest authentication flows, consumed through
     * `App\Services\AuthenticationRateLimiter` (RF-09, RF-11).
     *
     * The thresholds are deliberately literal and live only here (D-02):
     * they are a product decision, not deployment configuration, so they are
     * never read from env/config. Two login limiters exist because
     * `trustProxies(at: '*')` makes the client IP `X-Forwarded-For`-derived
     * and forgeable (D-01): the e-mail-only `login-account` ceiling holds
     * regardless of IP trust.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('login', fn () => Limit::perMinute(5));
        RateLimiter::for('login-account', fn () => Limit::perMinutes(15, 20));
        RateLimiter::for('recovery', fn () => Limit::perMinute(3));
        RateLimiter::for('recovery-ip', fn () => Limit::perMinute(6));
    }
}
