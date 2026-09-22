<?php

use App\Livewire\Auth\LoginForm;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

test('the login screen renders without error', function () {
    $this->get(route('login'))->assertOk();

    Livewire::test(LoginForm::class)
        ->assertSet('email', '')
        ->assertSet('password', '')
        ->assertSee('E-mail')
        ->assertSee('Senha')
        ->assertSee('Entrar');
});

test('a valid demonstration user authenticates through the component', function () {
    $user = User::factory()->obra()->create([
        'email' => 'demo-obra@example.com',
        'password' => Hash::make('demo-password'),
    ]);

    Livewire::test(LoginForm::class)
        ->set('email', 'demo-obra@example.com')
        ->set('password', 'demo-password')
        ->call('authenticate')
        ->assertRedirect(route('home'));

    expect(Auth::id())->toBe($user->id);
});

test('the login screen offers the "Esqueci minha senha" link to the recovery page (RF-19, UI-15)', function () {
    $html = $this->get(route('login'))->assertOk()->assertSee('Esqueci minha senha')->getContent();

    expect($html)->toMatch('/<a[^>]*href="'.preg_quote(route('password.request'), '/').'"[^>]*>\s*Esqueci minha senha\s*<\/a>/');

    Livewire::test(LoginForm::class)->assertSee('Esqueci minha senha')->assertSee(route('password.request'));
});

test('the component normalizes the e-mail before validating, independently of TrimStrings (RF-12)', function () {
    $user = User::factory()->obra()->create([
        'email' => 'demo-obra@example.com',
        'password' => Hash::make('demo-password'),
    ]);

    Livewire::test(LoginForm::class)
        ->set('email', "  Demo-Obra@Example.COM\t")
        ->set('password', 'demo-password')
        ->call('authenticate')
        ->assertSet('email', 'demo-obra@example.com')
        ->assertHasNoErrors()
        ->assertRedirect(route('home'));

    expect(Auth::id())->toBe($user->id);
});

test('a tripped login limiter renders one PT-BR error on the e-mail field, in the same channel as bad credentials (UI-02)', function () {
    User::factory()->obra()->create([
        'email' => 'demo-obra@example.com',
        'password' => Hash::make('demo-password'),
    ]);

    for ($attempt = 1; $attempt <= 5; $attempt++) {
        Livewire::test(LoginForm::class)
            ->set('email', 'demo-obra@example.com')
            ->set('password', 'senha-errada')
            ->call('authenticate')
            ->assertHasErrors(['email'])
            ->assertHasNoErrors(['password'])
            ->assertSeeHtml('role="alert"')
            ->assertSee('E-mail ou senha inválidos.');
    }

    Livewire::test(LoginForm::class)
        ->set('email', 'demo-obra@example.com')
        ->set('password', 'demo-password')
        ->call('authenticate')
        ->assertHasErrors(['email'])
        ->assertHasNoErrors(['password'])
        ->assertSeeHtml('role="alert"')
        ->assertSee(LoginForm::THROTTLED_MESSAGE)
        ->assertDontSee('E-mail ou senha inválidos.')
        ->assertNoRedirect();

    expect(Auth::check())->toBeFalse();
});
