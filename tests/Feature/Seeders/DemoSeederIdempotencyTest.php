<?php

use App\Enums\EventTypeSlug;
use App\Enums\ObraStatus;
use App\Models\EventType;
use App\Models\Obra;
use App\Models\Pedido;
use App\Models\PedidoEvent;
use App\Models\Status;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\DB;

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

test('the seeder keeps exactly 7 statuses and 10 event types after one and two runs (RF-42, RF-43)', function () {
    $this->seed(DemoSeeder::class);

    expect(Status::query()->count())->toBe(7);
    expect(EventType::query()->count())->toBe(10);
    expect(Status::query()->where('slug', 'finalizado')->value('sort_order'))->toBe(7);

    $this->seed(DemoSeeder::class);

    expect(Status::query()->count())->toBe(7);
    expect(EventType::query()->count())->toBe(10);
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

test('the demo Suprimentos user holds exactly one association per active demo obra after two runs (RF-43, RF-48)', function () {
    $concluida = Obra::factory()->concluida()->create(['name' => '[DEMO] Obra Concluída', 'is_demo' => true]);
    $realObra = Obra::factory()->emAndamento()->create(['is_demo' => false]);
    $realSuprimentos = User::factory()->suprimentos()->create(['is_demo' => false]);

    $this->seed(DemoSeeder::class);
    $this->seed(DemoSeeder::class);

    expect(Status::query()->count())->toBe(7);
    expect(EventType::query()->count())->toBe(10);

    $suprimentos = User::query()->where('email', 'suprimentos.demo@example.com')->firstOrFail();
    $activeDemoObraIds = Obra::query()->where('is_demo', true)->active()->orderBy('id')->pluck('id')->all();

    expect($activeDemoObraIds)->not->toBeEmpty();

    $associations = DB::table('obra_profile')->where('user_id', $suprimentos->id)->orderBy('obra_id')->pluck('obra_id')->all();

    expect($associations)->toBe($activeDemoObraIds);
    expect($associations)->not->toContain($concluida->id);
    expect($associations)->not->toContain($realObra->id);
    expect(DB::table('obra_profile')->where('user_id', $realSuprimentos->id)->count())->toBe(0);
    expect(DB::table('obra_profile')->where('obra_id', $realObra->id)->count())->toBe(0);
});

test('the demo Suprimentos user sees the Nova Solicitação form, not the empty state (RF-01, F-03)', function () {
    $this->seed(DemoSeeder::class);

    $suprimentos = User::query()->where('email', 'suprimentos.demo@example.com')->firstOrFail();

    $this->actingAs($suprimentos)
        ->get(route('suprimentos.nova-solicitacao'))
        ->assertOk()
        ->assertSee('wire:submit="submit"', false)
        ->assertSee('[DEMO] Obra Alfa')
        ->assertDontSee('Nenhuma obra ativa está associada ao seu usuário.');
});

test('the seeder criacao_pedido events carry the obra label snapshot (F-09)', function () {
    $this->seed(DemoSeeder::class);

    $events = PedidoEvent::query()
        ->whereHas('eventType', fn ($query) => $query->where('slug', EventTypeSlug::CriacaoPedido->value))
        ->with('pedido.obra')
        ->get();

    expect($events)->not->toBeEmpty();
    expect($events->every(fn (PedidoEvent $event): bool => $event->new_value === $event->pedido->obraLabel()))->toBeTrue();
});

test('php artisan db:seed through DatabaseSeeder keeps model events, so every demo pedido gets requested_at and data_prevista, twice in a row', function () {
    $this->artisan('db:seed', ['--force' => true])->assertSuccessful();
    $this->artisan('db:seed', ['--force' => true])->assertSuccessful();

    $pedidos = Pedido::query()->where('is_demo', true)->get();

    expect($pedidos)->not->toBeEmpty();
    expect($pedidos->whereNull('requested_at'))->toBeEmpty();
    expect($pedidos->whereNull('data_prevista'))->toBeEmpty();
});
