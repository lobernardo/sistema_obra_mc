<?php

use App\Actions\Pedidos\CreatePedidoAction;
use App\Livewire\Obra\PedidoDetalhe;
use App\Models\EventType;
use App\Models\Obra;
use App\Models\Pedido;
use App\Models\Status;
use App\Models\User;
use App\Services\PedidoCodeGenerator;
use Illuminate\Auth\Access\AuthorizationException;
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

test('the detail route returns 403 for a pedido from another obra', function () {
    $requester = User::factory()->obra()->create();
    $requester->obras()->attach(Obra::factory()->create());
    $pedido = Pedido::factory()->for(Status::query()->firstOrFail())->create();

    $this->actingAs($requester)
        ->get(route('obra.pedidos.show', $pedido))
        ->assertForbidden();
});

test('a forged detail mount throws the scope authorization exception', function () {
    $requester = User::factory()->obra()->create();
    $requester->obras()->attach(Obra::factory()->create());
    $pedido = Pedido::factory()->for(Status::query()->firstOrFail())->create();
    $this->actingAs($requester);
    $this->withoutExceptionHandling();

    expect(fn () => Livewire::test(PedidoDetalhe::class, ['pedido' => $pedido]))
        ->toThrow(AuthorizationException::class, 'Pedido fora do escopo do solicitante.');
});

test('the detail route returns 404 when the pedido does not exist', function () {
    $requester = User::factory()->obra()->create();

    $this->actingAs($requester)
        ->get(route('obra.pedidos.show', ['pedido' => 999999999]))
        ->assertNotFound();
});

test('the detail route returns 200 for a pedido from an associated obra', function () {
    $requester = User::factory()->obra()->create();
    $pedido = Pedido::factory()->for(Status::query()->firstOrFail())
        ->for($requester, 'requester')->create();

    $this->actingAs($requester)
        ->get(route('obra.pedidos.show', $pedido))
        ->assertOk()
        ->assertSee($pedido->code);
});
