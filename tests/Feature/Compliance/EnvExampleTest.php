<?php

/**
 * T15 — `.env.example` and README operations documentation (RF-28, RF-32,
 * CT-03, CT-04, UI-15, RNF-07, RNF-12). The committed environment example
 * carries the visible brand name (not a secret), keeps `log` as the mail
 * transport and only ever names `RESEND_API_KEY` / `GESTAO_BOOTSTRAP_PASSWORD`
 * without a value; the README documents the production variables, the
 * IH-01 human steps, the bootstrap command and the operator runbook.
 */
function envExampleContents(): string
{
    return file_get_contents(base_path('.env.example'));
}

function readmeContents(): string
{
    return file_get_contents(base_path('README.md'));
}

test('.env.example sets APP_NAME to Albuquerque Engenharia (UI-15)', function () {
    expect(envExampleContents())->toMatch('/^APP_NAME="Albuquerque Engenharia"$/m');
});

test('.env.example keeps MAIL_MAILER=log as the committed default (RF-26)', function () {
    $contents = envExampleContents();

    expect($contents)->toMatch('/^MAIL_MAILER=log$/m');
    expect($contents)->not->toMatch('/^MAIL_MAILER=resend$/m');
});

test('.env.example only names RESEND_API_KEY, commented and empty (RNF-07, CT-04)', function () {
    $contents = envExampleContents();

    expect($contents)->toContain('RESEND_API_KEY');
    expect($contents)->not->toMatch('/^RESEND_API_KEY=/m');
    expect($contents)->not->toMatch('/RESEND_API_KEY=\S+/m');
    expect($contents)->toMatch('/^#\s*MAIL_MAILER=resend$/m');
});

test('.env.example only names GESTAO_BOOTSTRAP_PASSWORD, commented and empty (RF-32, RNF-07)', function () {
    $contents = envExampleContents();

    expect($contents)->toContain('GESTAO_BOOTSTRAP_PASSWORD');
    expect($contents)->not->toMatch('/^GESTAO_BOOTSTRAP_PASSWORD=/m');
    expect($contents)->not->toMatch('/GESTAO_BOOTSTRAP_PASSWORD=\S+/m');
});

test('.env.example keeps the sender identity keyed on MAIL_FROM_* with APP_NAME as the default name (RF-27, CT-04)', function () {
    $contents = envExampleContents();

    expect($contents)->toMatch('/^MAIL_FROM_ADDRESS=/m');
    expect($contents)->toMatch('/^MAIL_FROM_NAME="\$\{APP_NAME\}"$/m');
});

test('README documents the e-mail and brand production variables with placeholders only (RF-28, CT-04)', function () {
    $readme = readmeContents();

    foreach (['APP_NAME', 'MAIL_MAILER', 'RESEND_API_KEY', 'MAIL_FROM_ADDRESS', 'MAIL_FROM_NAME', 'BCRYPT_ROUNDS'] as $variable) {
        expect($readme)->toContain("`{$variable}`");
    }

    expect($readme)
        ->toContain('"Albuquerque Engenharia"')
        ->toContain('| `MAIL_MAILER` | `resend` |')
        ->toContain('| `RESEND_API_KEY` | `<')
        ->toContain('| `MAIL_FROM_ADDRESS` | `<')
        ->toContain('| `BCRYPT_ROUNDS` | `12` |')
        ->not->toMatch('/RESEND_API_KEY\s*=\s*["\']?re_[A-Za-z0-9_]+/');
});

test('README documents IH-01, the transports per environment and the behaviour while unset (RF-28)', function () {
    $readme = readmeContents();

    expect($readme)
        ->toContain('### E-mail transacional')
        ->toContain('IH-01')
        ->toContain('`log`')
        ->toContain('`array`')
        ->toContain('`resend`')
        ->toContain('Resend')
        ->toContain('Variables')
        ->toContain('storage/logs/laravel.log');
});

test('README documents the users:create-gestao bootstrap and that the password is never stored (RF-32)', function () {
    $readme = readmeContents();

    expect($readme)
        ->toContain('### Bootstrap do primeiro Gestão')
        ->toContain('php artisan users:create-gestao')
        ->toContain('--reset-password')
        ->toContain('GESTAO_BOOTSTRAP_PASSWORD')
        ->toContain('Esqueci minha senha')
        ->not->toMatch('/--password=["\']?[A-Za-z0-9]{8,}/');
});

test('README states the 72-hour invite and 60-minute reset validity and the shared token table (CT-03, RNF-01)', function () {
    $readme = readmeContents();

    expect($readme)
        ->toContain('72 horas')
        ->toContain('60 minutos')
        ->toContain('password_reset_tokens');
});

test('README no longer declares e-mail sending out of scope and carries the runbook and the deferred domain checklist (RF-33, RNF-12)', function () {
    $readme = readmeContents();

    expect($readme)
        ->not->toContain('envio de e-mail')
        ->toContain('### Runbook de produção (Etapa 10)')
        ->toContain('is_demo = true AND is_active = true')
        ->toContain('### Domínio definitivo (Etapa 11 — diferido)')
        ->toContain('APP_URL=https://<subdominio>');
});

test('.env.example only names PEDIDO_ANEXOS_ROOT, commented and empty (T32, RF-20, RNF-07)', function () {
    $contents = envExampleContents();

    expect($contents)
        ->toMatch('/^#\s*PEDIDO_ANEXOS_ROOT=$/m')
        ->not->toMatch('/^PEDIDO_ANEXOS_ROOT=/m')
        ->not->toMatch('/PEDIDO_ANEXOS_ROOT=\S+/m');
});

test('README carries the attachments runbook: Volume, disk root, upload limits and Suprimentos associations (T32, RF-20, RF-48, RNF-07)', function () {
    $readme = readmeContents();

    expect($readme)
        ->toContain('### Anexos de pedidos (Volume e limites de upload)')
        ->toContain('PEDIDO_ANEXOS_ROOT')
        ->toContain('Volume')
        ->toContain('PHP_INI_SCAN_DIR')
        ->toContain('PEDIDO_ANEXOS_ROOT=/data/pedido-anexos')
        ->toContain('PHP_INI_SCAN_DIR=:/app/config/php')
        ->toContain('associar os usuários Suprimentos às obras em /associacoes antes de anunciar a Nova Solicitação')
        ->toContain('America/Sao_Paulo');
});
