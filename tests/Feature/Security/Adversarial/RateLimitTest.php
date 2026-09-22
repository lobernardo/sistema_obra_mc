<?php

use App\Enums\AuthenticationEventType;
use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\LoginForm;
use App\Models\AuthenticationEvent;
use App\Models\User;
use App\Notifications\ResetPasswordPtBr;
use App\Services\AuthenticationRateLimiter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * RF-30 (G-12), RF-09..RF-12, AC-F14, AC-F15 — adversarial rate-limit suite
 * (decision D-12). The four limiters fixed in code by D-02 trip at exactly
 * their thresholds — one attempt below is still answered by the regular
 * path, the attempt at the limit is refused:
 *
 * | Limiter                    | Key                 | Limit | Window |
 * |----------------------------|---------------------|-------|--------|
 * | login per e-mail + IP      | sha256(email) | ip  | 5     | 60 s   |
 * | login per account          | sha256(email)       | 20    | 15 min |
 * | recovery per e-mail + IP   | sha256(email) | ip  | 3     | 60 s   |
 * | recovery per IP            | ip                  | 6     | 60 s   |
 *
 * Non-enumeration: the refused response is byte-identical for an existing
 * and an unknown e-mail. Trail: every refused login attempt, including the
 * limiter-tripped one, appends `login_failed` (D-08). Recovery: nothing is
 * e-mailed once the limiter trips. Duplicating Phase C proofs is intended.
 *
 * `Livewire::test` always submits from `127.0.0.1`; the multi-IP scenarios
 * drive the real `/livewire/update` transport with `REMOTE_ADDR` set.
 */
const ADVERSARIAL_RL_EMAIL = 'g12-conta@example.com';

const ADVERSARIAL_RL_UNKNOWN_EMAIL = 'g12-ninguem@example.com';

const ADVERSARIAL_RL_PASSWORD = 'g12-senha-correta-a1b2c3';

const ADVERSARIAL_RL_WRONG_PASSWORD = 'g12-senha-errada-x9y8z7';

const ADVERSARIAL_RL_IP = '127.0.0.1';

const ADVERSARIAL_RL_INVALID_MESSAGE = 'E-mail ou senha inválidos.';

const ADVERSARIAL_RL_RECOVERY_CONFIRMATION = 'Se o e-mail informado estiver cadastrado e ativo, você receberá um link para redefinir a senha em instantes.';

function adversarialLoginAttempt(string $email, string $password): Testable
{
    return Livewire::test(LoginForm::class)
        ->set('email', $email)
        ->set('password', $password)
        ->call('authenticate');
}

function adversarialNormalizeHtml(string $html): string
{
    return (string) preg_replace('/\s(wire:snapshot|wire:effects|wire:id)="[^"]*"/', '', $html);
}

function adversarialPageSnapshot(string $routeName): string
{
    preg_match('/wire:snapshot="([^"]+)"/', test()->get(route($routeName))->assertOk()->getContent(), $matches);

    expect($matches)->toHaveCount(2, 'No wire:snapshot found on the page.');

    return htmlspecialchars_decode($matches[1], ENT_QUOTES | ENT_SUBSTITUTE);
}

/**
 * @param  array<string, string>  $updates
 */
function adversarialLivewireCallFromIp(string $ip, string $snapshot, array $updates, string $method): TestResponse
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
                'updates' => $updates,
                'calls' => [['method' => $method, 'params' => []]],
            ]],
        ]);
}

/**
 * The `/livewire/update` JSON body decoded so PT-BR messages can be matched
 * as text (the transport escapes non-ASCII characters as `\uXXXX`).
 */
function adversarialTransportText(TestResponse $response): string
{
    return json_encode(json_decode($response->getContent(), true), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function adversarialRecoverySubmit(string $email): string
{
    $component = Livewire::test(ForgotPassword::class)
        ->set('email', $email)
        ->call('sendResetLink')
        ->assertOk()
        ->assertHasNoErrors()
        ->assertSet('sent', true)
        ->assertSee(ADVERSARIAL_RL_RECOVERY_CONFIRMATION);

    return adversarialNormalizeHtml($component->html());
}

function adversarialLoginFailedCount(?int $userId = null): int
{
    return AuthenticationEvent::query()
        ->where('event', AuthenticationEventType::LoginFailed->value)
        ->when($userId !== null, fn ($query) => $query->where('user_id', $userId))
        ->count();
}

function adversarialCreateAccount(string $email = ADVERSARIAL_RL_EMAIL): User
{
    return User::factory()->obra()->create([
        'email' => $email,
        'password' => Hash::make(ADVERSARIAL_RL_PASSWORD),
    ]);
}

describe('G-12 login limiters', function () {
    test('G-12 login e-mail + IP limiter trips at exactly 5 failures in 60 s: attempt 5 is a plain refusal, attempt 6 with the correct password is throttled, every attempt appends login_failed (RF-09, RF-10, D-08, AC-F14)', function () {
        $user = adversarialCreateAccount();
        $limiter = app(AuthenticationRateLimiter::class);

        for ($attempt = 1; $attempt <= 4; $attempt++) {
            adversarialLoginAttempt(ADVERSARIAL_RL_EMAIL, ADVERSARIAL_RL_WRONG_PASSWORD)->assertHasErrors(['email']);
        }

        expect($limiter->tooManyLoginAttempts(ADVERSARIAL_RL_EMAIL, ADVERSARIAL_RL_IP))->toBeFalse();

        $fifth = adversarialLoginAttempt(ADVERSARIAL_RL_EMAIL, ADVERSARIAL_RL_WRONG_PASSWORD)->assertHasErrors(['email']);

        expect($fifth->errors()->first('email'))->toBe(ADVERSARIAL_RL_INVALID_MESSAGE);
        expect(RateLimiter::attempts($limiter->loginKey(ADVERSARIAL_RL_EMAIL, ADVERSARIAL_RL_IP)))->toBe(5);
        expect($limiter->tooManyLoginAttempts(ADVERSARIAL_RL_EMAIL, ADVERSARIAL_RL_IP))->toBeTrue();
        expect(adversarialLoginFailedCount($user->id))->toBe(5);

        $sixth = adversarialLoginAttempt(ADVERSARIAL_RL_EMAIL, ADVERSARIAL_RL_PASSWORD)->assertHasErrors(['email'])->assertNoRedirect();

        expect($sixth->errors()->first('email'))->toBe(LoginForm::THROTTLED_MESSAGE);
        expect(Auth::check())->toBeFalse();
        expect(RateLimiter::attempts($limiter->loginKey(ADVERSARIAL_RL_EMAIL, ADVERSARIAL_RL_IP)))->toBe(5);
        expect(adversarialLoginFailedCount($user->id))->toBe(6);
        expect(AuthenticationEvent::query()->where('event', AuthenticationEventType::LoginSuccess->value)->count())->toBe(0);

        $this->travel(61)->seconds();

        adversarialLoginAttempt(ADVERSARIAL_RL_EMAIL, ADVERSARIAL_RL_PASSWORD)->assertHasNoErrors()->assertRedirect(route('home'));
        expect(Auth::id())->toBe($user->id);
        expect(adversarialLoginFailedCount($user->id))->toBe(6);
    });

    test('G-12 login throttled response is byte-identical for an existing and an unknown e-mail, and the unknown e-mail leaves login_failed rows with user_id null (RF-09, UI-02)', function () {
        $user = adversarialCreateAccount();

        foreach ([ADVERSARIAL_RL_EMAIL, ADVERSARIAL_RL_UNKNOWN_EMAIL] as $email) {
            for ($attempt = 1; $attempt <= 5; $attempt++) {
                adversarialLoginAttempt($email, ADVERSARIAL_RL_WRONG_PASSWORD)->assertHasErrors(['email']);
            }
        }

        $existing = adversarialLoginAttempt(ADVERSARIAL_RL_EMAIL, ADVERSARIAL_RL_PASSWORD)->assertHasErrors(['email']);
        $unknown = adversarialLoginAttempt(ADVERSARIAL_RL_UNKNOWN_EMAIL, ADVERSARIAL_RL_PASSWORD)->assertHasErrors(['email']);

        expect($existing->errors()->all())->toBe($unknown->errors()->all());
        expect($existing->errors()->first('email'))->toBe(LoginForm::THROTTLED_MESSAGE);
        expect(adversarialNormalizeHtml($existing->html()))->toBe(adversarialNormalizeHtml($unknown->html()));
        expect(Auth::check())->toBeFalse();

        expect(adversarialLoginFailedCount($user->id))->toBe(6);
        expect(AuthenticationEvent::query()
            ->where('event', AuthenticationEventType::LoginFailed->value)
            ->where('email', ADVERSARIAL_RL_UNKNOWN_EMAIL)
            ->whereNull('user_id')
            ->count())->toBe(6);
    });

    test('G-12 login account limiter trips at exactly 20 failures in 15 min across IPs: attempt 20 is a plain refusal, attempt 21 from a fresh IP with the correct password is throttled and recorded (RF-09, RF-10, D-01, AC-F14)', function () {
        $user = adversarialCreateAccount();
        $limiter = app(AuthenticationRateLimiter::class);
        $snapshot = adversarialPageSnapshot('login');

        for ($attempt = 0; $attempt < 19; $attempt++) {
            $ip = '198.51.100.'.(1 + intdiv($attempt, 4));

            adversarialLivewireCallFromIp($ip, $snapshot, ['email' => ADVERSARIAL_RL_EMAIL, 'password' => ADVERSARIAL_RL_WRONG_PASSWORD], 'authenticate')->assertOk();
        }

        expect(RateLimiter::attempts($limiter->loginAccountKey(ADVERSARIAL_RL_EMAIL)))->toBe(19);
        expect($limiter->tooManyLoginAttempts(ADVERSARIAL_RL_EMAIL, '198.51.100.5'))->toBeFalse();

        $twentieth = adversarialLivewireCallFromIp('198.51.100.5', $snapshot, ['email' => ADVERSARIAL_RL_EMAIL, 'password' => ADVERSARIAL_RL_WRONG_PASSWORD], 'authenticate')->assertOk();

        expect(adversarialTransportText($twentieth))->toContain(ADVERSARIAL_RL_INVALID_MESSAGE)->not->toContain(LoginForm::THROTTLED_MESSAGE);
        expect(RateLimiter::attempts($limiter->loginAccountKey(ADVERSARIAL_RL_EMAIL)))->toBe(20);
        expect(RateLimiter::attempts($limiter->loginKey(ADVERSARIAL_RL_EMAIL, '198.51.100.6')))->toBe(0);
        expect($limiter->tooManyLoginAttempts(ADVERSARIAL_RL_EMAIL, '198.51.100.6'))->toBeTrue();
        expect(adversarialLoginFailedCount($user->id))->toBe(20);

        $refused = adversarialLivewireCallFromIp('198.51.100.6', $snapshot, ['email' => ADVERSARIAL_RL_EMAIL, 'password' => ADVERSARIAL_RL_PASSWORD], 'authenticate')->assertOk();

        expect($refused->status())->not->toBe(429);
        expect(adversarialTransportText($refused))->toContain(LoginForm::THROTTLED_MESSAGE);
        expect(Auth::check())->toBeFalse();
        expect(adversarialLoginFailedCount($user->id))->toBe(21);

        $this->travel(61)->seconds();

        $stillRefused = adversarialLivewireCallFromIp('198.51.100.7', $snapshot, ['email' => ADVERSARIAL_RL_EMAIL, 'password' => ADVERSARIAL_RL_PASSWORD], 'authenticate')->assertOk();

        expect(adversarialTransportText($stillRefused))->toContain(LoginForm::THROTTLED_MESSAGE);
        expect(Auth::check())->toBeFalse();

        $this->travel(15)->minutes();

        $accepted = adversarialLivewireCallFromIp('198.51.100.8', $snapshot, ['email' => ADVERSARIAL_RL_EMAIL, 'password' => ADVERSARIAL_RL_PASSWORD], 'authenticate')->assertOk();

        expect(adversarialTransportText($accepted))->not->toContain(LoginForm::THROTTLED_MESSAGE)->not->toContain(ADVERSARIAL_RL_INVALID_MESSAGE);
        expect(Auth::id())->toBe($user->id);
    });
});

describe('G-12 recovery limiters', function () {
    beforeEach(function () {
        Notification::fake();
    });

    test('G-12 recovery e-mail + IP limiter trips at exactly 3 submissions in 60 s: the 4th sends nothing, does not reach the broker and renders the same confirmation as an unknown e-mail (RF-11, UI-03, AC-F15)', function () {
        $user = adversarialCreateAccount();
        $limiter = app(AuthenticationRateLimiter::class);

        $unknownHtml = adversarialRecoverySubmit(ADVERSARIAL_RL_UNKNOWN_EMAIL);

        $first = adversarialRecoverySubmit(ADVERSARIAL_RL_EMAIL);
        adversarialRecoverySubmit(ADVERSARIAL_RL_EMAIL);

        expect($limiter->tooManyRecoveryAttempts(ADVERSARIAL_RL_EMAIL, ADVERSARIAL_RL_IP))->toBeFalse();

        adversarialRecoverySubmit(ADVERSARIAL_RL_EMAIL);

        expect(RateLimiter::attempts($limiter->recoveryKey(ADVERSARIAL_RL_EMAIL, ADVERSARIAL_RL_IP)))->toBe(3);
        expect($limiter->tooManyRecoveryAttempts(ADVERSARIAL_RL_EMAIL, ADVERSARIAL_RL_IP))->toBeTrue();
        Notification::assertSentTimes(ResetPasswordPtBr::class, 1);

        DB::table('password_reset_tokens')->where('email', ADVERSARIAL_RL_EMAIL)->delete();

        $fourth = adversarialRecoverySubmit(ADVERSARIAL_RL_EMAIL);

        expect($fourth)->toBe($first)->toBe($unknownHtml);
        expect($fourth)->not->toContain('Muitas tentativas');
        expect(DB::table('password_reset_tokens')->where('email', ADVERSARIAL_RL_EMAIL)->exists())->toBeFalse();
        expect(RateLimiter::attempts($limiter->recoveryKey(ADVERSARIAL_RL_EMAIL, ADVERSARIAL_RL_IP)))->toBe(4);
        Notification::assertSentTimes(ResetPasswordPtBr::class, 1);
        Notification::assertSentTo($user, ResetPasswordPtBr::class);

        $this->travel(61)->seconds();

        adversarialRecoverySubmit(ADVERSARIAL_RL_EMAIL);

        expect(DB::table('password_reset_tokens')->where('email', ADVERSARIAL_RL_EMAIL)->exists())->toBeTrue();
        Notification::assertSentTimes(ResetPasswordPtBr::class, 2);
    });

    test('G-12 recovery per-IP limiter trips at exactly 6 submissions in 60 s: the 7th for a valid active account sends nothing and creates no token, another IP still gets through (RF-11, AC-F15)', function () {
        $user = adversarialCreateAccount();
        $limiter = app(AuthenticationRateLimiter::class);
        $snapshot = adversarialPageSnapshot('password.request');

        for ($index = 1; $index <= 5; $index++) {
            adversarialLivewireCallFromIp('198.51.100.1', $snapshot, ['email' => "g12-desconhecida-{$index}@example.com"], 'sendResetLink')->assertOk();
        }

        expect(RateLimiter::attempts($limiter->recoveryIpKey('198.51.100.1')))->toBe(5);
        expect($limiter->tooManyRecoveryAttempts(ADVERSARIAL_RL_EMAIL, '198.51.100.1'))->toBeFalse();

        adversarialLivewireCallFromIp('198.51.100.1', $snapshot, ['email' => 'g12-desconhecida-6@example.com'], 'sendResetLink')->assertOk();

        expect(RateLimiter::attempts($limiter->recoveryIpKey('198.51.100.1')))->toBe(6);
        expect(RateLimiter::attempts($limiter->recoveryKey(ADVERSARIAL_RL_EMAIL, '198.51.100.1')))->toBe(0);
        expect($limiter->tooManyRecoveryAttempts(ADVERSARIAL_RL_EMAIL, '198.51.100.1'))->toBeTrue();
        Notification::assertNothingSent();

        $seventh = adversarialLivewireCallFromIp('198.51.100.1', $snapshot, ['email' => ADVERSARIAL_RL_EMAIL], 'sendResetLink')->assertOk();

        expect($seventh->status())->not->toBe(429);
        expect(adversarialTransportText($seventh))->toContain(ADVERSARIAL_RL_RECOVERY_CONFIRMATION)->not->toContain('Muitas tentativas');
        Notification::assertNothingSent();
        expect(DB::table('password_reset_tokens')->where('email', ADVERSARIAL_RL_EMAIL)->exists())->toBeFalse();
        expect(RateLimiter::attempts($limiter->recoveryIpKey('198.51.100.1')))->toBe(7);

        adversarialLivewireCallFromIp('198.51.100.2', $snapshot, ['email' => ADVERSARIAL_RL_EMAIL], 'sendResetLink')->assertOk();

        Notification::assertSentTimes(ResetPasswordPtBr::class, 1);
        Notification::assertSentTo($user, ResetPasswordPtBr::class);
        expect(DB::table('password_reset_tokens')->where('email', ADVERSARIAL_RL_EMAIL)->exists())->toBeTrue();
    });

    test('G-12 case and whitespace variants of the e-mail share the login and recovery counters (RF-12)', function () {
        adversarialCreateAccount();
        $limiter = app(AuthenticationRateLimiter::class);

        foreach (['G12-Conta@Example.com', ' g12-conta@example.com ', "G12-CONTA@EXAMPLE.COM\t", 'g12-conta@example.com', ' G12-conta@example.COM'] as $variant) {
            adversarialLoginAttempt($variant, ADVERSARIAL_RL_WRONG_PASSWORD)
                ->assertSet('email', ADVERSARIAL_RL_EMAIL)
                ->assertHasErrors(['email']);
        }

        expect(RateLimiter::attempts($limiter->loginKey(ADVERSARIAL_RL_EMAIL, ADVERSARIAL_RL_IP)))->toBe(5);
        expect($limiter->tooManyLoginAttempts(ADVERSARIAL_RL_EMAIL, ADVERSARIAL_RL_IP))->toBeTrue();

        adversarialLoginAttempt(' G12-Conta@Example.COM ', ADVERSARIAL_RL_PASSWORD)->assertHasErrors(['email']);
        expect(Auth::check())->toBeFalse();

        foreach (['G12-Conta@Example.com', ' g12-conta@example.com ', "G12-CONTA@EXAMPLE.COM\t"] as $variant) {
            Livewire::test(ForgotPassword::class)
                ->set('email', $variant)
                ->call('sendResetLink')
                ->assertSet('email', ADVERSARIAL_RL_EMAIL)
                ->assertSet('sent', true);
        }

        expect(RateLimiter::attempts($limiter->recoveryKey(ADVERSARIAL_RL_EMAIL, ADVERSARIAL_RL_IP)))->toBe(3);
        expect($limiter->tooManyRecoveryAttempts(ADVERSARIAL_RL_EMAIL, ADVERSARIAL_RL_IP))->toBeTrue();
        Notification::assertSentTimes(ResetPasswordPtBr::class, 1);
    });
});
