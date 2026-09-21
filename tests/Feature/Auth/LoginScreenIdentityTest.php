<?php

use App\Models\User;
use Illuminate\Support\Facades\Password;
use Symfony\Component\Finder\Finder;

/**
 * UI-15 / UI-16 / UI-24: the authentication experience is branded through
 * `config('app.name')` (APP_NAME="Albuquerque Engenharia" in phpunit.xml),
 * the primary action is institutional red, and the MC Inteligência signature
 * lives in the shared auth layout so every auth page inherits it. Etapa 9
 * (T28 — UI-16, UI-17, UI-18, UI-20, UI-25) adds the official logos: the
 * Albuquerque logo above the brand heading (proportion preserved by `w-auto`
 * plus intrinsic width/height) and the smaller MC logo beside the signature —
 * on the auth screens only, never on authenticated pages.
 */

/**
 * @return list<string> repository-relative paths
 */
function filesContainingLiteral(string $literal, array $directories): array
{
    $finder = (new Finder)->files()->in(array_map(fn (string $dir) => base_path($dir), $directories))->contains($literal);

    return array_values(array_map(
        fn (SplFileInfo $file) => str_replace('\\', '/', substr($file->getRealPath(), strlen(base_path()) + 1)),
        iterator_to_array($finder, false),
    ));
}

function submitButton(string $html, string $label): string
{
    preg_match('/<button[^>]*type="submit"[^>]*>\s*'.preg_quote($label, '/').'\s*<\/button>/s', $html, $button);

    expect($button)->not->toBeEmpty("submit button \"{$label}\" not found");

    return $button[0];
}

/**
 * The three auth pages besides login, each resolving to a renderable URL.
 *
 * @return array<string, string> page label => url
 */
function secondaryAuthPages(): array
{
    $user = User::factory()->create(['email' => 'ana@example.com']);

    return [
        'password.request' => route('password.request'),
        'password.reset' => route('password.reset', ['token' => Password::broker('users')->createToken($user), 'email' => $user->email]),
        'invite.show' => route('invite.show', ['token' => Password::broker('invites')->createToken($user), 'email' => $user->email]),
    ];
}

function technologySignature(string $html): string
{
    preg_match('/<p[^>]*data-technology-signature[^>]*>.*?<\/p>/s', $html, $signature);

    expect($signature)->not->toBeEmpty('signature <p data-technology-signature> not found');

    return $signature[0];
}

function albuquerqueLogo(string $html): string
{
    preg_match('/<img[^>]*images\/logo-albuquerque-simbolo\.png[^>]*>/', $html, $img);

    expect($img)->not->toBeEmpty('Albuquerque logo <img> not found');

    return $img[0];
}

function mcLogo(string $signature): string
{
    preg_match('/<img[^>]*images\/logo-mc\.png[^>]*>/', $signature, $img);

    expect($img)->not->toBeEmpty('MC logo <img> not found inside the signature element');

    return $img[0];
}

/**
 * @return list<string>
 */
function imgClasses(string $img): array
{
    preg_match('/class="([^"]*)"/', $img, $class);

    return preg_split('/\s+/', trim($class[1] ?? ''), -1, PREG_SPLIT_NO_EMPTY);
}

/**
 * Explicit `w-<n>`/`w-[..]` utilities (any breakpoint) that would fight the
 * intrinsic proportion when paired with a fixed height — `w-auto` is allowed.
 *
 * @param  list<string>  $classes
 * @return list<string>
 */
function fixedWidthClasses(array $classes): array
{
    return array_values(array_filter($classes, fn (string $class) => preg_match('/^(?:[a-z]+:)?w-(?!auto$)/', $class) === 1));
}

/**
 * Rendered height in px of the smallest-breakpoint `h-*` utility (4px scale).
 *
 * @param  list<string>  $classes
 */
function renderedHeightPx(array $classes): int
{
    foreach ($classes as $class) {
        if (preg_match('/^h-(\d+)$/', $class, $match)) {
            return (int) $match[1] * 4;
        }
    }

    throw new RuntimeException('no unprefixed h-<n> utility among: '.implode(' ', $classes));
}

test('the test environment names the application Albuquerque Engenharia through APP_NAME (UI-15)', function () {
    expect(config('app.name'))->toBe('Albuquerque Engenharia');
    expect(file_get_contents(base_path('phpunit.xml')))->toContain('<env name="APP_NAME" value="Albuquerque Engenharia"/>');
});

test('GET /login shows the §28 structure: brand, title, fields, recovery link, button and signature (UI-15)', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSeeInOrder([
            'Albuquerque Engenharia',
            'Entrar no sistema',
            'E-mail',
            'Senha',
            'Esqueci minha senha',
            'Entrar',
            'Tecnologia por MC Inteligência',
        ]);
});

test('the brand heading and <title> of the login page read config(app.name)', function () {
    $html = $this->get(route('login'))->assertOk()->getContent();

    expect($html)->toMatch('/<h1[^>]*>\s*Albuquerque Engenharia\s*<\/h1>/s')
        ->toContain('<title>Entrar - Albuquerque Engenharia</title>');
});

test('the submit button is the institutional primary action, never the old blue one (UI-09, UI-15)', function () {
    $html = $this->get(route('login'))->assertOk()->getContent();

    $button = submitButton($html, 'Entrar');

    expect($button)->toContain('btn-primary')
        ->not->toContain('sky-');

    expect($html)->not->toContain('sky-')
        ->not->toContain('bg-slate-900')
        ->not->toContain('bg-slate-800');
});

test('login inputs, labels, errors and recovery link use the token component classes (UI-10)', function () {
    $html = $this->get(route('login'))->assertOk()->getContent();

    expect($html)->toMatch('/<label for="email" class="form-label">/')
        ->toMatch('/<label for="password" class="form-label">/')
        ->toMatch('/<input id="email"[^>]*class="form-control"/')
        ->toMatch('/<input id="password"[^>]*class="form-control"/')
        ->toMatch('/<a[^>]*href="'.preg_quote(route('password.request'), '/').'"[^>]*class="[^"]*text-primary[^"]*"[^>]*>\s*Esqueci minha senha\s*<\/a>/s');

    expect(file_get_contents(resource_path('views/livewire/auth/login-form.blade.php')))
        ->toContain('role="alert" class="form-error"');
});

test('the signature sits in a footer position below the card, on the muted text token (UI-16)', function () {
    $html = $this->get(route('login'))->assertOk()->getContent();

    $signature = technologySignature($html);

    expect($signature)->toContain('text-text-muted');
    expect(strpos($html, 'Tecnologia por MC Inteligência'))->toBeGreaterThan(strpos($html, 'Entrar no sistema'));
    expect(substr_count($html, 'Tecnologia por MC Inteligência'))->toBe(1);
    // "MC Inteligência" appears exactly twice: the MC logo `alt` and the signature text.
    expect(substr_count($html, 'MC Inteligência'))->toBe(2);
});

test('password.request, password.reset and invite.show inherit the same h1 and signature (UI-24)', function () {
    foreach (secondaryAuthPages() as $page => $url) {
        $html = $this->get($url)->assertOk()->getContent();

        expect($html)->toMatch('/<h1[^>]*>\s*Albuquerque Engenharia\s*<\/h1>/s', "{$page} lacks the brand h1")
            ->toContain('Tecnologia por MC Inteligência')
            ->toContain('data-brand-logo-slot')
            ->toContain('<title>Entrar - Albuquerque Engenharia</title>')
            ->not->toContain('sky-');

        expect(substr_count($html, 'Tecnologia por MC Inteligência'))->toBe(1);
        expect(substr_count($html, 'MC Inteligência'))->toBe(2);
    }
});

test('the brand name is never hardcoded in views or application code (UI-15, Q-09a)', function () {
    expect(filesContainingLiteral('Albuquerque Engenharia', ['resources/views', 'app']))->toBe([]);
});

test('the logo assets are referenced only by the shared auth layout (UI-20, UI-25)', function () {
    expect(filesContainingLiteral('logo-albuquerque-simbolo', ['resources/views']))->toBe(['resources/views/auth/login.blade.php']);
    expect(filesContainingLiteral('logo-mc', ['resources/views']))->toBe(['resources/views/auth/login.blade.php']);
    expect(filesContainingLiteral('<img', ['resources/views/auth', 'resources/views/livewire/auth']))->toBe(['resources/views/auth/login.blade.php']);
    expect(filesContainingLiteral('images/logo-', ['resources/views/layouts', 'resources/views/livewire', 'resources/views/mail']))->toBe([]);
});

test('the Albuquerque logo sits above the brand heading with its proportion preserved (UI-17)', function () {
    $html = $this->get(route('login'))->assertOk()->getContent();

    $logo = albuquerqueLogo($html);

    expect($logo)->toContain('src="'.asset('images/logo-albuquerque-simbolo.png').'"')
        ->toContain('alt="Albuquerque Engenharia"')
        ->toContain('width="512"')
        ->toContain('height="512"')
        ->toContain('rounded-md');

    expect(imgClasses($logo))->toContain('w-auto')->toContain('h-20')->toContain('sm:h-24')->toContain('mb-4');
    expect(fixedWidthClasses(imgClasses($logo)))->toBe([]);

    expect(strpos($html, 'images/logo-albuquerque-simbolo.png'))->toBeLessThan(strpos($html, '<h1'));
    expect($html)->toMatch('/data-brand-logo-slot[^>]*>\s*<img[^>]*images\/logo-albuquerque-simbolo\.png/s');
});

test('the MC logo is smaller than the Albuquerque logo and shares the signature element (UI-16, UI-18)', function () {
    $html = $this->get(route('login'))->assertOk()->getContent();

    $signature = technologySignature($html);
    $mcLogo = mcLogo($signature);

    expect($mcLogo)->toContain('src="'.asset('images/logo-mc.png').'"')
        ->toContain('alt="MC Inteligência"')
        ->toContain('width="1305"')
        ->toContain('height="200"');

    expect(imgClasses($mcLogo))->toContain('h-4')->toContain('w-auto');
    expect(fixedWidthClasses(imgClasses($mcLogo)))->toBe([]);

    expect($signature)->toMatch('/<img[^>]*images\/logo-mc\.png[^>]*>\s*<span>\s*Tecnologia por MC Inteligência\s*<\/span>/s');

    // h-4 (16px) < h-14 (56px) mobile and < text-xs line-height × 2 (32px).
    expect(renderedHeightPx(imgClasses($mcLogo)))->toBeLessThan(renderedHeightPx(imgClasses(albuquerqueLogo($html))))
        ->toBeLessThan(2 * 16);
});

test('password.request, password.reset and invite.show render both logos (UI-20, UI-24)', function () {
    foreach (secondaryAuthPages() as $page => $url) {
        $html = $this->get($url)->assertOk()->getContent();

        expect(imgClasses(albuquerqueLogo($html)))->toContain('w-auto');
        expect(fixedWidthClasses(imgClasses(albuquerqueLogo($html))))->toBe([]);
        expect(mcLogo(technologySignature($html)))->toContain('images/logo-mc.png');
        expect(substr_count($html, 'images/logo-albuquerque-simbolo.png'))->toBe(1, "{$page} must render the Albuquerque logo exactly once");
        expect(substr_count($html, 'images/logo-mc.png'))->toBe(1, "{$page} must render the MC logo exactly once");
    }
});

test('authenticated pages show neither logo nor any MC reference (UI-16, UI-25)', function () {
    $gestao = User::factory()->gestao()->create();

    foreach (['gestao.dashboard', 'gestao.usuarios.index'] as $routeName) {
        $html = $this->actingAs($gestao)->get(route($routeName))->assertOk()->getContent();

        expect($html)->not->toContain('images/logo-albuquerque-simbolo.png')
            ->not->toContain('images/logo-mc.png')
            ->not->toContain('MC Inteligência')
            ->not->toContain('<img');
    }

    expect(file_get_contents(resource_path('views/layouts/app.blade.php')))
        ->not->toContain('<img')
        ->not->toContain('images/logo-')
        ->not->toContain('MC Inteligência');
});
