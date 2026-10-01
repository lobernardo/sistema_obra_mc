<?php

use App\Enums\EventTypeSlug;
use App\Livewire\Gestao\PedidoDetalhe as GestaoPedidoDetalhe;
use App\Livewire\Obra\PedidoDetalhe as ObraPedidoDetalhe;
use App\Livewire\Suprimentos\PedidoDetalhe as SuprimentosPedidoDetalhe;
use App\Models\Obra;
use App\Models\Pedido;
use App\Models\PedidoEvent;
use App\Models\User;
use App\Support\LocalTime;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

/**
 * "Observação / ocorrência" in the detail screens (UI-09, RF-10..RF-13,
 * CT-04, CT-05): offered to Obra, Suprimentos and Gestão in every status,
 * as a single free-text field without category.
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
        ->assertSee('Observação / ocorrência');

    $this->actingAs($this->suprimentos);
    Livewire::test(SuprimentosPedidoDetalhe::class, ['pedido' => $pedido])
        ->assertSeeHtml('wire:submit="adicionarObservacao"')
        ->assertSeeHtml('maxlength="2000"')
        ->assertSee('Observação / ocorrência');
})->with(['solicitado', 'aguardando_entrega', 'entregue', 'cancelado', 'finalizado']);

test('the Gestão detail has no observation control nor method (UI-05)', function () {
    $pedido = observacaoPedido('em_analise');

    $this->actingAs($this->gestao);

    Livewire::test(GestaoPedidoDetalhe::class, ['pedido' => $pedido])
        ->assertDontSee('Observação / ocorrência')
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

test('a forged adicionarObservacao by a user without a recognised papel on an open Obra or Suprimentos detail → 403, 0 events', function (string $component, string $actor) {
    $pedido = observacaoPedido('em_analise');

    $this->actingAs($this->{$actor});
    $testable = Livewire::test($component, ['pedido' => $pedido]);

    $this->actingAs(User::factory()->create());

    $testable->set('observacao', 'Tentativa')
        ->call('adicionarObservacao')
        ->assertForbidden();

    expect(observacaoCount($pedido))->toBe(0);
})->with([
    'obra detail' => [ObraPedidoDetalhe::class, 'obraUser'],
    'suprimentos detail' => [SuprimentosPedidoDetalhe::class, 'suprimentos'],
]);

test('the Obra, Suprimentos and Gestão detail routes show "Observação / ocorrência" without any category select (UI-09)', function (string $actor, string $route) {
    $pedido = observacaoPedido('em_analise');

    $html = $this->actingAs($this->{$actor})
        ->get(route($route, $pedido))
        ->assertOk()
        ->assertSee('<section aria-label="Observação / ocorrência"', false)
        ->assertSee('<label for="observacao" class="section-title">Observação / ocorrência</label>', false)
        ->assertSee('Registre falta de produto, troca, atraso, problema de entrega ou qualquer informação relevante. Até 2000 caracteres.')
        ->assertSee('Registrar observação / ocorrência')
        ->getContent();

    expect(preg_match('/<section aria-label="Observação \/ ocorrência".*?<\/section>/su', $html, $section))->toBe(1)
        ->and($section[0])->not->toContain('<select')
        ->and(substr_count($section[0], '<textarea'))->toBe(1)
        ->and($section[0])->toContain('maxlength="2000"');
})->with([
    'obra' => ['obraUser', 'obra.pedidos.show'],
    'suprimentos' => ['suprimentos', 'suprimentos.pedidos.show'],
    'gestao' => ['gestao', 'gestao.pedidos.show'],
]);

test('submitting an empty "Observação / ocorrência" shows "Escreva a observação." inline and writes nothing (UI-09, RF-11)', function (string $component, string $actor) {
    $pedido = observacaoPedido('em_analise');

    $this->actingAs($this->{$actor});

    $html = Livewire::test($component, ['pedido' => $pedido])
        ->set('observacao', '')
        ->call('adicionarObservacao')
        ->assertHasErrors(['observacao'])
        ->html();

    expect($html)->toMatch('/<span role="alert" class="form-error">Escreva a observação\.<\/span>/u')
        ->and(observacaoCount($pedido))->toBe(0);
})->with([
    'obra' => [ObraPedidoDetalhe::class, 'obraUser'],
    'suprimentos' => [SuprimentosPedidoDetalhe::class, 'suprimentos'],
    'gestao' => [SuprimentosPedidoDetalhe::class, 'gestao'],
]);

test('a registered observação / ocorrência appears in the history with its author and local date/time (UI-09, RF-10)', function (string $component, string $actor) {
    $pedido = observacaoPedido('entregue');

    $this->travelTo(Carbon::parse('2026-09-30 01:15:00', 'UTC'));
    $this->actingAs($this->{$actor});

    $html = Livewire::test($component, ['pedido' => $pedido])
        ->set('observacao', 'Faltou 1 saco de cimento na entrega.')
        ->call('adicionarObservacao')
        ->assertHasNoErrors()
        ->html();

    $event = PedidoEvent::query()->where('pedido_id', $pedido->id)->sole();

    expect($event->actor_id)->toBe($this->{$actor}->id)
        ->and(LocalTime::formatDateTime($event->created_at))->toBe('29/09/2026 22:15')
        ->and($html)->toContain('Faltou 1 saco de cimento na entrega.')
        ->and($html)->toMatch('/<time datetime="2026-09-30T01:15:00\+00:00">29\/09\/2026 22:15<\/time>(?:<!--.*?-->)?\s*· '.preg_quote($this->{$actor}->name, '/').'/u');
})->with([
    'obra' => [ObraPedidoDetalhe::class, 'obraUser'],
    'suprimentos' => [SuprimentosPedidoDetalhe::class, 'suprimentos'],
    'gestao' => [SuprimentosPedidoDetalhe::class, 'gestao'],
]);
