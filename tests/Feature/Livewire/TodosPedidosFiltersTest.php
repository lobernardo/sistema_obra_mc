<?php

use App\Domain\Pedidos\AtrasoClassifier;
use App\Livewire\Gestao\TodosPedidos as GestaoTodosPedidos;
use App\Livewire\Suprimentos\TodosPedidos;
use App\Models\Obra;
use App\Models\Pedido;
use App\Models\Priority;
use App\Models\Status;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    $this->solicitado = Status::factory()->solicitado()->create();
    $this->entregue = Status::factory()->entregue()->create();
});

test('non-suprimentos actors are denied access to the component', function (string $role) {
    $actor = User::factory()->{$role}()->create();

    $this->actingAs($actor);

    Livewire::test(TodosPedidos::class)->assertSee('403');
})->with(['obra', 'gestao']);

test('free-text search matches code, obra name and items description independently', function () {
    $actor = User::factory()->suprimentos()->create();
    $this->actingAs($actor);

    $obraA = Obra::factory()->create(['name' => 'Residencial Aurora']);
    $obraB = Obra::factory()->create(['name' => 'Comercial Bravo']);

    $byCode = Pedido::factory()->create(['obra_id' => $obraA->id, 'status_id' => $this->solicitado->id, 'code' => 'PED-000123', 'items_description' => 'Cimento']);
    $byObra = Pedido::factory()->create(['obra_id' => $obraA->id, 'status_id' => $this->solicitado->id, 'code' => 'PED-000200', 'items_description' => 'Areia']);
    $byItem = Pedido::factory()->create(['obra_id' => $obraB->id, 'status_id' => $this->solicitado->id, 'code' => 'PED-000300', 'items_description' => 'Telha ceramica']);
    $unmatched = Pedido::factory()->create(['obra_id' => $obraB->id, 'status_id' => $this->solicitado->id, 'code' => 'PED-000400', 'items_description' => 'Vergalhao']);

    Livewire::test(TodosPedidos::class)
        ->set('search', 'PED-000123')
        ->assertSee($byCode->code)
        ->assertDontSee($unmatched->code);

    Livewire::test(TodosPedidos::class)
        ->set('search', 'Aurora')
        ->assertSee($byCode->code)
        ->assertSee($byObra->code)
        ->assertDontSee($byItem->code);

    Livewire::test(TodosPedidos::class)
        ->set('search', 'ceramica')
        ->assertSee($byItem->code)
        ->assertDontSee($unmatched->code);
});

test('the atraso filter matches AtrasoClassifier output on the same dataset', function () {
    $actor = User::factory()->suprimentos()->create();
    $this->actingAs($actor);

    $atrasado = Pedido::factory()->create(['status_id' => $this->solicitado->id, 'needed_at' => now()->subDays(2)]);
    $noPrazo = Pedido::factory()->create(['status_id' => $this->solicitado->id, 'needed_at' => now()->addDays(2)]);
    $entregueVencido = Pedido::factory()->create(['status_id' => $this->entregue->id, 'needed_at' => now()->subDays(5)]);

    expect(AtrasoClassifier::isAtrasado($atrasado->fresh()))->toBeTrue();
    expect(AtrasoClassifier::isAtrasado($noPrazo->fresh()))->toBeFalse();
    expect(AtrasoClassifier::isAtrasado($entregueVencido->fresh()))->toBeFalse();

    Livewire::test(TodosPedidos::class)
        ->set('atrasoOnly', true)
        ->assertSee($atrasado->code)
        ->assertDontSee($noPrazo->code)
        ->assertDontSee($entregueVencido->code);
});

test('the neededAtFrom/neededAtTo range filters independently of requested range', function () {
    $actor = User::factory()->suprimentos()->create();
    $this->actingAs($actor);

    $inRange = Pedido::factory()->create(['status_id' => $this->solicitado->id, 'needed_at' => '2026-06-15']);
    $before = Pedido::factory()->create(['status_id' => $this->solicitado->id, 'needed_at' => '2026-06-01']);
    $after = Pedido::factory()->create(['status_id' => $this->solicitado->id, 'needed_at' => '2026-07-01']);

    Livewire::test(TodosPedidos::class)
        ->set('neededAtFrom', '2026-06-10')
        ->set('neededAtTo', '2026-06-20')
        ->assertSee($inRange->code)
        ->assertDontSee($before->code)
        ->assertDontSee($after->code);
});

test('the requestedFrom/requestedTo range filters independently of needed range', function () {
    $actor = User::factory()->suprimentos()->create();
    $this->actingAs($actor);

    $inRange = Pedido::factory()->create(['status_id' => $this->solicitado->id, 'requested_at' => '2026-06-15 10:00:00']);
    $before = Pedido::factory()->create(['status_id' => $this->solicitado->id, 'requested_at' => '2026-06-01 10:00:00']);
    $after = Pedido::factory()->create(['status_id' => $this->solicitado->id, 'requested_at' => '2026-07-01 10:00:00']);

    Livewire::test(TodosPedidos::class)
        ->set('requestedFrom', '2026-06-10')
        ->set('requestedTo', '2026-06-20')
        ->assertSee($inRange->code)
        ->assertDontSee($before->code)
        ->assertDontSee($after->code);
});

test('combined filters narrow the result to the intersection', function () {
    $actor = User::factory()->suprimentos()->create();
    $this->actingAs($actor);

    $obra = Obra::factory()->create(['name' => 'Residencial Aurora']);

    $matchesAll = Pedido::factory()->create([
        'obra_id' => $obra->id,
        'status_id' => $this->solicitado->id,
        'needed_at' => now()->subDays(2),
    ]);

    $matchesSearchOnly = Pedido::factory()->create([
        'obra_id' => $obra->id,
        'status_id' => $this->solicitado->id,
        'needed_at' => now()->addDays(2),
    ]);

    Livewire::test(TodosPedidos::class)
        ->set('search', 'Aurora')
        ->set('atrasoOnly', true)
        ->assertSee($matchesAll->code)
        ->assertDontSee($matchesSearchOnly->code);
});

test('query count stays constant between a 5-pedido and a 50-pedido dataset', function () {
    $actor = User::factory()->suprimentos()->create();
    $this->actingAs($actor);

    // Warm up lazy-loaded caches (e.g. the actor's `role` relation used by
    // the `is-suprimentos` gate) so they don't skew the first measurement.
    Livewire::test(TodosPedidos::class);

    Pedido::factory()->count(5)->create(['status_id' => $this->solicitado->id]);

    DB::enableQueryLog();
    Livewire::test(TodosPedidos::class);
    $smallDatasetQueryCount = count(DB::getQueryLog());
    DB::flushQueryLog();
    DB::disableQueryLog();

    Pedido::factory()->count(45)->create(['status_id' => $this->solicitado->id]);

    DB::enableQueryLog();
    Livewire::test(TodosPedidos::class);
    $largeDatasetQueryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect(Pedido::query()->count())->toBe(50);
    expect($largeDatasetQueryCount)->toBe($smallDatasetQueryCount);
});

/*
|--------------------------------------------------------------------------
| RF-14 / RF-15 — obra, status, prioridade and responsável filters
|--------------------------------------------------------------------------
|
| The Suprimentos and Gestão listings share the same filter contract, so
| every assertion below runs against both components with the role each one
| requires.
*/

/**
 * Seeds two pedidos that differ on all four new filter axes, plus the actor
 * of the given role.
 *
 * @return array{0: Pedido, 1: Pedido, 2: array<string, int>}
 */
function seedQuatroFiltros(string $role, Status $solicitado, Status $entregue): array
{
    $actor = User::factory()->{$role}()->create();
    test()->actingAs($actor);

    $obraAlvo = Obra::factory()->create(['name' => 'Residencial Aurora']);
    $obraOutra = Obra::factory()->create(['name' => 'Comercial Bravo']);
    $prioridadeAlvo = Priority::factory()->alta()->create();
    $prioridadeOutra = Priority::factory()->baixa()->create();
    $responsavelAlvo = User::factory()->suprimentos()->create();
    $responsavelOutro = User::factory()->suprimentos()->create();

    $alvo = Pedido::factory()->create([
        'obra_id' => $obraAlvo->id,
        'status_id' => $solicitado->id,
        'priority_id' => $prioridadeAlvo->id,
        'responsible_id' => $responsavelAlvo->id,
    ]);

    $outro = Pedido::factory()->create([
        'obra_id' => $obraOutra->id,
        'status_id' => $entregue->id,
        'priority_id' => $prioridadeOutra->id,
        'responsible_id' => $responsavelOutro->id,
    ]);

    return [$alvo, $outro, [
        'obraId' => $obraAlvo->id,
        'statusId' => $solicitado->id,
        'priorityId' => $prioridadeAlvo->id,
        'responsibleId' => $responsavelAlvo->id,
    ]];
}

test('each new filter applied alone reduces the set to exactly the expected pedidos', function (string $component, string $role, string $filter) {
    [$alvo, $outro, $values] = seedQuatroFiltros($role, $this->solicitado, $this->entregue);

    Livewire::test($component)
        ->set($filter, $values[$filter])
        ->assertSee($alvo->code)
        ->assertDontSee($outro->code);
})->with([
    'suprimentos' => [TodosPedidos::class, 'suprimentos'],
    'gestao' => [GestaoTodosPedidos::class, 'gestao'],
])->with(['obraId', 'statusId', 'priorityId', 'responsibleId']);

test('the four new filters combined return the intersection', function (string $component, string $role) {
    [$alvo, $outro, $values] = seedQuatroFiltros($role, $this->solicitado, $this->entregue);

    // A pedido matching only three of the four axes must fall out of the set.
    $parcial = Pedido::factory()->create([
        'obra_id' => $values['obraId'],
        'status_id' => $values['statusId'],
        'priority_id' => $values['priorityId'],
        'responsible_id' => null,
    ]);

    Livewire::test($component)
        ->set('obraId', $values['obraId'])
        ->set('statusId', $values['statusId'])
        ->set('priorityId', $values['priorityId'])
        ->set('responsibleId', $values['responsibleId'])
        ->assertSee($alvo->code)
        ->assertDontSee($parcial->code)
        ->assertDontSee($outro->code);
})->with([
    'suprimentos' => [TodosPedidos::class, 'suprimentos'],
    'gestao' => [GestaoTodosPedidos::class, 'gestao'],
]);

test('the pre-existing filters behave identically with and without the new ones', function (string $component, string $role) {
    $actor = User::factory()->{$role}()->create();
    $this->actingAs($actor);

    $obra = Obra::factory()->create(['name' => 'Residencial Aurora']);
    $priority = Priority::factory()->alta()->create();

    $alvo = Pedido::factory()->create([
        'obra_id' => $obra->id,
        'status_id' => $this->solicitado->id,
        'priority_id' => $priority->id,
        'needed_at' => now()->subDays(2),
        'requested_at' => '2026-06-15 10:00:00',
    ]);

    $foraDoAtraso = Pedido::factory()->create([
        'obra_id' => $obra->id,
        'status_id' => $this->solicitado->id,
        'priority_id' => $priority->id,
        'needed_at' => now()->addDays(20),
        'requested_at' => '2026-06-15 10:00:00',
    ]);

    $semNovosFiltros = fn () => Livewire::test($component)
        ->set('search', 'Aurora')
        ->set('atrasoOnly', true)
        ->set('neededAtFrom', now()->subDays(10)->toDateString())
        ->set('neededAtTo', now()->toDateString())
        ->set('requestedFrom', '2026-06-01')
        ->set('requestedTo', '2026-06-30');

    $semNovosFiltros()
        ->assertSee($alvo->code)
        ->assertDontSee($foraDoAtraso->code);

    $semNovosFiltros()
        ->set('obraId', $obra->id)
        ->set('statusId', $this->solicitado->id)
        ->set('priorityId', $priority->id)
        ->assertSee($alvo->code)
        ->assertDontSee($foraDoAtraso->code);
})->with([
    'suprimentos' => [TodosPedidos::class, 'suprimentos'],
    'gestao' => [GestaoTodosPedidos::class, 'gestao'],
]);

test('a pedido of a deactivated obra stays filterable and the obra stays in the select', function (string $component, string $role) {
    $actor = User::factory()->{$role}()->create();
    $this->actingAs($actor);

    $obraInativa = Obra::factory()->concluida()->create(['name' => 'Residencial Desativada']);
    $outraObra = Obra::factory()->create(['name' => 'Comercial Ativa']);

    $pedido = Pedido::factory()->create(['obra_id' => $obraInativa->id, 'status_id' => $this->solicitado->id]);
    $outro = Pedido::factory()->create(['obra_id' => $outraObra->id, 'status_id' => $this->solicitado->id]);

    Livewire::test($component)
        ->assertSee($obraInativa->name)
        ->set('obraId', $obraInativa->id)
        ->assertSee($pedido->code)
        ->assertDontSee($outro->code);
})->with([
    'suprimentos' => [TodosPedidos::class, 'suprimentos'],
    'gestao' => [GestaoTodosPedidos::class, 'gestao'],
]);

test('pendente combined with statusId returns the intersection of both', function () {
    $actor = User::factory()->gestao()->create();
    $this->actingAs($actor);

    $pendenteSolicitado = Pedido::factory()->create(['status_id' => $this->solicitado->id]);
    $entregue = Pedido::factory()->create(['status_id' => $this->entregue->id]);
    $emAnalise = Status::factory()->emAnalise()->create();
    $pendenteEmAnalise = Pedido::factory()->create(['status_id' => $emAnalise->id]);

    $this->get(route('gestao.pedidos.index', ['pendente' => 'true', 'statusId' => $this->solicitado->id]))
        ->assertOk()
        ->assertSee($pendenteSolicitado->code)
        ->assertDontSee($pendenteEmAnalise->code)
        ->assertDontSee($entregue->code);

    // The boolean criterion still narrows on its own, unchanged from HEAD.
    $this->get(route('gestao.pedidos.index', ['pendente' => 'true']))
        ->assertOk()
        ->assertSee($pendenteSolicitado->code)
        ->assertSee($pendenteEmAnalise->code)
        ->assertDontSee($entregue->code);
});

/*
|--------------------------------------------------------------------------
| RF-20 / CT-02 — URL-addressable filter state
|--------------------------------------------------------------------------
*/

test('a direct URL carrying filter parameters renders the filtered set on first paint with the selects pre-selected', function (string $route, string $role) {
    $actor = User::factory()->{$role}()->create();
    $this->actingAs($actor);

    $obra = Obra::factory()->create(['name' => 'Residencial Aurora']);
    $alvo = Pedido::factory()->create(['obra_id' => $obra->id, 'status_id' => $this->solicitado->id]);
    $outro = Pedido::factory()->create(['status_id' => $this->entregue->id]);

    $response = $this->get(route($route, ['statusId' => $this->solicitado->id, 'obraId' => $obra->id]));

    $response->assertOk()
        ->assertSee($alvo->code)
        ->assertDontSee($outro->code);

    $html = $response->getContent();

    expect($html)->toContain('value="'.$obra->id.'" selected');
    expect($html)->toContain('value="'.$this->solicitado->id.'" selected');
})->with([
    'suprimentos' => ['suprimentos.pedidos.index', 'suprimentos'],
    'gestao' => ['gestao.pedidos.index', 'gestao'],
]);

test('the legacy drill-down parameter names still resolve to the same filtered sets', function () {
    $actor = User::factory()->gestao()->create();
    $this->actingAs($actor);

    $atrasado = Pedido::factory()->create(['status_id' => $this->solicitado->id, 'needed_at' => now()->subDays(2), 'requested_at' => '2026-06-15 10:00:00']);
    $noPrazo = Pedido::factory()->create(['status_id' => $this->solicitado->id, 'needed_at' => now()->addDays(2), 'requested_at' => '2026-06-15 10:00:00']);
    $foraDoPeriodo = Pedido::factory()->create(['status_id' => $this->solicitado->id, 'needed_at' => now()->subDays(2), 'requested_at' => '2026-07-15 10:00:00']);
    $entregue = Pedido::factory()->create(['status_id' => $this->entregue->id, 'needed_at' => now()->subDays(5), 'requested_at' => '2026-06-15 10:00:00']);

    $this->get(route('gestao.pedidos.index', ['atrasado' => 'true']))
        ->assertOk()
        ->assertSee($atrasado->code)
        ->assertSee($foraDoPeriodo->code)
        ->assertDontSee($noPrazo->code)
        ->assertDontSee($entregue->code);

    $this->get(route('gestao.pedidos.index', ['pendente' => 'true']))
        ->assertOk()
        ->assertSee($atrasado->code)
        ->assertSee($noPrazo->code)
        ->assertDontSee($entregue->code);

    $this->get(route('gestao.pedidos.index', ['atrasado' => 'true', 'requestedFrom' => '2026-06-01', 'requestedTo' => '2026-06-30']))
        ->assertOk()
        ->assertSee($atrasado->code)
        ->assertDontSee($foraDoPeriodo->code)
        ->assertDontSee($noPrazo->code);
});

test('changing a filter while on page 3 returns the listing to page 1', function (string $component, string $role) {
    $actor = User::factory()->{$role}()->create();
    $this->actingAs($actor);

    Pedido::factory()->count(25)->create(['status_id' => $this->solicitado->id]);

    Livewire::test($component)
        ->call('gotoPage', 3)
        ->assertSet('paginators.page', 3)
        ->set('search', 'PED-')
        ->assertSet('paginators.page', 1);
})->with([
    'suprimentos' => [TodosPedidos::class, 'suprimentos'],
    'gestao' => [GestaoTodosPedidos::class, 'gestao'],
]);

/*
|--------------------------------------------------------------------------
| RF-19 — "Limpar filtros"
|--------------------------------------------------------------------------
*/

test('limparFiltros resets every filter property to its declared default and returns to page 1', function (string $componentClass, string $role, array $defaults) {
    $actor = User::factory()->{$role}()->create();
    $this->actingAs($actor);

    $obra = Obra::factory()->create();
    $priority = Priority::factory()->alta()->create();
    Pedido::factory()->count(25)->create(['obra_id' => $obra->id, 'status_id' => $this->solicitado->id]);

    $component = Livewire::test($componentClass)
        ->set('search', 'Aurora')
        ->set('atrasoOnly', true)
        ->set('obraId', $obra->id)
        ->set('statusId', $this->solicitado->id)
        ->set('neededAtFrom', '2026-06-01')
        ->set('neededAtTo', '2026-06-30')
        ->set('requestedFrom', '2026-06-01')
        ->set('requestedTo', '2026-06-30');

    if (array_key_exists('priorityId', $defaults)) {
        $component->set('priorityId', $priority->id)->set('responsibleId', $actor->id);
    }

    if (array_key_exists('pendenteOnly', $defaults)) {
        $component->set('pendenteOnly', true);
    }

    // Every filter property now holds a non-default value.
    foreach ($defaults as $property => $default) {
        expect($component->get($property))->not->toBe($default, $property);
    }

    $component->call('gotoPage', 2)->assertSet('paginators.page', 2);

    $component->call('limparFiltros')->assertSet('paginators.page', 1);

    foreach ($defaults as $property => $default) {
        $component->assertSet($property, $default);
    }
})->with([
    'suprimentos' => [TodosPedidos::class, 'suprimentos', [
        'search' => '',
        'atrasoOnly' => false,
        'obraId' => null,
        'statusId' => null,
        'priorityId' => null,
        'responsibleId' => null,
        'neededAtFrom' => '',
        'neededAtTo' => '',
        'requestedFrom' => '',
        'requestedTo' => '',
    ]],
    'gestao' => [GestaoTodosPedidos::class, 'gestao', [
        'search' => '',
        'atrasoOnly' => false,
        'obraId' => null,
        'statusId' => null,
        'priorityId' => null,
        'responsibleId' => null,
        'neededAtFrom' => '',
        'neededAtTo' => '',
        'requestedFrom' => '',
        'requestedTo' => '',
        'pendenteOnly' => null,
    ]],
]);

test('reloading the parameterless listing URL after clearing yields the unfiltered listing', function (string $route, string $role) {
    $actor = User::factory()->{$role}()->create();
    $this->actingAs($actor);

    $atrasado = Pedido::factory()->create(['status_id' => $this->solicitado->id, 'needed_at' => now()->subDays(2)]);
    $noPrazo = Pedido::factory()->create(['status_id' => $this->solicitado->id, 'needed_at' => now()->addDays(2)]);

    $this->get(route($route, ['atrasado' => 'true']))
        ->assertOk()
        ->assertSee($atrasado->code)
        ->assertDontSee($noPrazo->code);

    $this->get(route($route))
        ->assertOk()
        ->assertSee($atrasado->code)
        ->assertSee($noPrazo->code);
})->with([
    'suprimentos' => ['suprimentos.pedidos.index', 'suprimentos'],
    'gestao' => ['gestao.pedidos.index', 'gestao'],
]);

/*
|--------------------------------------------------------------------------
| UI-02 — markup contract of the new controls
|--------------------------------------------------------------------------
*/

test('each new filter control is labelled once, uses form-control, binds live and offers an empty option', function (string $component, string $role) {
    $actor = User::factory()->{$role}()->create();
    $this->actingAs($actor);

    $html = Livewire::test($component)->html();

    $expectedEmptyOptions = [
        'obraId' => 'Todas as obras',
        'statusId' => 'Todos os status',
        'priorityId' => 'Todas as prioridades',
        'responsibleId' => 'Todos os responsáveis',
    ];

    foreach ($expectedEmptyOptions as $controlId => $emptyLabel) {
        expect(substr_count($html, 'id="'.$controlId.'"'))->toBe(1, $controlId.' id must be unique in the document');
        expect(substr_count($html, 'for="'.$controlId.'"'))->toBe(1, $controlId.' must be labelled exactly once');
        expect($html)->toContain('<select id="'.$controlId.'" wire:model.live="'.$controlId.'" class="form-control">');
        expect($html)->toContain('<option value="">'.$emptyLabel.'</option>');
    }

    expect($html)->toContain('>Limpar filtros</button>');
})->with([
    'suprimentos' => [TodosPedidos::class, 'suprimentos'],
    'gestao' => [GestaoTodosPedidos::class, 'gestao'],
]);
