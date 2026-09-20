<?php

/**
 * T13 — Resend transport wiring (RF-26, RF-28, CT-04, RNF-07, RNF-09).
 * The only approved dependency addition is `resend/resend-php`; the
 * transport is selected exclusively by `MAIL_MAILER`, the API key is read
 * from `config('services.resend.key')` (`RESEND_API_KEY`) and no other mail
 * provider SDK (Postmark, SES, Mailgun) may be introduced. Tests keep the
 * `array` mailer and never carry a real key.
 */
function composerManifest(): array
{
    return json_decode(file_get_contents(base_path('composer.json')), true);
}

test('composer.json requires resend/resend-php with a ^1.x constraint (RF-26, RNF-09)', function () {
    $require = composerManifest()['require'] ?? [];

    expect($require)->toHaveKey('resend/resend-php');
    expect($require['resend/resend-php'])->toMatch('/^\^1\.\d+(\.\d+)?$/');
});

test('composer.lock pins resend/resend-php so the Railway build resolves it (RF-26)', function () {
    $lock = json_decode(file_get_contents(base_path('composer.lock')), true);

    $locked = array_column($lock['packages'] ?? [], 'name');

    expect($locked)->toContain('resend/resend-php');
});

test('no other mail provider SDK is required (CT-04, RNF-09)', function () {
    $composer = composerManifest();

    $declared = array_keys(array_merge(
        $composer['require'] ?? [],
        $composer['require-dev'] ?? [],
    ));

    $otherProviders = array_filter(
        $declared,
        fn (string $package) => (bool) preg_match('/postmark|aws-sdk|mailgun|sendgrid|mailersend|brevo/i', $package),
    );

    expect($otherProviders)->toBeEmpty();
});

test('the test suite uses the array mailer and never a real transport (RF-26)', function () {
    expect(config('mail.default'))->toBe('array');
});

test('the resend mailer is configured with the native resend transport (RF-26, CT-04)', function () {
    expect(config('mail.mailers.resend.transport'))->toBe('resend');
    expect(config('mail.mailers.log.transport'))->toBe('log');
    expect(config('mail.mailers.array.transport'))->toBe('array');
});

test('the resend API key comes only from RESEND_API_KEY and is absent under tests (CT-04, RNF-07)', function () {
    expect(config('services.resend.key'))->toBeNull();

    $services = file_get_contents(base_path('config/services.php'));

    expect($services)->toMatch("/'resend'\s*=>\s*\[\s*'key'\s*=>\s*env\('RESEND_API_KEY'\)/");
});

test('the mailer sender identity is driven only by MAIL_FROM_* (RF-27, CT-04)', function () {
    $mail = file_get_contents(base_path('config/mail.php'));

    expect($mail)
        ->toContain("env('MAIL_FROM_ADDRESS'")
        ->toContain("env('MAIL_FROM_NAME'");

    expect(config('mail.from.address'))->not->toBeEmpty();
    expect(config('mail.from.name'))->not->toBeEmpty();
});

test('the default committed transport is log and no failover mailer is the default (RF-26)', function () {
    $mail = file_get_contents(base_path('config/mail.php'));

    expect($mail)->toContain("env('MAIL_MAILER', 'log')");
    expect(config('mail.default'))->not->toBe('failover');
});
