<?php

use App\Actions\Usuarios\DetachUserObraAction;
use App\Enums\UserAdminAction;
use App\Models\EventType;
use App\Models\Obra;
use App\Models\Pedido;
use App\Models\PedidoEvent;
use App\Models\Status;
use App\Models\User;
use App\Models\UserAdminEvent;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->action = app(DetachUserObraAction::class);
});

test('removing an association deletes only that row, audits it and leaves pedidos and events untouched (RF-13, RF-12)', function () {
    $actor = User::factory()->suprimentos()->create();
    $target = User::factory()->obra()->create();
    [$obraA, $obraB] = Obra::factory()->count(2)->create();
    $target->obras()->attach([$obraA->id, $obraB->id]);
    $status = Status::factory()->solicitado()->create();
    $eventType = EventType::factory()->criacaoPedido()->create();
    $pedido = Pedido::factory()->for($obraA)->for($status)->create(['requester_id' => $target->id]);
    $pedido->events()->create(['event_type_id' => $eventType->id, 'actor_id' => $target->id]);
    $pedidosBefore = DB::table('pedidos')->get()->toArray();
    $eventsBefore = DB::table('pedido_events')->get()->toArray();

    $this->action->execute($actor, $target, ['obra_id' => $obraA->id]);

    expect($target->obras()->pluck('obras.id')->all())->toBe([$obraB->id]);
    expect(DB::table('pedidos')->get()->toArray())->toEqual($pedidosBefore);
    expect(DB::table('pedido_events')->get()->toArray())->toEqual($eventsBefore);

    $audit = UserAdminEvent::query()->sole();

    expect($audit->action)->toBe(UserAdminAction::ObraAccessChanged);
    expect($audit->actor_id)->toBe($actor->id);
    expect($audit->target_id)->toBe($target->id);
    expect($audit->before)->toBe(['obra_ids' => collect([$obraA->id, $obraB->id])->sort()->values()->all()]);
    expect($audit->after)->toBe(['obra_ids' => [$obraB->id]]);
});

test('after the removal the obra user gets 403 on that pedido while Suprimentos and Gestão still get 200 (RF-13, RF-15)', function () {
    $target = User::factory()->obra()->create();
    $obra = Obra::factory()->create();
    $target->obras()->attach($obra->id);
    $pedido = Pedido::factory()->for($obra)->for(Status::factory()->solicitado())->create(['requester_id' => $target->id]);

    $this->actingAs($target)->get(route('obra.pedidos.show', $pedido))->assertOk();

    $this->action->execute(User::factory()->gestao()->create(), $target, ['obra_id' => $obra->id]);

    $this->actingAs($target->fresh())->get(route('obra.pedidos.show', $pedido))->assertForbidden();
    $this->actingAs(User::factory()->suprimentos()->create())->get(route('suprimentos.pedidos.show', $pedido))->assertOk();
    $this->actingAs(User::factory()->gestao()->create())->get(route('gestao.pedidos.show', $pedido))->assertOk();
    expect(Pedido::query()->whereKey($pedido->id)->exists())->toBeTrue();
    expect(PedidoEvent::query()->where('pedido_id', $pedido->id)->count())->toBe($pedido->events()->count());
});

test('an obra that is not associated yields 422 naming it and nothing is written', function () {
    $actor = User::factory()->gestao()->create();
    $target = User::factory()->obra()->create();
    $obra = Obra::factory()->create(['name' => 'Obra Avulsa']);

    try {
        $this->action->execute($actor, $target, ['obra_id' => $obra->id]);

        $this->fail('A ValidationException was expected.');
    } catch (ValidationException $exception) {
        expect($exception->status)->toBe(422);
        expect($exception->errors()['obra_id'])->toBe(['A obra «Obra Avulsa» não está associada a este usuário.']);
    }

    expect(UserAdminEvent::query()->count())->toBe(0);
});

test('a gestao target is rejected with 422 on user_id (NC-03)', function () {
    $actor = User::factory()->gestao()->create();
    $target = User::factory()->gestao()->create();
    $obra = Obra::factory()->create();

    try {
        $this->action->execute($actor, $target, ['obra_id' => $obra->id]);

        $this->fail('A ValidationException was expected.');
    } catch (ValidationException $exception) {
        expect($exception->errors()['user_id'])->toBe(['Somente usuários dos perfis Obra e Suprimentos podem ter obras associadas.']);
    }
});

test('an obra actor gets AuthorizationException and the association stays (RF-07)', function () {
    $actor = User::factory()->obra()->create();
    $target = User::factory()->obra()->create();
    $obra = Obra::factory()->create();
    $target->obras()->attach($obra->id);

    expect(fn () => $this->action->execute($actor, $target, ['obra_id' => $obra->id]))
        ->toThrow(AuthorizationException::class, 'Apenas os perfis Gestão e Suprimentos podem administrar obras.');

    expect($target->obras()->pluck('obras.id')->all())->toBe([$obra->id]);
    expect(UserAdminEvent::query()->count())->toBe(0);
});

test('a Suprimentos actor removes an obra from its own account: −1 row, 1 audit with actor = target (RF-11 v1.3, F-13)', function () {
    $actor = User::factory()->suprimentos()->create();
    $obra = Obra::factory()->create();
    $actor->obras()->attach($obra->id);

    $this->action->execute($actor, $actor, ['obra_id' => $obra->id]);

    expect(DB::table('obra_profile')->where('user_id', $actor->id)->count())->toBe(0);

    $audit = UserAdminEvent::query()->sole();

    expect($audit->actor_id)->toBe($actor->id);
    expect($audit->target_id)->toBe($actor->id);
});
