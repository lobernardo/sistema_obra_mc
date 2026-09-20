<?php

namespace App\Providers;

use App\Enums\RoleSlug;
use App\Http\Middleware\EnsureUserIsActive;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
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
    }
}
