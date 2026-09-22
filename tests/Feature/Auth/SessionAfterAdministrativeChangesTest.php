<?php

use App\Actions\Pedidos\UpdatePedidoStatusAction;
use App\Actions\Usuarios\SetUserActiveAction;
use App\Actions\Usuarios\UpdateUserAction;
use App\Enums\AuthenticationEventType;
use App\Enums\RoleSlug;
use App\Http\Middleware\EnsureUserIsActive;
use App\Livewire\Kanban\KanbanBoard;
use App\Models\AuthenticationEvent;
use App\Models\Obra;
use App\Models\Pedido;
use App\Models\Role;
use App\Models\Status;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;

/**
 * RF-16, RF-17 (decision D-10), AC-F12, AC-F13 — what happens to a live
 * session when Gestão acts on the account through the administrative
 * Actions (never through a raw update):
 *
 * (a) deactivation via `SetUserActiveAction` cuts the session on its next
 *     request — route or `/livewire/update` — with
 *     `EnsureUserIsActive::DEACTIVATED_MESSAGE` (RF-16, G-10);
 * (b) a papel change via `UpdateUserAction` keeps the session: the old
 *     area answers 403 through the `can:is-*` gates, `/home` reroutes to
 *     the new papel's landing route and the new area opens (RF-17, D-10,
 *     G-09);
 * (c) a `suprimentos` downgraded to `obra` is refused by the operational
 *     Action guards and by the Kanban control of a screen opened before
 *     the change (RF-17) — `AuthorizationException` from the component
 *     method, rendered as 403 by the Livewire transport.
 *
 * Simulation: the guard singleton of the test kernel caches the user
 * object across requests, while production rebuilds it from `users` on
 * every request by the id kept in the session. `signInThroughSession()`
 * writes that id and `reloadAuthenticatedUserFromDatabase()` drops the
 * cached object, so the next request reads `role_id`/`is_active` fresh.
 *
 * Trail (RF-26, D-09, D-10): the deactivation cut appends exactly one
 * `session_revoked` and never a `logout`; a papel change appends nothing.
 */
function signInThroughSession(User $user): void
{
    test()->actingAs($user);

    session()->put(Auth::guard('web')->getName(), $user->getAuthIdentifier());
}

function reloadAuthenticatedUserFromDatabase(): void
{
    Auth::guard('web')->forgetUser();
}

function authenticationEventCount(AuthenticationEventType $event): int
{
    return AuthenticationEvent::query()->where('event', $event->value)->count();
}

/**
 * Extracts the page component's `wire:snapshot` from a rendered full-page
 * Livewire response (see `EnsureUserIsActiveTest::firstLivewireSnapshot`).
 */
function openPageSnapshot(TestResponse $response): string
{
    preg_match('/wire:snapshot="([^"]+)"/', $response->getContent(), $matches);

    expect($matches)->toHaveCount(2, 'No wire:snapshot found in the rendered page.');

    return htmlspecialchars_decode($matches[1], ENT_QUOTES | ENT_SUBSTITUTE);
}

/**
 * Replays a `$refresh` call to `/livewire/update` for a page that was
 * already open when the administrative change happened (same transport as
 * `EnsureUserIsActiveTest::livewireRefresh`).
 */
function livewireRefreshOfOpenPage(string $snapshot): TestResponse
{
    Livewire::flushState();

    $updateUri = app('router')->getRoutes()->getByName('default-livewire.update')->uri();

    return test()->withHeaders(['X-Livewire' => 'true'])->postJson('/'.$updateUri, [
        '_token' => csrf_token(),
        'components' => [[
            'snapshot' => $snapshot,
            'updates' => [],
            'calls' => [['method' => '$refresh', 'params' => []]],
        ]],
    ]);
}

/**
 * @return array{name: string, email: string, role_id: int, obra_ids: list<int>}
 */
function downgradeToObraPayload(User $target, Obra $obra): array
{
    $obraRole = Role::query()->firstOrCreate(['slug' => RoleSlug::Obra->value], ['name' => 'Obra']);

    return [
        'name' => $target->name,
        'email' => $target->email,
        'role_id' => $obraRole->id,
        'obra_ids' => [$obra->id],
    ];
}

test('(a) deactivation through SetUserActiveAction cuts the live session on its next route request (RF-16, AC-F12)', function () {
    $user = User::factory()->obra()->create();
    $gestao = User::factory()->gestao()->create();

    signInThroughSession($user);
    $this->get(route('obra.pedidos.index'))->assertOk();
    expect(Auth::check())->toBeTrue();

    (app(SetUserActiveAction::class))->execute($gestao, User::query()->findOrFail($user->id), false);
    reloadAuthenticatedUserFromDatabase();

    $this->get(route('obra.pedidos.index'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('status', EnsureUserIsActive::DEACTIVATED_MESSAGE);

    expect(Auth::check())->toBeFalse();
    expect(session('status'))->toBe(EnsureUserIsActive::DEACTIVATED_MESSAGE);
    expect(authenticationEventCount(AuthenticationEventType::SessionRevoked))->toBe(1);
    expect(authenticationEventCount(AuthenticationEventType::Logout))->toBe(0);
    expect(AuthenticationEvent::query()->where('event', AuthenticationEventType::SessionRevoked->value)->sole()->user_id)->toBe($user->id);
});

test('(a) deactivation through SetUserActiveAction cuts a Livewire update issued from an already-open page (RF-16, AC-F12)', function () {
    $user = User::factory()->obra()->create();
    $gestao = User::factory()->gestao()->create();

    signInThroughSession($user);
    $snapshot = openPageSnapshot($this->get(route('obra.pedidos.index'))->assertOk());

    livewireRefreshOfOpenPage($snapshot)->assertOk();
    expect(Auth::check())->toBeTrue();

    (app(SetUserActiveAction::class))->execute($gestao, User::query()->findOrFail($user->id), false);
    reloadAuthenticatedUserFromDatabase();

    livewireRefreshOfOpenPage($snapshot)->assertRedirect(route('login'));

    expect(Auth::check())->toBeFalse();
    expect(session('status'))->toBe(EnsureUserIsActive::DEACTIVATED_MESSAGE);
    expect(authenticationEventCount(AuthenticationEventType::SessionRevoked))->toBe(1);
    expect(authenticationEventCount(AuthenticationEventType::Logout))->toBe(0);
});

test('(b) a gestao downgraded to obra through UpdateUserAction keeps the session: old area 403, /home reroutes, new area opens (RF-17, D-10, AC-F13)', function () {
    $user = User::factory()->gestao()->create();
    $admin = User::factory()->gestao()->create();
    $obra = Obra::factory()->create();

    signInThroughSession($user);
    $this->get(route('gestao.dashboard'))->assertOk();

    (app(UpdateUserAction::class))->execute($admin, User::query()->findOrFail($user->id), downgradeToObraPayload($user, $obra));
    reloadAuthenticatedUserFromDatabase();

    $this->get(route('gestao.dashboard'))->assertForbidden();
    expect(Auth::check())->toBeTrue();
    expect(Auth::id())->toBe($user->id);
    expect(Auth::user()->role->slug)->toBe(RoleSlug::Obra->value);
    expect(session()->has('status'))->toBeFalse();

    $this->get(route('gestao.usuarios.index'))->assertForbidden();
    expect(Auth::check())->toBeTrue();

    $this->get(route('home'))->assertRedirect(route('obra.pedidos.index'));
    expect(Auth::check())->toBeTrue();

    $this->get(route('obra.pedidos.index'))->assertOk();
    expect(Auth::id())->toBe($user->id);
    expect(authenticationEventCount(AuthenticationEventType::SessionRevoked))->toBe(0);
    expect(authenticationEventCount(AuthenticationEventType::Logout))->toBe(0);
});

test('(c) a suprimentos downgraded to obra is refused by the status Action guard and by the Kanban control of an open board (RF-17, AC-F13)', function () {
    $user = User::factory()->suprimentos()->create();
    $admin = User::factory()->gestao()->create();
    $obra = Obra::factory()->create();
    $solicitado = Status::factory()->solicitado()->create();
    $emAnalise = Status::factory()->emAnalise()->create();
    $pedido = Pedido::factory()->create(['status_id' => $solicitado->id]);

    signInThroughSession($user);
    $this->get(route('suprimentos.kanban'))->assertOk();
    $board = Livewire::test(KanbanBoard::class)->assertSee($pedido->code);

    (app(UpdateUserAction::class))->execute($admin, User::query()->findOrFail($user->id), downgradeToObraPayload($user, $obra));
    reloadAuthenticatedUserFromDatabase();

    expect(fn () => app(UpdatePedidoStatusAction::class)->execute(Auth::user(), $pedido, $emAnalise->id))
        ->toThrow(AuthorizationException::class);

    expect(fn () => $board->instance()->moveViaControl($pedido->id, $emAnalise->id))
        ->toThrow(AuthorizationException::class);

    $board->call('moveViaControl', $pedido->id, $emAnalise->id)->assertForbidden();

    expect($pedido->fresh()->status_id)->toBe($solicitado->id);
    expect($pedido->events()->count())->toBe(0);

    $this->get(route('suprimentos.kanban'))->assertForbidden();
    expect(Auth::check())->toBeTrue();
    expect(AuthenticationEvent::query()->count())->toBe(0);
});
