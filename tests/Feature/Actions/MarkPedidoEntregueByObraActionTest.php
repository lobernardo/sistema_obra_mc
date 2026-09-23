<?php

use App\Actions\Pedidos\CancelPedidoAction;
use App\Actions\Pedidos\MarkPedidoEntregueByObraAction;
use App\Enums\EventTypeSlug;
use App\Exceptions\Pedidos\PedidoTerminalStateException;
use App\Models\Obra;
use App\Models\Pedido;
use App\Models\PedidoEvent;
use App\Models\User;
use App\Services\DashboardIndicatorsService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(function () {
    $this->statuses = seedWorkflowStatuses();
    seedHistoryEventTypes();

    $this->obraUser = User::factory()->obra()->create();
    $this->obra = Obra::factory()->create();
    $this->obraUser->obras()->attach($this->obra->id);
    $this->action = app(MarkPedidoEntregueByObraAction::class);
});

function entregaObraPedido(string $status): Pedido
{
    return Pedido::factory()->for(test()->obraUser, 'requester')->create([
        'obra_id' => test()->obra->id,
        'status_id' => test()->statuses[$status]->id,
    ]);
}

function entregaEventCount(Pedido $pedido): int
{
    return PedidoEvent::query()
        ->where('pedido_id', $pedido->id)
        ->whereHas('eventType', fn ($query) => $query->where('slug', EventTypeSlug::Entrega->value))
        ->count();
}

test('from each active status the obra user marks entregue with 1 entrega event, counted in entreguesHoje the same local day (RF-27, RF-29)', function (string $status) {
    $this->travelTo(CarbonImmutable::parse('2026-09-23T15:00:00Z'));
    $pedido = entregaObraPedido($status);

    $result = $this->action->execute($this->obraUser, $pedido);

    $event = PedidoEvent::query()->where('pedido_id', $pedido->id)->sole();

    expect($result->status_id)->toBe($this->statuses['entregue']->id)
        ->and($pedido->fresh()->status_id)->toBe($this->statuses['entregue']->id)
        ->and($event->eventType->slug)->toBe(EventTypeSlug::Entrega->value)
        ->and($event->actor_id)->toBe($this->obraUser->id)
        ->and($event->previous_value)->toBe((string) $this->statuses[$status]->id)
        ->and($event->new_value)->toBe((string) $this->statuses['entregue']->id)
        ->and(app(DashboardIndicatorsService::class)->compute()['entreguesHoje'])->toBe(1);
})->with(['solicitado', 'em_analise', 'em_compra_preparacao', 'aguardando_entrega']);

test('from a terminal status → 409 and 0 events (RF-28, RF-29)', function (string $status) {
    $pedido = entregaObraPedido($status);

    expect(fn () => $this->action->execute($this->obraUser, $pedido))
        ->toThrow(PedidoTerminalStateException::class);

    expect(PedidoEvent::query()->where('pedido_id', $pedido->id)->count())->toBe(0)
        ->and($pedido->fresh()->status_id)->toBe($this->statuses[$status]->id);
})->with(['entregue', 'cancelado', 'finalizado']);

test('the 409 renders as HTTP 409', function () {
    $response = PedidoTerminalStateException::forPedido()->render(request());

    expect($response->getStatusCode())->toBe(409);
});

test('an obra user of another obra, gestao and suprimentos are denied, status unchanged (RF-28)', function (Closure $makeActor) {
    $pedido = entregaObraPedido('aguardando_entrega');

    expect(fn () => $this->action->execute($makeActor(), $pedido))
        ->toThrow(AuthorizationException::class);

    expect(entregaEventCount($pedido))->toBe(0)
        ->and($pedido->fresh()->status_id)->toBe($this->statuses['aguardando_entrega']->id);
})->with([
    'obra of another obra' => [fn () => tap(User::factory()->obra()->create(), fn (User $user) => $user->obras()->attach(Obra::factory()->create()->id))],
    'gestao' => [fn () => User::factory()->gestao()->create()],
    'suprimentos' => [fn () => User::factory()->suprimentos()->create()],
]);

test('the requester of an "Outra" pedido may mark it; another obra user may not (RF-40, Q-12)', function () {
    $pedido = Pedido::factory()->outra('Galpão provisório')->for($this->obraUser, 'requester')->create([
        'status_id' => $this->statuses['em_analise']->id,
    ]);
    $other = User::factory()->obra()->create();
    $other->obras()->attach($this->obra->id);

    expect(fn () => $this->action->execute($other, $pedido))->toThrow(AuthorizationException::class);
    expect(entregaEventCount($pedido))->toBe(0);

    $this->action->execute($this->obraUser, $pedido);

    expect($pedido->fresh()->status_id)->toBe($this->statuses['entregue']->id)
        ->and(entregaEventCount($pedido))->toBe(1);
});

test('a stale instance loaded before a Suprimentos cancellation → 409 thanks to the locked re-read (RNF-02)', function () {
    $pedido = entregaObraPedido('aguardando_entrega');
    $stale = Pedido::query()->with('status')->findOrFail($pedido->id);

    app(CancelPedidoAction::class)->execute(User::factory()->suprimentos()->create(), $pedido->fresh());

    expect(fn () => $this->action->execute($this->obraUser, $stale))
        ->toThrow(PedidoTerminalStateException::class);

    expect($pedido->fresh()->status_id)->toBe($this->statuses['cancelado']->id)
        ->and(entregaEventCount($pedido))->toBe(0);
});
