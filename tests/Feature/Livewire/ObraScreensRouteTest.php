<?php

use App\Actions\Pedidos\CreatePedidoAction;
use App\Models\EventType;
use App\Models\Obra;
use App\Models\Status;
use App\Models\User;
use App\Services\PedidoCodeGenerator;

beforeEach(function () {
    Status::factory()->solicitado()->create();
    EventType::factory()->criacaoPedido()->create();
});

test('all obra screens render without error over http', function () {
    $user = User::factory()->obra()->create();
    $obra = Obra::factory()->create();
    $user->obras()->attach($obra->id);

    $pedido = (new CreatePedidoAction(new PedidoCodeGenerator))->execute($user, [
        'obra_id' => $obra->id,
        'needed_at' => '2026-07-01',
        'items_description' => 'Cimento e areia',
    ]);

    $this->actingAs($user);

    $this->get(route('obra.nova-solicitacao'))->assertOk();
    $this->get(route('obra.pedidos.index'))->assertOk();
    $this->get(route('obra.pedidos.show', $pedido))->assertOk();
});
