<?php

use App\Enums\EventTypeSlug;
use App\Livewire\Obra\NovaSolicitacao;
use App\Models\EventType;
use App\Models\Obra;
use App\Models\Pedido;
use App\Models\Status;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    Status::factory()->solicitado()->create();
    EventType::factory()->criacaoPedido()->create();
});

test('the obra select only lists the requester associated obras', function () {
    $requester = User::factory()->obra()->create();
    $associatedObra = Obra::factory()->create();
    $otherObra = Obra::factory()->create();
    $requester->obras()->attach($associatedObra->id);

    $this->actingAs($requester);

    Livewire::test(NovaSolicitacao::class)
        ->assertSee($associatedObra->name)
        ->assertDontSee($otherObra->name);
});

test('a valid submission creates the pedido and shows the generated code', function () {
    $requester = User::factory()->obra()->create();
    $obra = Obra::factory()->create();
    $requester->obras()->attach($obra->id);

    $this->actingAs($requester);

    Livewire::test(NovaSolicitacao::class)
        ->set('obra_id', $obra->id)
        ->set('needed_at', '2026-07-01')
        ->set('items_description', 'Cimento e areia')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSee('PED-');

    expect(Pedido::query()->count())->toBe(1);
    $pedido = Pedido::query()->first();
    expect($pedido->events()->where('event_type_id', EventType::query()->where('slug', EventTypeSlug::CriacaoPedido->value)->value('id'))->count())->toBe(1);
});

test('missing obra_id is rejected server-side without creating a pedido', function () {
    $requester = User::factory()->obra()->create();
    $obra = Obra::factory()->create();
    $requester->obras()->attach($obra->id);

    $this->actingAs($requester);

    Livewire::test(NovaSolicitacao::class)
        ->set('obra_id', null)
        ->set('needed_at', '2026-07-01')
        ->set('items_description', 'Cimento e areia')
        ->call('submit')
        ->assertHasErrors(['obra_id' => 'required']);

    expect(Pedido::query()->count())->toBe(0);
});

test('missing needed_at is rejected server-side without creating a pedido', function () {
    $requester = User::factory()->obra()->create();
    $obra = Obra::factory()->create();
    $requester->obras()->attach($obra->id);

    $this->actingAs($requester);

    Livewire::test(NovaSolicitacao::class)
        ->set('obra_id', $obra->id)
        ->set('needed_at', '')
        ->set('items_description', 'Cimento e areia')
        ->call('submit')
        ->assertHasErrors(['needed_at' => 'required']);

    expect(Pedido::query()->count())->toBe(0);
});

test('missing items_description is rejected server-side without creating a pedido', function () {
    $requester = User::factory()->obra()->create();
    $obra = Obra::factory()->create();
    $requester->obras()->attach($obra->id);

    $this->actingAs($requester);

    Livewire::test(NovaSolicitacao::class)
        ->set('obra_id', $obra->id)
        ->set('needed_at', '2026-07-01')
        ->set('items_description', '')
        ->call('submit')
        ->assertHasErrors(['items_description' => 'required']);

    expect(Pedido::query()->count())->toBe(0);
});

test('an obra_id outside the requester association is rejected even when set directly', function () {
    $requester = User::factory()->obra()->create();
    $associatedObra = Obra::factory()->create();
    $unassociatedObra = Obra::factory()->create();
    $requester->obras()->attach($associatedObra->id);

    $this->actingAs($requester);

    Livewire::test(NovaSolicitacao::class)
        ->set('obra_id', $unassociatedObra->id)
        ->set('needed_at', '2026-07-01')
        ->set('items_description', 'Cimento e areia')
        ->call('submit')
        ->assertHasErrors('obra_id');

    expect(Pedido::query()->count())->toBe(0);
});

test('non-obra actors are denied access to the component', function (string $role) {
    $actor = User::factory()->{$role}()->create();

    $this->actingAs($actor);

    Livewire::test(NovaSolicitacao::class)->assertSee('403');
})->with(['suprimentos', 'gestao']);
