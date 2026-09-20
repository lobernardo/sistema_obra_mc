<?php

use App\Livewire\Auth\LoginForm;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

test('valid credentials authenticate and create a session', function () {
    $user = User::factory()->create([
        'email' => 'demo@example.com',
        'password' => Hash::make('correct-password'),
    ]);

    Livewire::test(LoginForm::class)
        ->set('email', 'demo@example.com')
        ->set('password', 'correct-password')
        ->call('authenticate')
        ->assertRedirect(route('home'));

    expect(Auth::check())->toBeTrue();
    expect(Auth::id())->toBe($user->id);
});

test('an invalid password does not authenticate', function () {
    User::factory()->create([
        'email' => 'demo@example.com',
        'password' => Hash::make('correct-password'),
    ]);

    Livewire::test(LoginForm::class)
        ->set('email', 'demo@example.com')
        ->set('password', 'wrong-password')
        ->call('authenticate')
        ->assertHasErrors('email');

    expect(Auth::check())->toBeFalse();
});

test('a deactivated user does not authenticate even with valid credentials', function () {
    User::factory()->create([
        'email' => 'inactive@example.com',
        'password' => Hash::make('correct-password'),
        'is_active' => false,
    ]);

    Livewire::test(LoginForm::class)
        ->set('email', 'inactive@example.com')
        ->set('password', 'correct-password')
        ->call('authenticate')
        ->assertHasErrors('email');

    expect(Auth::check())->toBeFalse();
});

test('an unknown email does not authenticate', function () {
    Livewire::test(LoginForm::class)
        ->set('email', 'nobody@example.com')
        ->set('password', 'whatever-password')
        ->call('authenticate')
        ->assertHasErrors('email');

    expect(Auth::check())->toBeFalse();
});

test('email and password are required', function () {
    Livewire::test(LoginForm::class)
        ->set('email', '')
        ->set('password', '')
        ->call('authenticate')
        ->assertHasErrors(['email' => 'required', 'password' => 'required']);

    expect(Auth::check())->toBeFalse();
});

test('the stored password is hashed, never plaintext', function () {
    $user = User::factory()->create(['password' => Hash::make('correct-password')]);

    expect($user->password)->not->toBe('correct-password');
    expect(Hash::check('correct-password', $user->password))->toBeTrue();
});

test('logout terminates the authenticated session', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('logout'))
        ->assertRedirect(route('login'));

    expect(Auth::check())->toBeFalse();
});
