<?php

use App\Livewire\Auth\ForgotPassword;
use App\Models\User;
use App\Notifications\ResetPasswordPtBr;
use App\Services\AuthenticationRateLimiter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;

const RECOVERY_RL_CONFIRMATION = 'Se o e-mail informado estiver cadastrado e ativo, você receberá um link para redefinir a senha em instantes.';

const RECOVERY_RL_IP = '127.0.0.1';

/**
 * Strips the per-instance Livewire attributes so two renders of the recovery
 * form can be compared byte for byte (UI-03, RNF-11).
 */
function normalizeRecoveryHtml(string $html): string
{
    return (string) preg_replace('/\s(wire:snapshot|wire:effects|wire:id)="[^"]*"/', '', $html);
}

/**
 * Submits the recovery form as a fresh visitor (always from 127.0.0.1 under
 * `Livewire::test`) and returns the normalized HTML, asserting the fixed
 * confirmation state every outcome must render.
 */
function submitRecoveryAttempt(string $email): string
{
    $component = Livewire::test(ForgotPassword::class)
        ->set('email', $email)
        ->call('sendResetLink')
        ->assertOk()
        ->assertHasNoErrors()
        ->assertSet('sent', true)
        ->assertSee(RECOVERY_RL_CONFIRMATION)
        ->assertDontSee('Enviar link de redefinição');

    return normalizeRecoveryHtml($component->html());
}

/**
 * Removes the broker's token row so its own 60 s throttle cannot mask the
 * limiter: if the broker were reached again it would send a new link.
 */
function forgetRecoveryToken(string $email): void
{
    DB::table('password_reset_tokens')->where('email', $email)->delete();
}

function recoveryPageSnapshot(): string
{
    preg_match('/wire:snapshot="([^"]+)"/', test()->get(route('password.request'))->assertOk()->getContent(), $matches);

    expect($matches)->toHaveCount(2, 'No wire:snapshot found on the recovery page.');

    return htmlspecialchars_decode($matches[1], ENT_QUOTES | ENT_SUBSTITUTE);
}

/**
 * Submits the recovery form through the real `/livewire/update` transport
 * from a given client IP (see `LoginRateLimitTest::attemptLoginFromIp`).
 */
function submitRecoveryFromIp(string $ip, string $snapshot, string $email): TestResponse
{
    Livewire::flushState();

    $updateUri = app('router')->getRoutes()->getByName('default-livewire.update')->uri();

    return test()
        ->withServerVariables(['REMOTE_ADDR' => $ip])
        ->withHeaders(['X-Livewire' => 'true'])
        ->postJson('/'.$updateUri, [
            '_token' => csrf_token(),
            'components' => [[
                'snapshot' => $snapshot,
                'updates' => ['email' => $email],
                'calls' => [['method' => 'sendResetLink', 'params' => []]],
            ]],
        ]);
}

beforeEach(function () {
    Notification::fake();
});

test('4 submissions for the same active e-mail from one IP send exactly one link and render identically (RF-11, UI-03)', function () {
    $user = User::factory()->obra()->create(['email' => 'ativa@example.com']);
    $limiter = app(AuthenticationRateLimiter::class);

    $first = submitRecoveryAttempt('ativa@example.com');
    submitRecoveryAttempt('ativa@example.com');
    submitRecoveryAttempt('ativa@example.com');

    expect(RateLimiter::attempts($limiter->recoveryKey('ativa@example.com', RECOVERY_RL_IP)))->toBe(3);
    expect($limiter->tooManyRecoveryAttempts('ativa@example.com', RECOVERY_RL_IP))->toBeTrue();

    forgetRecoveryToken('ativa@example.com');

    $fourth = submitRecoveryAttempt('ativa@example.com');

    expect($fourth)->toBe($first);
    expect(RateLimiter::attempts($limiter->recoveryKey('ativa@example.com', RECOVERY_RL_IP)))->toBe(4);
    expect(RateLimiter::attempts($limiter->recoveryIpKey(RECOVERY_RL_IP)))->toBe(4);
    expect(DB::table('password_reset_tokens')->where('email', 'ativa@example.com')->exists())->toBeFalse();

    Notification::assertSentTimes(ResetPasswordPtBr::class, 1);
    Notification::assertSentTo($user, ResetPasswordPtBr::class);
});

test('6 submissions for distinct unknown e-mails from one IP block the 7th even for a valid active account (RF-11)', function () {
    $user = User::factory()->gestao()->create(['email' => 'ativa@example.com']);
    $limiter = app(AuthenticationRateLimiter::class);

    for ($index = 1; $index <= 6; $index++) {
        submitRecoveryAttempt("desconhecida-{$index}@example.com");
    }

    expect(RateLimiter::attempts($limiter->recoveryIpKey(RECOVERY_RL_IP)))->toBe(6);
    expect(RateLimiter::attempts($limiter->recoveryKey('ativa@example.com', RECOVERY_RL_IP)))->toBe(0);

    submitRecoveryAttempt('ativa@example.com');

    Notification::assertNothingSent();
    expect(DB::table('password_reset_tokens')->where('email', 'ativa@example.com')->exists())->toBeFalse();
    expect(RateLimiter::attempts($limiter->recoveryIpKey(RECOVERY_RL_IP)))->toBe(7);

    Carbon::setTestNow(now()->addSeconds(61));

    submitRecoveryAttempt('ativa@example.com');

    Notification::assertSentTimes(ResetPasswordPtBr::class, 1);
    Notification::assertSentTo($user, ResetPasswordPtBr::class);
    expect(DB::table('password_reset_tokens')->where('email', 'ativa@example.com')->exists())->toBeTrue();
});

test('the per-IP limiter is scoped to the client IP: another IP still reaches the broker (RF-11)', function () {
    $user = User::factory()->suprimentos()->create(['email' => 'ativa@example.com']);
    $snapshot = recoveryPageSnapshot();

    for ($index = 1; $index <= 6; $index++) {
        submitRecoveryFromIp('198.51.100.1', $snapshot, "desconhecida-{$index}@example.com")->assertOk();
    }

    submitRecoveryFromIp('198.51.100.1', $snapshot, 'ativa@example.com')->assertOk();

    Notification::assertNothingSent();

    $otherIp = submitRecoveryFromIp('198.51.100.2', $snapshot, 'ativa@example.com')->assertOk();

    expect($otherIp->status())->not->toBe(429);
    Notification::assertSentTimes(ResetPasswordPtBr::class, 1);
    Notification::assertSentTo($user, ResetPasswordPtBr::class);
});

test('case and whitespace variants share the e-mail + IP counter and trip at 3 in total (RF-12)', function () {
    $user = User::factory()->obra()->create(['email' => 'ativa@example.com']);
    $limiter = app(AuthenticationRateLimiter::class);

    foreach (['Ativa@Example.com', ' ativa@example.com ', "ATIVA@EXAMPLE.COM\t"] as $variant) {
        Livewire::test(ForgotPassword::class)
            ->set('email', $variant)
            ->call('sendResetLink')
            ->assertSet('email', 'ativa@example.com')
            ->assertHasNoErrors()
            ->assertSet('sent', true);
    }

    expect(RateLimiter::attempts($limiter->recoveryKey('ativa@example.com', RECOVERY_RL_IP)))->toBe(3);
    expect($limiter->tooManyRecoveryAttempts('ativa@example.com', RECOVERY_RL_IP))->toBeTrue();
    expect($limiter->tooManyRecoveryAttempts('outra@example.com', RECOVERY_RL_IP))->toBeFalse();

    forgetRecoveryToken('ativa@example.com');

    submitRecoveryAttempt(' Ativa@Example.COM ');

    Notification::assertSentTimes(ResetPasswordPtBr::class, 1);
    Notification::assertSentTo($user, ResetPasswordPtBr::class);
    expect(DB::table('password_reset_tokens')->where('email', 'ativa@example.com')->exists())->toBeFalse();
});

test('a refused submission renders the same fixed confirmation as unknown and inactive e-mails, with no error (UI-03, RNF-11)', function () {
    User::factory()->obra()->create(['email' => 'ativa@example.com']);
    User::factory()->obra()->inactive()->create(['email' => 'inativa@example.com']);

    $unknownHtml = submitRecoveryAttempt('desconhecida@example.com');
    $inactiveHtml = submitRecoveryAttempt('inativa@example.com');

    for ($index = 1; $index <= 3; $index++) {
        submitRecoveryAttempt('ativa@example.com');
    }

    forgetRecoveryToken('ativa@example.com');

    $refusedHtml = submitRecoveryAttempt('ativa@example.com');

    expect($refusedHtml)->toBe($unknownHtml)->toBe($inactiveHtml);
    expect($refusedHtml)->not->toContain('Muitas tentativas')->not->toContain('role="alert"');

    Notification::assertSentTimes(ResetPasswordPtBr::class, 1);
});
