<?php

use App\Livewire\Obra\Acompanhamento;
use App\Models\Obra;
use App\Models\Pedido;
use App\Models\Priority;
use App\Models\Status;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

test('only pedidos from the user\'s associated obras are listed', function () {
    $user = User::factory()->obra()->create();
    $ownObra = Obra::factory()->create();
    $otherObra = Obra::factory()->create();
    $user->obras()->attach($ownObra->id);

    $status = Status::factory()->solicitado()->create();

    $ownPedido = Pedido::factory()->create(['obra_id' => $ownObra->id, 'status_id' => $status->id]);
    $otherPedido = Pedido::factory()->create(['obra_id' => $otherObra->id, 'status_id' => $status->id]);

    $this->actingAs($user);

    Livewire::test(Acompanhamento::class)
        ->assertSee($ownPedido->code)
        ->assertDontSee($otherPedido->code);
});

test('non-obra actors are denied access to the component', function (string $role) {
    $actor = User::factory()->{$role}()->create();

    $this->actingAs($actor);

    Livewire::test(Acompanhamento::class)->assertSee('403');
})->with(['suprimentos', 'gestao']);

test('query count stays constant between a 5-pedido and a 50-pedido dataset', function () {
    $user = User::factory()->obra()->create();
    $obra = Obra::factory()->create();
    $user->obras()->attach($obra->id);
    $status = Status::factory()->solicitado()->create();
    $priority = Priority::factory()->normal()->create();
    $responsible = User::factory()->suprimentos()->create();

    $this->actingAs($user);

    // Warm up lazy-loaded caches (e.g. the actor's `role` relation used by
    // the `is-obra` gate) so they don't skew the first measurement.
    Livewire::test(Acompanhamento::class);

    $attributes = [
        'obra_id' => $obra->id,
        'status_id' => $status->id,
        'priority_id' => $priority->id,
        'responsible_id' => $responsible->id,
    ];

    Pedido::factory()->count(5)->create($attributes);

    DB::enableQueryLog();
    Livewire::test(Acompanhamento::class);
    $smallDatasetQueryCount = count(DB::getQueryLog());
    DB::flushQueryLog();
    DB::disableQueryLog();

    Pedido::factory()->count(45)->create($attributes);

    DB::enableQueryLog();
    Livewire::test(Acompanhamento::class);
    $largeDatasetQueryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect(Pedido::query()->count())->toBe(50);
    expect($largeDatasetQueryCount)->toBe($smallDatasetQueryCount);
});

test('pedidos of an inactive associated obra remain in the listing', function () {
    $requester = User::factory()->obra()->create();
    $obra = Obra::factory()->create(['is_active' => false]);
    $requester->obras()->attach($obra);
    $status = Status::factory()->solicitado()->create();
    $ownPedido = Pedido::factory()->for($obra)->for($status)->create();
    $otherPedido = Pedido::factory()->for($status)->create();

    $this->actingAs($requester);

    Livewire::test(Acompanhamento::class)
        ->assertSee($ownPedido->code)
        ->assertDontSee($otherPedido->code);
});

/*
|--------------------------------------------------------------------------
| RF-16 / RF-19 / RF-20 — the reduced Obra filter set
|--------------------------------------------------------------------------
*/

/**
 * Seeds an Obra user associated with two obras, and one pedido per filter
 * axis so each control can be asserted in isolation.
 *
 * @return array<string, mixed>
 */
function seedAcompanhamentoFiltros(): array
{
    $user = User::factory()->obra()->create();
    $obraAlvo = Obra::factory()->create(['name' => 'Residencial Aurora']);
    $obraOutra = Obra::factory()->create(['name' => 'Comercial Bravo']);
    $user->obras()->attach([$obraAlvo->id, $obraOutra->id]);

    $solicitado = Status::factory()->solicitado()->create();
    $entregue = Status::factory()->entregue()->create();

    $alvo = Pedido::factory()->create([
        'obra_id' => $obraAlvo->id,
        'status_id' => $solicitado->id,
        'code' => 'PED-000901',
        'items_description' => 'Cimento CP II',
        'needed_at' => now()->subDays(2),
    ]);

    $outro = Pedido::factory()->create([
        'obra_id' => $obraOutra->id,
        'status_id' => $entregue->id,
        'code' => 'PED-000902',
        'items_description' => 'Vergalhao 10mm',
        'needed_at' => now()->addDays(10),
    ]);

    test()->actingAs($user);

    return compact('user', 'obraAlvo', 'obraOutra', 'solicitado', 'entregue', 'alvo', 'outro');
}

test('each of the four Obra controls reduces the visible set correctly', function (string $property, string $key) {
    $seed = seedAcompanhamentoFiltros();

    $value = match ($key) {
        'search' => 'Cimento',
        'obraId' => $seed['obraAlvo']->id,
        'statusId' => $seed['solicitado']->id,
        'atrasoOnly' => true,
    };

    Livewire::test(Acompanhamento::class)
        ->set($property, $value)
        ->assertSee($seed['alvo']->code)
        ->assertDontSee($seed['outro']->code);
})->with([
    'busca' => ['search', 'search'],
    'obra' => ['obraId', 'obraId'],
    'status' => ['statusId', 'statusId'],
    'atraso' => ['atrasoOnly', 'atrasoOnly'],
]);

test('the Obra free-text search matches code, items description and obra name', function () {
    $seed = seedAcompanhamentoFiltros();

    Livewire::test(Acompanhamento::class)
        ->set('search', $seed['alvo']->code)
        ->assertSee($seed['alvo']->code)
        ->assertDontSee($seed['outro']->code);

    Livewire::test(Acompanhamento::class)
        ->set('search', 'Vergalhao')
        ->assertSee($seed['outro']->code)
        ->assertDontSee($seed['alvo']->code);

    Livewire::test(Acompanhamento::class)
        ->set('search', 'Aurora')
        ->assertSee($seed['alvo']->code)
        ->assertDontSee($seed['outro']->code);
});

test('no prioridade or responsavel control is rendered on the Obra listing', function () {
    seedAcompanhamentoFiltros();

    $html = Livewire::test(Acompanhamento::class)->html();

    expect($html)->not->toContain('id="priorityId"')
        ->and($html)->not->toContain('id="responsibleId"')
        ->and($html)->not->toContain('for="priorityId"')
        ->and($html)->not->toContain('for="responsibleId"');
});

test('priorityId and responsibleId in the Obra query string are ignored, not applied', function () {
    $seed = seedAcompanhamentoFiltros();
    $priority = Priority::factory()->alta()->create();
    $responsible = User::factory()->suprimentos()->create();

    $this->get(route('obra.pedidos.index', [
        'priorityId' => $priority->id,
        'responsibleId' => $responsible->id,
    ]))
        ->assertOk()
        ->assertSee($seed['alvo']->code)
        ->assertSee($seed['outro']->code);
});

test('a direct Obra URL carrying filter parameters renders filtered on first paint with the selects pre-selected', function () {
    $seed = seedAcompanhamentoFiltros();

    $response = $this->get(route('obra.pedidos.index', [
        'obraId' => $seed['obraAlvo']->id,
        'statusId' => $seed['solicitado']->id,
    ]));

    $response->assertOk()
        ->assertSee($seed['alvo']->code)
        ->assertDontSee($seed['outro']->code);

    expect($response->getContent())
        ->toContain('value="'.$seed['obraAlvo']->id.'" selected')
        ->toContain('value="'.$seed['solicitado']->id.'" selected');
});

test('changing an Obra filter while on page 3 returns the listing to page 1', function () {
    $user = User::factory()->obra()->create();
    $obra = Obra::factory()->create();
    $user->obras()->attach($obra->id);
    $status = Status::factory()->solicitado()->create();
    Pedido::factory()->count(25)->create(['obra_id' => $obra->id, 'status_id' => $status->id]);

    $this->actingAs($user);

    Livewire::test(Acompanhamento::class)
        ->call('gotoPage', 3)
        ->assertSet('paginators.page', 3)
        ->set('search', 'PED-')
        ->assertSet('paginators.page', 1);
});

test('limparFiltros on the Obra listing resets every filter and returns to page 1', function () {
    $seed = seedAcompanhamentoFiltros();
    Pedido::factory()->count(25)->create([
        'obra_id' => $seed['obraAlvo']->id,
        'status_id' => $seed['solicitado']->id,
    ]);

    Livewire::test(Acompanhamento::class)
        ->set('search', 'Cimento')
        ->set('obraId', $seed['obraAlvo']->id)
        ->set('statusId', $seed['solicitado']->id)
        ->set('atrasoOnly', true)
        ->call('gotoPage', 2)
        ->assertSet('paginators.page', 2)
        ->call('limparFiltros')
        ->assertSet('search', '')
        ->assertSet('obraId', null)
        ->assertSet('statusId', null)
        ->assertSet('atrasoOnly', false)
        ->assertSet('paginators.page', 1);
});

test('reloading the parameterless Obra listing URL yields the unfiltered listing', function () {
    $seed = seedAcompanhamentoFiltros();

    $this->get(route('obra.pedidos.index', ['atrasado' => 'true']))
        ->assertOk()
        ->assertSee($seed['alvo']->code)
        ->assertDontSee($seed['outro']->code);

    $this->get(route('obra.pedidos.index'))
        ->assertOk()
        ->assertSee($seed['alvo']->code)
        ->assertSee($seed['outro']->code);
});

test('a pedido of a deactivated associated obra stays filterable through the obra select', function () {
    $user = User::factory()->obra()->create();
    $obraInativa = Obra::factory()->inactive()->create(['name' => 'Residencial Desativada']);
    $obraAtiva = Obra::factory()->create(['name' => 'Comercial Ativa']);
    $user->obras()->attach([$obraInativa->id, $obraAtiva->id]);
    $status = Status::factory()->solicitado()->create();

    $pedido = Pedido::factory()->create(['obra_id' => $obraInativa->id, 'status_id' => $status->id]);
    $outro = Pedido::factory()->create(['obra_id' => $obraAtiva->id, 'status_id' => $status->id]);

    $this->actingAs($user);

    Livewire::test(Acompanhamento::class)
        ->assertSee($obraInativa->name)
        ->set('obraId', $obraInativa->id)
        ->assertSee($pedido->code)
        ->assertDontSee($outro->code);
});

test('the two Obra filter selects follow the UI-02 markup contract', function () {
    seedAcompanhamentoFiltros();

    $html = Livewire::test(Acompanhamento::class)->html();

    foreach (['obraId' => 'Todas as obras', 'statusId' => 'Todos os status'] as $controlId => $emptyLabel) {
        expect(substr_count($html, 'id="'.$controlId.'"'))->toBe(1, $controlId.' id must be unique in the document');
        expect(substr_count($html, 'for="'.$controlId.'"'))->toBe(1, $controlId.' must be labelled exactly once');
        expect($html)->toContain('<select id="'.$controlId.'" wire:model.live="'.$controlId.'" class="form-control">');
        expect($html)->toContain('<option value="">'.$emptyLabel.'</option>');
    }

    expect($html)->toContain('id="search"')
        ->and($html)->toContain('id="atrasoOnly"')
        ->and($html)->toContain('>Limpar filtros</button>');
});
