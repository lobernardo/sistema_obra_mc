<?php

use App\Models\User;
use App\Notifications\FirstAccessInvite;
use App\Notifications\ResetPasswordPtBr;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

/**
 * T07 — `passwords.invites` broker and PT-BR auth notifications (RF-15,
 * RF-27, CT-03, CT-07, RNF-01, RNF-08, RNF-12).
 */
const PLAIN_PASSWORD = 'senha-secreta-nunca-enviada';

beforeEach(function () {
    $this->user = User::factory()->obra()->create([
        'name' => 'Ana Convidada',
        'email' => 'ana.convidada@example.com',
        'password' => Hash::make(PLAIN_PASSWORD),
    ]);
});

test('the invites broker mirrors the users broker on the same table with a 72 h expiry (CT-03, RNF-01)', function () {
    $invites = config('auth.passwords.invites');

    expect($invites)->toBeArray()
        ->and($invites['provider'])->toBe('users')
        ->and($invites['table'])->toBe('password_reset_tokens')
        ->and($invites['expire'])->toBe(4320)
        ->and($invites['throttle'])->toBe(60);

    expect(config('auth.passwords.users.table'))->toBe($invites['table']);
    expect(config('auth.passwords.users.expire'))->toBe(60);
    expect(config('auth.passwords.users.throttle'))->toBe(60);
});

test('neither notification is queued because no worker exists (RNF-08)', function () {
    expect(new FirstAccessInvite('token'))->not->toBeInstanceOf(ShouldQueue::class);
    expect(new ResetPasswordPtBr('token'))->not->toBeInstanceOf(ShouldQueue::class);
});

test('the first-access invite is PT-BR, links to invite.show with token and e-mail, states 72 hours and carries no password nor MC signature (CT-07a)', function () {
    $mail = (new FirstAccessInvite('invite-token-abc'))->toMail($this->user);

    expect($mail->subject)->toBe('Seu acesso ao '.config('app.name'));
    expect($mail->actionText)->toBe('Definir minha senha');
    expect($mail->actionUrl)->toBe(route('invite.show', ['token' => 'invite-token-abc', 'email' => 'ana.convidada@example.com']));
    expect($mail->actionUrl)->toContain('invite-token-abc')->toContain(urlencode('ana.convidada@example.com'));
    expect(parse_url($mail->actionUrl, PHP_URL_HOST))->toBe(parse_url(config('app.url'), PHP_URL_HOST));

    $html = $mail->render()->toHtml();

    expect($html)->toContain('Olá, Ana Convidada!')
        ->toContain('válido por 72 horas')
        ->toContain('Definir minha senha')
        ->not->toContain(PLAIN_PASSWORD)
        ->not->toContain($this->user->password)
        ->not->toContain('$2y$')
        ->not->toContain('MC Inteligência');
});

test('the reset notification is PT-BR, links to password.reset with token and e-mail, states 60 minutes and carries no password nor MC signature (CT-07b)', function () {
    $mail = (new ResetPasswordPtBr('reset-token-xyz'))->toMail($this->user);

    expect($mail->subject)->toBe('Redefinição de senha - '.config('app.name'));
    expect($mail->actionText)->toBe('Redefinir senha');
    expect($mail->actionUrl)->toBe(route('password.reset', ['token' => 'reset-token-xyz', 'email' => 'ana.convidada@example.com']));
    expect($mail->actionUrl)->toContain('reset-token-xyz')->toContain(urlencode('ana.convidada@example.com'));
    expect(parse_url($mail->actionUrl, PHP_URL_HOST))->toBe(parse_url(config('app.url'), PHP_URL_HOST));

    $html = $mail->render()->toHtml();

    expect($html)->toContain('Olá, Ana Convidada!')
        ->toContain('válido por 60 minutos')
        ->toContain('Redefinir senha')
        ->not->toContain(PLAIN_PASSWORD)
        ->not->toContain($this->user->password)
        ->not->toContain('$2y$')
        ->not->toContain('MC Inteligência');
});

test('both links follow APP_URL when the configured domain changes (RNF-12, RF-27)', function () {
    config(['app.url' => 'https://albuquerque.exemplo-mc.com.br']);

    $invite = (new FirstAccessInvite('t1'))->toMail($this->user)->actionUrl;
    $reset = (new ResetPasswordPtBr('t2'))->toMail($this->user)->actionUrl;

    expect($invite)->toStartWith('https://albuquerque.exemplo-mc.com.br/');
    expect($reset)->toStartWith('https://albuquerque.exemplo-mc.com.br/');
    expect(parse_url($invite, PHP_URL_HOST))->toBe('albuquerque.exemplo-mc.com.br');
    expect(parse_url($reset, PHP_URL_HOST))->toBe('albuquerque.exemplo-mc.com.br');

    config(['app.url' => 'http://outro-host.local:8080']);

    expect((new FirstAccessInvite('t1'))->toMail($this->user)->actionUrl)->toStartWith('http://outro-host.local:8080/');
    expect((new ResetPasswordPtBr('t2'))->toMail($this->user)->actionUrl)->toStartWith('http://outro-host.local:8080/');
});

test('the users broker sends the PT-BR reset notification through the model hook', function () {
    Notification::fake();

    $status = Password::broker('users')->sendResetLink(['email' => $this->user->email]);

    expect($status)->toBe(Password::RESET_LINK_SENT);

    Notification::assertSentTo($this->user, ResetPasswordPtBr::class);
    Notification::assertCount(1);
});
