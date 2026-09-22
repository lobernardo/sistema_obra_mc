<?php

use App\Actions\Pedidos\CreatePedidoAction;
use App\Enums\EventTypeSlug;
use App\Models\EventType;
use App\Models\Obra;
use App\Models\Pedido;
use App\Models\PedidoEvent;
use App\Models\Status;
use App\Models\User;
use App\Services\PedidoCodeGenerator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Status::factory()->solicitado()->create();
    Status::factory()->emAnalise()->create();
    EventType::factory()->criacaoPedido()->create();
});

function createPedidoAction(): CreatePedidoAction
{
    return new CreatePedidoAction(new PedidoCodeGenerator);
}

test('an associated inactive obra is rejected without inserts or consuming the pedido code sequence', function () {
    $requester = User::factory()->obra()->create();
    $obra = Obra::factory()->inactive()->create();
    $requester->obras()->attach($obra->id);
    $pedidoCount = Pedido::query()->count();
    $eventCount = PedidoEvent::query()->count();
    $sequence = DB::selectOne('select last_value, is_called from pedido_code_sequence');

    expect(fn () => createPedidoAction()->execute($requester, [
        'obra_id' => $obra->id,
        'needed_at' => '2026-07-01',
        'items_description' => 'Cimento e areia',
    ]))->toThrow(function (ValidationException $exception) {
        expect($exception->errors())->toBe([
            'obra_id' => ['A obra informada está inativa e não recebe novas solicitações.'],
        ]);
    });

    expect(Pedido::query()->count())->toBe($pedidoCount);
    expect(PedidoEvent::query()->count())->toBe($eventCount);
    expect(DB::selectOne('select last_value, is_called from pedido_code_sequence'))->toEqual($sequence);
});

test('an unassociated inactive obra keeps the existing association error', function () {
    $requester = User::factory()->obra()->create();
    $obra = Obra::factory()->inactive()->create();

    expect(fn () => createPedidoAction()->execute($requester, [
        'obra_id' => $obra->id,
        'needed_at' => '2026-07-01',
        'items_description' => 'Cimento e areia',
    ]))->toThrow(function (ValidationException $exception) {
        expect($exception->errors())->toBe([
            'obra_id' => ['A obra informada não está associada ao solicitante.'],
        ]);
    });

    $this->assertDatabaseCount('pedidos', 0);
    $this->assertDatabaseCount('pedido_events', 0);
});

test('valid creation persists the pedido with exactly 1 criacao_pedido event', function () {
    $requester = User::factory()->obra()->create();
    $obra = Obra::factory()->create();
    $requester->obras()->attach($obra->id);

    $pedido = createPedidoAction()->execute($requester, [
        'obra_id' => $obra->id,
        'needed_at' => '2026-07-01',
        'items_description' => 'Cimento e areia',
    ]);

    expect(Pedido::query()->count())->toBe(1);
    expect($pedido->code)->toMatch('/^PED-\d{6}$/');
    expect($pedido->status->slug)->toBe('solicitado');
    expect($pedido->requester_id)->toBe($requester->id);

    expect($pedido->events()->count())->toBe(1);

    $event = $pedido->events()->first();
    expect($event->eventType->slug)->toBe(EventTypeSlug::CriacaoPedido->value);
    expect($event->actor_id)->toBe($requester->id);
});

test('missing obra_id rejects the submission without creating a pedido', function () {
    $requester = User::factory()->obra()->create();

    expect(fn () => createPedidoAction()->execute($requester, [
        'needed_at' => '2026-07-01',
        'items_description' => 'Cimento e areia',
    ]))->toThrow(ValidationException::class);

    expect(Pedido::query()->count())->toBe(0);
});

test('missing needed_at rejects the submission without creating a pedido', function () {
    $requester = User::factory()->obra()->create();
    $obra = Obra::factory()->create();
    $requester->obras()->attach($obra->id);

    expect(fn () => createPedidoAction()->execute($requester, [
        'obra_id' => $obra->id,
        'items_description' => 'Cimento e areia',
    ]))->toThrow(ValidationException::class);

    expect(Pedido::query()->count())->toBe(0);
});

test('missing items_description rejects the submission without creating a pedido', function () {
    $requester = User::factory()->obra()->create();
    $obra = Obra::factory()->create();
    $requester->obras()->attach($obra->id);

    expect(fn () => createPedidoAction()->execute($requester, [
        'obra_id' => $obra->id,
        'needed_at' => '2026-07-01',
    ]))->toThrow(ValidationException::class);

    expect(Pedido::query()->count())->toBe(0);
});

test('obra_id outside the requester obra_profile association is rejected even as a direct payload', function () {
    $requester = User::factory()->obra()->create();
    $unassociatedObra = Obra::factory()->create();

    expect(fn () => createPedidoAction()->execute($requester, [
        'obra_id' => $unassociatedObra->id,
        'needed_at' => '2026-07-01',
        'items_description' => 'Cimento e areia',
    ]))->toThrow(ValidationException::class);

    expect(Pedido::query()->count())->toBe(0);
});

test('a failure inserting the criacao_pedido event rolls back the pedido insert too', function () {
    $requester = User::factory()->obra()->create();
    $obra = Obra::factory()->create();
    $requester->obras()->attach($obra->id);

    // Remove the event type the action depends on, forcing the event insert
    // (a not-null foreign key) to fail inside the same transaction.
    EventType::query()->where('slug', EventTypeSlug::CriacaoPedido->value)->delete();

    expect(fn () => createPedidoAction()->execute($requester, [
        'obra_id' => $obra->id,
        'needed_at' => '2026-07-01',
        'items_description' => 'Cimento e areia',
    ]))->toThrow(QueryException::class);

    expect(Pedido::query()->count())->toBe(0);
});
