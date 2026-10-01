<?php

use App\Enums\EventTypeSlug;
use App\Livewire\Notificacoes\Index;
use App\Models\EventType;
use App\Models\InternalNotification;
use App\Models\Obra;
use App\Models\Pedido;
use App\Models\PedidoEvent;
use App\Models\User;
use App\Support\LocalTime;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Livewire;

/**
 * notificacoes-internas T14 — CT-01, CT-05, UI-01..UI-04, RF-18..RF-21,
 * RNF-06: the Notificações Internas page lists the user's own notifications,
 * filters them through `#[Url]`, marks them as read and opens them.
 */
beforeEach(function () {
    $this->statuses = seedWorkflowStatuses();
    $this->eventTypes = seedHistoryEventTypes();
    $this->actor = User::factory()->obra()->create(['name' => 'Autora da Obra']);
});

/**
 * One notification for `$recipient` on `$pedido`, from an event of the
 * given type created at `$at`.
 */
function notificacaoPagina(User $recipient, Pedido $pedido, EventTypeSlug $type = EventTypeSlug::Observacao, ?string $newValue = 'Observação de teste.', ?CarbonImmutable $at = null, ?User $actor = null): InternalNotification
{
    $at ??= CarbonImmutable::now();

    $event = PedidoEvent::factory()->create([
        'pedido_id' => $pedido->id,
        'event_type_id' => EventType::query()->where('slug', $type->value)->value('id'),
        'previous_value' => null,
        'new_value' => $newValue,
        'actor_id' => ($actor ?? User::factory()->obra()->create())->id,
        'created_at' => $at,
    ]);

    return InternalNotification::factory()->create([
        'recipient_id' => $recipient->id,
        'pedido_event_id' => $event->id,
        'created_at' => $at,
    ]);
}

function pedidoPagina(array $attributes = []): Pedido
{
    return Pedido::factory()->create($attributes + ['status_id' => test()->statuses['solicitado']->id]);
}

test('GET /notificacoes answers 200 for the 3 papéis and 403 for an unknown papel (CT-01)', function (string $papel, int $status) {
    $this->actingAs(userForPapel($papel));

    $this->get('/notificacoes')->assertStatus($status);
})->with([
    'obra' => ['obra', 200],
    'suprimentos' => ['suprimentos', 200],
    'gestao' => ['gestao', 200],
    'sem papel' => ['sem papel', 403],
]);

test('mount re-checks view-notifications', function () {
    $this->actingAs(userForPapel('sem papel'));

    Livewire::test(Index::class)->assertSee('403');
});

test('a guest is redirected to login', function () {
    $this->get(route('notificacoes.index'))->assertRedirect(route('login'));
});

test('with 25 notifications page 1 shows 20, most recent first, with the 7 fields (UI-01)', function () {
    $user = User::factory()->gestao()->create();
    $obra = Obra::factory()->create(['name' => 'Obra Central']);
    $pedido = pedidoPagina(['obra_id' => $obra->id]);
    $base = CarbonImmutable::parse('2026-09-20T12:00:00Z');

    $notifications = collect(range(1, 25))->map(fn (int $i) => notificacaoPagina(
        $user, $pedido, EventTypeSlug::Observacao, "Texto número {$i}.", $base->addMinutes($i), $this->actor,
    ));
    $notifications[23]->update(['read_at' => now()]);

    $this->actingAs($user);

    $component = Livewire::test(Index::class);
    $html = $component->html();

    expect(substr_count($html, 'data-testid="notificacao"'))->toBe(20);

    preg_match_all('/data-notification-id="(\d+)"/', $html, $ids);

    expect(array_map('intval', $ids[1]))->toBe($notifications->reverse()->take(20)->pluck('id')->values()->all());

    $component->assertSee($pedido->code)
        ->assertSee('Obra Central')
        ->assertSee('Observação adicionada')
        ->assertSee('Texto número 25.')
        ->assertSee('Autora da Obra')
        ->assertSee(LocalTime::formatDateTime($base->addMinutes(25)))
        ->assertDontSee('Texto número 5.');

    expect(substr_count($html, 'Não lida'))->toBe(19);
});

test('the change of status shows the old → new content (UI-01)', function () {
    $user = User::factory()->suprimentos()->create();
    $pedido = pedidoPagina();

    $event = PedidoEvent::factory()->create([
        'pedido_id' => $pedido->id,
        'event_type_id' => $this->eventTypes['mudanca_status']->id,
        'previous_value' => (string) $this->statuses['solicitado']->id,
        'new_value' => (string) $this->statuses['em_analise']->id,
        'actor_id' => $this->actor->id,
    ]);
    InternalNotification::factory()->create(['recipient_id' => $user->id, 'pedido_event_id' => $event->id]);

    $this->actingAs($user);

    Livewire::test(Index::class)
        ->assertSee($this->statuses['solicitado']->name.' → '.$this->statuses['em_analise']->name);
});

test('the listing shows only the user own visible notifications (RF-21, RNF-07)', function () {
    $obraUser = User::factory()->obra()->create();
    $obraX = Obra::factory()->create();
    $obraY = Obra::factory()->create();
    $obraUser->obras()->attach([$obraX->id, $obraY->id]);

    $onX = notificacaoPagina($obraUser, pedidoPagina(['obra_id' => $obraX->id]));
    $onY = notificacaoPagina($obraUser, pedidoPagina(['obra_id' => $obraY->id]));
    $other = notificacaoPagina(User::factory()->obra()->create(), pedidoPagina(['obra_id' => $obraY->id]));

    $obraUser->obras()->detach($obraX->id);

    $this->actingAs($obraUser);

    Livewire::test(Index::class)
        ->assertSee($onY->pedido->code)
        ->assertDontSee($onX->pedido->code)
        ->assertDontSee($other->pedido->code);
});

test('abrir on an unread notification fills read_at and redirects to the suprimentos detail (UI-02)', function () {
    $user = User::factory()->suprimentos()->create();
    $pedido = pedidoPagina();
    $notification = notificacaoPagina($user, $pedido);

    $this->actingAs($user);

    Livewire::test(Index::class)
        ->call('abrir', $notification->id)
        ->assertRedirect('/suprimentos/pedidos/'.$pedido->id);

    expect($notification->fresh()->read_at)->not->toBeNull();
});

test('abrir redirects each papel to its own detail route (UI-02)', function (string $papel, string $prefix) {
    $user = User::factory()->{$papel}()->create();
    $obra = Obra::factory()->create();
    $user->obras()->attach($obra->id);
    $pedido = pedidoPagina(['obra_id' => $obra->id]);
    $notification = notificacaoPagina($user, $pedido);

    $this->actingAs($user);

    Livewire::test(Index::class)
        ->call('abrir', $notification->id)
        ->assertRedirect("/{$prefix}/pedidos/{$pedido->id}");
})->with([
    'obra' => ['obra', 'obra'],
    'gestao' => ['gestao', 'gestao'],
]);

test('abrir on an already read notification keeps the first read_at and redirects (UI-02)', function () {
    $user = User::factory()->suprimentos()->create();
    $pedido = pedidoPagina();

    $this->travelTo(CarbonImmutable::parse('2026-09-21T12:00:00Z'));
    $notification = notificacaoPagina($user, $pedido);
    $notification->update(['read_at' => now()]);
    $first = $notification->fresh()->read_at->toIso8601String();

    $this->travelTo(CarbonImmutable::parse('2026-09-22T12:00:00Z'));
    $this->actingAs($user);

    Livewire::test(Index::class)
        ->call('abrir', $notification->id)
        ->assertRedirect('/suprimentos/pedidos/'.$pedido->id);

    expect($notification->fresh()->read_at->toIso8601String())->toBe($first);
});

test('abrir with another user notification id is a 404, with no redirect and read_at intact (RF-20)', function () {
    $owner = User::factory()->suprimentos()->create();
    $intruder = User::factory()->suprimentos()->create();
    $notification = notificacaoPagina($owner, pedidoPagina());

    $this->actingAs($intruder);

    $component = Livewire::test(Index::class);

    expect(fn () => $component->call('abrir', $notification->id))->toThrow(ModelNotFoundException::class);
    $component->assertNoRedirect();

    expect($notification->fresh()->read_at)->toBeNull();
});

test('markAsRead marks only that notification and dispatches notificacoes-atualizadas (UI-03)', function () {
    $user = User::factory()->gestao()->create();
    $target = notificacaoPagina($user, pedidoPagina());
    $other = notificacaoPagina($user, pedidoPagina());

    $this->actingAs($user);

    Livewire::test(Index::class)
        ->call('markAsRead', $target->id)
        ->assertDispatched('notificacoes-atualizadas');

    expect($target->fresh()->read_at)->not->toBeNull()
        ->and($other->fresh()->read_at)->toBeNull();
});

test('markAsRead with another user id is a 404 and changes nothing (RF-20)', function () {
    $owner = User::factory()->gestao()->create();
    $notification = notificacaoPagina($owner, pedidoPagina());

    $this->actingAs(User::factory()->gestao()->create());

    expect(fn () => Livewire::test(Index::class)->call('markAsRead', $notification->id))
        ->toThrow(ModelNotFoundException::class);

    expect($notification->fresh()->read_at)->toBeNull();
});

test('Marcar todas como lidas removes every Não lida and dispatches notificacoes-atualizadas (UI-03, RF-19)', function () {
    $user = User::factory()->gestao()->create();
    $other = User::factory()->gestao()->create();
    collect(range(1, 3))->each(fn () => notificacaoPagina($user, pedidoPagina()));
    $foreign = notificacaoPagina($other, pedidoPagina());

    $this->actingAs($user);

    $component = Livewire::test(Index::class)->assertSee('Não lida');

    $component->call('markAllAsRead')
        ->assertDispatched('notificacoes-atualizadas')
        ->assertDontSee('Não lida');

    expect(InternalNotification::query()->where('recipient_id', $user->id)->whereNull('read_at')->count())->toBe(0)
        ->and($foreign->fresh()->read_at)->toBeNull();
});

test('?lidas=nao shows only unread and ?lidas=sim only read (UI-04)', function () {
    $user = User::factory()->gestao()->create();
    $unread = notificacaoPagina($user, pedidoPagina());
    $read = notificacaoPagina($user, pedidoPagina());
    $read->update(['read_at' => now()]);

    $this->actingAs($user);

    Livewire::withQueryParams(['lidas' => 'nao'])->test(Index::class)
        ->assertSet('readState', 'nao')
        ->assertSee($unread->pedido->code)
        ->assertDontSee($read->pedido->code);

    Livewire::withQueryParams(['lidas' => 'sim'])->test(Index::class)
        ->assertSee($read->pedido->code)
        ->assertDontSee($unread->pedido->code);

    Livewire::withQueryParams(['lidas' => 'talvez'])->test(Index::class)
        ->assertSee($read->pedido->code)
        ->assertSee($unread->pedido->code);
});

test('the tipo filter keeps only that event type and ignores an unknown slug (UI-04)', function () {
    $user = User::factory()->gestao()->create();
    $observacao = notificacaoPagina($user, pedidoPagina(), EventTypeSlug::Observacao);
    $romaneio = notificacaoPagina($user, pedidoPagina(), EventTypeSlug::RomaneioAnexado, 'romaneio.pdf');

    $this->actingAs($user);

    Livewire::withQueryParams(['tipo' => 'romaneio_anexado'])->test(Index::class)
        ->assertSee($romaneio->pedido->code)
        ->assertDontSee($observacao->pedido->code);

    Livewire::withQueryParams(['tipo' => 'inexistente'])->test(Index::class)
        ->assertSee($romaneio->pedido->code)
        ->assertSee($observacao->pedido->code);
});

test('the codigo filter matches the pedido code case-insensitively (UI-04)', function () {
    $user = User::factory()->gestao()->create();
    $wanted = notificacaoPagina($user, pedidoPagina(['code' => 'PED-123456']));
    $unwanted = notificacaoPagina($user, pedidoPagina(['code' => 'PED-999999']));

    $this->actingAs($user);

    Livewire::test(Index::class)
        ->set('code', 'ped-1234')
        ->assertSee($wanted->pedido->code)
        ->assertDontSee($unwanted->pedido->code);
});

test('Limpar filtros resets every filter to its neutral value (UI-04)', function () {
    $this->actingAs(User::factory()->gestao()->create());

    Livewire::withQueryParams(['lidas' => 'nao', 'tipo' => 'observacao', 'codigo' => 'PED'])->test(Index::class)
        ->assertSet('readState', 'nao')
        ->assertSet('eventType', 'observacao')
        ->assertSet('code', 'PED')
        ->call('limparFiltros')
        ->assertSet('readState', '')
        ->assertSet('eventType', '')
        ->assertSet('code', '');
});

test('the filter properties are Url-bound with a neutral except', function (string $property, string $urlName) {
    $attributes = (new ReflectionProperty(Index::class, $property))->getAttributes(Url::class);

    expect($attributes)->toHaveCount(1);

    $url = $attributes[0]->newInstance();

    expect($url->as)->toBe($urlName)
        ->and($url->except)->toBe('');
})->with([
    'lidas' => ['readState', 'lidas'],
    'tipo' => ['eventType', 'tipo'],
    'codigo' => ['code', 'codigo'],
]);

test('the empty state reads Nenhuma notificação encontrada.', function () {
    $this->actingAs(User::factory()->obra()->create());

    Livewire::test(Index::class)->assertSee('Nenhuma notificação encontrada.');
});

test('each item opens through a wire:click button, never a direct link to the detail (UI-02)', function () {
    $user = User::factory()->suprimentos()->create();
    $pedido = pedidoPagina();
    $notification = notificacaoPagina($user, $pedido);

    $this->actingAs($user);

    $html = Livewire::test(Index::class)->html();

    expect($html)->toContain('wire:click="abrir('.$notification->id.')"')
        ->not->toContain('href="'.route('suprimentos.pedidos.show', $pedido).'"');
});

test('the query count is the same for 1 and 20 items', function () {
    $user = User::factory()->gestao()->create();
    $this->actingAs($user);

    Livewire::test(Index::class);

    $measure = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        Livewire::test(Index::class);
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    };

    notificacaoPagina($user, pedidoPagina(['obra_id' => Obra::factory()->create()->id]), EventTypeSlug::AlteracaoResponsavel, (string) User::factory()->suprimentos()->create()->id);
    $one = $measure();

    collect(range(1, 19))->each(fn () => notificacaoPagina($user, pedidoPagina(['obra_id' => Obra::factory()->create()->id]), EventTypeSlug::AlteracaoResponsavel, (string) User::factory()->suprimentos()->create()->id));
    $twenty = $measure();

    expect(InternalNotification::query()->count())->toBe(20)
        ->and($twenty)->toBe($one);
});
