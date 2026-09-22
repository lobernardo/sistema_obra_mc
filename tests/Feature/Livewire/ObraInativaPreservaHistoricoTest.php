<?php

use App\Livewire\Gestao\KanbanReadOnly;
use App\Livewire\Kanban\KanbanBoard;
use App\Models\Pedido;
use App\Models\PedidoEvent;
use App\Models\User;
use App\Services\DashboardIndicatorsService;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/**
 * D-06, RF-07, RF-08: deactivating an obra only blocks new requests;
 * existing pedidos remain visible and their history stays intact.
 * Full row snapshots include pedidos.updated_at and every event attribute:
 * pedido_events is append-only and has no updated_at column.
 */
beforeEach(function () {
    $this->pedido = Pedido::factory()->create();
    $this->event = PedidoEvent::factory()->create([
        'pedido_id' => $this->pedido->id,
        'actor_id' => $this->pedido->requester_id,
    ]);
});

test('an obra user keeps listing and detail access after their obra is deactivated', function () {
    $pedido = $this->pedido;
    $requester = $pedido->requester;

    $pedido->obra->update(['is_active' => false]);

    expect(Pedido::visibleTo($requester)->pluck('id')->all())->toBe([$pedido->id]);

    $this->actingAs($requester)
        ->get(route('obra.pedidos.index'))
        ->assertOk()
        ->assertSee($pedido->code);

    $this->get(route('obra.pedidos.show', $pedido))
        ->assertOk()
        ->assertSee($pedido->code)
        ->assertSee($this->event->eventType->name);
});

test('suprimentos and gestao keep the inactive obra pedido in their listing and kanban', function (string $role, string $component) {
    $actor = User::factory()->{$role}()->create();
    $pedido = $this->pedido;

    $pedido->obra->update(['is_active' => false]);

    expect(Pedido::visibleTo($actor)->pluck('id')->all())->toBe([$pedido->id]);

    $this->actingAs($actor)
        ->get(route($role.'.pedidos.index'))
        ->assertOk()
        ->assertSee($pedido->code);

    Livewire::test($component)
        ->assertSee($pedido->code)
        ->assertSee($pedido->obra->name);
})->with([
    'suprimentos' => ['suprimentos', KanbanBoard::class],
    'gestao' => ['gestao', KanbanReadOnly::class],
]);

test('dashboard indicators still count an existing pedido after its obra is deactivated', function () {
    $pedido = $this->pedido;

    $pedido->obra->update(['is_active' => false]);

    $indicators = app(DashboardIndicatorsService::class)->compute([]);

    expect($indicators['volumeTotal'])->toBe(1);
    expect($indicators['pendentes'])->toBe(1);
    expect($indicators['porObra']->sole()['obra']->id)->toBe($pedido->obra_id);
    expect($indicators['porObra']->sole()['count'])->toBe(1);
    expect($indicators['porStatus']->sole()['count'])->toBe(1);
});

test('deactivating an obra preserves all pedido and history rows including timestamps', function () {
    $pedidosBefore = DB::table('pedidos')->orderBy('id')->get()->all();
    $eventsBefore = DB::table('pedido_events')->orderBy('id')->get()->all();

    $this->travel(1)->day();
    $this->pedido->obra->update(['is_active' => false]);

    expect($this->pedido->obra->fresh()->is_active)->toBeFalse();
    $this->assertDatabaseCount('pedidos', 1);
    $this->assertDatabaseCount('pedido_events', 1);
    expect(DB::table('pedidos')->orderBy('id')->get()->all())->toEqual($pedidosBefore);
    expect(DB::table('pedido_events')->orderBy('id')->get()->all())->toEqual($eventsBefore);
});
