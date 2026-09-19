<?php

use App\Enums\StatusSlug;
use App\Livewire\Gestao\Dashboard;
use App\Livewire\Kanban\KanbanBoard;
use App\Livewire\Obra\Acompanhamento;
use App\Livewire\Suprimentos\TodosPedidos;
use App\Models\Obra;
use App\Models\Pedido;
use App\Models\Priority;
use App\Models\Status;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/**
 * RNF-07 consolidated: every screen that lists potentially numerous pedidos
 * must paginate (or, for the Kanban/dashboard aggregates, at least eager-load)
 * so the number of SQL queries it issues does not scale with the size of the
 * dataset. Each test below measures the query count against a 5-pedido
 * dataset, adds 45 more pedidos (50 total), and asserts the query count for
 * an identical request is unchanged — proving there is no N+1 relation load
 * hidden behind the pagination/aggregation.
 */
function measureQueryCount(Closure $action): int
{
    DB::enableQueryLog();
    $action();
    $count = count(DB::getQueryLog());
    DB::flushQueryLog();
    DB::disableQueryLog();

    return $count;
}

test('query count stays constant for Obra\'s Acompanhamento listing (T32)', function () {
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
    $smallDatasetQueryCount = measureQueryCount(fn () => Livewire::test(Acompanhamento::class));

    Pedido::factory()->count(45)->create($attributes);
    $largeDatasetQueryCount = measureQueryCount(fn () => Livewire::test(Acompanhamento::class));

    expect(Pedido::query()->count())->toBe(50);
    expect($largeDatasetQueryCount)->toBe($smallDatasetQueryCount);
});

test('query count stays constant for Suprimentos\' Todos os Pedidos listing (T34)', function () {
    $actor = User::factory()->suprimentos()->create();
    $status = Status::factory()->solicitado()->create();
    $priority = Priority::factory()->normal()->create();

    $this->actingAs($actor);

    Livewire::test(TodosPedidos::class);

    $attributes = [
        'status_id' => $status->id,
        'priority_id' => $priority->id,
        'responsible_id' => $actor->id,
    ];

    Pedido::factory()->count(5)->create($attributes);
    $smallDatasetQueryCount = measureQueryCount(fn () => Livewire::test(TodosPedidos::class));

    Pedido::factory()->count(45)->create($attributes);
    $largeDatasetQueryCount = measureQueryCount(fn () => Livewire::test(TodosPedidos::class));

    expect(Pedido::query()->count())->toBe(50);
    expect($largeDatasetQueryCount)->toBe($smallDatasetQueryCount);
});

test('query count stays constant for the Kanban board (T35)', function () {
    $actor = User::factory()->suprimentos()->create();

    $statuses = [];
    foreach (StatusSlug::cases() as $slug) {
        $statuses[$slug->value] = Status::factory()->create([
            'slug' => $slug->value,
            'name' => ucfirst($slug->value),
            'sort_order' => array_search($slug, StatusSlug::cases(), true) + 1,
        ]);
    }
    $priority = Priority::factory()->normal()->create();

    $this->actingAs($actor);

    Livewire::test(KanbanBoard::class);

    $attributes = [
        'status_id' => $statuses['solicitado']->id,
        'priority_id' => $priority->id,
        'responsible_id' => $actor->id,
    ];

    Pedido::factory()->count(5)->create($attributes);
    $smallDatasetQueryCount = measureQueryCount(fn () => Livewire::test(KanbanBoard::class));

    Pedido::factory()->count(45)->create($attributes);
    $largeDatasetQueryCount = measureQueryCount(fn () => Livewire::test(KanbanBoard::class));

    expect(Pedido::query()->where('status_id', $statuses['solicitado']->id)->count())->toBe(50);
    expect($largeDatasetQueryCount)->toBe($smallDatasetQueryCount);
});

test('query count stays constant for the Gestão dashboard (T43)', function () {
    $actor = User::factory()->gestao()->create();
    $status = Status::factory()->solicitado()->create();
    $priority = Priority::factory()->normal()->create();
    $responsible = User::factory()->suprimentos()->create();

    $this->actingAs($actor);

    Livewire::test(Dashboard::class);

    $attributes = [
        'status_id' => $status->id,
        'priority_id' => $priority->id,
        'responsible_id' => $responsible->id,
    ];

    Pedido::factory()->count(5)->create($attributes);
    $smallDatasetQueryCount = measureQueryCount(fn () => Livewire::test(Dashboard::class));

    Pedido::factory()->count(45)->create($attributes);
    $largeDatasetQueryCount = measureQueryCount(fn () => Livewire::test(Dashboard::class));

    expect(Pedido::query()->count())->toBe(50);
    expect($largeDatasetQueryCount)->toBe($smallDatasetQueryCount);
});
