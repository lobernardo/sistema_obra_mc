<?php

use Illuminate\Support\Facades\Route;

/**
 * RF-02 / RF-09 / RF-24 (navegacao-sidebar-listagens T01): the middleware of
 * every named application route, captured at `<base>` =
 * 03b0451e5558fb6a42c57a00feeb3db090b2dd52 before any change of this slice,
 * with `Route::getRoutes()` → `gatherMiddleware()`.
 *
 * The sidebar is presentation only (SPEC RF-02): it never replaces route
 * authorization, so no route of the base may lose, gain or reorder a
 * middleware. This map may only be edited when a later slice **adds** a
 * route; an existing entry never changes. Development-only vendor routes
 * (`boost.*`) are left out because they are not registered in every
 * environment.
 *
 * @return array<string, list<string>>
 */
function routeMiddlewareBaseline(): array
{
    return [
        'default-livewire.update' => ['web', 'Livewire\\Mechanisms\\HandleRequests\\RequireLivewireHeaders'],
        'livewire.upload-file' => ['web', 'throttle:60,1'],
        'livewire.preview-file' => ['web'],
        'obra-invitation.show' => ['web', 'active'],
        'obra-invitation.unavailable' => ['web'],
        'obra-invitation.throttled' => ['web'],
        'login' => ['web', 'guest'],
        'register' => ['web', 'guest'],
        'password.request' => ['web', 'guest'],
        'password.reset' => ['web', 'guest'],
        'invite.show' => ['web', 'guest'],
        'home' => ['web', 'auth', 'active'],
        'logout' => ['web', 'auth', 'active'],
        'obra.nova-solicitacao' => ['web', 'auth', 'active', 'can:is-obra', 'can:create-pedido'],
        'obra.pedidos.index' => ['web', 'auth', 'active', 'can:is-obra'],
        'obra.pedidos.show' => ['web', 'auth', 'active', 'can:is-obra'],
        'suprimentos.pedidos.index' => ['web', 'auth', 'active', 'can:is-suprimentos'],
        'suprimentos.pedidos.show' => ['web', 'auth', 'active', 'can:is-suprimentos'],
        'suprimentos.kanban' => ['web', 'auth', 'active', 'can:is-suprimentos'],
        'suprimentos.visao-geral' => ['web', 'auth', 'active', 'can:is-suprimentos'],
        'suprimentos.nova-solicitacao' => ['web', 'auth', 'active', 'can:is-suprimentos', 'can:create-pedido'],
        'pedidos.anexos.download' => ['web', 'auth', 'active'],
        'obras.index' => ['web', 'auth', 'active', 'can:manage-obras'],
        'obras.create' => ['web', 'auth', 'active', 'can:manage-obras'],
        'obras.edit' => ['web', 'auth', 'active', 'can:manage-obras'],
        'associacoes.index' => ['web', 'auth', 'active', 'can:manage-obras'],
        'gestao.dashboard' => ['web', 'auth', 'active', 'can:is-gestao'],
        'gestao.pedidos.index' => ['web', 'auth', 'active', 'can:is-gestao'],
        'gestao.pedidos.show' => ['web', 'auth', 'active', 'can:is-gestao'],
        'gestao.kanban' => ['web', 'auth', 'active', 'can:is-gestao'],
        'gestao.usuarios.index' => ['web', 'auth', 'active', 'can:is-gestao', 'can:manage-users'],
        'gestao.usuarios.create' => ['web', 'auth', 'active', 'can:is-gestao', 'can:manage-users'],
        'gestao.usuarios.edit' => ['web', 'auth', 'active', 'can:is-gestao', 'can:manage-users'],
        'storage.local' => [],
        'storage.local.upload' => [],
    ];
}

test('every baseline route still exists with exactly the same middleware in the same order', function (string $name, array $middleware) {
    $route = Route::getRoutes()->getByName($name);

    expect($route)->not->toBeNull("Route [{$name}] no longer exists.")
        ->and($route->gatherMiddleware())->toBe($middleware);
})->with(fn (): array => collect(routeMiddlewareBaseline())
    ->map(fn (array $middleware, string $name): array => [$name, $middleware])
    ->all());

test('home keeps the web, auth and active middleware', function () {
    expect(Route::getRoutes()->getByName('home')->gatherMiddleware())
        ->toContain('web', 'auth', 'active');
});
