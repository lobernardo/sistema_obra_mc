<?php

use App\Models\EventType;
use App\Models\Obra;
use App\Models\Pedido;
use App\Models\PedidoAttachment;
use App\Models\Priority;
use App\Models\Status;
use App\Models\User;
use Illuminate\Support\Facades\DB;

test('Pedido exposes all its relations', function () {
    $obra = Obra::factory()->create();
    $requester = User::factory()->obra()->create();
    $responsible = User::factory()->suprimentos()->create();
    $status = Status::factory()->solicitado()->create();
    $priority = Priority::factory()->normal()->create();

    $pedido = Pedido::factory()->create([
        'obra_id' => $obra->id,
        'requester_id' => $requester->id,
        'responsible_id' => $responsible->id,
        'status_id' => $status->id,
        'priority_id' => $priority->id,
    ]);

    $eventType = EventType::factory()->criacaoPedido()->create();
    $pedido->events()->create([
        'event_type_id' => $eventType->id,
        'actor_id' => $requester->id,
    ]);

    expect($pedido->obra->is($obra))->toBeTrue();
    expect($pedido->requester->is($requester))->toBeTrue();
    expect($pedido->responsible->is($responsible))->toBeTrue();
    expect($pedido->status->is($status))->toBeTrue();
    expect($pedido->priority->is($priority))->toBeTrue();
    expect($pedido->events)->toHaveCount(1);
    expect($pedido->events->first()->eventType->is($eventType))->toBeTrue();
});

test('Pedido declares an explicit fillable list', function () {
    $pedido = new Pedido;

    expect($pedido->getFillable())->toEqualCanonicalizing([
        'code',
        'obra_id',
        'obra_reference',
        'requester_id',
        'requested_at',
        'needed_at',
        'items_description',
        'status_id',
        'priority_id',
        'responsible_id',
        'expected_delivery_at',
        'is_demo',
    ]);
});

test('PedidoEvent exposes pedido, eventType and actor relations', function () {
    $requester = User::factory()->obra()->create();
    $pedido = Pedido::factory()->create(['requester_id' => $requester->id]);
    $eventType = EventType::factory()->criacaoPedido()->create();

    $event = $pedido->events()->create([
        'event_type_id' => $eventType->id,
        'actor_id' => $requester->id,
    ]);

    expect($event->pedido->is($pedido))->toBeTrue();
    expect($event->eventType->is($eventType))->toBeTrue();
    expect($event->actor->is($requester))->toBeTrue();
});

test('obraLabel renders the three canonical shapes (CT-07)', function () {
    $status = Status::factory()->solicitado()->create();
    $obra = Obra::factory()->create(['name' => 'Residencial Aurora']);

    $withObra = Pedido::factory()->create(['obra_id' => $obra->id, 'status_id' => $status->id]);
    $outra = Pedido::factory()->outra()->create(['status_id' => $status->id]);
    $outraComReferencia = Pedido::factory()->outra('Galpão provisório')->create(['status_id' => $status->id]);

    expect($withObra->obraLabel())->toBe('Residencial Aurora');
    expect($outra->obraLabel())->toBe('Outra');
    expect($outraComReferencia->obraLabel())->toBe('Outra — Galpão provisório');
    expect(Pedido::OUTRA_LABEL)->toBe('Outra');
});

test('dataPrevistaLabel presents the Data prevista fixed at creation as d/m/Y (CT-06)', function () {
    $this->travelTo('2026-09-21 12:00');

    $pedido = Pedido::factory()->create(['status_id' => Status::factory()->solicitado()->create()->id]);

    expect($pedido->dataPrevistaLabel())->toBe('24/09/2026');
    expect(Pedido::presentDataPrevista($pedido->data_prevista))->toBe('24/09/2026');
});

test('the outra factory state creates no obra and no obra_profile row', function () {
    $status = Status::factory()->solicitado()->create();
    $obraProfileBefore = DB::table('obra_profile')->count();
    $obrasBefore = Obra::query()->count();

    $pedido = Pedido::factory()->outra('X')->create(['status_id' => $status->id]);

    expect($pedido->obra_id)->toBeNull();
    expect($pedido->obra_reference)->toBe('X');
    expect(DB::table('obra_profile')->count())->toBe($obraProfileBefore);
    expect(Obra::query()->count())->toBe($obrasBefore);
});

test('attachments and romaneios relations follow the explicit kind', function () {
    $pedido = Pedido::factory()->create(['status_id' => Status::factory()->solicitado()->create()->id]);

    $anexo = PedidoAttachment::factory()->create(['pedido_id' => $pedido->id, 'kind' => 'anexo', 'original_name' => 'romaneio.pdf']);
    $romaneio = PedidoAttachment::factory()->create(['pedido_id' => $pedido->id, 'kind' => 'romaneio']);

    expect($pedido->attachments()->pluck('id')->all())->toBe([$anexo->id, $romaneio->id]);
    expect($pedido->romaneios()->pluck('id')->all())->toBe([$romaneio->id]);
});
