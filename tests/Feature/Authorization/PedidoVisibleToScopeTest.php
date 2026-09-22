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
    $obra = Obra::factory()->create(['is_active' => false]);
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
