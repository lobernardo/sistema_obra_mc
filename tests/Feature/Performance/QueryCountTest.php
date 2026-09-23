<?php

use App\Enums\StatusSlug;
use App\Livewire\Associacoes\Index as AssociacoesIndex;
use App\Livewire\Gestao\Dashboard;
use App\Livewire\Gestao\TodosPedidos as GestaoTodosPedidos;
use App\Livewire\Kanban\KanbanBoard;
use App\Livewire\Obra\Acompanhamento;
use App\Livewire\Obras\Form as ObrasForm;
use App\Livewire\Obras\Index as ObrasIndex;
use App\Livewire\Suprimentos\TodosPedidos;
use App\Livewire\Suprimentos\VisaoGeral;
use App\Models\Obra;
use App\Models\ObraInvitation;
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

    $statuses = seedWorkflowStatuses(fn (StatusSlug $slug): string => ucfirst($slug->value));
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

test('query count stays constant for Gestão\'s Todos os Pedidos listing (T12)', function () {
    $actor = User::factory()->gestao()->create();
    $status = Status::factory()->solicitado()->create();
    $priority = Priority::factory()->normal()->create();
    $responsible = User::factory()->suprimentos()->create();

    $this->actingAs($actor);

    Livewire::test(GestaoTodosPedidos::class);

    $attributes = [
        'status_id' => $status->id,
        'priority_id' => $priority->id,
        'responsible_id' => $responsible->id,
    ];

    Pedido::factory()->count(5)->create($attributes);
    $smallDatasetQueryCount = measureQueryCount(fn () => Livewire::test(GestaoTodosPedidos::class));

    Pedido::factory()->count(45)->create($attributes);
    $largeDatasetQueryCount = measureQueryCount(fn () => Livewire::test(GestaoTodosPedidos::class));

    expect(Pedido::query()->count())->toBe(50);
    expect($largeDatasetQueryCount)->toBe($smallDatasetQueryCount);
});

/**
 * RNF-02 (T27): the Suprimentos "Visão Geral" aggregates over the whole
 * dataset and lists the 5 most recent pedidos, so it must eager-load
 * everything it renders — including the single authorized `entreguesHoje`
 * query, which is constant and never one per pedido.
 */
test('query count stays constant for the Suprimentos Visão Geral screen (T27)', function () {
    $actor = User::factory()->suprimentos()->create();
    $status = Status::factory()->solicitado()->create();
    $priority = Priority::factory()->normal()->create();

    $this->actingAs($actor);

    Livewire::test(VisaoGeral::class);

    $attributes = [
        'status_id' => $status->id,
        'priority_id' => $priority->id,
        'responsible_id' => $actor->id,
    ];

    Pedido::factory()->count(5)->create($attributes);
    $smallDatasetQueryCount = measureQueryCount(fn () => Livewire::test(VisaoGeral::class));

    Pedido::factory()->count(45)->create($attributes);
    $largeDatasetQueryCount = measureQueryCount(fn () => Livewire::test(VisaoGeral::class));

    expect(Pedido::query()->count())->toBe(50);
    expect($largeDatasetQueryCount)->toBe($smallDatasetQueryCount);
});

/**
 * RNF-04 (obras-associacoes-cadastro-convites T24): the three listings of
 * the Obras area — the Obras list, the Associações list and the convite list
 * of the obra form — issue a query count that does not grow with the number
 * of rows on the page.
 */
test('query count stays constant for the Obras listing (RNF-04)', function () {
    $actor = User::factory()->gestao()->create();

    $this->actingAs($actor);

    Livewire::test(ObrasIndex::class);

    Obra::factory()->count(5)->create();
    $smallDatasetQueryCount = measureQueryCount(fn () => Livewire::test(ObrasIndex::class));

    Obra::factory()->count(10)->create();
    $largeDatasetQueryCount = measureQueryCount(fn () => Livewire::test(ObrasIndex::class)->assertViewHas('obras', fn ($obras) => $obras->count() === 15));

    expect(Obra::query()->count())->toBe(15);
    expect($largeDatasetQueryCount)->toBe($smallDatasetQueryCount);
});

test('query count stays constant for the Associações listing with 3 obras per user (RNF-04)', function () {
    $actor = User::factory()->suprimentos()->create();
    $obras = Obra::factory()->count(3)->create();

    $this->actingAs($actor);

    Livewire::test(AssociacoesIndex::class);

    $createUsersWithObras = function (int $count) use ($obras): void {
        User::factory()->count($count)->obra()->create()
            ->each(fn (User $user) => $user->obras()->attach($obras->pluck('id')));
    };

    // The authenticated suprimentos user is listed too: 4 listed users, then 14.
    $createUsersWithObras(4);
    $smallDatasetQueryCount = measureQueryCount(fn () => Livewire::test(AssociacoesIndex::class));

    $createUsersWithObras(10);
    $largeDatasetQueryCount = measureQueryCount(fn () => Livewire::test(AssociacoesIndex::class)->assertViewHas('users', fn ($users) => $users->count() === 15));

    expect(DB::table('obra_profile')->count())->toBe(14 * 3);
    expect($largeDatasetQueryCount)->toBe($smallDatasetQueryCount);
});

test('query count stays constant for the convite list of the obra form, in mixed states (RNF-04)', function () {
    $actor = User::factory()->gestao()->create();
    $obra = Obra::factory()->create();

    $this->actingAs($actor);

    Livewire::test(ObrasForm::class, ['obra' => $obra]);

    $createMixedInvitations = function (int $perState) use ($obra): void {
        $factory = ObraInvitation::factory()->for($obra);

        $factory->count($perState)->create();
        $factory->revoked()->count($perState)->create();
        $factory->used()->count($perState)->create();
        $factory->expired()->count($perState)->create();
    };

    $createMixedInvitations(1);
    ObraInvitation::factory()->for($obra)->create();
    $smallDatasetQueryCount = measureQueryCount(fn () => Livewire::test(ObrasForm::class, ['obra' => $obra]));

    $createMixedInvitations(6);
    ObraInvitation::factory()->for($obra)->used()->create();
    $largeDatasetQueryCount = measureQueryCount(fn () => Livewire::test(ObrasForm::class, ['obra' => $obra])->assertViewHas('invitations', fn ($invitations) => $invitations->count() === 30));

    expect($obra->invitations()->count())->toBe(30);
    expect($largeDatasetQueryCount)->toBe($smallDatasetQueryCount);
});
