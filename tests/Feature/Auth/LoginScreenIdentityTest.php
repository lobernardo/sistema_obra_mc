<?php

use App\Models\User;
use Illuminate\Support\Facades\Password;
use Symfony\Component\Finder\Finder;

/**
 * UI-15 / UI-16 / UI-24: the authentication experience is branded through
 * `config('app.name')` (APP_NAME="Albuquerque Engenharia" in phpunit.xml),
 * the primary action is institutional red, and the text-only MC Inteligência
 * signature lives in the shared auth layout so every auth page inherits it.
 * Logos are deliberately absent until Etapa 9 (UI-20).
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

    preg_match('/<p[^>]*data-technology-signature[^>]*>\s*Tecnologia por MC Inteligência\s*<\/p>/s', $html, $signature);

    expect($signature)->not->toBeEmpty();
    expect($signature[0])->toContain('text-text-muted');
    expect(strpos($html, 'Tecnologia por MC Inteligência'))->toBeGreaterThan(strpos($html, 'Entrar no sistema'));
    expect(substr_count($html, 'MC Inteligência'))->toBe(1);
});

test('password.request, password.reset and invite.show inherit the same h1 and signature (UI-24)', function () {
    foreach (secondaryAuthPages() as $page => $url) {
        $html = $this->get($url)->assertOk()->getContent();

        expect($html)->toMatch('/<h1[^>]*>\s*Albuquerque Engenharia\s*<\/h1>/s', "{$page} lacks the brand h1")
            ->toContain('Tecnologia por MC Inteligência')
            ->toContain('data-brand-logo-slot')
            ->toContain('<title>Entrar - Albuquerque Engenharia</title>')
            ->not->toContain('sky-');

        expect(substr_count($html, 'MC Inteligência'))->toBe(1);
    }
});

test('the brand name is never hardcoded in views or application code (UI-15, Q-09a)', function () {
    expect(filesContainingLiteral('Albuquerque Engenharia', ['resources/views', 'app']))->toBe([]);
});

test('no view references the logo assets before Etapa 9 (UI-20)', function () {
    expect(filesContainingLiteral('logo-albuquerque', ['resources/views']))->toBe([]);
    expect(filesContainingLiteral('logo-mc', ['resources/views']))->toBe([]);
    expect(filesContainingLiteral('<img', ['resources/views/auth', 'resources/views/livewire/auth']))->toBe([]);
});
