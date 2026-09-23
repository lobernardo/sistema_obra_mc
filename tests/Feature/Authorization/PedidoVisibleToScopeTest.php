<?php

use App\Models\Obra;
use App\Models\Pedido;
use App\Models\Role;
use App\Models\Status;
use App\Models\User;

test('visibleTo returns exactly the pedidos allowed for each role', function (string $role) {
    $user = $role === 'unknown'
        ? User::factory()->for(Role::factory()->state(['slug' => 'unknown']))->create()
        : User::factory()->{$role}()->create();
    $ownObra = Obra::factory()->create();
    $otherObra = Obra::factory()->create();
    $user->obras()->attach($ownObra);
    $status = Status::factory()->solicitado()->create();
    $ownPedidos = Pedido::factory()->count(2)->for($ownObra)->for($status)->create();
    $otherPedido = Pedido::factory()->for($otherObra)->for($status)->create();

    $expected = match ($role) {
        'obra' => $ownPedidos->modelKeys(),
        'suprimentos', 'gestao' => [...$ownPedidos->modelKeys(), $otherPedido->id],
        default => [],
    };

    expect(Pedido::query()->visibleTo($user)->pluck('id')->all())
        ->toEqualCanonicalizing($expected);

    $page = Pedido::query()->visibleTo($user)
        ->with(['obra', 'status'])->latest('requested_at')->paginate(10);

    expect($page->getCollection()->modelKeys())->toEqualCanonicalizing($expected)
        ->and($page->total())->toBe(count($expected));
})->with(['obra', 'suprimentos', 'gestao', 'unknown']);

test('associated inactive obras remain visible without including other obras', function () {
    $user = User::factory()->obra()->create();
    $obra = Obra::factory()->concluida()->create();
    $user->obras()->attach($obra);
    $status = Status::factory()->solicitado()->create();
    $pedido = Pedido::factory()->for($obra)->for($status)->create();
    Pedido::factory()->for($status)->create();

    expect(Pedido::query()->visibleTo($user)->pluck('id')->all())->toBe([$pedido->id]);
});

test('missing role and obra users without associations see no pedidos', function () {
    Pedido::factory()->create();
    $user = User::factory()->obra()->create();

    expect(Pedido::query()->visibleTo($user)->pluck('id')->all())->toBe([]);

    $user->setRelation('role', null);

    expect(Pedido::query()->visibleTo($user)->pluck('id')->all())->toBe([]);
});

/**
 * RF-17 / RNF-08: the obra filter narrows inside `visibleTo`, it never
 * widens it. If the scope were applied after the filter, this case would
 * return the foreign obra's pedido instead of an empty set.
 */
test('a forged obraId of a foreign obra returns an empty set on the Obra listing', function () {
    $user = User::factory()->obra()->create();
    $ownObra = Obra::factory()->create(['name' => 'Residencial Aurora']);
    $foreignObra = Obra::factory()->create(['name' => 'Comercial Alheia']);
    $user->obras()->attach($ownObra);

    $status = Status::factory()->solicitado()->create();
    $ownPedido = Pedido::factory()->for($ownObra)->for($status)->create();
    $foreignPedido = Pedido::factory()->for($foreignObra)->for($status)->create();

    $this->actingAs($user)
        ->get(route('obra.pedidos.index', ['obraId' => $foreignObra->id]))
        ->assertOk()
        ->assertDontSee($foreignPedido->code)
        ->assertDontSee($ownPedido->code)
        ->assertSee('Nenhum pedido encontrado para as suas obras.');
});

/**
 * RF-18: the Obra screen's obra option set is scoped to the user's own
 * obras. An unscoped source would enumerate every obra name in the company
 * to a user entitled to one — a disclosure the row-count assertion above
 * cannot catch.
 */
test('the rendered obra select of an Obra user lists no obra they are not associated with', function () {
    $user = User::factory()->obra()->create();
    $ownObra = Obra::factory()->create(['name' => 'Residencial Aurora']);
    $foreignObra = Obra::factory()->create(['name' => 'Comercial Alheia']);
    $user->obras()->attach($ownObra);

    $status = Status::factory()->solicitado()->create();
    Pedido::factory()->for($ownObra)->for($status)->create();
    Pedido::factory()->for($foreignObra)->for($status)->create();

    $response = $this->actingAs($user)->get(route('obra.pedidos.index'));

    $response->assertOk()
        ->assertSee($ownObra->name)
        ->assertDontSee($foreignObra->name);

    expect($response->getContent())->not->toContain('value="'.$foreignObra->id.'"');
});

/**
 * RF-40: an `obra` user sees their own pedidos "Outra" (no obra,
 * `requester_id` = user) and never another user's, whatever their
 * associations; Suprimentos and Gestão see every pedido "Outra".
 */
test('visibleTo includes only the requester\'s own pedidos "Outra" for obra users', function () {
    $status = Status::factory()->solicitado()->create();
    $u1 = User::factory()->obra()->create();
    $u2 = User::factory()->obra()->create();
    $u3 = User::factory()->obra()->create();
    $obraA = Obra::factory()->create();
    $obraB = Obra::factory()->create();
    $u1->obras()->attach($obraA);
    $u3->obras()->attach([$obraA->id, $obraB->id]);

    $outraU1 = Pedido::factory()->outra()->for($status)->create(['requester_id' => $u1->id]);
    $outraU2 = Pedido::factory()->outra('Galpão provisório')->for($status)->create(['requester_id' => $u2->id]);
    $pedidoA = Pedido::factory()->for($obraA)->for($status)->create(['requester_id' => $u1->id]);

    expect(Pedido::query()->visibleTo($u1)->pluck('id')->all())
        ->toEqualCanonicalizing([$outraU1->id, $pedidoA->id]);
    expect(Pedido::query()->visibleTo($u2)->pluck('id')->all())->toBe([$outraU2->id]);
    expect(Pedido::query()->visibleTo($u3)->pluck('id')->all())->toBe([$pedidoA->id]);

    foreach (['suprimentos', 'gestao'] as $role) {
        expect(Pedido::query()->visibleTo(User::factory()->{$role}()->create())->pluck('id')->all())
            ->toEqualCanonicalizing([$outraU1->id, $outraU2->id, $pedidoA->id]);
    }
});

test('an obraId filter after visibleTo never adds another user\'s pedido "Outra" nor a foreign obra\'s pedido', function () {
    $status = Status::factory()->solicitado()->create();
    $user = User::factory()->obra()->create();
    $ownObra = Obra::factory()->create();
    $foreignObra = Obra::factory()->create();
    $user->obras()->attach($ownObra);

    $ownPedido = Pedido::factory()->for($ownObra)->for($status)->create(['requester_id' => $user->id]);
    $ownOutra = Pedido::factory()->outra()->for($status)->create(['requester_id' => $user->id]);
    $foreignOutra = Pedido::factory()->outra()->for($status)->create();
    $foreignPedido = Pedido::factory()->for($foreignObra)->for($status)->create();

    $filtered = fn (?int $obraId): array => Pedido::query()->visibleTo($user)
        ->when($obraId !== null, fn ($query) => $query->where('obra_id', $obraId))
        ->pluck('id')->all();

    expect($filtered($ownObra->id))->toBe([$ownPedido->id]);
    expect($filtered($foreignObra->id))->toBe([]);
    expect(Pedido::query()->visibleTo($user)->whereNull('obra_id')->pluck('id')->all())->toBe([$ownOutra->id]);
    expect(Pedido::query()->visibleTo($user)->pluck('id')->all())
        ->not->toContain($foreignOutra->id)
        ->not->toContain($foreignPedido->id);
});

test('a pedido "Outra" whose reference equals an obra name is not visible to that obra\'s users', function () {
    $status = Status::factory()->solicitado()->create();
    $obraX = Obra::factory()->create(['name' => 'Residencial Aurora']);
    $userOfX = User::factory()->obra()->create();
    $userOfX->obras()->attach($obraX);

    $outra = Pedido::factory()->outra('Residencial Aurora')->for($status)->create();

    expect(Pedido::query()->visibleTo($userOfX)->pluck('id')->all())->not->toContain($outra->id);
});
