<?php

use App\Exceptions\ObraInvitations\ObraInvitationUnavailableException;
use App\Livewire\Auth\LoginForm;
use App\Models\Obra;
use App\Models\ObraInvitation;
use App\Models\User;
use Illuminate\Support\Facades\Route;

test('the unavailable page answers 404 with the generic text, a login link and no convite data (RF-28, RNF-08)', function () {
    $obra = Obra::factory()->create(['name' => 'Residencial Sigiloso']);
    $creator = User::factory()->gestao()->create(['name' => 'Criador Secreto', 'email' => 'criador@example.com']);
    ObraInvitation::factory()->for($obra)->create(['created_by' => $creator->id]);

    $response = $this->get(route('obra-invitation.unavailable'))->assertNotFound();

    expect(route('obra-invitation.unavailable'))->toEndWith('/convite/indisponivel');

    $response
        ->assertSee('Este convite é inválido, expirou ou já foi utilizado. Peça um novo convite à Gestão ou a Suprimentos.')
        ->assertSee(route('login'), false)
        ->assertSee(config('app.name'))
        ->assertDontSee('Residencial Sigiloso')
        ->assertDontSee('Criador Secreto')
        ->assertDontSee('criador@example.com');

    expect($response->getContent())->not->toMatch('/<form\b/i')->not->toMatch('/<input\b/i');
    expect(ObraInvitationUnavailableException::MESSAGE)->toBe('Este convite é inválido, expirou ou já foi utilizado. Peça um novo convite à Gestão ou a Suprimentos.');
});

test('the unavailable page is the same for an authenticated user', function () {
    $this->actingAs(User::factory()->obra()->create())
        ->get(route('obra-invitation.unavailable'))
        ->assertNotFound()
        ->assertSee(ObraInvitationUnavailableException::MESSAGE);
});

test('the throttled page answers 429 with the PT-BR limiter message (RF-19b)', function () {
    $response = $this->get(route('obra-invitation.throttled'))->assertStatus(429);

    expect(route('obra-invitation.throttled'))->toEndWith('/convite/limite');

    $response->assertSee(LoginForm::THROTTLED_MESSAGE)->assertSee(route('login'), false);

    expect($response->getContent())->not->toMatch('/<form\b/i');
});

test('both outcome routes have no parameter and neither guest nor auth middleware', function (string $name) {
    $route = Route::getRoutes()->getByName($name);

    expect($route->parameterNames())->toBe([]);
    expect($route->uri())->not->toContain('{');
    expect($route->gatherMiddleware())->not->toContain('guest')->not->toContain('auth');
})->with(['obra-invitation.unavailable', 'obra-invitation.throttled']);
