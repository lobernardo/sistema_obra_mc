<?php

use App\Actions\Obras\GenerateObraInvitationAction;
use App\Exceptions\ObraInvitations\ObraInvitationUnavailableException;
use App\Livewire\Auth\ObraInvitationPage;
use App\Models\Obra;
use App\Models\ObraInvitation;
use App\Models\Role;
use App\Models\User;

/**
 * T25 (RF-38 AC-b, RF-30, UI-07): the convite link in a real browser.
 *
 * The token travels only in the URL fragment: the inline script of the
 * pending state reads `location.hash`, clears it with `history.replaceState`
 * and hands it to the Livewire lookup. After the lookup the address bar
 * carries neither the fragment nor the token. The existing-account path goes
 * through the standard login and comes back to the token-free `/convite`
 * page, and a garbage fragment ends on the fixed `/convite/indisponivel`.
 */
beforeEach(function () {
    Role::factory()->obra()->create();

    $this->obra = Obra::factory()->emAndamento()->create(['name' => 'Residencial Navegador']);

    $result = app(GenerateObraInvitationAction::class)->execute(User::factory()->gestao()->create(), $this->obra);

    $this->token = (string) parse_url($result['url'], PHP_URL_FRAGMENT);
});

test('opening /convite#<token> clears the fragment, and "Já tenho conta" → login → confirm associates the obra (RF-38 AC-b, RF-30)', function () {
    $user = User::factory()->obra()->create([
        'name' => 'Paula Navegadora',
        'email' => 'paula.navegadora@example.com',
        'password' => 'password',
    ]);

    $page = $this->visit('/login');

    $page->page()->goto(route('obra-invitation.show').'#'.$this->token);
    $page->page()->locator('[data-invitation-state="guest"]')->waitFor(['state' => 'visible']);

    $page->assertSee('Residencial Navegador')->assertSee('Já tenho conta');

    expect($page->script('window.location.hash'))->toBe('');
    expect($page->script('window.location.href'))->not->toContain($this->token)->toEndWith('/convite');

    $page->press('Já tenho conta')->assertPathIs('/login');

    // `wire:model` syncs the inputs from component state in a deferred
    // Alpine effect after boot; typing before that flush is wiped.
    $page->page()->locator('#email')->waitFor(['state' => 'visible']);
    $page->page()->waitForFunction('() => document.getElementById("email")?._x_model !== undefined');
    $page->page()->evaluate('() => new Promise((resolve) => setTimeout(resolve, 50))');

    $page->type('email', 'paula.navegadora@example.com')
        ->type('password', 'password')
        ->press('Entrar');

    $page->page()->locator('[data-invitation-state="obra"]')->waitFor(['state' => 'visible']);

    $page->assertPathIs('/convite')
        ->assertSee('Residencial Navegador')
        ->assertSee('Aceitar convite como Paula Navegadora');

    expect($page->script('window.location.search + window.location.hash'))->toBe('');
    expect($page->script('window.location.href'))->not->toContain($this->token);

    $page->press('Aceitar convite como Paula Navegadora');
    $page->page()->locator('[data-invitation-state="obra"] [role="status"]')->waitFor(['state' => 'visible']);

    $page->assertPathIs('/convite')
        ->assertSee(ObraInvitationPage::ACCEPTED_NOTICE)
        ->assertNoJavascriptErrors();

    expect($user->obras()->pluck('obras.id')->all())->toBe([$this->obra->id]);
    expect(ObraInvitation::query()->sole()->used_by)->toBe($user->id);
});

test('opening /convite#<garbage> ends on /convite/indisponivel with the generic text (RF-28)', function () {
    $page = $this->visit('/login');

    $page->page()->goto(route('obra-invitation.show').'#lixo-'.bin2hex(random_bytes(8)));
    $page->page()->waitForURL(route('obra-invitation.unavailable'));

    $page->assertPathIs('/convite/indisponivel')
        ->assertSee('Convite indisponível')
        ->assertSee(ObraInvitationUnavailableException::MESSAGE)
        ->assertDontSee('Residencial Navegador');

    expect(ObraInvitation::query()->sole()->used_at)->toBeNull();
});
