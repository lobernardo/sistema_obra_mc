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
