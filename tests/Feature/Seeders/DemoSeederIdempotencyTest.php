<?php

use App\Enums\ObraStatus;
use App\Models\Obra;
use App\Models\Pedido;
use App\Models\PedidoEvent;
use App\Models\User;
use Database\Seeders\DemoSeeder;

test('running the seeder twice produces identical demo counts without unique violations', function () {
    $this->seed(DemoSeeder::class);

    $usersAfterFirstRun = User::query()->where('is_demo', true)->count();
    $obrasAfterFirstRun = Obra::query()->where('is_demo', true)->count();
    $pedidosAfterFirstRun = Pedido::query()->where('is_demo', true)->count();
    $eventsAfterFirstRun = PedidoEvent::query()
        ->whereHas('pedido', fn ($query) => $query->where('is_demo', true))
        ->count();

    expect($usersAfterFirstRun)->toBeGreaterThan(0);
    expect($obrasAfterFirstRun)->toBeGreaterThan(0);
    expect($pedidosAfterFirstRun)->toBeGreaterThan(0);
    expect($eventsAfterFirstRun)->toBeGreaterThan(0);

    $this->seed(DemoSeeder::class);

    expect(User::query()->where('is_demo', true)->count())->toBe($usersAfterFirstRun);
    expect(Obra::query()->where('is_demo', true)->count())->toBe($obrasAfterFirstRun);
    expect(Pedido::query()->where('is_demo', true)->count())->toBe($pedidosAfterFirstRun);
    expect(PedidoEvent::query()->whereHas('pedido', fn ($query) => $query->where('is_demo', true))->count())
        ->toBe($eventsAfterFirstRun);
});

test('every user, obra and pedido created by the seeder is flagged is_demo', function () {
    $this->seed(DemoSeeder::class);

    expect(User::query()->count())->toBe(User::query()->where('is_demo', true)->count());
    expect(Obra::query()->count())->toBe(Obra::query()->where('is_demo', true)->count());
    expect(Pedido::query()->count())->toBe(Pedido::query()->where('is_demo', true)->count());
});

test('every demo obra is seeded with the Em andamento status (RF-35, CT-01)', function () {
    $this->seed(DemoSeeder::class);

    expect(Obra::query()->whereNull('status')->count())->toBe(0);
    expect(Obra::query()->get()->every(fn (Obra $obra): bool => $obra->status === ObraStatus::EmAndamento))->toBeTrue();
});

test('demo dataset includes at least one atrasado pedido and one entregue pedido', function () {
    $this->seed(DemoSeeder::class);

    $entregueCount = Pedido::query()
        ->whereHas('status', fn ($query) => $query->where('slug', 'entregue'))
        ->count();

    $atrasadoCount = Pedido::query()
        ->whereHas('status', fn ($query) => $query->whereNotIn('slug', ['entregue', 'cancelado']))
        ->where('needed_at', '<', now()->toDateString())
        ->count();

    expect($entregueCount)->toBeGreaterThanOrEqual(1);
    expect($atrasadoCount)->toBeGreaterThanOrEqual(1);
});

test('demo dataset includes a multi-obra obra user', function () {
    $this->seed(DemoSeeder::class);

    $multiObraUser = User::query()->where('email', 'obra.multiobra.demo@example.com')->firstOrFail();

    expect($multiObraUser->obras()->count())->toBeGreaterThanOrEqual(2);
});
