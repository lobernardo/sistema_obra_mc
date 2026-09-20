<?php

use App\Livewire\Auth\LoginForm;
use App\Livewire\Auth\ResetPassword;
use App\Models\User;
use App\Notifications\ResetPasswordPtBr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

const OLD_PASSWORD = 'senha-antiga-123';

const NEW_PASSWORD = 'nova-senha-forte-456';

const RESET_FAILURE = 'Este link é inválido ou expirou. Solicite um novo.';

function loginAttempt(string $email, string $password): Testable
{
    Auth::logout();

    return Livewire::test(LoginForm::class)
        ->set('email', $email)
        ->set('password', $password)
        ->call('authenticate');
}

beforeEach(function () {
    $this->user = User::factory()->obra()->create([
        'email' => 'ana@example.com',
        'password' => Hash::make(OLD_PASSWORD),
    ]);
    $this->originalHash = $this->user->password;
});

test('the reset page is a guest route named password.reset that pre-fills the e-mail from the link (CT-02, UI-24)', function () {
    $route = app('router')->getRoutes()->getByName('password.reset');

    expect($route)->not->toBeNull();
    expect($route->middleware())->toContain('guest');

    $token = Password::broker('users')->createToken($this->user);

    $this->get(route('password.reset', ['token' => $token, 'email' => 'ana@example.com']))
        ->assertOk()
        ->assertSee('Redefinir senha')
        ->assertSee('Nova senha')
        ->assertSee('Confirmar nova senha')
        ->assertSee('Voltar ao login');

    Livewire::withQueryParams(['email' => 'ana@example.com'])
        ->test(ResetPassword::class, ['token' => $token])
        ->assertSet('token', $token)
        ->assertSet('email', 'ana@example.com');

    $this->actingAs(User::factory()->gestao()->create());

    $this->get(route('password.reset', ['token' => $token]))->assertRedirect();
});

test('a valid token from the recovery e-mail resets the password, the old one stops working and the token is consumed (TC-13, TC-14, RF-22)', function () {
    Notification::fake();

    Password::broker('users')->sendResetLink(['email' => 'ana@example.com']);

    $token = null;
    Notification::assertSentTo($this->user, ResetPasswordPtBr::class, function (ResetPasswordPtBr $notification) use (&$token) {
        $token = $notification->token;

        return true;
    });

    Livewire::test(ResetPassword::class, ['token' => $token])
        ->set('email', 'ana@example.com')
        ->set('password', NEW_PASSWORD)
        ->set('password_confirmation', NEW_PASSWORD)
        ->call('resetPassword')
        ->assertHasNoErrors()
        ->assertRedirect(route('login'));

    expect(session('status'))->toBe('Senha redefinida. Entre com a nova senha.');

    $this->user->refresh();

    expect(Hash::check(NEW_PASSWORD, $this->user->password))->toBeTrue();
    expect(Hash::check(OLD_PASSWORD, $this->user->password))->toBeFalse();
    expect($this->user->password)->not->toBe($this->originalHash);
    expect(DB::table('password_reset_tokens')->where('email', 'ana@example.com')->exists())->toBeFalse();

    loginAttempt('ana@example.com', OLD_PASSWORD)
        ->assertHasErrors(['email'])
        ->assertSee('E-mail ou senha inválidos.');
    expect(Auth::check())->toBeFalse();

    loginAttempt('ana@example.com', NEW_PASSWORD)->assertRedirect(route('home'));
    expect(Auth::id())->toBe($this->user->id);

    Auth::logout();

    Livewire::test(ResetPassword::class, ['token' => $token])
        ->set('email', 'ana@example.com')
        ->set('password', 'outra-senha-789')
        ->set('password_confirmation', 'outra-senha-789')
        ->call('resetPassword')
        ->assertHasErrors(['email'])
        ->assertSee(RESET_FAILURE)
        ->assertNoRedirect();

    expect(Hash::check(NEW_PASSWORD, $this->user->fresh()->password))->toBeTrue();
});

test('a tampered token or a mismatched e-mail is refused with the generic error and the hash is untouched (TC-11, RF-23)', function () {
    $token = Password::broker('users')->createToken($this->user);

    Livewire::test(ResetPassword::class, ['token' => $token.'x'])
        ->set('email', 'ana@example.com')
        ->set('password', NEW_PASSWORD)
        ->set('password_confirmation', NEW_PASSWORD)
        ->call('resetPassword')
        ->assertHasErrors(['email'])
        ->assertSee(RESET_FAILURE)
        ->assertNoRedirect();

    $unknownEmail = Livewire::test(ResetPassword::class, ['token' => $token])
        ->set('email', 'ninguem@example.com')
        ->set('password', NEW_PASSWORD)
        ->set('password_confirmation', NEW_PASSWORD)
        ->call('resetPassword')
        ->assertHasErrors(['email'])
        ->assertSee(RESET_FAILURE)
        ->assertNoRedirect()
        ->html();

    $otherUsersToken = Livewire::test(ResetPassword::class, ['token' => $token])
        ->set('email', User::factory()->suprimentos()->create(['email' => 'outra@example.com'])->email)
        ->set('password', NEW_PASSWORD)
        ->set('password_confirmation', NEW_PASSWORD)
        ->call('resetPassword')
        ->assertHasErrors(['email'])
        ->assertSee(RESET_FAILURE)
        ->assertNoRedirect()
        ->html();

    expect(substr_count($unknownEmail, RESET_FAILURE))->toBe(substr_count($otherUsersToken, RESET_FAILURE));

    expect($this->user->fresh()->password)->toBe($this->originalHash);
    expect(Hash::check(OLD_PASSWORD, $this->user->fresh()->password))->toBeTrue();
});

test('a token older than 60 minutes is expired while a younger one still works (TC-12, RNF-01)', function () {
    $token = Password::broker('users')->createToken($this->user);

    $this->travel(61)->minutes();

    Livewire::test(ResetPassword::class, ['token' => $token])
        ->set('email', 'ana@example.com')
        ->set('password', NEW_PASSWORD)
        ->set('password_confirmation', NEW_PASSWORD)
        ->call('resetPassword')
        ->assertHasErrors(['email'])
        ->assertSee(RESET_FAILURE);

    expect($this->user->fresh()->password)->toBe($this->originalHash);

    $this->travelBack();

    $fresh = Password::broker('users')->createToken($this->user);

    $this->travel(59)->minutes();

    Livewire::test(ResetPassword::class, ['token' => $fresh])
        ->set('email', 'ana@example.com')
        ->set('password', NEW_PASSWORD)
        ->set('password_confirmation', NEW_PASSWORD)
        ->call('resetPassword')
        ->assertHasNoErrors()
        ->assertRedirect(route('login'));

    expect(Hash::check(NEW_PASSWORD, $this->user->fresh()->password))->toBeTrue();
});

test('a short password or a diverging confirmation produces PT-BR field errors and keeps the token usable (RNF-06)', function () {
    $token = Password::broker('users')->createToken($this->user);

    Livewire::test(ResetPassword::class, ['token' => $token])
        ->set('email', 'ana@example.com')
        ->set('password', 'curta')
        ->set('password_confirmation', 'curta')
        ->call('resetPassword')
        ->assertHasErrors(['password'])
        ->assertSee('A senha deve ter pelo menos 8 caracteres.')
        ->assertNoRedirect();

    Livewire::test(ResetPassword::class, ['token' => $token])
        ->set('email', 'ana@example.com')
        ->set('password', NEW_PASSWORD)
        ->set('password_confirmation', 'outra-coisa-999')
        ->call('resetPassword')
        ->assertHasErrors(['password' => 'confirmed'])
        ->assertSee('A confirmação não confere com a senha.')
        ->assertNoRedirect();

    Livewire::test(ResetPassword::class, ['token' => $token])
        ->set('email', 'ana@example.com')
        ->set('password', '')
        ->set('password_confirmation', '')
        ->call('resetPassword')
        ->assertHasErrors(['password' => 'required'])
        ->assertSee('Informe a nova senha.');

    expect($this->user->fresh()->password)->toBe($this->originalHash);
    expect(Password::broker('users')->tokenExists($this->user, $token))->toBeTrue();
});

test('the reset works for obra, suprimentos and gestao users (RF-24)', function (string $role) {
    $user = User::factory()->{$role}()->create([
        'email' => "{$role}@example.com",
        'password' => Hash::make(OLD_PASSWORD),
    ]);

    $token = Password::broker('users')->createToken($user);

    Livewire::test(ResetPassword::class, ['token' => $token])
        ->set('email', "{$role}@example.com")
        ->set('password', NEW_PASSWORD)
        ->set('password_confirmation', NEW_PASSWORD)
        ->call('resetPassword')
        ->assertHasNoErrors()
        ->assertRedirect(route('login'));

    expect(Hash::check(NEW_PASSWORD, $user->fresh()->password))->toBeTrue();

    loginAttempt("{$role}@example.com", NEW_PASSWORD)->assertRedirect(route('home'));
    expect(Auth::id())->toBe($user->id);
})->with(['obra', 'suprimentos', 'gestao']);

test('no reset response ever contains the submitted password or a hash', function () {
    $token = Password::broker('users')->createToken($this->user);

    $success = Livewire::test(ResetPassword::class, ['token' => $token])
        ->set('email', 'ana@example.com')
        ->set('password', NEW_PASSWORD)
        ->set('password_confirmation', NEW_PASSWORD)
        ->call('resetPassword')
        ->html();

    $failure = Livewire::test(ResetPassword::class, ['token' => 'invalido'])
        ->set('email', 'ana@example.com')
        ->set('password', NEW_PASSWORD)
        ->set('password_confirmation', NEW_PASSWORD)
        ->call('resetPassword')
        ->html();

    foreach ([$success, $failure] as $html) {
        expect($html)->not->toContain(NEW_PASSWORD)
            ->not->toContain(OLD_PASSWORD)
            ->not->toContain('$2y$');
    }
});
