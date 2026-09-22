<?php

use App\Actions\Usuarios\CreateUserAction;
use App\Enums\RoleSlug;
use App\Livewire\Auth\AcceptInvite;
use App\Livewire\Auth\LoginForm;
use App\Models\Obra;
use App\Models\Role;
use App\Models\User;
use App\Notifications\FirstAccessInvite;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

const INVITE_PASSWORD = 'primeira-senha-2026';

const INVITE_FAILURE = 'Este link é inválido ou expirou. Peça um novo convite à Gestão.';

/**
 * Creates a user through the real action (as Gestão does) and returns the
 * created user together with the raw token carried by the invite e-mail.
 *
 * @return array{user: User, token: string}
 */
function inviteUser(User $actor, string $email, string $role = 'suprimentos'): array
{
    $roleId = Role::query()->firstOrCreate(['slug' => RoleSlug::from($role)->value], ['name' => ucfirst($role)])->id;

    $data = ['name' => 'Convidado '.ucfirst($role), 'email' => $email, 'role_id' => $roleId];

    if ($role === 'obra') {
        $data['obra_ids'] = [Obra::factory()->create()->id];
    }

    $result = app(CreateUserAction::class)->execute($actor, $data);

    expect($result['invite_sent'])->toBeTrue();

    $token = null;
    Notification::assertSentTo($result['user'], FirstAccessInvite::class, function (FirstAccessInvite $notification) use (&$token, $result) {
        $token = $notification->token;

        $url = $notification->toMail($result['user'])->actionUrl;

        expect($url)->toBe(route('invite.show', ['token' => $token, 'email' => $result['user']->email]));

        return true;
    });

    return ['user' => $result['user'], 'token' => $token];
}

function acceptInviteWith(string $token, string $email, string $password = INVITE_PASSWORD): Testable
{
    return Livewire::test(AcceptInvite::class, ['token' => $token])
        ->set('email', $email)
        ->set('password', $password)
        ->set('password_confirmation', $password)
        ->call('acceptInvite');
}

beforeEach(function () {
    Notification::fake();

    $this->gestao = User::factory()->gestao()->create();
});

test('the invite page is a guest route named invite.show with first-access copy (CT-03, UI-24)', function () {
    $route = app('router')->getRoutes()->getByName('invite.show');

    expect($route)->not->toBeNull();
    expect($route->middleware())->toContain('guest');

    ['token' => $token] = inviteUser($this->gestao, 'nova@example.com');

    $this->get(route('invite.show', ['token' => $token, 'email' => 'nova@example.com']))
        ->assertOk()
        ->assertSee('Defina sua senha')
        ->assertSee('Bem-vindo(a) ao '.config('app.name'))
        ->assertSee('Definir senha')
        ->assertSeeHtml('<meta name="csrf-token"');

    Livewire::withQueryParams(['email' => 'nova@example.com'])
        ->test(AcceptInvite::class, ['token' => $token])
        ->assertSet('token', $token)
        ->assertSet('email', 'nova@example.com');

    $this->actingAs($this->gestao);

    $this->get(route('invite.show', ['token' => $token]))->assertRedirect();
});

test('a user created by gestao opens the invite link, defines the password and logs in (TC-16, RF-15, RF-16)', function (string $role) {
    ['user' => $user, 'token' => $token] = inviteUser($this->gestao, "{$role}@example.com", $role);

    $randomHash = $user->password;

    expect(DB::table('password_reset_tokens')->where('email', "{$role}@example.com")->exists())->toBeTrue();

    acceptInviteWith($token, "{$role}@example.com")
        ->assertHasNoErrors()
        ->assertRedirect(route('login'));

    expect(session('status'))->toBe('Senha definida. Entre com seu e-mail e a nova senha.');

    $user->refresh();

    expect(Hash::check(INVITE_PASSWORD, $user->password))->toBeTrue();
    expect($user->password)->not->toBe($randomHash);
    expect(DB::table('password_reset_tokens')->where('email', "{$role}@example.com")->exists())->toBeFalse();

    Livewire::test(LoginForm::class)
        ->set('email', "{$role}@example.com")
        ->set('password', INVITE_PASSWORD)
        ->call('authenticate')
        ->assertRedirect(route('home'));

    expect(Auth::id())->toBe($user->id);
})->with(['obra', 'suprimentos', 'gestao']);

test('a tampered or already consumed token is refused with the generic error without revealing the e-mail (TC-11, RF-17)', function () {
    ['user' => $user, 'token' => $token] = inviteUser($this->gestao, 'nova@example.com');
    $randomHash = $user->password;

    $tampered = acceptInviteWith($token.'x', 'nova@example.com')
        ->assertHasErrors(['email'])
        ->assertSee(INVITE_FAILURE)
        ->assertNoRedirect()
        ->html();

    $unknownEmail = acceptInviteWith($token, 'ninguem@example.com')
        ->assertHasErrors(['email'])
        ->assertSee(INVITE_FAILURE)
        ->assertNoRedirect()
        ->html();

    expect(substr_count($tampered, INVITE_FAILURE))->toBe(substr_count($unknownEmail, INVITE_FAILURE));
    expect($user->fresh()->password)->toBe($randomHash);

    acceptInviteWith($token, 'nova@example.com')->assertHasNoErrors()->assertRedirect(route('login'));

    acceptInviteWith($token, 'nova@example.com', 'segunda-tentativa-789')
        ->assertHasErrors(['email'])
        ->assertSee(INVITE_FAILURE)
        ->assertNoRedirect();

    expect(Hash::check(INVITE_PASSWORD, $user->fresh()->password))->toBeTrue();
    expect(Hash::check('segunda-tentativa-789', $user->fresh()->password))->toBeFalse();
});

test('the invite token lives 72 hours: valid after 71 h, expired after 73 h (TC-12, RNF-01)', function () {
    ['user' => $user, 'token' => $token] = inviteUser($this->gestao, 'nova@example.com');
    $randomHash = $user->password;

    $this->travel(73)->hours();

    acceptInviteWith($token, 'nova@example.com')
        ->assertHasErrors(['email'])
        ->assertSee(INVITE_FAILURE);

    expect($user->fresh()->password)->toBe($randomHash);

    $this->travelBack();

    Notification::fake();
    $this->travel(61)->seconds();

    ['token' => $fresh] = inviteUser($this->gestao, 'outra@example.com');

    $this->travel(71)->hours();

    acceptInviteWith($fresh, 'outra@example.com')
        ->assertHasNoErrors()
        ->assertRedirect(route('login'));

    expect(Hash::check(INVITE_PASSWORD, User::query()->where('email', 'outra@example.com')->firstOrFail()->password))->toBeTrue();
});

test('both brokers share one token row per e-mail: a users-broker token is honoured on the invite page within 72 h (documented RNF-01 trade-off)', function () {
    $user = User::factory()->obra()->create(['email' => 'ana@example.com']);
    $randomHash = $user->password;

    $resetToken = Password::broker('users')->createToken($user);

    $this->travel(61)->minutes();

    expect(Password::broker('users')->tokenExists($user, $resetToken))->toBeFalse();
    expect(Password::broker('invites')->tokenExists($user, $resetToken))->toBeTrue();

    acceptInviteWith($resetToken, 'ana@example.com')
        ->assertHasNoErrors()
        ->assertRedirect(route('login'));

    expect(Hash::check(INVITE_PASSWORD, $user->fresh()->password))->toBeTrue();
    expect($user->fresh()->password)->not->toBe($randomHash);
    expect(DB::table('password_reset_tokens')->where('email', 'ana@example.com')->exists())->toBeFalse();

    $this->travel(72)->hours();

    $inviteToken = Password::broker('invites')->createToken($user);

    $this->travel(73)->hours();

    expect(Password::broker('invites')->tokenExists($user, $inviteToken))->toBeFalse();
    expect(Password::broker('users')->tokenExists($user, $inviteToken))->toBeFalse();
});

test('weak or diverging passwords produce PT-BR field errors and leave the invite usable (RNF-06)', function () {
    ['user' => $user, 'token' => $token] = inviteUser($this->gestao, 'nova@example.com');
    $randomHash = $user->password;

    acceptInviteWith($token, 'nova@example.com', 'curta')
        ->assertHasErrors(['password'])
        ->assertSee('A senha deve ter pelo menos 8 caracteres.')
        ->assertNoRedirect();

    Livewire::test(AcceptInvite::class, ['token' => $token])
        ->set('email', 'nova@example.com')
        ->set('password', INVITE_PASSWORD)
        ->set('password_confirmation', 'diferente-123456')
        ->call('acceptInvite')
        ->assertHasErrors(['password' => 'confirmed'])
        ->assertSee('A confirmação não confere com a senha.')
        ->assertNoRedirect();

    expect($user->fresh()->password)->toBe($randomHash);
    expect(Password::broker('invites')->tokenExists($user, $token))->toBeTrue();
});

test('no invite response contains the defined password or a hash (RF-18)', function () {
    ['user' => $user, 'token' => $token] = inviteUser($this->gestao, 'nova@example.com');

    $page = $this->get(route('invite.show', ['token' => $token, 'email' => 'nova@example.com']))->getContent();
    $failure = acceptInviteWith('invalido', 'nova@example.com')->html();
    $success = acceptInviteWith($token, 'nova@example.com')->html();

    foreach ([$page, $failure, $success] as $html) {
        expect($html)->not->toContain(INVITE_PASSWORD)->not->toContain('$2y$')->not->toContain($user->fresh()->password);
    }
});

test('an invite link carrying a mixed-case e-mail pre-fills the canonical value (RF-04)', function () {
    ['token' => $token] = inviteUser($this->gestao, 'marcelo@example.com');

    Livewire::withQueryParams(['email' => '  Marcelo@Example.COM '])
        ->test(AcceptInvite::class, ['token' => $token])
        ->assertSet('email', 'marcelo@example.com');
});

test('an invite link carrying a mixed-case e-mail completes the flow and records the canonical address (RF-04)', function () {
    ['user' => $user, 'token' => $token] = inviteUser($this->gestao, 'marcelo@example.com');
    $randomHash = $user->password;

    Livewire::withQueryParams(['email' => 'Marcelo@Example.com'])
        ->test(AcceptInvite::class, ['token' => $token])
        ->set('password', INVITE_PASSWORD)
        ->set('password_confirmation', INVITE_PASSWORD)
        ->call('acceptInvite')
        ->assertHasNoErrors()
        ->assertRedirect(route('login'));

    expect(Hash::check(INVITE_PASSWORD, $user->fresh()->password))->toBeTrue();
    expect($user->fresh()->password)->not->toBe($randomHash);
    expect(DB::table('password_reset_tokens')->where('email', 'marcelo@example.com')->exists())->toBeFalse();

    $row = DB::table('authentication_events')->where('event', 'password_defined')->sole();

    expect($row->email)->toBe('marcelo@example.com');
    expect($row->user_id)->toBe($user->id);
});

test('a mixed-case e-mail typed into the editable field still reaches the broker canonically (RF-04)', function () {
    ['user' => $user, 'token' => $token] = inviteUser($this->gestao, 'marcelo@example.com');

    acceptInviteWith($token, ' MARCELO@Example.COM ')
        ->assertHasNoErrors()
        ->assertRedirect(route('login'));

    expect(Hash::check(INVITE_PASSWORD, $user->fresh()->password))->toBeTrue();

    Auth::logout();

    Livewire::test(LoginForm::class)
        ->set('email', 'marcelo@example.com')
        ->set('password', INVITE_PASSWORD)
        ->call('authenticate')
        ->assertHasNoErrors();

    expect(Auth::id())->toBe($user->id);
});
