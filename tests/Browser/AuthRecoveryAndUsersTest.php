<?php

use App\Enums\RoleSlug;
use App\Models\Obra;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Symfony\Component\Mime\Email;

/**
 * E2E for the flows introduced by `ajustes-finais-albuquerque` (T26, UI-21):
 * Gestão's Usuários administration (TC-01, TC-07), "Esqueci minha senha"
 * (TC-10) and the first-access invite accepted end to end (TC-16). Every
 * scenario drives the real Blade/Livewire UI through a headless browser
 * against the demo dataset, exactly as the operator would.
 *
 * The suite runs with `MAIL_MAILER=array` (phpunit.xml), and the browser
 * plugin serves every request from this same in-process application, so the
 * e-mails the UI triggers land in the `array` transport and the invite token
 * is read straight from the message that would reach the user — no broker
 * or notification is faked. Role switches go through the sidebar logout ("Sair")
 * because the in-process session guard keeps the previous user resolved
 * across browser contexts (see `DemoRoteiroTest`).
 */
const E2E_INVITE_PASSWORD = 'primeira-senha-e2e-2026';

const E2E_GENERIC_RECOVERY_TEXT = 'Se o e-mail informado estiver cadastrado e ativo, você receberá um link para redefinir a senha em instantes.';

/**
 * Livewire's `wire:model` syncs each input from the component state in a
 * deferred Alpine effect after boot, so text typed before that flush is
 * wiped. Waits for the binding on the given input and lets the pending
 * effect run before the caller types.
 */
function waitForWireModelOn($page, string $inputId): void
{
    $page->page()->waitForFunction('() => document.getElementById("'.$inputId.'")?._x_model !== undefined');
    $page->page()->evaluate('() => new Promise((resolve) => setTimeout(resolve, 50))');
}

function loginThroughBrowser($page, string $email, string $password, string $expectedPath)
{
    $page->assertPathIs('/login');

    waitForWireModelOn($page, 'email');

    return $page
        ->type('email', $email)
        ->type('password', $password)
        ->press('Entrar')
        ->assertPathIs($expectedPath);
}

function logoutThroughBrowser($page)
{
    return logoutThroughSidebar($page);
}

/**
 * Every message the `array` transport captured for the given recipient,
 * oldest first.
 *
 * @return list<Email>
 */
function arrayMailerMessagesTo(string $email): array
{
    return Mail::mailer()->getSymfonyTransport()->messages()
        ->map(fn ($sentMessage) => $sentMessage->getOriginalMessage())
        ->filter(fn ($message) => $message instanceof Email
            && collect($message->getTo())->contains(fn ($address) => $address->getAddress() === $email))
        ->values()
        ->all();
}

/**
 * Reads the raw first-access token from the last invite e-mail sent to the
 * given address — the same link the user would click.
 */
function inviteTokenFromArrayMailer(string $email): string
{
    $messages = arrayMailerMessagesTo($email);

    expect($messages)->not->toBeEmpty("No e-mail reached the array transport for {$email}.");

    $invite = end($messages);

    expect($invite->getSubject())->toBe('Seu acesso ao '.config('app.name'));

    $body = html_entity_decode((string) $invite->getHtmlBody());

    expect(preg_match('#/primeiro-acesso/([A-Za-z0-9]+)\?email=#', $body, $matches))->toBe(1, 'Invite e-mail carries no invite.show link.');

    return $matches[1];
}

/**
 * Gestão creates an Obra user associated with one obra through the Usuários
 * form and returns the listing page. Shared by the administration and the
 * invite-acceptance scenarios.
 */
function createObraUserThroughBrowser($gestao, string $name, string $email, Obra $obra)
{
    $obraRole = Role::query()->where('slug', RoleSlug::Obra->value)->firstOrFail();

    $gestao->click('#sidebar a[href$="/gestao/usuarios"]')->assertPathIs('/gestao/usuarios');

    $gestao->click('Novo usuário')->assertPathIs('/gestao/usuarios/novo');

    waitForWireModelOn($gestao, 'name');

    $gestao->type('name', $name)
        ->type('email', $email)
        ->select('roleId', (string) $obraRole->id);

    // The obra selector is only rendered once the live perfil binding round-trips as Obra (RF-09).
    $gestao->page()->waitForSelector('[data-obra-selector]');

    return $gestao->check('obra-'.$obra->id)
        ->press('Criar usuário')
        ->assertPathIs('/gestao/usuarios')
        ->assertSee("Usuário criado. Convite enviado para {$email}.");
}

beforeEach(function () {
    $this->seed(DemoSeeder::class);

    Mail::mailer()->getSymfonyTransport()->flush();
});

test('gestão administers a user end to end: create an Obra user, deactivate, reactivate and resend the invite (TC-01, TC-07, UI-21)', function () {
    $obra = Obra::query()->where('name', '[DEMO] Obra Alfa')->firstOrFail();
    $email = 'novo.obra.'.Str::lower(Str::random(6)).'@example.com';
    $row = 'tr[data-user-email="'.$email.'"]';

    $gestao = loginThroughBrowser($this->visit('/login'), 'gestao.demo@example.com', 'password', '/gestao/pedidos');

    $page = createObraUserThroughBrowser($gestao, 'Novo Usuário Obra', $email, $obra);

    // The listing shows the new row with perfil, status and the associated obra (RF-03).
    $page->assertSeeIn($row, 'Novo Usuário Obra')
        ->assertSeeIn($row, 'Ativo')
        ->assertSeeIn($row, $obra->name);

    // The perfil cell reads exactly "Obra" (a substring match would also hit the name, e-mail and obra cells).
    expect(trim((string) $page->text($row.' td:nth-child(3)')))->toBe('Obra');

    $created = User::query()->where('email', $email)->firstOrFail();
    expect($created->is_active)->toBeTrue();
    expect($created->obras()->pluck('obras.id')->all())->toBe([$obra->id]);
    expect(arrayMailerMessagesTo($email))->toHaveCount(1);

    // Desativar → badge Inativo, persisted (RF-10).
    $page->click($row.' button:has-text("Desativar")')
        ->assertSee('Usuário Novo Usuário Obra desativado.')
        ->assertSeeIn($row, 'Inativo');

    expect($created->fresh()->is_active)->toBeFalse();

    // Ativar → badge Ativo again (RF-11).
    $page->click($row.' button:has-text("Ativar")')
        ->assertSee('Usuário Novo Usuário Obra ativado.')
        ->assertSeeIn($row, 'Ativo');

    expect($created->fresh()->is_active)->toBeTrue();

    // Reenviar convite right after the creation invite: the authenticated
    // surface tells Gestão explicitly that a link was already sent (RF-14, TC-25).
    $page->click($row.' button:has-text("Reenviar convite")')
        ->assertSee('Um link já foi enviado para este e-mail há menos de 1 minuto. Aguarde para reenviar.');

    expect(arrayMailerMessagesTo($email))->toHaveCount(1);

    // Once the 60 s throttle elapses the resend goes out and is confirmed.
    $this->travel(61)->seconds();

    $page->click($row.' button:has-text("Reenviar convite")')
        ->assertSee("Link de acesso enviado para {$email}.");

    expect(arrayMailerMessagesTo($email))->toHaveCount(2);

    logoutThroughBrowser($page);
});

test('"Esqueci minha senha" answers with the same generic text for a known and an unknown e-mail (TC-10, RF-21)', function () {
    $page = $this->visit('/login');

    $page->click('Esqueci minha senha')->assertPathIs('/esqueci-senha');

    waitForWireModelOn($page, 'email');

    $page->type('email', 'obra.demo@example.com')
        ->press('Enviar link de redefinição')
        ->assertSee(E2E_GENERIC_RECOVERY_TEXT);

    // A real reset link reached the active demo account.
    $known = arrayMailerMessagesTo('obra.demo@example.com');
    expect($known)->toHaveCount(1);
    expect(html_entity_decode((string) end($known)->getHtmlBody()))->toContain('/redefinir-senha/');

    // The confirmation replaces the form, so the page is reopened for the next attempt.
    $page->page()->goto(route('password.request'));
    $page->assertPathIs('/esqueci-senha');

    waitForWireModelOn($page, 'email');

    $page->type('email', 'ninguem.desconhecido@example.com')
        ->press('Enviar link de redefinição')
        ->assertSee(E2E_GENERIC_RECOVERY_TEXT);

    // Same text, nothing sent: the form never reveals whether an account exists.
    expect(arrayMailerMessagesTo('ninguem.desconhecido@example.com'))->toBeEmpty();
    expect(Mail::mailer()->getSymfonyTransport()->messages())->toHaveCount(1);

    $page->click('Voltar ao login')->assertPathIs('/login');
});

test('an invited Obra user accepts the invite from the e-mail token, defines the password and lands on the obra home (TC-16, RF-15, RF-16)', function () {
    $obra = Obra::query()->where('name', '[DEMO] Obra Beta')->firstOrFail();
    $email = 'convidada.'.Str::lower(Str::random(6)).'@example.com';

    $gestao = loginThroughBrowser($this->visit('/login'), 'gestao.demo@example.com', 'password', '/gestao/pedidos');

    $listing = createObraUserThroughBrowser($gestao, 'Convidada Obra', $email, $obra);

    logoutThroughBrowser($listing);

    // The token comes from the invite message captured by the array transport (CT-03).
    $token = inviteTokenFromArrayMailer($email);

    $invite = $this->visit(route('invite.show', ['token' => $token, 'email' => $email], absolute: false));

    $invite->assertPathIs('/primeiro-acesso/'.$token)
        ->assertSee('Defina sua senha')
        ->assertValue('email', $email);

    waitForWireModelOn($invite, 'password');

    $invite->type('password', E2E_INVITE_PASSWORD)
        ->type('password_confirmation', E2E_INVITE_PASSWORD)
        ->press('Definir senha')
        ->assertPathIs('/login');

    // The password the user chose is the only one that works, and it opens the obra home (RF-22).
    $home = loginThroughBrowser($invite, $email, E2E_INVITE_PASSWORD, '/obra/pedidos');

    $home->assertSee('Convidada Obra')
        ->assertSee($obra->name);

    $invited = User::query()->where('email', $email)->firstOrFail();
    expect($invited->role->slug)->toBe(RoleSlug::Obra->value);
    expect($invited->obras()->pluck('obras.id')->all())->toBe([$obra->id]);

    logoutThroughBrowser($home);
});
