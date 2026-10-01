<?php

use App\Actions\Notificacoes\MarkInternalNotificationReadAction;
use App\Enums\EventTypeSlug;
use App\Enums\InternalNotificationEmailStatus;
use App\Models\EventType;
use App\Models\InternalNotification;
use App\Models\Obra;
use App\Models\Pedido;
use App\Models\PedidoEvent;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * notificacoes-internas T13 — RF-18, RF-20, RF-21, RNF-07, CT-05: marking
 * one notification as read is idempotent, owner-only and scoped by
 * `InternalNotification::forRecipient`.
 */
beforeEach(function () {
    $this->statuses = seedWorkflowStatuses();
    seedHistoryEventTypes();
    $this->action = app(MarkInternalNotificationReadAction::class);
});

function markReadNotificationFor(User $recipient, Pedido $pedido): InternalNotification
{
    $event = PedidoEvent::factory()->create([
        'pedido_id' => $pedido->id,
        'event_type_id' => EventType::query()->where('slug', EventTypeSlug::Observacao->value)->value('id'),
        'new_value' => 'Observação.',
    ]);

    return InternalNotification::factory()->create([
        'recipient_id' => $recipient->id,
        'pedido_event_id' => $event->id,
    ]);
}

test('marking fills read_at and returns the notification with its pedido (RF-18)', function () {
    $user = User::factory()->suprimentos()->create();
    $pedido = Pedido::factory()->create(['status_id' => $this->statuses['solicitado']->id]);
    $notification = markReadNotificationFor($user, $pedido);

    $result = $this->action->execute($user, $notification->id);

    expect($notification->fresh()->read_at)->not->toBeNull()
        ->and($result->id)->toBe($notification->id)
        ->and($result->relationLoaded('pedido'))->toBeTrue()
        ->and($result->pedido->id)->toBe($pedido->id);
});

test('a second marking keeps the first read_at (RF-18)', function () {
    $user = User::factory()->gestao()->create();
    $notification = markReadNotificationFor($user, Pedido::factory()->create(['status_id' => $this->statuses['solicitado']->id]));

    $this->travelTo(CarbonImmutable::parse('2026-09-21T12:00:00Z'));
    $this->action->execute($user, $notification->id);
    $first = $notification->fresh()->read_at->toIso8601String();

    $this->travelTo(CarbonImmutable::parse('2026-09-22T15:00:00Z'));
    $result = $this->action->execute($user, $notification->id);

    expect($notification->fresh()->read_at->toIso8601String())->toBe($first)
        ->and($result->read_at->toIso8601String())->toBe($first);
});

test('marking touches only read_at', function () {
    $user = User::factory()->gestao()->create();
    $notification = markReadNotificationFor($user, Pedido::factory()->create(['status_id' => $this->statuses['solicitado']->id]));
    $before = collect($notification->fresh()->getAttributes())->except('read_at')->all();

    $this->action->execute($user, $notification->id);

    $fresh = $notification->fresh();

    expect(collect($fresh->getAttributes())->except('read_at')->all())->toBe($before)
        ->and($fresh->email_status)->toBe(InternalNotificationEmailStatus::Pendente);
});

test('the id of another user notification is a 404 and changes nothing (RF-20)', function () {
    $owner = User::factory()->gestao()->create();
    $intruder = User::factory()->gestao()->create();
    $notification = markReadNotificationFor($owner, Pedido::factory()->create(['status_id' => $this->statuses['solicitado']->id]));

    expect(fn () => $this->action->execute($intruder, $notification->id))
        ->toThrow(ModelNotFoundException::class);

    expect($notification->fresh()->read_at)->toBeNull();
});

test('an unknown id is a 404', function () {
    $user = User::factory()->gestao()->create();

    expect(fn () => $this->action->execute($user, 999999))->toThrow(ModelNotFoundException::class);
});

test('an obra user removed from the obra can no longer mark its notifications (RF-21)', function () {
    $obraUser = User::factory()->obra()->create();
    $obra = Obra::factory()->create();
    $obraUser->obras()->attach($obra->id);
    $pedido = Pedido::factory()->create(['obra_id' => $obra->id, 'status_id' => $this->statuses['solicitado']->id]);
    $notification = markReadNotificationFor($obraUser, $pedido);

    $obraUser->obras()->detach($obra->id);

    expect(fn () => $this->action->execute($obraUser, $notification->id))
        ->toThrow(ModelNotFoundException::class);

    expect($notification->fresh()->read_at)->toBeNull();
});

test('forRecipient lists and counts only the user visible notifications (RF-21)', function () {
    $obraUser = User::factory()->obra()->create();
    $obraX = Obra::factory()->create();
    $obraY = Obra::factory()->create();
    $obraUser->obras()->attach([$obraX->id, $obraY->id]);

    $pedidoX = Pedido::factory()->create(['obra_id' => $obraX->id, 'status_id' => $this->statuses['solicitado']->id]);
    $pedidoY = Pedido::factory()->create(['obra_id' => $obraY->id, 'status_id' => $this->statuses['solicitado']->id]);
    $outra = Pedido::factory()->outra()->create(['requester_id' => $obraUser->id, 'status_id' => $this->statuses['solicitado']->id]);

    $onX = markReadNotificationFor($obraUser, $pedidoX);
    $onY = markReadNotificationFor($obraUser, $pedidoY);
    $onOutra = markReadNotificationFor($obraUser, $outra);
    markReadNotificationFor(User::factory()->obra()->create(), $pedidoY);

    expect(InternalNotification::query()->forRecipient($obraUser)->pluck('id')->sort()->values()->all())
        ->toBe(collect([$onX->id, $onY->id, $onOutra->id])->sort()->values()->all());

    $obraUser->obras()->detach($obraX->id);

    expect(InternalNotification::query()->forRecipient($obraUser)->whereNull('read_at')->count())->toBe(2)
        ->and(InternalNotification::query()->forRecipient($obraUser)->pluck('id')->all())->not->toContain($onX->id);
});
