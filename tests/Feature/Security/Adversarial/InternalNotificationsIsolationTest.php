<?php

use App\Enums\EventTypeSlug;
use App\Models\EventType;
use App\Models\InternalNotification;
use App\Models\Obra;
use App\Models\Pedido;
use App\Models\PedidoEvent;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;

/**
 * notificacoes-internas T19 — RNF-07, RF-20, RF-21, UI-02: two users who may
 * both view the same pedidos never see, count, mark or open each other's
 * notifications. Every Livewire method is driven through the real
 * `/livewire/update` transport with a snapshot rendered for the attacker, so
 * a forged id arrives exactly as it would from a tampered browser.
 */
beforeEach(function () {
    $this->statuses = seedWorkflowStatuses();
    seedHistoryEventTypes();

    $this->userA = User::factory()->gestao()->create();
    $this->userB = User::factory()->gestao()->create();

    $this->pedidoA = Pedido::factory()->create(['code' => 'PED-ISO-0001', 'status_id' => $this->statuses['solicitado']->id]);
    $this->pedidoB = Pedido::factory()->create(['code' => 'PED-ISO-0002', 'status_id' => $this->statuses['solicitado']->id]);

    $this->notificationsA = collect(range(1, 2))->map(fn () => isolamentoNotificacao($this->userA, $this->pedidoA));
    $this->notificationsB = collect(range(1, 3))->map(fn () => isolamentoNotificacao($this->userB, $this->pedidoB));
});

function isolamentoNotificacao(User $recipient, Pedido $pedido): InternalNotification
{
    $event = PedidoEvent::factory()->create([
        'pedido_id' => $pedido->id,
        'event_type_id' => EventType::query()->where('slug', EventTypeSlug::Observacao->value)->value('id'),
        'previous_value' => null,
        'new_value' => 'Observação de isolamento.',
        'actor_id' => User::factory()->suprimentos()->create()->id,
    ]);

    return InternalNotification::factory()->create([
        'recipient_id' => $recipient->id,
        'pedido_event_id' => $event->id,
    ]);
}

/**
 * The snapshot of the Livewire component `$name` rendered in the page.
 */
function isolamentoSnapshot(TestResponse $response, string $name): string
{
    preg_match_all('/wire:snapshot="([^"]+)"/', $response->getContent(), $matches);

    foreach ($matches[1] as $encoded) {
        $snapshot = htmlspecialchars_decode($encoded, ENT_QUOTES | ENT_SUBSTITUTE);

        if ((json_decode($snapshot, true)['memo']['name'] ?? null) === $name) {
            return $snapshot;
        }
    }

    throw new RuntimeException("No {$name} snapshot in the rendered page.");
}

/**
 * @param  list<mixed>  $params
 */
function isolamentoCall(string $snapshot, string $method, array $params = []): TestResponse
{
    Livewire::flushState();

    $updateUri = app('router')->getRoutes()->getByName('default-livewire.update')->uri();

    return test()->withHeaders(['X-Livewire' => 'true'])->postJson('/'.$updateUri, [
        '_token' => csrf_token(),
        'components' => [[
            'snapshot' => $snapshot,
            'updates' => [],
            'calls' => [['method' => $method, 'params' => $params]],
        ]],
    ]);
}

/**
 * @return array<int, ?string> read_at by notification id, for every row
 */
function isolamentoReadState(): array
{
    return InternalNotification::query()
        ->orderBy('id')
        ->get()
        ->mapWithKeys(fn (InternalNotification $notification) => [$notification->id => $notification->read_at?->toIso8601String()])
        ->all();
}

function isolamentoBadge(string $html): ?string
{
    return preg_match('/data-testid="notificacoes-badge"[^>]*>([^<]*)</', $html, $match) === 1 ? trim($match[1]) : null;
}

test('the page and the bell counter show only the user own notifications (RNF-07)', function () {
    $this->actingAs($this->userA);

    $html = $this->get(route('notificacoes.index'))->assertOk()->getContent();

    expect($html)->toContain('PED-ISO-0001')
        ->not->toContain('PED-ISO-0002')
        ->toContain('aria-label="Notificações, 2 não lidas"')
        ->and(isolamentoBadge($html))->toBe('2');
});

test('the bell panel lists only the user own notifications (RNF-07)', function () {
    $this->actingAs($this->userA);
    $bell = isolamentoSnapshot($this->get(route('notificacoes.index'))->assertOk(), 'notificacoes.bell');

    $html = isolamentoCall($bell, 'loadPanel')->assertOk()->json('components.0.effects.html');

    expect(substr_count($html, 'data-testid="sino-notificacao"'))->toBe(2)
        ->and($html)->toContain('PED-ISO-0001')
        ->not->toContain('PED-ISO-0002');
});

test('a forged id of another user notification is refused with 403/404, no redirect and read_at intact (RF-20, UI-02)', function (string $component, string $method) {
    $this->actingAs($this->userA);
    $snapshot = isolamentoSnapshot($this->get(route('notificacoes.index'))->assertOk(), $component);
    $before = isolamentoReadState();

    $response = isolamentoCall($snapshot, $method, [$this->notificationsB->first()->id]);

    expect($response->status())->toBeIn([403, 404])
        ->and($response->headers->get('Location'))->toBeNull()
        ->and($response->json('components.0.effects.redirect'))->toBeNull()
        ->and(isolamentoReadState())->toBe($before);
})->with([
    'markAsRead on the page' => ['notificacoes', 'markAsRead'],
    'abrir on the page' => ['notificacoes', 'abrir'],
    'abrir in the bell' => ['notificacoes.bell', 'abrir'],
]);

test('the user own id still works through the same transport (control for the forged-id cases)', function () {
    $this->actingAs($this->userA);
    $snapshot = isolamentoSnapshot($this->get(route('notificacoes.index'))->assertOk(), 'notificacoes.bell');
    $own = $this->notificationsA->first();

    $response = isolamentoCall($snapshot, 'abrir', [$own->id])->assertOk();

    expect($response->json('components.0.effects.redirect'))->toBe(route('gestao.pedidos.show', $this->pedidoA))
        ->and($own->fresh()->read_at)->not->toBeNull();
});

test('markAllAsRead on the page and in the bell marks only the actor notifications (RF-19, RNF-07)', function (string $component) {
    $this->actingAs($this->userA);
    $snapshot = isolamentoSnapshot($this->get(route('notificacoes.index'))->assertOk(), $component);

    isolamentoCall($snapshot, 'markAllAsRead')->assertOk();

    expect(InternalNotification::query()->where('recipient_id', $this->userA->id)->whereNull('read_at')->count())->toBe(0)
        ->and(InternalNotification::query()->where('recipient_id', $this->userB->id)->whereNull('read_at')->count())->toBe(3);
})->with(['notificacoes', 'notificacoes.bell']);

test('an obra user detached from the obra no longer sees, counts nor marks its notifications (RF-21)', function () {
    $obra = Obra::factory()->create();
    $obraUser = User::factory()->obra()->create();
    $obraUser->obras()->attach($obra->id);
    $pedido = Pedido::factory()->create(['code' => 'PED-ISO-0003', 'obra_id' => $obra->id, 'status_id' => $this->statuses['solicitado']->id]);
    $notification = isolamentoNotificacao($obraUser, $pedido);

    $this->actingAs($obraUser);

    $html = $this->get(route('notificacoes.index'))->assertOk()->getContent();
    expect($html)->toContain('PED-ISO-0003')
        ->and(isolamentoBadge($html))->toBe('1');

    $obraUser->obras()->detach($obra->id);

    $response = $this->get(route('notificacoes.index'))->assertOk();
    $html = $response->getContent();

    expect($html)->not->toContain('PED-ISO-0003')
        ->toContain('aria-label="Notificações, 0 não lidas"')
        ->and(isolamentoBadge($html))->toBeNull();

    foreach (['notificacoes' => 'markAsRead', 'notificacoes.bell' => 'abrir'] as $component => $method) {
        expect(isolamentoCall(isolamentoSnapshot($response, $component), $method, [$notification->id])->status())->toBeIn([403, 404]);
    }

    expect($notification->fresh()->read_at)->toBeNull();
});
