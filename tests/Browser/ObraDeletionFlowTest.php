<?php

use App\Models\Obra;
use App\Models\Pedido;
use App\Models\User;

/**
 * T17 (`obras-ativacao-exclusao` UI-02, UI-03, UI-04): the two-step "Excluir
 * obra" and the "Desativar obra" shortcut of a blocked deletion, in a real
 * browser, as Gestão on `/obras/{obra}/editar`.
 *
 * "Excluir obra" only opens the confirmation; "Cancelar" closes it with the
 * obra intact; "Confirmar exclusão" on an obra without pedidos lands on
 * `/obras` with "Obra «<nome>» excluída."; an obra with a pedido shows the
 * RF-16 message and its "Desativar obra" button turns the obra INATIVA.
 */
beforeEach(function () {
    $this->statuses = seedWorkflowStatuses();
    $this->gestao = User::factory()->gestao()->create();

    $this->actingAs($this->gestao);
});

/**
 * Opens the edit form of `$obra` and waits until Livewire has bound the
 * `wire:model` inputs, so clicks are never lost to a boot still running.
 */
function visitObraEditForm(Obra $obra): mixed
{
    $page = test()->visit('/login');

    $page->page()->goto(route('obras.edit', $obra));
    $page->page()->locator('#name')->waitFor(['state' => 'visible']);
    $page->page()->waitForFunction('() => document.getElementById("name")?._x_model !== undefined');
    $page->page()->evaluate('() => new Promise((resolve) => setTimeout(resolve, 50))');

    return $page;
}

test('"Excluir obra" asks for confirmation, "Cancelar" keeps the obra and "Confirmar exclusão" deletes it (UI-03)', function () {
    $obra = Obra::factory()->emAndamento()->create(['name' => 'Obra Sem Pedidos']);

    $page = visitObraEditForm($obra);

    $page->click('@delete-obra');
    $page->page()->locator('[data-testid="delete-confirm-dialog"]')->waitFor(['state' => 'visible']);

    $page->assertSee('Excluir definitivamente a obra «Obra Sem Pedidos»? Esta ação não pode ser desfeita.');
    expect(Obra::query()->whereKey($obra->id)->exists())->toBeTrue();

    $page->click('@delete-cancel');
    $page->page()->locator('[data-testid="delete-confirm-dialog"]')->waitFor(['state' => 'detached']);

    $page->assertPathIs("/obras/{$obra->id}/editar")
        ->assertDontSee('Excluir definitivamente a obra');
    expect(Obra::query()->whereKey($obra->id)->exists())->toBeTrue();

    $page->click('@delete-obra');
    $page->page()->locator('[data-testid="delete-confirm-dialog"]')->waitFor(['state' => 'visible']);
    $page->click('@delete-confirm');
    $page->page()->waitForURL(route('obras.index'));

    $page->assertPathIs('/obras')
        ->assertSee('Obra «Obra Sem Pedidos» excluída.')
        ->assertNoJavascriptErrors();

    expect(Obra::query()->whereKey($obra->id)->exists())->toBeFalse();
});

test('a blocked deletion shows the RF-16 message and its "Desativar obra" turns the obra INATIVA (UI-04, UI-02)', function () {
    $obra = Obra::factory()->emAndamento()->create(['name' => 'Obra Com Pedido']);
    Pedido::factory()->create([
        'obra_id' => $obra->id,
        'status_id' => $this->statuses['solicitado']->id,
    ]);

    $page = visitObraEditForm($obra);

    $page->assertDontSee('INATIVA');

    $page->click('@delete-obra');
    $page->page()->locator('[data-testid="delete-confirm-dialog"]')->waitFor(['state' => 'visible']);
    $page->click('@delete-confirm');
    $page->page()->locator('[data-testid="delete-blocked"]')->waitFor(['state' => 'visible']);

    $page->assertSee('Não é possível excluir a obra «Obra Com Pedido»: ela possui 1 pedido(s) e 0 convite(s) utilizado(s). Desative a obra para impedir novos usos.');
    expect(Obra::query()->whereKey($obra->id)->exists())->toBeTrue();

    $page->click('@delete-blocked-deactivate');
    $page->page()->locator('[data-obra-inativa]')->waitFor(['state' => 'visible']);

    $page->assertSee('INATIVA')
        ->assertSee('Obra desativada.')
        ->assertNoJavascriptErrors();

    expect($obra->fresh()->isActive())->toBeFalse();
    expect(Pedido::query()->where('obra_id', $obra->id)->count())->toBe(1);
});
