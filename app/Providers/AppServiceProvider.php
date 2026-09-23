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
     *
     * `manage-obras` (RF-07, CT-03) grants the Obras area, convites and the
     * user × obra associations to Gestão and Suprimentos. It is deliberately
     * distinct from `manage-users`, which stays Gestão-only (RF-37).
     *
     * `create-pedido` (RF-01, CT-05) grants Nova Solicitação to exactly Obra
     * and Suprimentos; Gestão never creates pedidos. The obra checks
     * (association, Concluído, zero obras) live in `CreatePedidoAction`.
     */
    public function boot(): void
    {
        Gate::define('is-obra', fn (User $user): bool => $user->role?->slug === RoleSlug::Obra->value);
        Gate::define('is-suprimentos', fn (User $user): bool => $user->role?->slug === RoleSlug::Suprimentos->value);
        Gate::define('is-gestao', fn (User $user): bool => $user->role?->slug === RoleSlug::Gestao->value);
        Gate::define('manage-users', fn (User $user): bool => $user->role?->slug === RoleSlug::Gestao->value);
        Gate::define('manage-obras', fn (User $user): bool => in_array($user->role?->slug, [RoleSlug::Gestao->value, RoleSlug::Suprimentos->value], true));
        Gate::define('create-pedido', fn (User $user): bool => in_array($user->role?->slug, [RoleSlug::Obra->value, RoleSlug::Suprimentos->value], true));

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
     *
     * Account creation (Novo Cadastro and the convite new-account path share
     * the counters, RF-19) is limited per e-mail + IP (`register`) and per IP
     * (`register-ip`); `invite-ip` guards the convite token lookup POST
     * (RF-19b), never a GET.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('login', fn () => Limit::perMinute(5));
        RateLimiter::for('login-account', fn () => Limit::perMinutes(15, 20));
        RateLimiter::for('recovery', fn () => Limit::perMinute(3));
        RateLimiter::for('recovery-ip', fn () => Limit::perMinute(6));
        RateLimiter::for('register', fn () => Limit::perMinutes(10, 3));
        RateLimiter::for('register-ip', fn () => Limit::perHour(10));
        RateLimiter::for('invite-ip', fn () => Limit::perMinute(20));
    }
}
