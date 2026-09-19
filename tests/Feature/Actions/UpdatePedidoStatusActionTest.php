<?php

use App\Actions\Pedidos\UpdatePedidoStatusAction;
use App\Enums\EventTypeSlug;
use App\Enums\StatusSlug;
use App\Exceptions\Pedidos\PedidoTerminalStateException;
use App\Models\EventType;
use App\Models\Pedido;
use App\Models\Status;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->statuses = [];

    foreach (StatusSlug::cases() as $slug) {
        $this->statuses[$slug->value] = Status::factory()->create([
            'slug' => $slug->value,
            'sort_order' => array_search($slug, StatusSlug::cases(), true) + 1,
        ]);
    }

    EventType::factory()->mudancaStatus()->create();
    EventType::factory()->entrega()->create();

    $this->action = new UpdatePedidoStatusAction;
});

dataset('valid transitions', function () {
    $activeSlugs = array_map(fn (StatusSlug $s) => $s->value, StatusSlug::activeNonFinal());
    $allowedTargets = [...$activeSlugs, StatusSlug::Entregue->value];

    $cases = [];

    foreach ($activeSlugs as $origin) {
        foreach ($allowedTargets as $target) {
            if ($origin === $target) {
                continue;
            }

            $cases["{$origin} -> {$target}"] = [$origin, $target];
        }
    }

    return $cases;
});

test('permitted transitions succeed and write the correct event type', function (string $originSlug, string $targetSlug) {
    $actor = User::factory()->suprimentos()->create();
    $pedido = Pedido::factory()->create(['status_id' => $this->statuses[$originSlug]->id]);

    $updated = $this->action->execute($actor, $pedido, $this->statuses[$targetSlug]->id);

    expect($updated->status->slug)->toBe($targetSlug);

    $event = $pedido->fresh()->events()->first();
    expect($pedido->fresh()->events()->count())->toBe(1);

    $expectedEventTypeSlug = $targetSlug === StatusSlug::Entregue->value
        ? EventTypeSlug::Entrega->value
        : EventTypeSlug::MudancaStatus->value;

    expect($event->eventType->slug)->toBe($expectedEventTypeSlug);
})->with('valid transitions');

test('cancelado is rejected as a target via this action', function () {
    $actor = User::factory()->suprimentos()->create();
    $pedido = Pedido::factory()->create(['status_id' => $this->statuses['solicitado']->id]);

    expect(fn () => $this->action->execute($actor, $pedido, $this->statuses['cancelado']->id))
        ->toThrow(ValidationException::class);

    expect($pedido->fresh()->status->slug)->toBe('solicitado');
});

test('a self-transition to the current status is rejected as invalid', function () {
    $actor = User::factory()->suprimentos()->create();
    $pedido = Pedido::factory()->create(['status_id' => $this->statuses['solicitado']->id]);

    expect(fn () => $this->action->execute($actor, $pedido, $this->statuses['solicitado']->id))
        ->toThrow(ValidationException::class);
});

test('a terminal origin status rejects any transition', function (string $terminalSlug) {
    $actor = User::factory()->suprimentos()->create();
    $pedido = Pedido::factory()->create(['status_id' => $this->statuses[$terminalSlug]->id]);

    expect(fn () => $this->action->execute($actor, $pedido, $this->statuses['em_analise']->id))
        ->toThrow(PedidoTerminalStateException::class);
})->with(['entregue', 'cancelado']);

test('a forged drag-and-drop-shaped payload from a non-suprimentos actor is rejected', function () {
    $actor = User::factory()->obra()->create();
    $pedido = Pedido::factory()->create(['status_id' => $this->statuses['solicitado']->id]);

    expect(fn () => $this->action->execute($actor, $pedido, $this->statuses['em_analise']->id))
        ->toThrow(AuthorizationException::class);

    expect($pedido->fresh()->status->slug)->toBe('solicitado');
    expect($pedido->fresh()->events()->count())->toBe(0);
});
