<?php

use App\Actions\Obras\AcceptObraInvitationAction;
use App\Actions\Obras\GenerateObraInvitationAction;
use App\Actions\Obras\RevokeObraInvitationAction;
use App\Enums\AuthenticationEventType;
use App\Enums\ObraStatus;
use App\Enums\RoleSlug;
use App\Livewire\Auth\LoginForm;
use App\Livewire\Auth\ObraInvitationPage;
use App\Models\AuthenticationEvent;
use App\Models\Obra;
use App\Models\ObraInvitation;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

const INVITATION_PAGE_PASSWORD = 'senha-forte-123';

beforeEach(function () {
    Role::factory()->obra()->create();

    $this->creator = User::factory()->gestao()->create(['name' => 'Gestora Criadora']);
    $this->obra = Obra::factory()->emAndamento()->create(['name' => 'Residencial Aurora']);

    $result = app(GenerateObraInvitationAction::class)->execute($this->creator, $this->obra);

    $this->url = $result['url'];
    $this->token = parse_url($result['url'], PHP_URL_FRAGMENT);
    $this->invitation = $result['invitation'];
});

test('the shareable link is the token-free route plus the fragment (CT-05, RF-38)', function () {
    expect(route('obra-invitation.show').'#'.$this->token)->toBe($this->url);
    expect(route('obra-invitation.show'))->toEndWith('/convite');
});

test('the route has active middleware, no parameter, and is outside guest and auth', function () {
    $route = Route::getRoutes()->getByName('obra-invitation.show');

    expect($route->parameterNames())->toBe([]);
    expect($route->gatherMiddleware())->toContain('active')->not->toContain('guest')->not->toContain('auth');
});

test('the pending state shows only "Verificando convite…" and the fragment script', function () {
    $html = html_entity_decode($this->get(route('obra-invitation.show'))->assertOk()->getContent());

    expect($html)
        ->toContain('Verificando convite…')
        ->toContain('window.location.hash.slice(1)')
        ->toContain("history.replaceState(null, '', window.location.pathname)")
        ->toContain('$wire.lookup(token)')
        ->not->toContain('Residencial Aurora')
        ->not->toContain('Já tenho conta')
        ->not->toMatch('/<input\b(?![^>]*type="hidden")/i');
});

test('a guest with a valid convite sees the obra and the new-account form with "Já tenho conta" (UI-07)', function () {
    Livewire::test(ObraInvitationPage::class)
        ->call('lookup', $this->token)
        ->assertNoRedirect()
        ->assertSet('invitationId', $this->invitation->id)
        ->assertSet('obraName', 'Residencial Aurora')
        ->assertSee('Residencial Aurora')
        ->assertSeeHtml('wire:model="name"')
        ->assertSeeHtml('wire:model="email"')
        ->assertSeeHtml('wire:model="password"')
        ->assertSeeHtml('wire:model="password_confirmation"')
        ->assertSee('Já tenho conta')
        ->assertDontSee('Aceitar convite como')
        ->assertDontSee('Verificando convite')
        ->assertDontSee(AcceptObraInvitationAction::ROLE_MISMATCH_MESSAGE)
        ->assertDontSee('Gestora Criadora');
});

test('an authenticated obra user sees "Aceitar convite como <nome>" and "Sair" only', function () {
    $user = User::factory()->obra()->create(['name' => 'Paulo Obra']);

    Livewire::actingAs($user)->test(ObraInvitationPage::class)
        ->call('lookup', $this->token)
        ->assertSee('Residencial Aurora')
        ->assertSee('Aceitar convite como Paulo Obra')
        ->assertSee('Sair')
        ->assertSeeHtml('action="'.route('logout').'"')
        ->assertDontSeeHtml('wire:model="email"')
        ->assertDontSee('Já tenho conta')
        ->assertDontSee(AcceptObraInvitationAction::ROLE_MISMATCH_MESSAGE);
});

test('Gestão and Suprimentos see only the RF-31 error and the convite stays pending', function (string $role) {
    $user = User::factory()->{$role}()->create();

    Livewire::actingAs($user)->test(ObraInvitationPage::class)
        ->call('lookup', $this->token)
        ->assertSee(AcceptObraInvitationAction::ROLE_MISMATCH_MESSAGE)
        ->assertDontSee('Aceitar convite como')
        ->assertDontSeeHtml('wire:model="email"')
        ->assertDontSee('Residencial Aurora')
        ->call('confirm')
        ->assertHasErrors(['invitation']);

    expect($this->invitation->fresh()->used_at)->toBeNull();
    expect($user->fresh()->role->slug)->toBe($role === 'gestao' ? RoleSlug::Gestao->value : RoleSlug::Suprimentos->value);
    expect(DB::table('obra_profile')->count())->toBe(0);
})->with(['gestao', 'suprimentos']);

test('every invalid cause and an empty fragment redirect to the fixed unavailable page (RF-28)', function (Closure $scenario) {
    $candidate = $scenario($this->token, $this->invitation, $this->obra);

    Livewire::test(ObraInvitationPage::class)
        ->call('lookup', $candidate)
        ->assertRedirect(route('obra-invitation.unavailable'))
        ->assertSet('invitationId', null)
        ->assertSet('obraName', null);
})->with([
    'expired' => [function (string $token) {
        test()->travel(25)->hours();

        return $token;
    }],
    'used' => [function (string $token, ObraInvitation $invitation) {
        app(AcceptObraInvitationAction::class)->acceptAsExistingAccount(User::factory()->obra()->create(), $invitation->id);

        return $token;
    }],
    'revoked' => [function (string $token, ObraInvitation $invitation) {
        app(RevokeObraInvitationAction::class)->execute(User::factory()->gestao()->create(), $invitation);

        return $token;
    }],
    'malformed' => [fn (string $token) => substr($token, 0, 63).'Z'],
    'unknown' => [fn () => str_repeat('b', 64)],
    'concluded obra' => [function (string $token, ObraInvitation $invitation, Obra $obra) {
        $obra->forceFill(['status' => ObraStatus::Concluido])->save();

        return $token;
    }],
    'empty fragment' => [fn () => ''],
]);

test('the 21st lookup from one IP within a minute is throttled before any convite query, then recovers (RF-19b)', function (bool $validToken) {
    for ($attempt = 1; $attempt <= 20; $attempt++) {
        Livewire::test(ObraInvitationPage::class)
            ->call('lookup', str_repeat('c', 64))
            ->assertRedirect(route('obra-invitation.unavailable'));
    }

    $component = Livewire::test(ObraInvitationPage::class);

    DB::enableQueryLog();
    DB::flushQueryLog();

    $component->call('lookup', $validToken ? $this->token : str_repeat('d', 64))
        ->assertRedirect(route('obra-invitation.throttled'))
        ->assertSet('invitationId', null);

    $queries = array_column(DB::getQueryLog(), 'query');
    DB::disableQueryLog();

    expect(array_filter($queries, fn (string $sql): bool => str_contains($sql, 'obra_invitations')))->toBe([]);

    $this->travel(61)->seconds();

    Livewire::test(ObraInvitationPage::class)
        ->call('lookup', $this->token)
        ->assertNoRedirect()
        ->assertSet('invitationId', $this->invitation->id);
})->with(['valid token' => true, 'invalid token' => false]);

test('a guest creates the account, is authenticated and lands on /home (RF-29, RF-21)', function () {
    Livewire::test(ObraInvitationPage::class)
        ->call('lookup', $this->token)
        ->set('name', 'Nova Pessoa')
        ->set('email', '  Nova@Example.com ')
        ->set('password', INVITATION_PAGE_PASSWORD)
        ->set('password_confirmation', INVITATION_PAGE_PASSWORD)
        ->call('register')
        ->assertHasNoErrors()
        ->assertRedirect(route('home'));

    $user = User::query()->where('email', 'nova@example.com')->sole();

    expect(Auth::id())->toBe($user->id);
    expect($user->obras()->pluck('obras.id')->all())->toBe([$this->obra->id]);
    expect($this->invitation->fresh()->used_by)->toBe($user->id);
    expect(AuthenticationEvent::query()->where('user_id', $user->id)->where('event', AuthenticationEventType::LoginSuccess)->count())->toBe(1);
});

test('a duplicate e-mail shows the RF-20 message with "Já tenho conta" and leaves the convite pending', function () {
    User::factory()->obra()->create(['email' => 'existente@example.com']);

    Livewire::test(ObraInvitationPage::class)
        ->call('lookup', $this->token)
        ->set('name', 'Nova Pessoa')
        ->set('email', 'EXISTENTE@example.com')
        ->set('password', INVITATION_PAGE_PASSWORD)
        ->set('password_confirmation', INVITATION_PAGE_PASSWORD)
        ->call('register')
        ->assertHasErrors(['email'])
        ->assertSee('Já existe uma conta com este e-mail. Entre ou use Esqueci minha senha.')
        ->assertSee('Já tenho conta')
        ->assertSet('password', '')
        ->assertNoRedirect();

    expect(Auth::check())->toBeFalse();
    expect($this->invitation->fresh()->used_at)->toBeNull();
});

test('a convite consumed meanwhile sends the register submit to the unavailable page', function () {
    $component = Livewire::test(ObraInvitationPage::class)->call('lookup', $this->token);

    app(RevokeObraInvitationAction::class)->execute($this->creator, $this->invitation);

    $component
        ->set('name', 'Nova Pessoa')
        ->set('email', 'nova@example.com')
        ->set('password', INVITATION_PAGE_PASSWORD)
        ->set('password_confirmation', INVITATION_PAGE_PASSWORD)
        ->call('register')
        ->assertRedirect(route('obra-invitation.unavailable'));

    expect(User::query()->where('email', 'nova@example.com')->exists())->toBeFalse();
});

test('the RF-30 flow: "Já tenho conta" → login → back to the token-free page → confirm associates', function () {
    $user = User::factory()->obra()->create([
        'name' => 'Paulo Obra',
        'email' => 'paulo@example.com',
        'password' => INVITATION_PAGE_PASSWORD,
    ]);

    Livewire::test(ObraInvitationPage::class)
        ->call('lookup', $this->token)
        ->call('useExistingAccount')
        ->assertRedirect(route('login'));

    expect(session(ObraInvitationPage::RETURN_SESSION_KEY))->toBe($this->invitation->id);

    Livewire::test(LoginForm::class)
        ->set('email', 'paulo@example.com')
        ->set('password', INVITATION_PAGE_PASSWORD)
        ->call('authenticate')
        ->assertRedirect(route('obra-invitation.show'));

    $page = Livewire::test(ObraInvitationPage::class)
        ->assertSet('invitationId', $this->invitation->id)
        ->assertSee('Aceitar convite como Paulo Obra')
        ->assertDontSee('Verificando convite');

    expect(session()->has(ObraInvitationPage::RETURN_SESSION_KEY))->toBeFalse();
    expect(DB::table('obra_profile')->count())->toBe(0);
    expect($this->invitation->fresh()->used_at)->toBeNull();

    $page->call('confirm')
        ->assertSet('notice', ObraInvitationPage::ACCEPTED_NOTICE)
        ->assertSee(ObraInvitationPage::ACCEPTED_NOTICE);

    expect($user->obras()->pluck('obras.id')->all())->toBe([$this->obra->id]);
    expect($this->invitation->fresh()->used_by)->toBe($user->id);
});

test('an already associated obra user confirming gets the notice and no new association', function () {
    $user = User::factory()->obra()->create();
    $user->obras()->attach($this->obra->id);

    Livewire::actingAs($user)->test(ObraInvitationPage::class)
        ->call('lookup', $this->token)
        ->call('confirm')
        ->assertSee(ObraInvitationPage::ALREADY_ASSOCIATED_NOTICE);

    expect(DB::table('obra_profile')->count())->toBe(1);
    expect($this->invitation->fresh()->used_by)->toBe($user->id);
});

test('a return to an invalid convite redirects to the unavailable page', function () {
    $user = User::factory()->obra()->create();
    session()->put(ObraInvitationPage::RETURN_SESSION_KEY, $this->invitation->id);

    app(RevokeObraInvitationAction::class)->execute($this->creator, $this->invitation);

    Livewire::actingAs($user)->test(ObraInvitationPage::class)
        ->assertRedirect(route('obra-invitation.unavailable'))
        ->assertSet('invitationId', null);
});

test('a login without a pending convite keeps the redirect to /home', function () {
    User::factory()->obra()->create(['email' => 'paulo@example.com', 'password' => INVITATION_PAGE_PASSWORD]);

    Livewire::test(LoginForm::class)
        ->set('email', 'paulo@example.com')
        ->set('password', INVITATION_PAGE_PASSWORD)
        ->call('authenticate')
        ->assertRedirect(route('home'));
});

test('a register forged by an authenticated user is forbidden', function () {
    $user = User::factory()->obra()->create();

    Livewire::actingAs($user)->test(ObraInvitationPage::class)
        ->call('lookup', $this->token)
        ->set('name', 'Forjado')
        ->set('email', 'forjado@example.com')
        ->set('password', INVITATION_PAGE_PASSWORD)
        ->set('password_confirmation', INVITATION_PAGE_PASSWORD)
        ->call('register')
        ->assertForbidden();

    expect(User::query()->where('email', 'forjado@example.com')->exists())->toBeFalse();
    expect($this->invitation->fresh()->used_at)->toBeNull();
});

test('forged invitationId and obraName updates are refused by #[Locked]', function (string $property, mixed $value) {
    $other = ObraInvitation::factory()->create();

    expect(fn () => Livewire::test(ObraInvitationPage::class)
        ->call('lookup', $this->token)
        ->set($property, $value === 'other' ? $other->id : $value))
        ->toThrow(CannotUpdateLockedPropertyException::class);
})->with([
    'invitationId' => ['invitationId', 'other'],
    'obraName' => ['obraName', 'Obra Forjada'],
]);
