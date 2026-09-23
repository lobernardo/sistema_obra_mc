<?php

use App\Enums\AuthenticationEventType;
use App\Enums\RoleSlug;
use App\Livewire\Auth\LoginForm;
use App\Livewire\Auth\Register;
use App\Models\AccountRegistrationEvent;
use App\Models\AuthenticationEvent;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

const REGISTER_UI_PASSWORD = 'senha-forte-123';

const REGISTER_UI_NOTICE = 'Conta criada. O acesso às obras depende de associação feita pela Gestão ou por Suprimentos.';

beforeEach(function () {
    Role::factory()->obra()->create();
});

/**
 * Drives one Novo Cadastro submission through the component (same client
 * IP for every call — `Livewire::test` always uses `127.0.0.1`).
 */
function submitNovoCadastro(string $email, string $name = 'Ana Souza', string $password = REGISTER_UI_PASSWORD, ?string $confirmation = null): Testable
{
    return Livewire::test(Register::class)
        ->set('name', $name)
        ->set('email', $email)
        ->set('password', $password)
        ->set('password_confirmation', $confirmation ?? $password)
        ->call('register');
}

test('the login screen offers a keyboard-focusable "Novo Cadastro" link-button to /cadastro below "Entrar" (UI-01)', function () {
    $html = $this->get(route('login'))->assertOk()->assertSee('Novo Cadastro')->getContent();

    expect(route('register'))->toEndWith('/cadastro');
    expect($html)->toMatch('/<a[^>]*href="'.preg_quote(route('register'), '/').'"[^>]*class="[^"]*btn-secondary[^"]*"[^>]*>\s*Novo Cadastro\s*<\/a>/s');
    expect(strpos($html, 'Novo Cadastro'))->toBeGreaterThan(strpos($html, 'Entrar</'));
});

test('the Novo Cadastro screen has exactly the 4 labelled inputs, no select or radio, and a link back to login (UI-02)', function () {
    $html = $this->get(route('register'))->assertOk()->getContent();

    preg_match_all('/<input\b[^>]*>/i', $html, $inputs);
    $inputs = array_values(array_filter($inputs[0], fn (string $input): bool => ! str_contains($input, 'type="hidden"')));

    expect($inputs)->toHaveCount(4);

    foreach (['name', 'email', 'password', 'password_confirmation'] as $field) {
        expect($html)->toContain('wire:model="'.$field.'"');
        expect($html)->toMatch('/<label[^>]*for="'.$field.'"/');
    }

    expect($html)
        ->not->toMatch('/<select\b/i')
        ->not->toContain('type="radio"')
        ->not->toContain('role_id')
        ->not->toContain('obra_id')
        ->toContain('Nome')
        ->toContain('Confirmação de senha')
        ->toContain('href="'.route('login').'"');
});

test('the component exposes exactly the 4 public properties, so a forged role_id has nothing to bind to (RF-17)', function () {
    $properties = array_map(
        fn (ReflectionProperty $property): string => $property->getName(),
        array_filter(
            (new ReflectionClass(Register::class))->getProperties(ReflectionProperty::IS_PUBLIC),
            fn (ReflectionProperty $property): bool => $property->getDeclaringClass()->getName() === Register::class,
        ),
    );

    expect(array_values($properties))->toBe(['name', 'email', 'password', 'password_confirmation']);

    expect(fn () => Livewire::test(Register::class)->set('role_id', 1))->toThrow(Exception::class);
});

test('a successful signup authenticates, regenerates the session, records one login_success and redirects to /home (RF-21)', function () {
    $sessionIdBefore = session()->getId();

    submitNovoCadastro('  Ana@Example.com ')
        ->assertHasNoErrors()
        ->assertRedirect(route('home'));

    $user = User::query()->sole();

    expect(Auth::id())->toBe($user->id);
    expect(session()->getId())->not->toBe($sessionIdBefore);
    expect($user->email)->toBe('ana@example.com');
    expect($user->role->slug)->toBe(RoleSlug::Obra->value);
    expect(DB::table('obra_profile')->count())->toBe(0);
    expect(AuthenticationEvent::query()->where('user_id', $user->id)->where('event', AuthenticationEventType::LoginSuccess)->count())->toBe(1);
    expect(AccountRegistrationEvent::query()->where('user_id', $user->id)->count())->toBe(1);
    expect(session('obra.registration_notice'))->toBeTrue();
});

test('the registration notice is shown once on /obra/pedidos after /home (RF-21)', function () {
    submitNovoCadastro('ana@example.com')->assertRedirect(route('home'));

    $this->get(route('home'))->assertRedirect(route('obra.pedidos.index'));

    $this->get(route('obra.pedidos.index'))->assertOk()->assertSee(REGISTER_UI_NOTICE);

    $this->get(route('obra.pedidos.index'))->assertOk()->assertDontSee(REGISTER_UI_NOTICE);
});

test('a duplicate e-mail shows the RF-20 message, creates no user and resets the password fields', function () {
    User::factory()->obra()->create(['email' => 'ana@example.com']);

    submitNovoCadastro('ANA@example.com ')
        ->assertHasErrors(['email'])
        ->assertSee('Já existe uma conta com este e-mail. Entre ou use Esqueci minha senha.')
        ->assertSet('password', '')
        ->assertSet('password_confirmation', '')
        ->assertNoRedirect();

    expect(User::query()->count())->toBe(1);
    expect(Auth::check())->toBeFalse();
});

test('the 4th submission for one e-mail + IP within 10 min is refused without a user and accepted after the window (RF-19)', function () {
    for ($attempt = 1; $attempt <= 3; $attempt++) {
        submitNovoCadastro('ana@example.com', password: 'curta')->assertHasErrors(['password']);
    }

    submitNovoCadastro('ana@example.com')
        ->assertHasErrors(['email'])
        ->assertSee(LoginForm::THROTTLED_MESSAGE)
        ->assertNoRedirect();

    expect(User::query()->count())->toBe(0);
    expect(Auth::check())->toBeFalse();

    $this->travel(11)->minutes();

    submitNovoCadastro('ana@example.com')->assertHasNoErrors()->assertRedirect(route('home'));

    expect(User::query()->count())->toBe(1);
});

test('the 11th submission from one IP within 1 hour is refused whatever the e-mail (RF-19)', function () {
    for ($attempt = 1; $attempt <= 10; $attempt++) {
        submitNovoCadastro("pessoa-{$attempt}@example.com", password: 'curta')->assertHasErrors(['password']);
    }

    submitNovoCadastro('nova@example.com')
        ->assertHasErrors(['email'])
        ->assertSee(LoginForm::THROTTLED_MESSAGE);

    expect(User::query()->count())->toBe(0);
});

test('duplicate submissions count toward the limiter (RF-20)', function () {
    User::factory()->obra()->create(['email' => 'ana@example.com']);

    for ($attempt = 1; $attempt <= 3; $attempt++) {
        submitNovoCadastro('ana@example.com')->assertSee('Já existe uma conta com este e-mail. Entre ou use Esqueci minha senha.');
    }

    submitNovoCadastro('ana@example.com')->assertSee(LoginForm::THROTTLED_MESSAGE);
});

test('an authenticated user cannot open /cadastro (guest)', function () {
    $user = User::factory()->obra()->create();

    $this->actingAs($user)->get(route('register'))->assertRedirect();
});
