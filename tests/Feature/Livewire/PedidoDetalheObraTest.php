<?php

use App\Actions\Pedidos\CreatePedidoAction;
use App\Livewire\Obra\PedidoDetalhe;
use App\Models\EventType;
use App\Models\Obra;
use App\Models\Pedido;
use App\Models\Status;
use App\Models\User;
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

    $pedido = app(CreatePedidoAction::class)->execute($requester, [
        'obra_selection' => $obra->id,
        'needed_at' => '2026-07-01',
        'descricao' => 'Cimento e areia',
    ]);

    $this->actingAs($requester);

    Livewire::test(PedidoDetalhe::class, ['pedido' => $pedido])
        ->assertSee($pedido->code)
        ->assertSee('Pedido criado');
});

test('obra detail renders no edit or Suprimentos control; only observação is offered', function () {
    $requester = User::factory()->obra()->create();
    $obra = Obra::factory()->create();
    $requester->obras()->attach($obra->id);

    $pedido = app(CreatePedidoAction::class)->execute($requester, [
        'obra_selection' => $obra->id,
        'needed_at' => '2026-07-01',
        'descricao' => 'Cimento e areia',
    ]);

    $this->actingAs($requester);

    $html = Livewire::test(PedidoDetalhe::class, ['pedido' => $pedido])->html();

    $targets = function (string $directive) use ($html): array {
        preg_match_all('/wire:'.$directive.'(?:\.[\w.-]+)?="([^"]*)"/', $html, $matches);

        return array_values(array_unique(array_map(
            fn (string $value): string => trim(explode('(', $value)[0]),
            $matches[1],
        )));
    };

    expect($targets('submit'))->toBe(['adicionarObservacao'])
        ->and($targets('model'))->toBe(['observacao'])
        ->and($targets('click'))->toBe([]);

    preg_match_all('/(?<![:\w-])name="([^"]*)"/', $html, $names);
    $referenced = strtolower(implode(' ', [...$targets('submit'), ...$targets('model'), ...$targets('click'), ...$names[1]]));

    foreach ([
        'obra_id', 'obra_selection', 'obra_reference', 'descricao', 'items_description', 'needed_at',
        'status_id', 'responsible_id', 'priority_id', 'expected_delivery_at', 'updatestatus', 'cancelarpedido',
        'romaneio', 'finalizar',
    ] as $forbidden) {
        expect($referenced)->not->toContain($forbidden);
    }
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
