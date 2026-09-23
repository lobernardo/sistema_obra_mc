<?php

use App\Actions\Pedidos\CreatePedidoAction;
use App\Livewire\Gestao\PedidoDetalhe;
use App\Models\EventType;
use App\Models\Obra;
use App\Models\Pedido;
use App\Models\Status;
use App\Models\User;
use App\Services\PedidoCodeGenerator;
use Livewire\Livewire;

beforeEach(function () {
    $this->solicitado = Status::factory()->solicitado()->create();
    EventType::factory()->criacaoPedido()->create();
});

test('non-gestao actors are denied access to the component', function (string $role) {
    $actor = User::factory()->{$role}()->create();
    $pedido = Pedido::factory()->create(['status_id' => $this->solicitado->id]);

    $this->actingAs($actor);

    Livewire::test(PedidoDetalhe::class, ['pedido' => $pedido])->assertSee('403');
})->with(['obra', 'suprimentos']);

test('the detail renders with the full history timeline', function () {
    $requester = User::factory()->obra()->create();
    $obra = Obra::factory()->create();
    $requester->obras()->attach($obra->id);

    $pedido = (new CreatePedidoAction(new PedidoCodeGenerator))->execute($requester, [
        'obra_selection' => $obra->id,
        'needed_at' => '2026-07-01',
        'descricao' => 'Cimento e areia',
    ]);

    $actor = User::factory()->gestao()->create();
    $this->actingAs($actor);

    Livewire::test(PedidoDetalhe::class, ['pedido' => $pedido])
        ->assertSee($pedido->code)
        ->assertSee('Criação do pedido');
});

test('no edit form or mutation control is rendered', function () {
    $requester = User::factory()->obra()->create();
    $obra = Obra::factory()->create();
    $requester->obras()->attach($obra->id);

    $pedido = (new CreatePedidoAction(new PedidoCodeGenerator))->execute($requester, [
        'obra_selection' => $obra->id,
        'needed_at' => '2026-07-01',
        'descricao' => 'Cimento e areia',
    ]);

    $actor = User::factory()->gestao()->create();
    $this->actingAs($actor);

    Livewire::test(PedidoDetalhe::class, ['pedido' => $pedido])
        ->assertDontSee('<form', false)
        ->assertDontSee('wire:click', false);
});

test('access to a pedido from any obra is allowed for gestao (unrestricted read)', function () {
    $actor = User::factory()->gestao()->create();
    $obra = Obra::factory()->create();
    $pedido = Pedido::factory()->create(['obra_id' => $obra->id, 'status_id' => $this->solicitado->id]);

    $this->actingAs($actor);

    Livewire::test(PedidoDetalhe::class, ['pedido' => $pedido])->assertSee($pedido->code);
});
