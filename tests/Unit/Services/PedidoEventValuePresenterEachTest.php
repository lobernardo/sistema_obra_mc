<?php

use App\Enums\EventTypeSlug;
use App\Models\Obra;
use App\Models\Pedido;
use App\Models\PedidoEvent;
use App\Models\Priority;
use App\Models\User;
use App\Services\PedidoEventValuePresenter;
use Illuminate\Support\Facades\DB;

/**
 * T05 — RF-14, UI-01: `describeEach()` renders events of several pedidos
 * exactly like `describeAll()` renders each pedido's history, with a
 * constant number of queries.
 */
beforeEach(function () {
    $this->statuses = seedWorkflowStatuses();
    $this->eventTypes = seedHistoryEventTypes();
    $this->actor = User::factory()->suprimentos()->create(['name' => 'Maria Souza']);
    $this->presenter = app(PedidoEventValuePresenter::class);
});

function eachEvent(Pedido $pedido, EventTypeSlug $slug, ?string $previous = null, ?string $new = null): PedidoEvent
{
    return PedidoEvent::query()->create([
        'pedido_id' => $pedido->id,
        'event_type_id' => test()->eventTypes[$slug->value]->id,
        'previous_value' => $previous,
        'new_value' => $new,
        'actor_id' => test()->actor->id,
    ]);
}

/**
 * Three pedidos — one with obra, one "Outra" with referência, one plain
 * "Outra" — each with a legacy `criacao_pedido` (fallback to the pedido's
 * own label) plus lookup-backed events.
 *
 * @return list<Pedido>
 */
function threePedidosWithHistory(): array
{
    $responsible = User::factory()->suprimentos()->create(['name' => 'Carlos Lima']);
    $priority = Priority::factory()->create(['name' => 'Urgente']);

    $pedidos = [
        Pedido::factory()->create(['obra_id' => Obra::factory()->create(['name' => 'Residencial Aurora'])->id, 'status_id' => test()->statuses['solicitado']->id]),
        Pedido::factory()->create(['obra_id' => null, 'obra_reference' => 'Galpão Norte', 'status_id' => test()->statuses['solicitado']->id]),
        Pedido::factory()->create(['obra_id' => null, 'obra_reference' => null, 'status_id' => test()->statuses['solicitado']->id]),
    ];

    foreach ($pedidos as $pedido) {
        eachEvent($pedido, EventTypeSlug::CriacaoPedido);
        eachEvent($pedido, EventTypeSlug::MudancaStatus, (string) test()->statuses['solicitado']->id, (string) test()->statuses['em_analise']->id);
        eachEvent($pedido, EventTypeSlug::AlteracaoResponsavel, null, (string) $responsible->id);
        eachEvent($pedido, EventTypeSlug::AlteracaoPrioridade, null, (string) $priority->id);
        eachEvent($pedido, EventTypeSlug::Observacao, null, 'Falta de cimento na obra '.$pedido->id.'.');
    }

    return $pedidos;
}

test('describeEach over events of 3 pedidos equals describeAll per pedido', function () {
    $pedidos = threePedidosWithHistory();

    $expected = [];

    foreach ($pedidos as $pedido) {
        $history = PedidoEvent::query()->where('pedido_id', $pedido->id)->with(['eventType', 'actor'])->orderBy('id')->get();
        $expected += $this->presenter->describeAll($history, $pedido->fresh()->load('obra'));
    }

    $events = PedidoEvent::query()->with(['eventType', 'actor', 'pedido.obra'])->orderBy('id')->get();

    $actual = $this->presenter->describeEach($events);

    expect($actual)->toBe($expected)->toHaveCount(15);

    $contexts = collect($actual)->pluck('context')->filter(fn (?string $context) => str_starts_with((string) $context, 'Solicitação'))->values()->all();

    expect($contexts)->toBe([
        'Solicitação registrada para Residencial Aurora.',
        'Solicitação registrada para Outra — Galpão Norte.',
        'Solicitação registrada para Outra.',
    ]);
});

test('describeEach issues a constant number of queries for 1 and 3 pedidos', function () {
    $measure = function (int $pedidoCount): int {
        DB::table('pedidos')->delete();
        DB::table('obras')->delete();

        $pedidos = array_slice(threePedidosWithHistory(), 0, $pedidoCount);
        $events = PedidoEvent::query()
            ->whereIn('pedido_id', array_map(fn (Pedido $pedido) => $pedido->id, $pedidos))
            ->with(['eventType', 'actor', 'pedido.obra'])
            ->get();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->presenter->describeEach($events);
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $queries;
    };

    expect($measure(1))->toBe($measure(3));
});
