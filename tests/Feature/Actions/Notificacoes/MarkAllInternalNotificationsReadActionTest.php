<?php

use App\Actions\Notificacoes\MarkAllInternalNotificationsReadAction;
use App\Models\InternalNotification;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * notificacoes-internas T13 — RF-19, RNF-07, CT-05: "Marcar todas como
 * lidas" marks every unread notification of the actor and of no other user.
 */
beforeEach(function () {
    seedWorkflowStatuses();
    seedHistoryEventTypes();
    $this->action = app(MarkAllInternalNotificationsReadAction::class);
});

function unreadCountOf(User $user): int
{
    return InternalNotification::query()->where('recipient_id', $user->id)->whereNull('read_at')->count();
}

test('A with 5 and B with 3 unread: after A acts, A has 0 and B still has 3 (RF-19)', function () {
    $a = User::factory()->gestao()->create();
    $b = User::factory()->suprimentos()->create();
    InternalNotification::factory()->count(5)->create(['recipient_id' => $a->id]);
    InternalNotification::factory()->count(3)->create(['recipient_id' => $b->id]);

    $marked = $this->action->execute($a);

    expect($marked)->toBe(5)
        ->and(unreadCountOf($a))->toBe(0)
        ->and(unreadCountOf($b))->toBe(3);
});

test('already read notifications keep their first read_at', function () {
    $user = User::factory()->gestao()->create();

    $this->travelTo(CarbonImmutable::parse('2026-09-21T12:00:00Z'));
    $read = InternalNotification::factory()->read()->create(['recipient_id' => $user->id]);
    $first = $read->fresh()->read_at->toIso8601String();
    InternalNotification::factory()->create(['recipient_id' => $user->id]);

    $this->travelTo(CarbonImmutable::parse('2026-09-22T15:00:00Z'));
    $marked = $this->action->execute($user);

    expect($marked)->toBe(1)
        ->and($read->fresh()->read_at->toIso8601String())->toBe($first)
        ->and(unreadCountOf($user))->toBe(0);
});

test('a user without notifications marks nothing', function () {
    $other = User::factory()->gestao()->create();
    InternalNotification::factory()->count(2)->create(['recipient_id' => $other->id]);

    expect($this->action->execute(User::factory()->obra()->create()))->toBe(0)
        ->and(unreadCountOf($other))->toBe(2);
});

test('marking all touches only read_at', function () {
    $user = User::factory()->gestao()->create();
    $notification = InternalNotification::factory()->create(['recipient_id' => $user->id]);
    $before = collect($notification->fresh()->getAttributes())->except('read_at')->all();

    $this->action->execute($user);

    expect(collect($notification->fresh()->getAttributes())->except('read_at')->all())->toBe($before);
});
