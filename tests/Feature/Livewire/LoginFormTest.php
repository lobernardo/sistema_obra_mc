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
