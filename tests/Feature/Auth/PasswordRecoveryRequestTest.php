<?php

use App\Livewire\Auth\ForgotPassword;
use App\Models\User;
use App\Notifications\ResetPasswordPtBr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

const GENERIC_CONFIRMATION = 'Se o e-mail informado estiver cadastrado e ativo, você receberá um link para redefinir a senha em instantes.';

/**
 * Strips the per-instance Livewire attributes so two renders of the same
 * component can be compared as the DOM a visitor actually receives.
 */
function normalizeLivewireHtml(string $html): string
{
    return (string) preg_replace('/\s(wire:snapshot|wire:effects|wire:id)="[^"]*"/', '', $html);
}

/**
 * Submits the recovery form as a fresh visitor and returns the normalized
 * HTML, asserting the request itself succeeded.
 */
function submitRecoveryFor(string $email): string
{
    $component = Livewire::test(ForgotPassword::class)
        ->set('email', $email)
        ->call('sendResetLink')
        ->assertOk()
        ->assertHasNoErrors()
        ->assertSet('sent', true)
        ->assertSee(GENERIC_CONFIRMATION)
        ->assertDontSee('Enviar link de redefinição');

    return normalizeLivewireHtml($component->html());
}

beforeEach(function () {
    Notification::fake();
});

test('the recovery page is a guest route named password.request that asks for the e-mail (RF-19, CT-02)', function () {
    $route = app('router')->getRoutes()->getByName('password.request');

    expect($route)->not->toBeNull();
    expect($route->middleware())->toContain('guest');

    $this->get(route('password.request'))
        ->assertOk()
        ->assertSee('Esqueci minha senha')
        ->assertSee('E-mail')
        ->assertSeeHtml('type="email"')
        ->assertSee('Voltar ao login')
        ->assertSee(route('login'));

    $this->actingAs(User::factory()->obra()->create());

    $this->get(route('password.request'))->assertRedirect();
});

test('an invalid or missing e-mail shows a PT-BR field error and sends nothing', function () {
    Livewire::test(ForgotPassword::class)
        ->set('email', '')
        ->call('sendResetLink')
        ->assertHasErrors(['email' => 'required'])
        ->assertSee('Informe o e-mail.')
        ->assertSet('sent', false);

    Livewire::test(ForgotPassword::class)
        ->set('email', 'nao-e-email')
        ->call('sendResetLink')
        ->assertHasErrors(['email' => 'email'])
        ->assertSee('Informe um e-mail válido.')
        ->assertSet('sent', false);

    Notification::assertNothingSent();
});

test('an active user receives the PT-BR reset link, a token row is stored and the page shows the generic confirmation (TC-10, RF-20)', function () {
    $user = User::factory()->obra()->create(['email' => 'ativa@example.com']);

    submitRecoveryFor('ativa@example.com');

    Notification::assertSentTo($user, ResetPasswordPtBr::class);
    Notification::assertCount(1);
    expect(DB::table('password_reset_tokens')->where('email', 'ativa@example.com')->exists())->toBeTrue();
});

test('unknown, inactive and throttled e-mails render byte-identical output to the active case and send nothing extra (RF-21, RNF-04, TC-15, TC-25)', function () {
    $active = User::factory()->obra()->create(['email' => 'ativa@example.com']);
    $inactive = User::factory()->suprimentos()->inactive()->create(['email' => 'inativa@example.com']);

    $activeHtml = submitRecoveryFor('ativa@example.com');
    $throttledHtml = submitRecoveryFor('ativa@example.com');
    $unknownHtml = submitRecoveryFor('desconhecida@example.com');
    $inactiveHtml = submitRecoveryFor('inativa@example.com');

    expect($throttledHtml)->toBe($activeHtml);
    expect($unknownHtml)->toBe($activeHtml);
    expect($inactiveHtml)->toBe($activeHtml);

    Notification::assertSentTimes(ResetPasswordPtBr::class, 1);
    Notification::assertSentTo($active, ResetPasswordPtBr::class);
    Notification::assertNotSentTo($inactive, ResetPasswordPtBr::class);

    expect(DB::table('password_reset_tokens')->where('email', 'ativa@example.com')->count())->toBe(1);
    expect(DB::table('password_reset_tokens')->where('email', 'inativa@example.com')->exists())->toBeFalse();
    expect(DB::table('password_reset_tokens')->where('email', 'desconhecida@example.com')->exists())->toBeFalse();
});

test('the same component instance renders identically before and after the broker status changes', function () {
    $component = Livewire::test(ForgotPassword::class)->set('email', 'alguem@example.com');

    $unknown = normalizeLivewireHtml($component->call('sendResetLink')->assertOk()->html());

    User::factory()->gestao()->create(['email' => 'alguem@example.com']);

    $sent = normalizeLivewireHtml($component->call('sendResetLink')->assertOk()->html());
    $throttled = normalizeLivewireHtml($component->call('sendResetLink')->assertOk()->html());

    expect($sent)->toBe($unknown);
    expect($throttled)->toBe($unknown);

    Notification::assertSentTimes(ResetPasswordPtBr::class, 1);
});

test('after the throttle window a second link can be requested', function () {
    $user = User::factory()->obra()->create(['email' => 'ativa@example.com']);

    submitRecoveryFor('ativa@example.com');

    $this->travel(61)->seconds();

    submitRecoveryFor('ativa@example.com');

    Notification::assertSentTimes(ResetPasswordPtBr::class, 2);
});

test('recovery is available to obra, suprimentos and gestao users alike (RF-24)', function (string $role) {
    $user = User::factory()->{$role}()->create(['email' => "{$role}@example.com"]);

    submitRecoveryFor("{$role}@example.com");

    Notification::assertSentTo($user, ResetPasswordPtBr::class);
    expect(DB::table('password_reset_tokens')->where('email', "{$role}@example.com")->exists())->toBeTrue();
})->with(['obra', 'suprimentos', 'gestao']);

test('the recovery responses never contain a password hash', function () {
    $user = User::factory()->obra()->create(['email' => 'ativa@example.com']);

    $html = submitRecoveryFor('ativa@example.com');

    expect($html)->not->toContain($user->password)->not->toContain('$2y$');
});
