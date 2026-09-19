<?php

use App\Actions\Pedidos\UpdatePedidoResponsavelAction;
use App\Enums\EventTypeSlug;
use App\Exceptions\Pedidos\PedidoTerminalStateException;
use App\Models\EventType;
use App\Models\Pedido;
use App\Models\Status;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->solicitado = Status::factory()->solicitado()->create();
    $this->entregue = Status::factory()->entregue()->create();
    EventType::factory()->alteracaoResponsavel()->create();
    $this->action = new UpdatePedidoResponsavelAction;
});

test('a valid change generates exactly 1 alteracao_responsavel event', function () {
    $actor = User::factory()->suprimentos()->create();
    $responsible = User::factory()->suprimentos()->create();
    $pedido = Pedido::factory()->create(['status_id' => $this->solicitado->id, 'responsible_id' => null]);

    $updated = $this->action->execute($actor, $pedido, $responsible->id);

    expect($updated->responsible_id)->toBe($responsible->id);
    expect($pedido->fresh()->events()->count())->toBe(1);
    expect($pedido->fresh()->events()->first()->eventType->slug)->toBe(EventTypeSlug::AlteracaoResponsavel->value);
});

test('reassigning the same responsible is a no-op and generates no event', function () {
    $actor = User::factory()->suprimentos()->create();
    $responsible = User::factory()->suprimentos()->create();
    $pedido = Pedido::factory()->create(['status_id' => $this->solicitado->id, 'responsible_id' => $responsible->id]);

    $this->action->execute($actor, $pedido, $responsible->id);

    expect($pedido->fresh()->events()->count())->toBe(0);
});

test('a non-suprimentos actor is rejected', function () {
    $actor = User::factory()->obra()->create();
    $responsible = User::factory()->suprimentos()->create();
    $pedido = Pedido::factory()->create(['status_id' => $this->solicitado->id]);

    expect(fn () => $this->action->execute($actor, $pedido, $responsible->id))
        ->toThrow(AuthorizationException::class);

    expect($pedido->fresh()->events()->count())->toBe(0);
});

test('a responsible_id without the suprimentos role is rejected even via a tampered payload', function () {
    $actor = User::factory()->suprimentos()->create();
    $notSuprimentos = User::factory()->obra()->create();
    $pedido = Pedido::factory()->create(['status_id' => $this->solicitado->id]);

    expect(fn () => $this->action->execute($actor, $pedido, $notSuprimentos->id))
        ->toThrow(ValidationException::class);

    expect($pedido->fresh()->responsible_id)->toBeNull();
    expect($pedido->fresh()->events()->count())->toBe(0);
});

test('a terminal pedido rejects the mutation', function () {
    $actor = User::factory()->suprimentos()->create();
    $responsible = User::factory()->suprimentos()->create();
    $pedido = Pedido::factory()->create(['status_id' => $this->entregue->id]);

    expect(fn () => $this->action->execute($actor, $pedido, $responsible->id))
        ->toThrow(PedidoTerminalStateException::class);

    expect($pedido->fresh()->events()->count())->toBe(0);
});
