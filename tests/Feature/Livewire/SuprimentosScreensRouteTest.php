<?php

use App\Enums\StatusSlug;
use App\Models\Pedido;
use App\Models\Status;
use App\Models\User;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    seedWorkflowStatuses();
});

test('all suprimentos screens render without error over http', function () {
    $user = User::factory()->suprimentos()->create();
    $pedido = Pedido::factory()->create([
        'status_id' => Status::query()->where('slug', StatusSlug::Solicitado->value)->value('id'),
    ]);

    $this->actingAs($user);

    $this->get(route('suprimentos.pedidos.index'))->assertOk();
    $this->get(route('suprimentos.pedidos.show', $pedido))->assertOk();
    $this->get(route('suprimentos.kanban'))->assertOk();
    $this->get(route('suprimentos.visao-geral'))->assertOk();
    $this->get(route('suprimentos.nova-solicitacao'))->assertOk();
});

test('suprimentos screens are unreachable over http for non-suprimentos roles', function (string $role) {
    $user = User::factory()->{$role}()->create();

    $this->actingAs($user);

    $this->get(route('suprimentos.pedidos.index'))->assertForbidden();
    $this->get(route('suprimentos.kanban'))->assertForbidden();
    $this->get(route('suprimentos.visao-geral'))->assertForbidden();
    $this->get(route('suprimentos.nova-solicitacao'))->assertForbidden();
})->with(['obra', 'gestao']);

test('the suprimentos Nova Solicitação route carries auth, active, can:is-suprimentos and can:create-pedido (CT-05)', function () {
    $middleware = Route::getRoutes()->getByName('suprimentos.nova-solicitacao')->gatherMiddleware();

    expect($middleware)->toContain('auth', 'active', 'can:is-suprimentos', 'can:create-pedido');
});

test('a guest is redirected from the suprimentos Nova Solicitação route to login', function () {
    $this->get(route('suprimentos.nova-solicitacao'))->assertRedirect(route('login'));
});
