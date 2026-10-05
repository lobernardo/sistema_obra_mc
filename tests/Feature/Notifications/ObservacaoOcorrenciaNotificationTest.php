<?php

use App\Actions\Pedidos\AddPedidoObservacaoAction;
use App\Domain\Pedidos\NotificationRecipientResolver;
use App\Enums\EventTypeSlug;
use App\Livewire\Obra\PedidoDetalhe as ObraPedidoDetalhe;
use App\Livewire\Suprimentos\PedidoDetalhe as SuprimentosPedidoDetalhe;
use App\Models\InternalNotification;
use App\Models\Obra;
use App\Models\Pedido;
use App\Models\PedidoEvent;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Defer\DeferredCallbackCollection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\Mailer\SentMessage;

/**
 * T10 — RF-10..RF-13: an "Observação / ocorrência" is the existing
 * `observacao` event — 1 event with the trimmed text, the pedido row
 * untouched (`updated_at` checked an hour later), accepted in every status — and it notifies and e-mails the
 * resolved recipients like any other event. Invalid text or an actor
 * without rights writes neither event nor notification.
 */
beforeEach(function () {
    $this->statuses = seedWorkflowStatuses();
    seedHistoryEventTypes();

    $this->obra = Obra::factory()->create(['name' => 'Residencial Aurora']);
    $this->obraUser = User::factory()->obra()->create();
    $this->suprimentos = User::factory()->suprimentos()->create();
    $this->obraUser->obras()->attach($this->obra->id);
    $this->suprimentos->obras()->attach($this->obra->id);
    $this->gestao = User::factory()->gestao()->create();
    $this->outsider = User::factory()->obra()->create();
    allowNotificationEmailsFor($this->obraUser, $this->suprimentos, $this->gestao, $this->outsider);

    $this->travelTo(CarbonImmutable::parse('2026-09-20T12:00:00Z'));
    $this->pedido = ocorrenciaPedido('em_analise');

    $this->transport = Mail::mailer()->getSymfonyTransport();
    $this->transport->flush();
});

function ocorrenciaPedido(string $status): Pedido
{
    return Pedido::factory()->for(test()->obraUser, 'requester')->create([
        'obra_id' => test()->obra->id,
        'status_id' => test()->statuses[$status]->id,
    ]);
}

function ocorrenciaEventCount(Pedido $pedido): int
{
    return PedidoEvent::query()
        ->where('pedido_id', $pedido->id)
        ->whereHas('eventType', fn ($query) => $query->where('slug', EventTypeSlug::Observacao->value))
        ->count();
}

/**
 * Registers the observation and asserts RF-10: exactly 1 new `observacao`
 * event with the trimmed text, the pedido row unchanged, one notification
 * per resolved recipient (never the actor) and, after the deferred
 * callbacks, one e-mail per notification carrying the pedido code.
 */
function ocorrenciaRegisterAndAssert(User $actor, Pedido $pedido): void
{
    $before = $pedido->fresh();
    $eventsBefore = ocorrenciaEventCount($pedido);
    test()->travelTo(now()->addHour());

    $event = app(AddPedidoObservacaoAction::class)->execute($actor, $pedido, "  Falta de produto: cimento CP-II.\n  ");

    $rows = InternalNotification::query()->where('pedido_event_id', $event->id)->with('recipient')->get();
    $after = $pedido->fresh();

    expect(ocorrenciaEventCount($pedido))->toBe($eventsBefore + 1)
        ->and($event->eventType->slug)->toBe(EventTypeSlug::Observacao->value)
        ->and($event->new_value)->toBe('Falta de produto: cimento CP-II.')
        ->and($event->actor_id)->toBe($actor->id)
        ->and($event->created_at)->not->toBeNull()
        ->and($after->status_id)->toBe($before->status_id)
        ->and($after->updated_at->toIso8601String())->toBe($before->updated_at->toIso8601String())
        ->and($rows->count())->toBeGreaterThanOrEqual(1)
        ->and($rows->pluck('recipient_id')->all())->toEqualCanonicalizing(app(NotificationRecipientResolver::class)->recipientIdsFor($event))
        ->and($rows->pluck('recipient_id')->all())->not->toContain($actor->id)
        ->and($rows->pluck('recipient_id')->all())->not->toContain(test()->outsider->id)
        ->and($rows->pluck('event_type_slug')->unique()->all())->toBe([EventTypeSlug::Observacao->value]);

    test()->transport->flush();
    app(DeferredCallbackCollection::class)->invoke();

    $messages = test()->transport->messages();

    expect($messages->map(fn (SentMessage $message) => $message->getOriginalMessage()->getTo()[0]->getAddress())->all())
        ->toEqualCanonicalizing($rows->pluck('recipient.email')->all());

    foreach ($messages as $message) {
        expect($message->getOriginalMessage()->getHtmlBody())->toContain($pedido->code);
    }
}

test('suprimentos, gestao and obra with view register an observation that notifies the other recipients (RF-10, RF-12)', function (string $actor) {
    ocorrenciaRegisterAndAssert($this->{$actor}, $this->pedido);
})->with(['obra com view' => 'obraUser', 'suprimentos' => 'suprimentos', 'gestao' => 'gestao']);

test('the observation is accepted in the terminal status Entregue and notifies (RF-13)', function () {
    ocorrenciaRegisterAndAssert($this->suprimentos, ocorrenciaPedido('entregue'));
});

test('the observation is accepted in the terminal status Cancelado and notifies (RF-13)', function () {
    ocorrenciaRegisterAndAssert($this->suprimentos, ocorrenciaPedido('cancelado'));
});

test('the observation is accepted in the terminal status Finalizado and notifies (RF-13)', function () {
    ocorrenciaRegisterAndAssert($this->obraUser, ocorrenciaPedido('finalizado'));
});

test('a blank or too long text is a 422 with the existing message and writes no event nor notification (RF-11)', function (string $texto, string $message) {
    try {
        app(AddPedidoObservacaoAction::class)->execute($this->suprimentos, $this->pedido, $texto);

        $this->fail('A ValidationException was expected.');
    } catch (ValidationException $exception) {
        expect($exception->status)->toBe(422)
            ->and($exception->errors())->toBe(['observacao' => [$message]]);
    }

    app(DeferredCallbackCollection::class)->invoke();

    expect(ocorrenciaEventCount($this->pedido))->toBe(0)
        ->and(InternalNotification::query()->count())->toBe(0)
        ->and($this->transport->messages())->toHaveCount(0);
})->with([
    'only spaces' => ['   ', 'Escreva a observação.'],
    '2001 characters' => [str_repeat('a', 2001), 'A observação deve ter no máximo 2000 caracteres.'],
]);

test('the detail screen shows the 422 message inline and writes nothing (RF-11)', function () {
    $this->actingAs($this->suprimentos);

    Livewire::test(SuprimentosPedidoDetalhe::class, ['pedido' => $this->pedido])
        ->set('observacao', '   ')
        ->call('adicionarObservacao')
        ->assertHasErrors(['observacao'])
        ->assertSee('Escreva a observação.');

    expect(ocorrenciaEventCount($this->pedido))->toBe(0)
        ->and(InternalNotification::query()->count())->toBe(0);
});

test('an obra user without view on the pedido is a 403 and writes no event nor notification (RF-12)', function () {
    expect(fn () => app(AddPedidoObservacaoAction::class)->execute($this->outsider, $this->pedido, 'Problema de entrega.'))
        ->toThrow(AuthorizationException::class);

    $this->actingAs($this->outsider);
    Livewire::test(ObraPedidoDetalhe::class, ['pedido' => $this->pedido])->assertForbidden();

    app(DeferredCallbackCollection::class)->invoke();

    expect(ocorrenciaEventCount($this->pedido))->toBe(0)
        ->and(InternalNotification::query()->count())->toBe(0)
        ->and($this->transport->messages())->toHaveCount(0);
});

test('through the Obra detail screen the observation notifies Suprimentos and Gestão (RF-10)', function () {
    $this->actingAs($this->obraUser);

    Livewire::test(ObraPedidoDetalhe::class, ['pedido' => $this->pedido])
        ->set('observacao', 'Troca: veio areia fina em vez de média.')
        ->call('adicionarObservacao')
        ->assertHasNoErrors();

    expect(InternalNotification::query()->pluck('recipient_id')->all())
        ->toEqualCanonicalizing([$this->suprimentos->id, $this->gestao->id]);
});
