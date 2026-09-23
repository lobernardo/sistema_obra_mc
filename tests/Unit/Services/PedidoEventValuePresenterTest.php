<?php

use App\Enums\EventTypeSlug;
use App\Models\Obra;
use App\Models\Pedido;
use App\Models\PedidoEvent;
use App\Models\Priority;
use App\Models\User;
use App\Services\PedidoEventValuePresenter;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->statuses = seedWorkflowStatuses();
    $this->eventTypes = seedHistoryEventTypes();

    foreach ([
        'mudanca_status' => 'Mudança de status',
        'entrega' => 'Entrega',
        'cancelamento' => 'Cancelamento',
        'alteracao_responsavel' => 'Alteração de responsável',
        'alteracao_prioridade' => 'Alteração de prioridade',
        'alteracao_previsao' => 'Alteração de previsão',
    ] as $slug => $name) {
        $this->eventTypes[$slug]->update(['name' => $name]);
    }

    $this->actor = User::factory()->suprimentos()->create(['name' => 'Maria Souza']);
    $this->pedido = Pedido::factory()->create([
        'obra_id' => Obra::factory()->create(['name' => 'Residencial Aurora'])->id,
        'status_id' => $this->statuses['solicitado']->id,
    ]);
});

/**
 * Writes one history event of the given type on the test pedido.
 */
function presenterEvent(EventTypeSlug $slug, ?string $previous = null, ?string $new = null, ?string $createdAt = null): PedidoEvent
{
    $event = PedidoEvent::query()->create([
        'pedido_id' => test()->pedido->id,
        'event_type_id' => test()->eventTypes[$slug->value]->id,
        'previous_value' => $previous,
        'new_value' => $new,
        'actor_id' => test()->actor->id,
    ]);

    if ($createdAt !== null) {
        DB::table('pedido_events')->where('id', $event->id)->update(['created_at' => $createdAt]);
    }

    return $event;
}

/**
 * @return array{action: string, context: ?string, at: string, actor: ?string}
 */
function describeOne(PedidoEvent $event): array
{
    $events = PedidoEvent::query()->whereKey($event->id)->with(['eventType', 'actor'])->get();

    return app(PedidoEventValuePresenter::class)->describeAll($events, test()->pedido->fresh())[$event->id];
}

test('criacao_pedido reads the snapshot in new_value (RF-21, F-09)', function () {
    $description = describeOne(presenterEvent(EventTypeSlug::CriacaoPedido, new: 'Residencial Aurora'));

    expect($description['action'])->toBe('Pedido criado')
        ->and($description['context'])->toBe('Solicitação registrada para Residencial Aurora.')
        ->and($description['actor'])->toBe('Maria Souza');
});

test('a legacy criacao_pedido with new_value null falls back to the current obraLabel (RF-21)', function () {
    $this->pedido->obra->update(['name' => 'Aurora II']);

    expect(describeOne(presenterEvent(EventTypeSlug::CriacaoPedido))['context'])
        ->toBe('Solicitação registrada para Aurora II.');
});

test('the "Outra" snapshot renders verbatim (CT-07)', function () {
    expect(describeOne(presenterEvent(EventTypeSlug::CriacaoPedido, new: 'Outra'))['context'])
        ->toBe('Solicitação registrada para Outra.');
});

test('observacao, romaneio_anexado and finalizacao carry their RF-21 labels and contexts', function (EventTypeSlug $slug, ?string $new, string $action, string $context) {
    $description = describeOne(presenterEvent($slug, new: $new));

    expect($description['action'])->toBe($action)
        ->and($description['context'])->toBe($context);
})->with([
    'observacao' => [EventTypeSlug::Observacao, "Entregar no portão 2.\nLigar antes.", 'Observação adicionada', "Entregar no portão 2.\nLigar antes."],
    'romaneio_anexado' => [EventTypeSlug::RomaneioAnexado, 'romaneio-1234.pdf', 'Romaneio anexado', 'romaneio-1234.pdf'],
    'finalizacao' => [EventTypeSlug::Finalizacao, null, 'Pedido finalizado', 'Pedido finalizado por Suprimentos.'],
]);

test('status events render "<anterior> → <novo>" with the existing type name', function (EventTypeSlug $slug, string $from, string $to, string $typeName) {
    $description = describeOne(presenterEvent(
        $slug,
        (string) $this->statuses[$from]->id,
        (string) $this->statuses[$to]->id,
    ));

    expect($description['action'])->toBe($typeName)
        ->and($description['context'])->toBe($this->statuses[$from]->name.' → '.$this->statuses[$to]->name);
})->with([
    'mudanca_status' => [EventTypeSlug::MudancaStatus, 'solicitado', 'em_analise', 'Mudança de status'],
    'entrega' => [EventTypeSlug::Entrega, 'aguardando_entrega', 'entregue', 'Entrega'],
    'cancelamento' => [EventTypeSlug::Cancelamento, 'em_analise', 'cancelado', 'Cancelamento'],
]);

test('alteracao_* events render "<anterior> → <novo>" with "—" for an empty side', function () {
    $responsible = User::factory()->suprimentos()->create(['name' => 'Carlos Lima']);
    $priority = Priority::factory()->create(['name' => 'Urgente']);

    $responsavel = describeOne(presenterEvent(EventTypeSlug::AlteracaoResponsavel, null, (string) $responsible->id));
    $prioridade = describeOne(presenterEvent(EventTypeSlug::AlteracaoPrioridade, null, (string) $priority->id));
    $previsao = describeOne(presenterEvent(EventTypeSlug::AlteracaoPrevisao, '2026-09-30', '2026-10-02'));

    expect($responsavel)->toMatchArray(['action' => 'Alteração de responsável', 'context' => '— → Carlos Lima'])
        ->and($prioridade)->toMatchArray(['action' => 'Alteração de prioridade', 'context' => '— → Urgente'])
        ->and($previsao)->toMatchArray(['action' => 'Alteração de previsão', 'context' => '30/09/2026 → 02/10/2026']);
});

test('the date/time is the America/Sao_Paulo local time (RF-47)', function () {
    $description = describeOne(presenterEvent(EventTypeSlug::CriacaoPedido, new: 'X', createdAt: '2026-09-25 01:30:00'));

    expect($description['at'])->toBe('24/09/2026 22:30');
});

test('a pre-existing event with unknown ids falls back to the raw value without error', function () {
    $description = describeOne(presenterEvent(EventTypeSlug::MudancaStatus, '999998', '999999'));

    expect($description['context'])->toBe('999998 → 999999');
});

test('the presenter issues a constant number of queries for 3 and 30 events (RNF-03)', function () {
    $measure = function (int $count): int {
        DB::table('pedido_events')->delete();

        foreach (range(1, $count) as $index) {
            $responsible = User::factory()->suprimentos()->create();
            presenterEvent(
                [EventTypeSlug::AlteracaoResponsavel, EventTypeSlug::MudancaStatus, EventTypeSlug::Observacao][$index % 3],
                null,
                $index % 3 === 0 ? (string) $responsible->id : ($index % 3 === 1 ? (string) $this->statuses['em_analise']->id : 'obs '.$index),
            );
        }

        $events = PedidoEvent::query()->where('pedido_id', $this->pedido->id)->with(['eventType', 'actor'])->get();
        $pedido = $this->pedido->fresh()->load('obra');

        DB::flushQueryLog();
        DB::enableQueryLog();
        app(PedidoEventValuePresenter::class)->describeAll($events, $pedido);
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $queries;
    };

    expect($measure(3))->toBe($measure(30));
});
