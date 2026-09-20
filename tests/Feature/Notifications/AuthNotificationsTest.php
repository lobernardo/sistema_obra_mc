<?php

use App\Models\User;
use App\Notifications\FirstAccessInvite;
use App\Notifications\ResetPasswordPtBr;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Markdown;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Symfony\Component\Mime\Email;

/**
 * T07 — `passwords.invites` broker and PT-BR auth notifications (RF-15,
 * RF-27, CT-03, CT-07, RNF-01, RNF-08, RNF-12).
 * T14 — PT-BR markdown templates and sender identity (RF-27, CT-07, UI-16).
 */
const PLAIN_PASSWORD = 'senha-secreta-nunca-enviada';

/**
 * English strings of the framework mail theme that must never leak into a
 * PT-BR message.
 *
 * @return list<string>
 */
function englishMailThemeStrings(): array
{
    return ['All rights reserved', 'Regards', 'Hello!', 'Whoops!', "If you're having trouble", 'into your web browser'];
}

/**
 * Sends a notification through the real `array` mailer and returns the
 * Symfony e-mail that reached the transport.
 */
function sendThroughArrayMailer(User $user, object $notification): Email
{
    $transport = Mail::mailer()->getSymfonyTransport();
    $transport->flush();

    Notification::send($user, $notification);

    $messages = $transport->messages();

    expect($messages)->toHaveCount(1);

    $email = $messages->first()->getOriginalMessage();

    expect($email)->toBeInstanceOf(Email::class);

    return $email;
}

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

test('the invite template renders integrally in PT-BR from a markdown view with the fallback URL line and no English theme string (RF-27, CT-07a)', function () {
    $mail = (new FirstAccessInvite('invite-token-abc'))->toMail($this->user);

    expect($mail->markdown)->toBe('mail.auth.first-access-invite');
    expect($mail->view)->toBeNull();

    $html = $mail->render()->toHtml();

    expect($html)
        ->toContain('Olá, Ana Convidada!')
        ->toContain('foi criado para você com este e-mail')
        ->toContain('defina a sua senha pelo botão abaixo')
        ->toContain('Definir minha senha')
        ->toContain('Este link é válido por 72 horas')
        ->toContain('Se você não esperava este e-mail, nenhuma ação é necessária')
        ->toContain('Se você tiver problemas para clicar no botão')
        ->toContain('copie e cole a URL abaixo no seu navegador')
        ->toContain('Atenciosamente')
        ->toContain('Todos os direitos reservados')
        ->toContain(config('app.name'))
        ->toContain(e($mail->actionUrl));

    foreach (englishMailThemeStrings() as $english) {
        expect($html)->not->toContain($english);
    }
});

test('the reset template renders integrally in PT-BR from a markdown view with the fallback URL line and no English theme string (RF-27, CT-07b)', function () {
    $mail = (new ResetPasswordPtBr('reset-token-xyz'))->toMail($this->user);

    expect($mail->markdown)->toBe('mail.auth.reset-password');
    expect($mail->view)->toBeNull();

    $html = $mail->render()->toHtml();

    expect($html)
        ->toContain('Olá, Ana Convidada!')
        ->toContain('Recebemos um pedido para redefinir a senha da sua conta')
        ->toContain('Redefinir senha')
        ->toContain('Este link é válido por 60 minutos')
        ->toContain('Se você não solicitou a redefinição, nenhuma ação é necessária')
        ->toContain('Se você tiver problemas para clicar no botão')
        ->toContain('copie e cole a URL abaixo no seu navegador')
        ->toContain('Atenciosamente')
        ->toContain('Todos os direitos reservados')
        ->toContain(config('app.name'))
        ->toContain(e($mail->actionUrl));

    foreach (englishMailThemeStrings() as $english) {
        expect($html)->not->toContain($english);
    }
});

test('the plain-text alternative of both templates is PT-BR and carries the raw link (RF-27)', function () {
    $markdown = app(Markdown::class);

    $invite = (new FirstAccessInvite('invite-token-abc'))->toMail($this->user);
    $inviteText = (string) $markdown->renderText($invite->markdown, $invite->data());

    expect($inviteText)
        ->toContain('Olá, Ana Convidada!')
        ->toContain('Definir minha senha: '.$invite->actionUrl)
        ->toContain('válido por 72 horas')
        ->toContain('Todos os direitos reservados')
        ->not->toContain('<')
        ->not->toContain('MC Inteligência');

    $reset = (new ResetPasswordPtBr('reset-token-xyz'))->toMail($this->user);
    $resetText = (string) $markdown->renderText($reset->markdown, $reset->data());

    expect($resetText)
        ->toContain('Olá, Ana Convidada!')
        ->toContain('Redefinir senha: '.$reset->actionUrl)
        ->toContain('válido por 60 minutos')
        ->toContain('Todos os direitos reservados')
        ->not->toContain('<')
        ->not->toContain('MC Inteligência');

    foreach (englishMailThemeStrings() as $english) {
        expect($inviteText)->not->toContain($english);
        expect($resetText)->not->toContain($english);
    }
});

test('the invite sent through the array mailer is addressed only to the user and signed by the configured sender (RF-27, CT-07a)', function () {
    config(['mail.from.name' => 'Remetente Configurado', 'mail.from.address' => 'remetente@example.com']);

    $email = sendThroughArrayMailer($this->user, new FirstAccessInvite('invite-token-abc'));

    expect($email->getSubject())->toBe('Seu acesso ao '.config('app.name'));

    expect($email->getTo())->toHaveCount(1);
    expect($email->getTo()[0]->getAddress())->toBe('ana.convidada@example.com');
    expect($email->getCc())->toBeEmpty();
    expect($email->getBcc())->toBeEmpty();

    expect($email->getFrom())->toHaveCount(1);
    expect($email->getFrom()[0]->getName())->toBe(config('mail.from.name'));
    expect($email->getFrom()[0]->getAddress())->toBe(config('mail.from.address'));

    $expectedUrl = route('invite.show', ['token' => 'invite-token-abc', 'email' => 'ana.convidada@example.com']);

    expect($email->getHtmlBody())
        ->toContain('Olá, Ana Convidada!')
        ->toContain(e($expectedUrl))
        ->not->toContain(PLAIN_PASSWORD)
        ->not->toContain('MC Inteligência');

    expect($email->getTextBody())
        ->toContain('Olá, Ana Convidada!')
        ->toContain($expectedUrl)
        ->not->toContain(PLAIN_PASSWORD)
        ->not->toContain('MC Inteligência');
});

test('the reset sent through the array mailer is addressed only to the user and signed by the configured sender (RF-27, CT-07b)', function () {
    config(['mail.from.name' => 'Remetente Configurado', 'mail.from.address' => 'remetente@example.com']);

    $email = sendThroughArrayMailer($this->user, new ResetPasswordPtBr('reset-token-xyz'));

    expect($email->getSubject())->toBe('Redefinição de senha - '.config('app.name'));

    expect($email->getTo())->toHaveCount(1);
    expect($email->getTo()[0]->getAddress())->toBe('ana.convidada@example.com');
    expect($email->getCc())->toBeEmpty();
    expect($email->getBcc())->toBeEmpty();

    expect($email->getFrom())->toHaveCount(1);
    expect($email->getFrom()[0]->getName())->toBe(config('mail.from.name'));
    expect($email->getFrom()[0]->getAddress())->toBe(config('mail.from.address'));

    $expectedUrl = route('password.reset', ['token' => 'reset-token-xyz', 'email' => 'ana.convidada@example.com']);

    expect($email->getHtmlBody())
        ->toContain(e($expectedUrl))
        ->not->toContain(PLAIN_PASSWORD)
        ->not->toContain('MC Inteligência');

    expect($email->getTextBody())
        ->toContain($expectedUrl)
        ->not->toContain(PLAIN_PASSWORD)
        ->not->toContain('MC Inteligência');
});

test('the sender follows MAIL_FROM_NAME and MAIL_FROM_ADDRESS at runtime and is never hardcoded in the notifications (RF-27, CT-04)', function () {
    config(['mail.from.name' => 'Albuquerque Engenharia', 'mail.from.address' => 'acesso@albuquerque.example']);

    $email = sendThroughArrayMailer($this->user, new FirstAccessInvite('invite-token-abc'));

    expect($email->getFrom()[0]->getName())->toBe('Albuquerque Engenharia');
    expect($email->getFrom()[0]->getAddress())->toBe('acesso@albuquerque.example');

    foreach (File::allFiles(app_path('Notifications')) as $file) {
        $source = $file->getContents();

        expect($source)
            ->not->toContain('->from(')
            ->not->toContain('->replyTo(')
            ->not->toMatch('/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/');
    }
});

test('the mail templates carry no MC Inteligência signature, no hardcoded brand and no hardcoded address (UI-16, RF-27, RNF-12)', function () {
    $templates = array_merge(
        File::allFiles(resource_path('views/mail')),
        File::allFiles(resource_path('views/components/mail')),
    );

    expect($templates)->not->toBeEmpty();

    foreach ($templates as $template) {
        $source = $template->getContents();

        expect($source)
            ->not->toContain('MC Inteligência')
            ->not->toContain('Inteligência')
            ->not->toContain('Albuquerque')
            ->not->toContain('Sistema de Solicitações')
            ->not->toContain('senha:')
            ->not->toMatch('/https?:\/\/[a-z0-9.-]+\.[a-z]{2,}/i')
            ->not->toMatch('/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/');
    }
});

test('the log mailer writes the whole PT-BR body including the invite link to the local log (RF-26, T14)', function () {
    $logFile = storage_path('logs/mail-transport-test-'.uniqid().'.log');

    config([
        'logging.channels.mail_transport_test' => ['driver' => 'single', 'path' => $logFile, 'level' => 'debug'],
        'mail.mailers.log.channel' => 'mail_transport_test',
        'mail.default' => 'log',
    ]);

    Mail::purge('log');

    try {
        Notification::send($this->user, new FirstAccessInvite('invite-token-abc'));

        expect(is_file($logFile))->toBeTrue();

        $log = file_get_contents($logFile);
        $expectedUrl = route('invite.show', ['token' => 'invite-token-abc', 'email' => 'ana.convidada@example.com']);

        expect($log)
            ->toContain('To: ana.convidada@example.com')
            ->toContain($expectedUrl)
            ->toContain('Definir minha senha')
            ->toContain('válido por 72 horas')
            ->not->toContain(PLAIN_PASSWORD)
            ->not->toContain('MC Inteligência');
    } finally {
        File::delete($logFile);
        Mail::purge('log');
    }
});
