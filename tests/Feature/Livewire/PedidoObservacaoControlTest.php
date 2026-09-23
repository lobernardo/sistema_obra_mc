<?php

use App\Enums\EventTypeSlug;
use App\Livewire\Gestao\PedidoDetalhe as GestaoPedidoDetalhe;
use App\Livewire\Obra\PedidoDetalhe as ObraPedidoDetalhe;
use App\Livewire\Suprimentos\PedidoDetalhe as SuprimentosPedidoDetalhe;
use App\Models\Obra;
use App\Models\Pedido;
use App\Models\PedidoEvent;
use App\Models\User;
use Livewire\Livewire;

/**
 * "Adicionar observação" in the detail screens (UI-05, RF-24..RF-26,
 * CT-04): offered to Obra and Suprimentos in every status, never to Gestão.
 */
beforeEach(function () {
    $this->statuses = seedWorkflowStatuses();
    seedHistoryEventTypes();

    $this->obraUser = User::factory()->obra()->create(['name' => 'João Silva']);
    $this->obra = Obra::factory()->create();
    $this->obraUser->obras()->attach($this->obra->id);
    $this->suprimentos = User::factory()->suprimentos()->create(['name' => 'Maria Souza']);
    $this->gestao = User::factory()->gestao()->create();
});

function observacaoPedido(string $status): Pedido
{
    return Pedido::factory()->for(test()->obraUser, 'requester')->create([
        'obra_id' => test()->obra->id,
        'status_id' => test()->statuses[$status]->id,
    ]);
}

function observacaoCount(Pedido $pedido): int
{
    return PedidoEvent::query()
        ->where('pedido_id', $pedido->id)
        ->whereHas('eventType', fn ($query) => $query->where('slug', EventTypeSlug::Observacao->value))
        ->count();
}

test('the control is present for obra and suprimentos in active and terminal statuses (UI-05, RF-26)', function (string $status) {
    $pedido = observacaoPedido($status);

    $this->actingAs($this->obraUser);
    Livewire::test(ObraPedidoDetalhe::class, ['pedido' => $pedido])
        ->assertSeeHtml('wire:submit="adicionarObservacao"')
        ->assertSeeHtml('maxlength="2000"')
        ->assertSee('Adicionar observação');

    $this->actingAs($this->suprimentos);
    Livewire::test(SuprimentosPedidoDetalhe::class, ['pedido' => $pedido])
        ->assertSeeHtml('wire:submit="adicionarObservacao"')
        ->assertSeeHtml('maxlength="2000"')
        ->assertSee('Adicionar observação');
})->with(['solicitado', 'aguardando_entrega', 'entregue', 'cancelado', 'finalizado']);

test('the Gestão detail has no observation control nor method (UI-05)', function () {
    $pedido = observacaoPedido('em_analise');

    $this->actingAs($this->gestao);

    Livewire::test(GestaoPedidoDetalhe::class, ['pedido' => $pedido])
        ->assertDontSee('Adicionar observação')
        ->assertDontSeeHtml('adicionarObservacao');

    expect(method_exists(GestaoPedidoDetalhe::class, 'adicionarObservacao'))->toBeFalse();
});

test('submitting clears the textarea and shows the new event without reload (UI-05)', function (string $component, string $actor, string $status) {
    $pedido = observacaoPedido($status);

    $this->actingAs($this->{$actor});

    $html = Livewire::test($component, ['pedido' => $pedido])
        ->set('observacao', '  Entregar no portão 2.  ')
        ->call('adicionarObservacao')
        ->assertHasNoErrors()
        ->assertSet('observacao', '')
        ->html();

    expect($html)->toMatch('/<strong[^>]*>Observação adicionada<\/strong>(?:\s|<!--.*?-->)*<p[^>]*>Entregar no portão 2\.<\/p>/u')
        ->and($html)->toMatch('/<textarea id="observacao"[^>]*><\/textarea>/');

    expect(observacaoCount($pedido))->toBe(1)
        ->and($pedido->fresh()->status_id)->toBe($this->statuses[$status]->id);
})->with([
    'obra, active' => [ObraPedidoDetalhe::class, 'obraUser', 'em_analise'],
    'obra, entregue' => [ObraPedidoDetalhe::class, 'obraUser', 'entregue'],
    'suprimentos, active' => [SuprimentosPedidoDetalhe::class, 'suprimentos', 'solicitado'],
    'suprimentos, finalizado' => [SuprimentosPedidoDetalhe::class, 'suprimentos', 'finalizado'],
    'suprimentos, cancelado' => [SuprimentosPedidoDetalhe::class, 'suprimentos', 'cancelado'],
]);

test('a blank or too long observation shows the PT-BR error and writes nothing (RF-25)', function (string $texto, string $message) {
    $pedido = observacaoPedido('em_analise');

    $this->actingAs($this->obraUser);

    Livewire::test(ObraPedidoDetalhe::class, ['pedido' => $pedido])
        ->set('observacao', $texto)
        ->call('adicionarObservacao')
        ->assertHasErrors(['observacao'])
        ->assertSee($message);

    expect(observacaoCount($pedido))->toBe(0);
})->with([
    'blank' => ['   ', 'Escreva a observação.'],
    '2001 chars' => [str_repeat('a', 2001), 'A observação deve ter no máximo 2000 caracteres.'],
]);

test('a forged adicionarObservacao by gestao on an open Obra or Suprimentos detail → 403, 0 events', function (string $component, string $actor) {
    $pedido = observacaoPedido('em_analise');

    $this->actingAs($this->{$actor});
    $testable = Livewire::test($component, ['pedido' => $pedido]);

    $this->actingAs($this->gestao);

    $testable->set('observacao', 'Tentativa')
        ->call('adicionarObservacao')
        ->assertForbidden();

    expect(observacaoCount($pedido))->toBe(0);
})->with([
    'obra detail' => [ObraPedidoDetalhe::class, 'obraUser'],
    'suprimentos detail' => [SuprimentosPedidoDetalhe::class, 'suprimentos'],
]);
