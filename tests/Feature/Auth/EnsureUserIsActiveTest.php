<?php

use App\Actions\Usuarios\SetUserActiveAction;
use App\Http\Middleware\EnsureUserIsActive;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;

/**
 * Extracts the first `wire:snapshot` (the page component) from a rendered
 * full-page Livewire response, exactly as the browser would send it back.
 */
function firstLivewireSnapshot(TestResponse $response): string
{
    preg_match('/wire:snapshot="([^"]+)"/', $response->getContent(), $matches);

    expect($matches)->toHaveCount(2, 'No wire:snapshot found in the rendered page.');

    return htmlspecialchars_decode($matches[1], ENT_QUOTES | ENT_SUBSTITUTE);
}

/**
 * Replays a `$refresh` call to the Livewire update endpoint for the given
 * snapshot, mimicking the request Livewire's JavaScript issues from an
 * already-open page. Livewire's per-request state is flushed first, as a
 * fresh PHP process would be in production (the test kernel keeps the
 * singleton alive across requests).
 */
function livewireRefresh(string $snapshot): TestResponse
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

test('a logged-in user who is deactivated is logged out and redirected on the next request (TC-20)', function () {
    $user = User::factory()->obra()->create();
    $gestao = User::factory()->gestao()->create();

    $this->actingAs($user);
    $this->get(route('obra.pedidos.index'))->assertOk();
    expect(Auth::check())->toBeTrue();

    (new SetUserActiveAction)->execute($gestao, $user, false);

    $this->get(route('obra.pedidos.index'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('status', EnsureUserIsActive::DEACTIVATED_MESSAGE);

    expect(Auth::check())->toBeFalse();
});

test('the redirect carries the PT-BR message and the login page shows it', function () {
    $user = User::factory()->suprimentos()->inactive()->create();

    $this->actingAs($user);

    $response = $this->get(route('suprimentos.kanban'))->assertRedirect(route('login'));

    expect(session('status'))->toBe('Sua conta foi desativada. Fale com a Gestão.');

    $this->followRedirects($response)->assertOk();
});

test('a Livewire update call from an already-open page is cut after deactivation (TC-20)', function () {
    $user = User::factory()->obra()->create();

    $this->actingAs($user);
    $snapshot = firstLivewireSnapshot($this->get(route('obra.pedidos.index'))->assertOk());

    livewireRefresh($snapshot)->assertOk();
    expect(Auth::check())->toBeTrue();

    $user->update(['is_active' => false]);

    livewireRefresh($snapshot)->assertRedirect(route('login'));

    expect(Auth::check())->toBeFalse();
    expect(session('status'))->toBe(EnsureUserIsActive::DEACTIVATED_MESSAGE);
});

test('an active user is unaffected on the same routes', function (string $factoryState, string $routeName) {
    $user = User::factory()->{$factoryState}()->create();

    $this->actingAs($user);

    $this->get(route($routeName))->assertOk();
    expect(Auth::id())->toBe($user->id);

    $this->get(route($routeName))->assertOk();
    expect(Auth::id())->toBe($user->id);
})->with([
    'obra' => ['obra', 'obra.pedidos.index'],
    'suprimentos' => ['suprimentos', 'suprimentos.kanban'],
    'gestao' => ['gestao', 'gestao.dashboard'],
]);

test('every authenticated route is covered by the active middleware', function () {
    $user = User::factory()->gestao()->inactive()->create();

    $this->actingAs($user);

    $this->get(route('home'))->assertRedirect(route('login'));
    expect(Auth::check())->toBeFalse();

    $this->actingAs($user);

    $this->get(route('gestao.dashboard'))->assertRedirect(route('login'));
    expect(Auth::check())->toBeFalse();
});

test('the middleware is aliased as active and applied to the authenticated group', function () {
    expect(app('router')->getMiddleware())->toHaveKey('active', EnsureUserIsActive::class);

    foreach (['home', 'logout', 'obra.pedidos.index', 'suprimentos.kanban', 'gestao.dashboard'] as $routeName) {
        $middleware = app('router')->getRoutes()->getByName($routeName)->gatherMiddleware();

        expect($middleware)->toContain('auth');
        expect($middleware)->toContain('active');
    }
});

test('deactivation never deletes sessions rows (Q-06)', function () {
    $user = User::factory()->obra()->create();
    $gestao = User::factory()->gestao()->create();

    DB::table('sessions')->insert([
        'id' => 'live-session',
        'user_id' => $user->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'pest',
        'payload' => base64_encode(serialize([])),
        'last_activity' => now()->timestamp,
    ]);

    (new SetUserActiveAction)->execute($gestao, $user, false);

    $this->actingAs($user);
    $this->get(route('obra.pedidos.index'))->assertRedirect(route('login'));

    expect(DB::table('sessions')->where('id', 'live-session')->exists())->toBeTrue();
});
