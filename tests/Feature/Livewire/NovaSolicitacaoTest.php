<?php

use App\Enums\EventTypeSlug;
use App\Livewire\Obra\NovaSolicitacao;
use App\Models\EventType;
use App\Models\Obra;
use App\Models\Pedido;
use App\Models\Status;
use App\Models\User;
use Illuminate\Support\Facades\DB;
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

test('the obra select excludes inactive associated obras', function () {
    $requester = User::factory()->obra()->create();
    $activeObra = Obra::factory()->create();
    $inactiveObra = Obra::factory()->concluida()->create();
    $requester->obras()->attach([$activeObra->id, $inactiveObra->id]);

    Livewire::actingAs($requester)->test(NovaSolicitacao::class)
        ->assertSee($activeObra->name)
        ->assertDontSee($inactiveObra->name)
        ->assertSee('Enviar solicitação');
});

test('no active associated obras shows the UI-01 notice and back link without a form', function (bool $hasInactiveObra) {
    $requester = User::factory()->obra()->create();

    if ($hasInactiveObra) {
        $obra = Obra::factory()->concluida()->create();
        $requester->obras()->attach($obra->id);
    }

    Livewire::actingAs($requester)->test(NovaSolicitacao::class)
        ->assertSeeHtml('<p role="status" class="alert-info">Nenhuma obra ativa está associada ao seu usuário. Fale com a Gestão ou com Suprimentos.</p>')
        ->assertSee('Voltar')
        ->assertSeeHtml('href="'.route('obra.pedidos.index').'"')
        ->assertDontSeeHtml('<form')
        ->assertDontSeeHtml('<option')
        ->assertDontSee('Enviar solicitação');
})->with(['only inactive obras' => true, 'no associations' => false]);

test('a forged inactive obra id is rejected without creating a pedido or history', function () {
    $requester = User::factory()->obra()->create();
    $activeObra = Obra::factory()->create();
    $inactiveObra = Obra::factory()->concluida()->create();
    $requester->obras()->attach([$activeObra->id, $inactiveObra->id]);
    $sequence = DB::selectOne('select last_value, is_called from pedido_code_sequence');

    Livewire::actingAs($requester)->test(NovaSolicitacao::class)
        ->set('obra_id', $inactiveObra->id)
        ->set('needed_at', '2026-07-01')
        ->set('items_description', 'Cimento e areia')
        ->call('submit')
        ->assertHasErrors(['obra_id'])
        ->assertSee('A obra informada está inativa e não recebe novas solicitações.')
        ->assertSet('code', null);

    $this->assertDatabaseCount('pedidos', 0);
    $this->assertDatabaseCount('pedido_events', 0);
    expect(DB::selectOne('select last_value, is_called from pedido_code_sequence'))->toEqual($sequence);
});

test('an associated obra with status A iniciar is offered and accepts a new solicitação (RF-03)', function () {
    $requester = User::factory()->obra()->create();
    $obra = Obra::factory()->aIniciar()->create();
    $requester->obras()->attach($obra->id);

    Livewire::actingAs($requester)->test(NovaSolicitacao::class)
        ->assertSee($obra->name)
        ->set('obra_id', $obra->id)
        ->set('needed_at', '2026-07-01')
        ->set('items_description', 'Cimento e areia')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSee('PED-');

    expect(Pedido::query()->where('obra_id', $obra->id)->count())->toBe(1);
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
