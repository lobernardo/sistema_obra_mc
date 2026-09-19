<?php

use App\Actions\Pedidos\CreatePedidoAction;
use App\Livewire\Obra\PedidoDetalhe;
use App\Models\EventType;
use App\Models\Obra;
use App\Models\Pedido;
use App\Models\Status;
use App\Models\User;
use App\Services\PedidoCodeGenerator;
use Livewire\Livewire;

beforeEach(function () {
    Status::factory()->solicitado()->create();
    EventType::factory()->criacaoPedido()->create();
});

test('the criacao_pedido event is visible after the full creation flow', function () {
    $requester = User::factory()->obra()->create();
    $obra = Obra::factory()->create();
    $requester->obras()->attach($obra->id);

    $pedido = (new CreatePedidoAction(new PedidoCodeGenerator))->execute($requester, [
        'obra_id' => $obra->id,
        'needed_at' => '2026-07-01',
        'items_description' => 'Cimento e areia',
    ]);

    $this->actingAs($requester);

    Livewire::test(PedidoDetalhe::class, ['pedido' => $pedido])
        ->assertSee($pedido->code)
        ->assertSee('Criação do pedido');
});

test('no edit form or mutation control is rendered', function () {
    $requester = User::factory()->obra()->create();
    $obra = Obra::factory()->create();
    $requester->obras()->attach($obra->id);

    $pedido = (new CreatePedidoAction(new PedidoCodeGenerator))->execute($requester, [
        'obra_id' => $obra->id,
        'needed_at' => '2026-07-01',
        'items_description' => 'Cimento e areia',
    ]);

    $this->actingAs($requester);

    Livewire::test(PedidoDetalhe::class, ['pedido' => $pedido])
        ->assertDontSee('<form', false)
        ->assertDontSee('wire:click', false);
});

test('access to a pedido from an unassociated obra is denied', function () {
    $requester = User::factory()->obra()->create();
    $otherObra = Obra::factory()->create();
    $status = Status::query()->firstOrFail();
    $pedido = Pedido::factory()->create(['obra_id' => $otherObra->id, 'status_id' => $status->id]);

    $this->actingAs($requester);

    Livewire::test(PedidoDetalhe::class, ['pedido' => $pedido])->assertSee('403');
});
