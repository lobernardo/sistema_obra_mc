<?php

use App\Actions\Obras\GenerateObraInvitationAction;
use App\Livewire\Auth\ObraInvitationPage;
use App\Models\Obra;
use App\Models\ObraInvitation;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Livewire\Attributes\Url;
use Livewire\Livewire;

/**
 * RF-38 (NC-08, FINAL) — the convite token travels only in the URL
 * fragment and in the `/livewire/update` POST body. These tests drive the
 * real HTTP transport: every request URI (path + query) and every redirect
 * target of the two acceptance flows is recorded and must never contain
 * the plaintext token.
 */
const TOKEN_TRANSPORT_PASSWORD = 'senha-forte-123';

beforeEach(function () {
    Role::factory()->obra()->create();

    $this->obra = Obra::factory()->emAndamento()->create();

    $result = app(GenerateObraInvitationAction::class)->execute(User::factory()->gestao()->create(), $this->obra);

    $this->token = parse_url($result['url'], PHP_URL_FRAGMENT);
    $this->invitation = $result['invitation'];

    $this->observed = [];

    Event::listen(RequestHandled::class, function (RequestHandled $event): void {
        $this->observed[] = $event->request->getRequestUri();
        $this->observed[] = (string) $event->response->headers->get('Location');
    });
});

function transportSnapshotFrom(TestResponse $response): string
{
    preg_match('/wire:snapshot="([^"]+)"/', $response->assertOk()->getContent(), $matches);

    expect($matches)->toHaveCount(2, 'No wire:snapshot found on the page.');

    return htmlspecialchars_decode($matches[1], ENT_QUOTES | ENT_SUBSTITUTE);
}

/**
 * One `/livewire/update` round trip, as Livewire's JavaScript issues it.
 *
 * @param  array<string, mixed>  $updates
 * @param  list<mixed>  $params
 * @return array{snapshot: string, effects: array<string, mixed>, raw: string}
 */
function transportCall(string $snapshot, string $method, array $params = [], array $updates = []): array
{
    Livewire::flushState();

    $updateUri = app('router')->getRoutes()->getByName('default-livewire.update')->uri();

    $response = test()->withHeaders(['X-Livewire' => 'true'])->postJson('/'.$updateUri, [
        '_token' => csrf_token(),
        'components' => [[
            'snapshot' => $snapshot,
            'updates' => $updates,
            'calls' => [['method' => $method, 'params' => $params]],
        ]],
    ])->assertOk();

    $component = $response->json('components.0');

    return [
        'snapshot' => $component['snapshot'],
        'effects' => $component['effects'] ?? [],
        'raw' => $response->getContent(),
    ];
}

test('the convite routes have no parameter and none carries {token} (AC-a)', function () {
    foreach (['obra-invitation.show', 'obra-invitation.unavailable', 'obra-invitation.throttled'] as $name) {
        expect(Route::getRoutes()->getByName($name)->parameterNames())->toBe([]);
    }

    $conviteRoutes = array_filter(
        Route::getRoutes()->getRoutes(),
        fn ($route): bool => str_starts_with($route->uri(), 'convite'),
    );

    expect($conviteRoutes)->toHaveCount(3);

    foreach ($conviteRoutes as $route) {
        expect($route->uri())->not->toContain('{');
    }
});

test('generate → open → register never puts the token in a URI or redirect, nor in the post-lookup snapshot (AC-a, AC-c)', function () {
    $snapshot = transportSnapshotFrom($this->get(route('obra-invitation.show')));

    $lookup = transportCall($snapshot, 'lookup', [$this->token]);

    expect($lookup['effects'])->not->toHaveKey('redirect');
    expect($lookup['snapshot'])->not->toContain($this->token);
    expect($lookup['effects']['html'] ?? '')->not->toContain($this->token);
    expect(json_decode($lookup['snapshot'], true)['data']['invitationId'])->toBe($this->invitation->id);

    $register = transportCall($lookup['snapshot'], 'register', [], [
        'name' => 'Nova Pessoa',
        'email' => 'nova@example.com',
        'password' => TOKEN_TRANSPORT_PASSWORD,
        'password_confirmation' => TOKEN_TRANSPORT_PASSWORD,
    ]);

    expect($register['effects']['redirect'])->toBe(route('home'));
    expect($register['raw'])->not->toContain($this->token);

    $this->get($register['effects']['redirect'])->assertRedirect();

    $user = User::query()->where('email', 'nova@example.com')->sole();

    expect(Auth::id())->toBe($user->id);
    expect($user->obras()->pluck('obras.id')->all())->toBe([$this->obra->id]);

    expect($this->observed)->not->toBeEmpty();

    foreach ($this->observed as $value) {
        expect($value)->not->toContain($this->token);
    }
});

test('generate → open → "Já tenho conta" → login → return → confirm never puts the token in a URI, redirect or session (AC-a, AC-d)', function () {
    $user = User::factory()->obra()->create(['email' => 'paulo@example.com', 'password' => TOKEN_TRANSPORT_PASSWORD]);

    $snapshot = transportSnapshotFrom($this->get(route('obra-invitation.show')));
    $lookup = transportCall($snapshot, 'lookup', [$this->token]);

    $existing = transportCall($lookup['snapshot'], 'useExistingAccount');

    expect($existing['effects']['redirect'])->toBe(route('login'));
    expect(session(ObraInvitationPage::RETURN_SESSION_KEY))->toBe($this->invitation->id);
    expect(serialize(session()->all()))->not->toContain($this->token)->not->toContain(ObraInvitation::hashToken($this->token));

    $loginSnapshot = transportSnapshotFrom($this->get($existing['effects']['redirect']));

    $login = transportCall($loginSnapshot, 'authenticate', [], [
        'email' => 'paulo@example.com',
        'password' => TOKEN_TRANSPORT_PASSWORD,
    ]);

    expect($login['effects']['redirect'])->toBe(route('obra-invitation.show'));

    $resumedResponse = $this->get($login['effects']['redirect']);
    $resumed = transportSnapshotFrom($resumedResponse);

    expect(json_decode($resumed, true)['data']['invitationId'])->toBe($this->invitation->id);
    expect($resumedResponse->getContent())->not->toContain('$wire.lookup');

    $confirm = transportCall($resumed, 'confirm');

    expect(json_decode($confirm['snapshot'], true)['data']['notice'])->toBe(ObraInvitationPage::ACCEPTED_NOTICE);
    expect($user->obras()->pluck('obras.id')->all())->toBe([$this->obra->id]);
    expect($this->invitation->fresh()->used_by)->toBe($user->id);

    foreach ($this->observed as $value) {
        expect($value)->not->toContain($this->token);
    }
});

test('after the lookup, the component HTML and snapshot never hold the token (AC-c)', function () {
    $component = Livewire::test(ObraInvitationPage::class)->call('lookup', $this->token);

    expect($component->html())->not->toContain($this->token);
    expect(json_encode($component->snapshot))->not->toContain($this->token);
});

test('the component declares no #[Url] property and lookup marks its argument as sensitive', function () {
    $reflection = new ReflectionClass(ObraInvitationPage::class);

    foreach ($reflection->getProperties() as $property) {
        expect($property->getAttributes(Url::class))->toBe([]);
    }

    $parameter = $reflection->getMethod('lookup')->getParameters()[0];

    expect($parameter->getAttributes(SensitiveParameter::class))->toHaveCount(1);
});
