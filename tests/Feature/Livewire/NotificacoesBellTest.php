<?php

use App\Enums\EventTypeSlug;
use App\Livewire\Notificacoes\Bell;
use App\Models\EventType;
use App\Models\InternalNotification;
use App\Models\Obra;
use App\Models\Pedido;
use App\Models\PedidoEvent;
use App\Models\User;
use App\Support\LocalTime;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

/**
 * notificacoes-internas T16 — UI-02, UI-05..UI-07, UI-10, RF-18, RF-20,
 * RF-21, RNF-04, RNF-07: the global bell counts the user's unread
 * notifications, lists the 10 most recent on demand and opens them.
 */
beforeEach(function () {
    $this->statuses = seedWorkflowStatuses();
    $this->eventTypes = seedHistoryEventTypes();
});

/**
 * One unread notification for `$recipient` on `$pedido`, created at `$at`.
 */
function notificacaoSino(User $recipient, ?Pedido $pedido = null, ?CarbonImmutable $at = null, EventTypeSlug $type = EventTypeSlug::Observacao): InternalNotification
{
    $at ??= CarbonImmutable::now();
    $pedido ??= pedidoSino();

    $event = PedidoEvent::factory()->create([
        'pedido_id' => $pedido->id,
        'event_type_id' => EventType::query()->where('slug', $type->value)->value('id'),
        'previous_value' => null,
        'new_value' => 'Observação do sino.',
        'actor_id' => User::factory()->obra()->create()->id,
        'created_at' => $at,
    ]);

    return InternalNotification::factory()->create([
        'recipient_id' => $recipient->id,
        'pedido_event_id' => $event->id,
        'created_at' => $at,
    ]);
}

function pedidoSino(array $attributes = []): Pedido
{
    return Pedido::factory()->create($attributes + ['status_id' => test()->statuses['solicitado']->id]);
}

function sinoBadge(string $html): ?string
{
    return preg_match('/data-testid="notificacoes-badge"[^>]*>([^<]*)</', $html, $match) === 1 ? trim($match[1]) : null;
}

test('3 unread notifications show the badge "3" and the accessible name (UI-05)', function () {
    $user = User::factory()->gestao()->create();
    foreach (range(1, 3) as $i) {
        notificacaoSino($user);
    }
    InternalNotification::factory()->read()->create(['recipient_id' => $user->id]);

    $this->actingAs($user);

    $html = Livewire::test(Bell::class)->html();

    expect(sinoBadge($html))->toBe('3')
        ->and($html)->toContain('aria-label="Notificações, 3 não lidas"');
});

test('12 unread notifications show "9+" (UI-05)', function () {
    $user = User::factory()->gestao()->create();
    foreach (range(1, 12) as $i) {
        notificacaoSino($user);
    }

    $this->actingAs($user);

    $html = Livewire::test(Bell::class)->html();

    expect(sinoBadge($html))->toBe('9+')
        ->and($html)->toContain('aria-label="Notificações, 12 não lidas"');
});

test('0 unread notifications hide the badge (UI-05)', function () {
    $user = User::factory()->suprimentos()->create();
    InternalNotification::factory()->read()->create(['recipient_id' => $user->id]);

    $this->actingAs($user);

    $html = Livewire::test(Bell::class)->html();

    expect(sinoBadge($html))->toBeNull()
        ->and($html)->not->toContain('data-testid="notificacoes-badge"')
        ->and($html)->toContain('aria-label="Notificações, 0 não lidas"');
});

test('another user notifications never count (RNF-07)', function () {
    $user = User::factory()->gestao()->create();
    $other = User::factory()->gestao()->create();
    notificacaoSino($user);
    foreach (range(1, 4) as $i) {
        notificacaoSino($other);
    }

    $this->actingAs($user);

    expect(sinoBadge(Livewire::test(Bell::class)->html()))->toBe('1');
});

test('the panel list is lazy: nothing is listed before loadPanel (UI-06)', function () {
    $user = User::factory()->gestao()->create();
    $notification = notificacaoSino($user);

    $this->actingAs($user);

    Livewire::test(Bell::class)
        ->assertDontSee($notification->pedido->code)
        ->assertViewHas('panelNotifications', fn ($items) => $items->isEmpty());
});

test('loadPanel lists 10 of 12 unread, most recent first, with code, type and local date/time (UI-06)', function () {
    $user = User::factory()->gestao()->create();
    $base = CarbonImmutable::parse('2026-09-20T12:00:00Z');

    $notifications = collect(range(1, 12))->map(fn (int $i) => notificacaoSino($user, null, $base->addMinutes($i)));
    $readOne = notificacaoSino($user, null, $base->addMinutes(30));
    $readOne->update(['read_at' => now()]);

    $this->actingAs($user);

    $component = Livewire::test(Bell::class)->call('loadPanel');
    $html = $component->html();

    preg_match_all('/data-testid="sino-notificacao"\s+data-notification-id="(\d+)"/', $html, $ids);

    expect(array_map('intval', $ids[1]))->toBe($notifications->reverse()->take(10)->pluck('id')->values()->all());

    $latest = $notifications->last();

    $component->assertSee($latest->pedido->code)
        ->assertSee('Observação adicionada')
        ->assertSee(LocalTime::formatDateTime($base->addMinutes(12)))
        ->assertDontSee($notifications->first()->pedido->code)
        ->assertDontSee($readOne->pedido->code);

    expect($html)->toContain('wire:click="abrir('.$latest->id.')"');
});

test('the panel shows "Nenhuma notificação nova." with 0 unread (UI-06)', function () {
    $user = User::factory()->obra()->create();

    $this->actingAs($user);

    Livewire::test(Bell::class)
        ->call('loadPanel')
        ->assertSee('Nenhuma notificação nova.');
});

test('"Ver todas" points to /notificacoes (UI-06)', function () {
    $this->actingAs(User::factory()->gestao()->create());

    expect(Livewire::test(Bell::class)->html())
        ->toMatch('/<a[^>]*href="'.preg_quote(url('/notificacoes'), '/').'"[^>]*>\s*Ver todas\s*<\/a>/');
});

test('abrir marks the notification as read, redirects to the papel detail and the counter drops by 1 (UI-02)', function (string $papel) {
    $user = User::factory()->{$papel}()->create();
    $obra = Obra::factory()->create();
    $user->obras()->attach($obra->id);
    $pedido = pedidoSino(['obra_id' => $obra->id]);
    $target = notificacaoSino($user, $pedido);
    notificacaoSino($user, $pedido);
    notificacaoSino($user, $pedido);

    $this->actingAs($user);

    $component = Livewire::test(Bell::class);

    expect(sinoBadge($component->html()))->toBe('3');

    $component->call('loadPanel')
        ->call('abrir', $target->id)
        ->assertRedirect("/{$papel}/pedidos/{$pedido->id}");

    expect($target->fresh()->read_at)->not->toBeNull()
        ->and(sinoBadge(Livewire::test(Bell::class)->html()))->toBe('2');
})->with(['obra', 'suprimentos', 'gestao']);

test('abrir with another user notification id is a 404, with no redirect and read_at intact (RF-20)', function () {
    $owner = User::factory()->suprimentos()->create();
    $notification = notificacaoSino($owner);

    $this->actingAs(User::factory()->suprimentos()->create());

    $component = Livewire::test(Bell::class);

    expect(fn () => $component->call('abrir', $notification->id))->toThrow(ModelNotFoundException::class);
    $component->assertNoRedirect();

    expect($notification->fresh()->read_at)->toBeNull();
});

test('markAllAsRead zeroes the counter, only for the actor, and dispatches notificacoes-atualizadas (UI-03)', function () {
    $user = User::factory()->gestao()->create();
    $other = User::factory()->gestao()->create();
    foreach (range(1, 5) as $i) {
        notificacaoSino($user);
    }
    $othersNotification = notificacaoSino($other);

    $this->actingAs($user);

    $html = Livewire::test(Bell::class)
        ->call('markAllAsRead')
        ->assertDispatched('notificacoes-atualizadas')
        ->html();

    expect(sinoBadge($html))->toBeNull()
        ->and($html)->toContain('aria-label="Notificações, 0 não lidas"')
        ->and(InternalNotification::query()->where('recipient_id', $user->id)->whereNull('read_at')->count())->toBe(0)
        ->and($othersNotification->fresh()->read_at)->toBeNull();
});

test('notificacoes-atualizadas re-renders the counter (UI-03)', function () {
    $user = User::factory()->gestao()->create();
    $notification = notificacaoSino($user);
    notificacaoSino($user);

    $this->actingAs($user);

    $component = Livewire::test(Bell::class);

    expect(sinoBadge($component->html()))->toBe('2');

    $notification->update(['read_at' => now()]);

    expect(sinoBadge($component->dispatch('notificacoes-atualizadas')->html()))->toBe('1');
});

test('notifications of a pedido from an obra the user was detached from no longer count (RF-21)', function () {
    $user = User::factory()->obra()->create();
    $obraX = Obra::factory()->create();
    $obraY = Obra::factory()->create();
    $user->obras()->attach([$obraX->id, $obraY->id]);
    notificacaoSino($user, pedidoSino(['obra_id' => $obraX->id]));
    notificacaoSino($user, pedidoSino(['obra_id' => $obraX->id]));
    $onY = notificacaoSino($user, pedidoSino(['obra_id' => $obraY->id]));

    $this->actingAs($user);

    expect(sinoBadge(Livewire::test(Bell::class)->html()))->toBe('3');

    $user->obras()->detach($obraX->id);

    $component = Livewire::test(Bell::class)->call('loadPanel');

    expect(sinoBadge($component->html()))->toBe('1');
    $component->assertSee($onY->pedido->code);
});

test('a user without a recognized papel gets no bell', function () {
    $this->actingAs(userForPapel('sem papel'));

    $html = Livewire::test(Bell::class)->html();

    expect($html)->not->toContain('notificacoes-sino');
});

test('the bell view uses data-open, the 60000 ms visible-only timer and no x-show or details (UI-07, UI-10)', function () {
    $view = file_get_contents(resource_path('views/livewire/notificacoes/bell.blade.php'));

    expect($view)
        ->toContain('data-open="false"')
        ->toContain('x-bind:data-open')
        ->toContain('data-[open=true]:flex')
        ->toContain('60000')
        ->toContain('refreshIfVisible')
        ->toContain("document.visibilityState === 'visible'")
        ->toContain('$wire.$refresh()')
        ->toContain('x-on:keydown.escape.window')
        ->not->toContain('x-show')
        ->not->toContain('<details')
        ->not->toContain('{!!')
        ->not->toMatch('/class="[^"]*\{\{/');
});

test('the layout renders a single bell, inside @auth, with no wire:click (UI-05, RNF-04)', function () {
    $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));

    expect(substr_count($layout, '<livewire:notificacoes.bell'))->toBe(1)
        ->and($layout)->not->toContain('wire:click')
        ->and($layout)->toMatch('/@auth\s*<livewire:notificacoes\.bell\s*\/>\s*@endauth/');
});

test('every papel page renders exactly one bell with its counter (UI-05)', function (string $papel, string $routeName) {
    $user = User::factory()->{$papel}()->create();
    $obra = Obra::factory()->create();
    $user->obras()->attach($obra->id);
    notificacaoSino($user, pedidoSino(['obra_id' => $obra->id]));
    notificacaoSino($user, pedidoSino(['obra_id' => $obra->id]));

    $this->actingAs($user);

    $html = $this->get(route($routeName))->assertOk()->getContent();

    expect(substr_count($html, 'data-testid="notificacoes-sino"'))->toBe(1)
        ->and(sinoBadge($html))->toBe('2')
        ->and($html)->toContain('aria-label="Notificações, 2 não lidas"');
})->with([
    'obra' => ['obra', 'obra.pedidos.index'],
    'suprimentos' => ['suprimentos', 'suprimentos.pedidos.index'],
    'gestao' => ['gestao', 'gestao.pedidos.index'],
    'página de notificações' => ['gestao', 'notificacoes.index'],
]);
