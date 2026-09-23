<?php

use App\Actions\Pedidos\CreatePedidoAction;
use App\Models\EventType;
use App\Models\Obra;
use App\Models\Status;
use App\Models\User;
use App\Services\PedidoCodeGenerator;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Status::factory()->solicitado()->create();
    EventType::factory()->criacaoPedido()->create();
});

test('all obra screens render without error over http', function () {
    $user = User::factory()->obra()->create();
    $obra = Obra::factory()->create();
    $user->obras()->attach($obra->id);

    $pedido = (new CreatePedidoAction(new PedidoCodeGenerator))->execute($user, [
        'obra_selection' => $obra->id,
        'needed_at' => '2026-07-01',
        'descricao' => 'Cimento e areia',
    ]);

    $this->actingAs($user);

    $this->get(route('obra.nova-solicitacao'))->assertOk();
    $this->get(route('obra.pedidos.index'))->assertOk();
    $this->get(route('obra.pedidos.show', $pedido))->assertOk();
});

test('the obra Nova Solicitação route carries auth, active, can:is-obra and can:create-pedido (RF-01, CT-05)', function () {
    $middleware = Route::getRoutes()->getByName('obra.nova-solicitacao')->gatherMiddleware();

    expect($middleware)->toContain('auth', 'active', 'can:is-obra', 'can:create-pedido');
});

test('the obra Nova Solicitação route answers 403 for gestao and redirects a guest to login', function () {
    $this->get(route('obra.nova-solicitacao'))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->gestao()->create())
        ->get(route('obra.nova-solicitacao'))
        ->assertForbidden();
});
