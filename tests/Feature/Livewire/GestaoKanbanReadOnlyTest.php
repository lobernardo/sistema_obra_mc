<?php

use App\Enums\StatusSlug;
use App\Livewire\Gestao\KanbanReadOnly;
use App\Livewire\Gestao\TodosPedidos as GestaoTodosPedidos;
use App\Livewire\Suprimentos\TodosPedidos as SuprimentosTodosPedidos;
use App\Models\Pedido;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->statuses = seedWorkflowStatuses(fn (StatusSlug $slug): string => ucfirst($slug->value));
});

test('non-gestao actors are denied access to the read-only kanban', function (string $role) {
    $actor = User::factory()->{$role}()->create();

    $this->actingAs($actor);

    Livewire::test(KanbanReadOnly::class)->assertSee('403');
})->with(['obra', 'suprimentos']);

test('non-gestao actors are denied access to the read-only listing', function (string $role) {
    $actor = User::factory()->{$role}()->create();

    $this->actingAs($actor);

    Livewire::test(GestaoTodosPedidos::class)->assertSee('403');
})->with(['obra', 'suprimentos']);

test('the read-only kanban renders the 6 columns in order and excludes cancelado', function () {
    $actor = User::factory()->gestao()->create();
    $this->actingAs($actor);

    $pedido = Pedido::factory()->create(['status_id' => $this->statuses['solicitado']->id]);
    $cancelado = Pedido::factory()->create(['status_id' => $this->statuses['cancelado']->id]);
    $finalizado = Pedido::factory()->create(['status_id' => $this->statuses['finalizado']->id]);

    $html = Livewire::test(KanbanReadOnly::class)
        ->assertSee($pedido->code)
        ->assertDontSee($cancelado->code)
        ->assertDontSee($this->statuses['cancelado']->name)
        ->html();

    preg_match_all('/data-column="([a-z_]+)"/', $html, $columns);

    expect($columns[1])->toBe([
        'solicitado',
        'em_analise',
        'em_compra_preparacao',
        'aguardando_entrega',
        'entregue',
        'finalizado',
    ]);
    expect($html)->toContain('xl:grid-cols-6');

    preg_match('/<section[^>]*data-column="finalizado".*?<\/section>/s', $html, $column);

    expect($column[0] ?? '')->toContain($finalizado->code);
});

test('no mutation control is rendered on the read-only kanban or listing', function () {
    $actor = User::factory()->gestao()->create();
    $this->actingAs($actor);

    Pedido::factory()->create(['status_id' => $this->statuses['solicitado']->id]);

    $kanbanHtml = Livewire::test(KanbanReadOnly::class)->html();
    $listingHtml = Livewire::test(GestaoTodosPedidos::class)->html();

    expect($kanbanHtml)->not->toContain('wire:click');
    expect($kanbanHtml)->not->toContain('wire:sort');
    expect($kanbanHtml)->not->toContain('Mover para');
    expect($listingHtml)->not->toContain('wire:click');
});

test('the read-only listing filter set matches the Suprimentos listing filter set', function () {
    $extractFilterFieldIds = function (string $html): array {
        preg_match_all('/id="(search|atrasoOnly|obraId|statusId|priorityId|responsibleId|neededAtFrom|neededAtTo|requestedFrom|requestedTo)"/', $html, $matches);

        return $matches[1];
    };

    $gestaoActor = User::factory()->gestao()->create();
    $this->actingAs($gestaoActor);
    $gestaoFields = $extractFilterFieldIds(Livewire::test(GestaoTodosPedidos::class)->html());

    $suprimentosActor = User::factory()->suprimentos()->create();
    $this->actingAs($suprimentosActor);
    $suprimentosFields = $extractFilterFieldIds(Livewire::test(SuprimentosTodosPedidos::class)->html());

    expect($gestaoFields)->not->toBeEmpty();
    expect($gestaoFields)->toBe($suprimentosFields);
});

test('the read-only listing exposes the four new filter controls and keeps pendenteOnly unrendered', function () {
    $actor = User::factory()->gestao()->create();
    $this->actingAs($actor);

    $html = Livewire::test(GestaoTodosPedidos::class)->html();

    foreach (['obraId', 'statusId', 'priorityId', 'responsibleId'] as $controlId) {
        expect(substr_count($html, 'id="'.$controlId.'"'))->toBe(1, $controlId);
        expect($html)->toContain('for="'.$controlId.'"');
    }

    expect($html)->not->toContain('"pendenteOnly"');
});
