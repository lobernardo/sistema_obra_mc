<?php

use App\Enums\StatusSlug;
use App\Models\Pedido;
use App\Models\User;

/**
 * RF-41 / CT-07: every screen that renders a pedido uses the canonical
 * `Pedido::obraLabel()` — "Outra" or "Outra — <referência>" — and never
 * dereferences the missing obra of a pedido "Outra".
 */
beforeEach(function () {
    $this->statuses = seedWorkflowStatuses();
    seedHistoryEventTypes();

    $this->requester = User::factory()->obra()->create();

    $this->outra = Pedido::factory()->outra()->create([
        'requester_id' => $this->requester->id,
        'status_id' => $this->statuses[StatusSlug::Solicitado->value]->id,
    ]);

    $this->outraComReferencia = Pedido::factory()->outra('Galpão provisório')->create([
        'requester_id' => $this->requester->id,
        'status_id' => $this->statuses[StatusSlug::EmAnalise->value]->id,
    ]);
});

test('the listings and Kanbans render both pedidos "Outra" with their canonical label', function (string $role, string $route) {
    $actor = $role === 'obra' ? $this->requester : User::factory()->{$role}()->create();

    $this->actingAs($actor)
        ->get(route($route))
        ->assertOk()
        ->assertSee($this->outra->code)
        ->assertSee($this->outraComReferencia->code)
        ->assertSee('Outra')
        ->assertSee('Outra — Galpão provisório');
})->with([
    'obra listing' => ['obra', 'obra.pedidos.index'],
    'suprimentos listing' => ['suprimentos', 'suprimentos.pedidos.index'],
    'gestao listing' => ['gestao', 'gestao.pedidos.index'],
    'suprimentos kanban' => ['suprimentos', 'suprimentos.kanban'],
    'gestao kanban' => ['gestao', 'gestao.kanban'],
]);

test('the three detail screens render "Outra" and the reference', function (string $role, string $route) {
    $actor = $role === 'obra' ? $this->requester : User::factory()->{$role}()->create();

    $this->actingAs($actor)
        ->get(route($route, $this->outraComReferencia))
        ->assertOk()
        ->assertSee('Outra — Galpão provisório');

    $this->actingAs($actor)
        ->get(route($route, $this->outra))
        ->assertOk()
        ->assertSee('Outra')
        ->assertDontSee('Outra —');
})->with([
    'obra' => ['obra', 'obra.pedidos.show'],
    'suprimentos' => ['suprimentos', 'suprimentos.pedidos.show'],
    'gestao' => ['gestao', 'gestao.pedidos.show'],
]);

test('the Gestão dashboard shows a single "Outra" row in the obra view', function () {
    $html = $this->actingAs(User::factory()->gestao()->create())
        ->get(route('gestao.dashboard'))
        ->assertOk()
        ->getContent();

    expect(substr_count($html, 'data-obra="outra"'))->toBe(1);
    expect($html)->not->toContain('Galpão provisório');

    preg_match('/<li data-obra="outra".*?<\/li>/s', $html, $row);

    expect($row[0] ?? '')->toContain('>Outra<')->toContain('>2<');

    preg_match('/data-testid="indicator-por-obra"[^>]*aria-label="([^"]*)"/', $html, $porObra);

    expect($porObra[1] ?? '')->toContain('Outra 2');
});

test('the Suprimentos Visão Geral renders the pedidos "Outra" among the most recent', function () {
    $this->actingAs(User::factory()->suprimentos()->create())
        ->get(route('suprimentos.visao-geral'))
        ->assertOk()
        ->assertSee($this->outra->code)
        ->assertSee('Outra — Galpão provisório');
});
