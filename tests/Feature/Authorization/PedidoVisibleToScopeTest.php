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
