<?php

use App\Actions\Pedidos\CreatePedidoAction;
use App\Enums\EventTypeSlug;
use App\Livewire\Obra\PedidoDetalhe as ObraPedidoDetalhe;
use App\Models\Obra;
use App\Models\Pedido;
use App\Models\PedidoEvent;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/**
 * The standardized history (RF-21, RF-23, UI-04): ação / contexto /
 * "dd/mm/aaaa HH:MM · Autor", with the `criacao_pedido` context read from
 * the creation-time snapshot (F-09) and identical on the three detail
 * screens.
 */
beforeEach(function () {
    $this->statuses = seedWorkflowStatuses();
    $this->eventTypes = seedHistoryEventTypes();

    $this->requester = User::factory()->obra()->create(['name' => 'João Silva']);
    $this->obra = Obra::factory()->create(['name' => 'Residencial Aurora']);
    $this->requester->obras()->attach($this->obra->id);
});

/**
 * Creates a pedido through the Action at the given UTC instant.
 */
function historyPedido(User $requester, int|string $obraSelection, string $at = '2026-09-16T13:05:00Z'): Pedido
{
    test()->travelTo(CarbonImmutable::parse($at));

    $pedido = app(CreatePedidoAction::class)->execute($requester, [
        'obra_selection' => $obraSelection,
        'needed_at' => '2026-09-30',
        'descricao' => 'Cimento e areia',
    ]);

    test()->travelBack();

    return $pedido;
}

/**
 * The visible text of the history timeline of a rendered detail page.
 */
function historyText(string $html): string
{
    preg_match('/<ol data-testid="pedido-history-timeline".*?<\/ol>/s', $html, $match);

    return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($match[0] ?? ''))));
}

test('a pedido created by the Action shows action, context and "dd/mm/aaaa HH:MM · Autor" in that order (RF-21, UI-04)', function () {
    $pedido = historyPedido($this->requester, $this->obra->id);

    $this->actingAs($this->requester);

    Livewire::test(ObraPedidoDetalhe::class, ['pedido' => $pedido])
        ->assertSeeInOrder(['<strong', 'Pedido criado', '<p', 'Solicitação registrada para Residencial Aurora.', '<time'], false)
        ->assertSeeTextInOrder(['Pedido criado', 'Solicitação registrada para Residencial Aurora.', '16/09/2026 10:05 · João Silva'])
        ->assertDontSee('Criação do pedido');
});

test('renaming the obra does not change the creation snapshot (F-09)', function () {
    $pedido = historyPedido($this->requester, $this->obra->id);
    $this->obra->update(['name' => 'Aurora II']);

    $this->actingAs($this->requester);

    Livewire::test(ObraPedidoDetalhe::class, ['pedido' => $pedido])
        ->assertSee('Solicitação registrada para Residencial Aurora.')
        ->assertDontSee('Solicitação registrada para Aurora II.');
});

test('a legacy criacao_pedido event with new_value null shows the current obra name', function () {
    $pedido = Pedido::factory()->for($this->requester, 'requester')->create([
        'obra_id' => $this->obra->id,
        'status_id' => $this->statuses['solicitado']->id,
    ]);
    PedidoEvent::query()->create([
        'pedido_id' => $pedido->id,
        'event_type_id' => $this->eventTypes[EventTypeSlug::CriacaoPedido->value]->id,
        'previous_value' => null,
        'new_value' => null,
        'actor_id' => $this->requester->id,
    ]);
    $this->obra->update(['name' => 'Aurora II']);

    $this->actingAs($this->requester);

    Livewire::test(ObraPedidoDetalhe::class, ['pedido' => $pedido->fresh()])
        ->assertSee('Solicitação registrada para Aurora II.');
});

test('an "Outra" pedido shows "Solicitação registrada para Outra."', function () {
    $pedido = historyPedido($this->requester, CreatePedidoAction::OUTRA_SELECTION);

    $this->actingAs($this->requester);

    Livewire::test(ObraPedidoDetalhe::class, ['pedido' => $pedido])
        ->assertSee('Solicitação registrada para Outra.');
});

test('the Obra, Suprimentos and Gestão detail screens render identical history text (RF-23)', function () {
    $pedido = historyPedido($this->requester, $this->obra->id);
    PedidoEvent::query()->create([
        'pedido_id' => $pedido->id,
        'event_type_id' => $this->eventTypes[EventTypeSlug::MudancaStatus->value]->id,
        'previous_value' => (string) $this->statuses['solicitado']->id,
        'new_value' => (string) $this->statuses['em_analise']->id,
        'actor_id' => User::factory()->suprimentos()->create(['name' => 'Maria Souza'])->id,
    ]);

    $obraText = historyText($this->actingAs($this->requester)->get(route('obra.pedidos.show', $pedido))->assertOk()->getContent());
    $suprimentosText = historyText($this->actingAs(User::factory()->suprimentos()->create())->get(route('suprimentos.pedidos.show', $pedido))->assertOk()->getContent());
    $gestaoText = historyText($this->actingAs(User::factory()->gestao()->create())->get(route('gestao.pedidos.show', $pedido))->assertOk()->getContent());

    expect($obraText)
        ->toContain('Pedido criado Solicitação registrada para Residencial Aurora. 16/09/2026 10:05 · João Silva')
        ->toContain($this->statuses['solicitado']->name.' → '.$this->statuses['em_analise']->name)
        ->toContain('· Maria Souza')
        ->and($suprimentosText)->toBe($obraText)
        ->and($gestaoText)->toBe($obraText);
});

test('the detail screens issue the same number of queries with 3 and 30 events (RNF-03)', function () {
    $pedido = historyPedido($this->requester, $this->obra->id);
    $gestao = User::factory()->gestao()->create();

    $measure = function (int $total) use ($pedido, $gestao): int {
        $existing = PedidoEvent::query()->where('pedido_id', $pedido->id)->count();

        for ($index = $existing + 1; $index <= $total; $index++) {
            PedidoEvent::query()->create([
                'pedido_id' => $pedido->id,
                'event_type_id' => $this->eventTypes[$index % 2 === 0 ? EventTypeSlug::Observacao->value : EventTypeSlug::AlteracaoResponsavel->value]->id,
                'previous_value' => null,
                'new_value' => $index % 2 === 0 ? 'Observação '.$index : (string) User::factory()->suprimentos()->create()->id,
                'actor_id' => User::factory()->suprimentos()->create()->id,
            ]);
        }

        $this->actingAs($gestao);
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get(route('gestao.pedidos.show', $pedido))->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $queries;
    };

    $measure(3);

    expect($measure(3))->toBe($measure(30))
        ->and(PedidoEvent::query()->where('pedido_id', $pedido->id)->count())->toBe(30);
});
