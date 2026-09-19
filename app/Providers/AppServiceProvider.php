<?php

namespace App\Providers;

use App\Enums\RoleSlug;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

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
     */
    public function boot(): void
    {
        Gate::define('is-obra', fn (User $user): bool => $user->role?->slug === RoleSlug::Obra->value);
        Gate::define('is-suprimentos', fn (User $user): bool => $user->role?->slug === RoleSlug::Suprimentos->value);
        Gate::define('is-gestao', fn (User $user): bool => $user->role?->slug === RoleSlug::Gestao->value);
    }
}
