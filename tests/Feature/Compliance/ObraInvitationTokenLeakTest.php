<?php

use App\Livewire\Auth\ObraInvitationPage;
use App\Livewire\Obras\Form as ObrasForm;
use App\Models\Obra;
use App\Models\ObraInvitation;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Livewire\Attributes\Url;
use Livewire\Livewire;
use Mockery\Exception\InvalidCountException;

/**
 * RF-38 / RNF-01 / RNF-08 (NC-08, FINAL): the plaintext convite token is a
 * secret. With `Log::spy()` and `Exceptions::fake()` active, the generate →
 * lookup → accept cycles on both acceptance paths and the invalid lookups
 * run through the real `/livewire/update` transport; afterwards the token
 * must be absent from every log call, every reported exception, the five
 * convite/audit tables, the serialized session and the reloaded obra
 * screen. Static scans pin the transport (no token route parameter, no
 * `#[Url]`, `#[\SensitiveParameter]` on every `$token` parameter), and the
 * convite page never shows who created the convite.
 */
const TOKEN_LEAK_PASSWORD = 'senha-forte-123';

const TOKEN_LEAK_TABLES = [
    'obra_invitations',
    'obra_admin_events',
    'account_registration_events',
    'user_admin_events',
    'authentication_events',
];

const TOKEN_LEAK_LOG_METHODS = [
    'emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug',
    'log', 'write', 'withContext', 'shareContext', 'channel', 'stack', 'build',
];

beforeEach(function () {
    Role::factory()->obra()->create();

    Log::spy();
    Exceptions::fake();

    $this->creator = User::factory()->gestao()->create([
        'name' => 'Gestora Criadora Sigilosa',
        'email' => 'criadora.sigilosa@example.com',
    ]);
    $this->obra = Obra::factory()->emAndamento()->create(['name' => 'Residencial Horizonte']);
});

/**
 * Generates a convite through the obra screen as the creator, reloads the
 * screen, then leaves the creator's session so the rest runs as a guest.
 */
function tokenLeakGenerateThroughObraScreen(User $creator, Obra $obra): string
{
    test()->actingAs($creator);

    $link = Livewire::test(ObrasForm::class, ['obra' => $obra])
        ->call('generateInvitation')
        ->get('generatedLink');

    $token = (string) parse_url((string) $link, PHP_URL_FRAGMENT);

    expect($token)->toMatch('/^[0-9a-f]{64}$/');

    $reloaded = test()->get(route('obras.edit', $obra))->assertOk()->getContent();

    expect($reloaded)->not->toContain($token);

    Auth::logout();
    session()->flush();
    Livewire::flushState();

    return $token;
}

function tokenLeakSnapshotFrom(TestResponse $response): string
{
    preg_match('/wire:snapshot="([^"]+)"/', $response->assertOk()->getContent(), $matches);

    expect($matches)->toHaveCount(2, 'No wire:snapshot found on the page.');

    return htmlspecialchars_decode($matches[1], ENT_QUOTES | ENT_SUBSTITUTE);
}

/**
 * @param  array<string, mixed>  $updates
 * @param  list<mixed>  $params
 * @return array{snapshot: string, effects: array<string, mixed>}
 */
function tokenLeakCall(string $snapshot, string $method, array $params = [], array $updates = []): array
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

    return ['snapshot' => $component['snapshot'], 'effects' => $component['effects'] ?? []];
}

function tokenLeakDump(mixed $value): string
{
    return print_r($value, true);
}

/**
 * Asserts the secret is nowhere the application could have written it.
 */
function assertTokenLeftNoTrace(string $secret): void
{
    $logger = Log::getFacadeRoot();

    foreach (TOKEN_LEAK_LOG_METHODS as $method) {
        $logger->shouldNotHaveReceived($method, fn (...$arguments): bool => str_contains(tokenLeakDump($arguments), $secret));
    }

    foreach (Exceptions::reported() as $exception) {
        expect($exception->getMessage())->not->toContain($secret);
        expect((string) $exception)->not->toContain($secret);

        if (method_exists($exception, 'context')) {
            expect(tokenLeakDump($exception->context()))->not->toContain($secret);
        }
    }

    foreach (TOKEN_LEAK_TABLES as $table) {
        expect(json_encode(DB::table($table)->get()))->not->toContain($secret);
    }

    expect(serialize(session()->all()))->not->toContain($secret);
}

test('the log spy sees an argument that carries the secret (self-check)', function () {
    Log::info('probe', ['nested' => ['value' => 'segredo-de-teste']]);

    expect(fn () => assertTokenLeftNoTrace('segredo-de-teste'))->toThrow(InvalidCountException::class);
});

test('generate → lookup → new account leaves no trace of the token (RF-38 AC-e, RNF-01)', function () {
    $token = tokenLeakGenerateThroughObraScreen($this->creator, $this->obra);

    $lookup = tokenLeakCall(tokenLeakSnapshotFrom($this->get(route('obra-invitation.show'))), 'lookup', [$token]);

    expect($lookup['effects'])->not->toHaveKey('redirect');

    $register = tokenLeakCall($lookup['snapshot'], 'register', [], [
        'name' => 'Nova Pessoa',
        'email' => 'nova.pessoa@example.com',
        'password' => TOKEN_LEAK_PASSWORD,
        'password_confirmation' => TOKEN_LEAK_PASSWORD,
    ]);

    expect($register['effects']['redirect'])->toBe(route('home'));

    $this->get(route('home'))->assertRedirect();

    $user = User::query()->where('email', 'nova.pessoa@example.com')->sole();

    expect($user->obras()->pluck('obras.id')->all())->toBe([$this->obra->id]);
    expect(ObraInvitation::query()->sole()->used_by)->toBe($user->id);

    assertTokenLeftNoTrace($token);

    Auth::logout();
    session()->flush();
    $this->actingAs($this->creator)->get(route('obras.edit', $this->obra))->assertOk()->assertDontSee($token, false);
});

test('generate → lookup → "Já tenho conta" → login → return → confirm leaves no trace of the token (RF-38 AC-e, RF-30)', function () {
    $user = User::factory()->obra()->create(['email' => 'paula.obra@example.com', 'password' => TOKEN_LEAK_PASSWORD]);

    $token = tokenLeakGenerateThroughObraScreen($this->creator, $this->obra);

    $lookup = tokenLeakCall(tokenLeakSnapshotFrom($this->get(route('obra-invitation.show'))), 'lookup', [$token]);
    $existing = tokenLeakCall($lookup['snapshot'], 'useExistingAccount');

    expect($existing['effects']['redirect'])->toBe(route('login'));
    expect(serialize(session()->all()))->not->toContain($token);

    $login = tokenLeakCall(tokenLeakSnapshotFrom($this->get(route('login'))), 'authenticate', [], [
        'email' => 'paula.obra@example.com',
        'password' => TOKEN_LEAK_PASSWORD,
    ]);

    expect($login['effects']['redirect'])->toBe(route('obra-invitation.show'));

    $confirm = tokenLeakCall(tokenLeakSnapshotFrom($this->get(route('obra-invitation.show'))), 'confirm');

    expect(json_decode($confirm['snapshot'], true)['data']['notice'])->toBe(ObraInvitationPage::ACCEPTED_NOTICE);
    expect($user->obras()->pluck('obras.id')->all())->toBe([$this->obra->id]);
    expect(ObraInvitation::query()->sole()->used_by)->toBe($user->id);

    assertTokenLeftNoTrace($token);

    Auth::logout();
    session()->flush();
    $this->actingAs($this->creator)->get(route('obras.edit', $this->obra))->assertOk()->assertDontSee($token, false);
});

test('invalid lookups leave no trace of the submitted value nor of a valid pending token (RF-38 AC-e, RF-28)', function () {
    $validToken = tokenLeakGenerateThroughObraScreen($this->creator, $this->obra);
    $unknownToken = bin2hex(random_bytes(32));
    $malformedToken = 'valor-malformado-'.bin2hex(random_bytes(8));

    foreach ([$unknownToken, $malformedToken] as $candidate) {
        $lookup = tokenLeakCall(tokenLeakSnapshotFrom($this->get(route('obra-invitation.show'))), 'lookup', [$candidate]);

        expect($lookup['effects']['redirect'])->toBe(route('obra-invitation.unavailable'));

        $this->get(route('obra-invitation.unavailable'))->assertNotFound()->assertDontSee('Residencial Horizonte');
    }

    expect(ObraInvitation::query()->sole()->used_at)->toBeNull();

    foreach ([$unknownToken, $malformedToken, $validToken] as $secret) {
        assertTokenLeftNoTrace($secret);
    }
});

test('no convite route has a parameter and app/ never builds a convite URL with an argument (RF-38 static)', function () {
    $conviteRoutes = array_filter(
        Route::getRoutes()->getRoutes(),
        fn ($route): bool => str_starts_with($route->uri(), 'convite'),
    );

    expect($conviteRoutes)->not->toBeEmpty();

    foreach ($conviteRoutes as $route) {
        expect($route->parameterNames())->toBe([], $route->uri());
        expect($route->uri())->not->toContain('{');
    }

    $violations = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path(), FilesystemIterator::SKIP_DOTS));

    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $source = file_get_contents($file->getPathname());

        if (preg_match('/url\(\s*[\'"]\/?convite\//', $source)
            || preg_match('/route\(\s*[\'"]obra-invitation\.show[\'"]\s*,/', $source)) {
            $violations[] = $file->getPathname();
        }
    }

    expect($violations)->toBe([]);
});

test('the convite page has no #[Url] property and never puts a token into the session (RF-38 static)', function () {
    $code = implode('', array_map(
        fn (PhpToken $token): string => $token->text,
        array_filter(
            PhpToken::tokenize(file_get_contents(app_path('Livewire/Auth/ObraInvitationPage.php'))),
            fn (PhpToken $token): bool => ! $token->is([T_COMMENT, T_DOC_COMMENT]),
        ),
    ));

    expect($code)->not->toContain('#[Url')
        ->not->toContain('Livewire\\Attributes\\Url');

    foreach ((new ReflectionClass(ObraInvitationPage::class))->getProperties() as $property) {
        expect($property->getAttributes(Url::class))->toBe([]);
    }

    foreach ([
        app_path('Livewire/Auth/ObraInvitationPage.php'),
        app_path('Livewire/Auth/LoginForm.php'),
        app_path('Actions/Obras/AcceptObraInvitationAction.php'),
        app_path('Actions/Obras/GenerateObraInvitationAction.php'),
    ] as $path) {
        preg_match_all('/(?:session\(\)->(?:put|push|flash|now)|Session::(?:put|push|flash|now)|session\(\s*\[)[^;]*;/s', file_get_contents($path), $writes);

        foreach ($writes[0] as $write) {
            expect($write)->not->toMatch('/\$token\b|token_hash|hashToken|\$link\b|\[[\'"]url[\'"]\]/i', $path);
        }
    }
});

test('every $token parameter of the convite flow is marked #[\SensitiveParameter] (RF-38, RNF-01)', function () {
    $classes = array_map(
        fn (string $file): string => 'App\\Actions\\Obras\\'.basename($file, '.php'),
        glob(app_path('Actions/Obras/*.php')),
    );
    $classes[] = ObraInvitation::class;
    $classes[] = ObraInvitationPage::class;

    $tokenParameters = [];

    foreach ($classes as $class) {
        foreach ((new ReflectionClass($class))->getMethods() as $method) {
            if ($method->getDeclaringClass()->getName() !== $class) {
                continue;
            }

            foreach ($method->getParameters() as $parameter) {
                if ($parameter->getName() !== 'token') {
                    continue;
                }

                $tokenParameters[] = "{$class}::{$method->getName()}";

                expect($parameter->getAttributes(SensitiveParameter::class))
                    ->toHaveCount(1, "{$class}::{$method->getName()}(\$token) must carry #[\\SensitiveParameter]");
            }
        }
    }

    expect($tokenParameters)->toContain(
        ObraInvitationPage::class.'::lookup',
        'App\\Actions\\Obras\\AcceptObraInvitationAction::resolveByToken',
        ObraInvitation::class.'::hashToken',
    );
});

test('the convite page never shows the creator name or e-mail, in any state (RNF-08)', function () {
    $token = tokenLeakGenerateThroughObraScreen($this->creator, $this->obra);
    $creatorTraces = ['Gestora Criadora Sigilosa', 'criadora.sigilosa@example.com'];

    $pages = [
        'pending' => $this->get(route('obra-invitation.show'))->assertOk()->getContent(),
        'guest' => Livewire::test(ObraInvitationPage::class)->call('lookup', $token)->html(),
        'obra' => Livewire::actingAs(User::factory()->obra()->create())->test(ObraInvitationPage::class)->call('lookup', $token)->html(),
        'gestao' => Livewire::actingAs(User::factory()->gestao()->create())->test(ObraInvitationPage::class)->call('lookup', $token)->html(),
        'suprimentos' => Livewire::actingAs(User::factory()->suprimentos()->create())->test(ObraInvitationPage::class)->call('lookup', $token)->html(),
        'unavailable' => $this->get(route('obra-invitation.unavailable'))->assertNotFound()->getContent(),
    ];

    foreach ($pages as $state => $html) {
        foreach ($creatorTraces as $trace) {
            expect($html)->not->toContain($trace);
        }
    }

    expect($pages['guest'])->toContain('Residencial Horizonte');

    $view = file_get_contents(resource_path('views/livewire/auth/obra-invitation-page.blade.php'));

    expect($view)->not->toMatch('/creator|created_by|revoker/i');
    expect(file_get_contents(app_path('Livewire/Auth/ObraInvitationPage.php')))->not->toMatch('/creator|created_by/i');
});
