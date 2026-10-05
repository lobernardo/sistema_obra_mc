<?php

use App\Enums\InternalNotificationEmailStatus;
use App\Models\InternalNotification;
use App\Models\Pedido;
use App\Models\PedidoEvent;
use App\Models\User;

/**
 * T02 — RF-22, CT-02: a notification is append-only except for the read
 * timestamp and the e-mail delivery state.
 */
test('updating read_at through Eloquent works', function () {
    $notification = InternalNotification::factory()->create();

    $notification->update(['read_at' => now()]);

    expect($notification->fresh()->read_at)->not->toBeNull();
});

test('updating the e-mail state through Eloquent works', function () {
    $notification = InternalNotification::factory()->create();

    $notification->update([
        'email_status' => InternalNotificationEmailStatus::Enviado,
        'email_status_at' => now(),
    ]);

    $fresh = $notification->fresh();

    expect($fresh->email_status)->toBe(InternalNotificationEmailStatus::Enviado);
    expect($fresh->email_status_at)->not->toBeNull();
});

test('marking the e-mail as ignorado through Eloquent works (CT-03)', function () {
    $notification = InternalNotification::factory()->create();

    $notification->update([
        'email_status' => InternalNotificationEmailStatus::Ignorado,
        'email_status_at' => now(),
    ]);

    $fresh = $notification->fresh();

    expect($fresh->email_status)->toBe(InternalNotificationEmailStatus::Ignorado);
    expect($fresh->email_status_at)->not->toBeNull();
});

test('updating any other column throws', function (string $column, Closure $value) {
    $notification = InternalNotification::factory()->create();
    $original = $notification->getAttribute($column);

    expect(fn () => $notification->update([$column => $value($notification)]))
        ->toThrow(LogicException::class);
    expect($notification->fresh()->getAttribute($column))->toEqual($original);
})->with([
    'pedido_id' => ['pedido_id', fn (InternalNotification $notification) => Pedido::factory()->create(['status_id' => $notification->pedido->status_id])->id],
    'recipient_id' => ['recipient_id', fn () => User::factory()->gestao()->create()->id],
    'event_type_slug' => ['event_type_slug', fn () => 'mudanca_status'],
    'actor_id' => ['actor_id', fn () => User::factory()->gestao()->create()->id],
    'pedido_event_id' => ['pedido_event_id', fn (InternalNotification $notification) => PedidoEvent::factory()->create([
        'pedido_id' => $notification->pedido_id,
        'event_type_id' => $notification->event->event_type_id,
    ])->id],
]);

test('changing a forbidden column together with read_at throws', function () {
    $notification = InternalNotification::factory()->create();

    $notification->read_at = now();
    $notification->event_type_slug = 'cancelamento';

    expect(fn () => $notification->save())->toThrow(LogicException::class);
    expect($notification->fresh()->read_at)->toBeNull();
});

test('deleting a notification throws', function () {
    $notification = InternalNotification::factory()->create();

    expect(fn () => $notification->delete())->toThrow(LogicException::class);
    expect(InternalNotification::query()->whereKey($notification->id)->exists())->toBeTrue();
});

test('the model casts its columns and exposes its relations', function () {
    $notification = InternalNotification::factory()->create()->fresh();

    expect($notification->email_status)->toBe(InternalNotificationEmailStatus::Pendente);
    expect($notification->created_at)->not->toBeNull();
    expect($notification->recipient)->toBeInstanceOf(User::class);
    expect($notification->actor)->toBeInstanceOf(User::class);
    expect($notification->pedido)->toBeInstanceOf(Pedido::class);
    expect($notification->event)->toBeInstanceOf(PedidoEvent::class);
    expect($notification->event->pedido_id)->toBe($notification->pedido_id);
    expect($notification->recipient->internalNotifications()->pluck('id')->all())->toBe([$notification->id]);
});
