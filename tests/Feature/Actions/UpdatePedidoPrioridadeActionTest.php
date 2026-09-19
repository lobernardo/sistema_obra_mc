<?php

use App\Actions\Pedidos\UpdatePedidoPrioridadeAction;
use App\Enums\EventTypeSlug;
use App\Exceptions\Pedidos\PedidoTerminalStateException;
use App\Models\EventType;
use App\Models\Pedido;
use App\Models\Priority;
use App\Models\Status;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->solicitado = Status::factory()->solicitado()->create();
    $this->entregue = Status::factory()->entregue()->create();
    $this->normal = Priority::factory()->normal()->create();
    $this->urgente = Priority::factory()->urgente()->create();
    EventType::factory()->alteracaoPrioridade()->create();
    $this->action = new UpdatePedidoPrioridadeAction;
});

test('a valid change generates exactly 1 alteracao_prioridade event', function () {
    $actor = User::factory()->suprimentos()->create();
    $pedido = Pedido::factory()->create(['status_id' => $this->solicitado->id, 'priority_id' => $this->normal->id]);

    $updated = $this->action->execute($actor, $pedido, $this->urgente->id);

    expect($updated->priority_id)->toBe($this->urgente->id);
    expect($pedido->fresh()->events()->count())->toBe(1);
    expect($pedido->fresh()->events()->first()->eventType->slug)->toBe(EventTypeSlug::AlteracaoPrioridade->value);
});

test('setting the same priority is a no-op and generates no event', function () {
    $actor = User::factory()->suprimentos()->create();
    $pedido = Pedido::factory()->create(['status_id' => $this->solicitado->id, 'priority_id' => $this->normal->id]);

    $this->action->execute($actor, $pedido, $this->normal->id);

    expect($pedido->fresh()->events()->count())->toBe(0);
});

test('a value outside the 4 seeded priorities is rejected', function () {
    $actor = User::factory()->suprimentos()->create();
    $pedido = Pedido::factory()->create(['status_id' => $this->solicitado->id, 'priority_id' => $this->normal->id]);
    $nonExistentPriorityId = Priority::query()->max('id') + 1000;

    expect(fn () => $this->action->execute($actor, $pedido, $nonExistentPriorityId))
        ->toThrow(ValidationException::class);

    expect($pedido->fresh()->priority_id)->toBe($this->normal->id);
    expect($pedido->fresh()->events()->count())->toBe(0);
});

test('a non-suprimentos actor is rejected', function () {
    $actor = User::factory()->obra()->create();
    $pedido = Pedido::factory()->create(['status_id' => $this->solicitado->id, 'priority_id' => $this->normal->id]);

    expect(fn () => $this->action->execute($actor, $pedido, $this->urgente->id))
        ->toThrow(AuthorizationException::class);

    expect($pedido->fresh()->events()->count())->toBe(0);
});

test('a terminal pedido rejects the mutation', function () {
    $actor = User::factory()->suprimentos()->create();
    $pedido = Pedido::factory()->create(['status_id' => $this->entregue->id, 'priority_id' => $this->normal->id]);

    expect(fn () => $this->action->execute($actor, $pedido, $this->urgente->id))
        ->toThrow(PedidoTerminalStateException::class);

    expect($pedido->fresh()->events()->count())->toBe(0);
});
