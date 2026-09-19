<?php

use App\Domain\Pedidos\AtrasoClassifier;
use App\Livewire\Suprimentos\TodosPedidos;
use App\Models\Obra;
use App\Models\Pedido;
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
