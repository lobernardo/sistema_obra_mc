<?php

use App\Actions\Pedidos\AddPedidoObservacaoAction;
use App\Actions\Pedidos\AttachRomaneioAction;
use App\Actions\Pedidos\CancelPedidoAction;
use App\Actions\Pedidos\CreatePedidoAction;
use App\Actions\Pedidos\FinalizePedidoAction;
use App\Actions\Pedidos\MarkPedidoEntregueByObraAction;
use App\Actions\Pedidos\UpdatePedidoPrevisaoAction;
use App\Actions\Pedidos\UpdatePedidoPrioridadeAction;
use App\Actions\Pedidos\UpdatePedidoResponsavelAction;
use App\Actions\Pedidos\UpdatePedidoStatusAction;
use App\Domain\Pedidos\NotifiableEventTypes;
use App\Domain\Pedidos\NotificationRecipientResolver;
use App\Enums\EventTypeSlug;
use App\Enums\InternalNotificationEmailStatus;
use App\Livewire\Kanban\KanbanBoard;
use App\Models\InternalNotification;
use App\Models\Obra;
use App\Models\Pedido;
use App\Models\PedidoEvent;
use App\Models\Priority;
use App\Models\User;
use App\Notifications\PedidoEventNotification;
use App\Services\InternalNotificationMailer;
use App\Services\PedidoAttachmentStorage;
use App\Services\PedidoNotificationRecorder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Defer\DeferredCallbackCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Symfony\Component\Mailer\SentMessage;

/**
 * T10 — RF-01, RF-02, RF-03, RF-05, RF-14, RF-17, RNF-01: every pedido
 * Action, run for real, turns its history event into exactly one
 * notification per resolved recipient — inside its transaction, discarded
 * on rollback — and the e-mails leave only with the deferred callbacks.
 *
 * Cast: obra X with the requester O1 (`obra`) and S1 (`suprimentos`)
 * associated; G1 and G2 (`gestao`); S3 (`suprimentos`) and O2 (`obra`) not
 * associated to X, so never recipients.
 */
beforeEach(function () {
    $this->statuses = seedWorkflowStatuses();
    seedHistoryEventTypes();
    Storage::fake(PedidoAttachmentStorage::DISK);

    $this->obra = Obra::factory()->create(['name' => 'Residencial Aurora']);
    $this->o1 = User::factory()->obra()->create();
    $this->s1 = User::factory()->suprimentos()->create();
    $this->o1->obras()->attach($this->obra->id);
    $this->s1->obras()->attach($this->obra->id);
    $this->g1 = User::factory()->gestao()->create();
    $this->g2 = User::factory()->gestao()->create();
    $this->s3 = User::factory()->suprimentos()->create();
    $this->o2 = User::factory()->obra()->create();

    $this->pedido = Pedido::factory()->for($this->o1, 'requester')->create([
        'obra_id' => $this->obra->id,
        'status_id' => $this->statuses['em_analise']->id,
    ]);

    $this->transport = Mail::mailer()->getSymfonyTransport();
    $this->transport->flush();
});

function generationLastEvent(Pedido $pedido): PedidoEvent
{
    return PedidoEvent::query()->where('pedido_id', $pedido->id)->latest('id')->firstOrFail();
}

/**
 * @return list<int>
 */
function generationIds(User ...$users): array
{
    return array_map(fn (User $user): int => $user->id, $users);
}

function generationRomaneio(Pedido $pedido, User $actor): void
{
    app(AttachRomaneioAction::class)->execute(
        $actor,
        $pedido,
        UploadedFile::fake()->createWithContent('romaneio.pdf', anexoPdfBytes()),
    );
}

/**
 * Runs the real Action that writes the given event type and returns the
 * event, its actor and the users it must notify.
 *
 * @return array{event: PedidoEvent, actor: User, expected: list<int>}
 */
function generationRun(string $slug): array
{
    $t = test();
    $pedido = $t->pedido;
    $s1 = $t->s1;
    $notS1 = generationIds($t->o1, $t->g1, $t->g2);
    $notO1 = generationIds($t->s1, $t->g1, $t->g2);

    [$actor, $expected, $pedido] = match ($slug) {
        'criacao_pedido' => [$t->o1, $notO1, app(CreatePedidoAction::class)->execute($t->o1, [
            'obra_selection' => $t->obra->id,
            'needed_at' => '2026-12-01',
            'descricao' => 'Cimento e areia',
        ])],
        'mudanca_status' => [$s1, $notS1, app(UpdatePedidoStatusAction::class)->execute($s1, $pedido, $t->statuses['em_compra_preparacao']->id)],
        'entrega' => [$s1, $notS1, app(UpdatePedidoStatusAction::class)->execute($s1, $pedido, $t->statuses['entregue']->id)],
        'entrega pela obra' => [$t->o1, $notO1, app(MarkPedidoEntregueByObraAction::class)->execute($t->o1, $pedido)],
        'cancelamento' => [$s1, $notS1, app(CancelPedidoAction::class)->execute($s1, $pedido)],
        'finalizacao' => (function () use ($s1, $notS1, $pedido): array {
            generationRomaneio($pedido, $s1);

            return [$s1, $notS1, app(FinalizePedidoAction::class)->execute($s1, $pedido)];
        })(),
        'alteracao_responsavel' => (function () use ($t, $s1, $pedido): array {
            $novoResponsavel = User::factory()->suprimentos()->create();

            return [
                $s1,
                [...generationIds($t->o1, $t->g1, $t->g2), $novoResponsavel->id],
                app(UpdatePedidoResponsavelAction::class)->execute($s1, $pedido, $novoResponsavel->id),
            ];
        })(),
        'alteracao_prioridade' => [$s1, $notS1, app(UpdatePedidoPrioridadeAction::class)->execute($s1, $pedido, Priority::factory()->alta()->create()->id)],
        'alteracao_previsao' => [$s1, $notS1, app(UpdatePedidoPrevisaoAction::class)->execute($s1, $pedido, '2026-10-20')],
        'observacao' => [$s1, $notS1, (function () use ($s1, $pedido): Pedido {
            app(AddPedidoObservacaoAction::class)->execute($s1, $pedido, 'Falta de cimento no fornecedor.');

            return $pedido;
        })()],
        'romaneio_anexado' => [$s1, $notS1, (function () use ($s1, $pedido): Pedido {
            generationRomaneio($pedido, $s1);

            return $pedido;
        })()],
    };

    return ['event' => generationLastEvent($pedido), 'actor' => $actor, 'expected' => $expected];
}

test('each notifiable type, written by its real Action, notifies exactly the resolved recipients with the right fields (RF-01, RF-02, RF-05, RF-07)', function (string $case, EventTypeSlug $slug) {
    ['event' => $event, 'actor' => $actor, 'expected' => $expected] = generationRun($case);

    $rows = InternalNotification::query()->where('pedido_event_id', $event->id)->get();
    $resolved = app(NotificationRecipientResolver::class)->recipientIdsFor($event);

    expect($event->eventType->slug)->toBe($slug->value)
        ->and($rows->count())->toBeGreaterThanOrEqual(1)
        ->and($rows)->toHaveCount(count($resolved))
        ->and($rows->pluck('recipient_id')->all())->toEqualCanonicalizing($expected)
        ->and($resolved)->toEqualCanonicalizing($expected)
        ->and($rows->pluck('recipient_id')->all())->not->toContain($actor->id)
        ->and($rows->pluck('recipient_id')->all())->not->toContain(test()->s3->id)
        ->and($rows->pluck('recipient_id')->all())->not->toContain(test()->o2->id)
        ->and($rows->pluck('pedido_id')->unique()->all())->toBe([$event->pedido_id])
        ->and($rows->pluck('actor_id')->unique()->all())->toBe([$actor->id])
        ->and($rows->pluck('event_type_slug')->unique()->all())->toBe([$slug->value])
        ->and($rows->pluck('email_status')->unique()->all())->toBe([InternalNotificationEmailStatus::Pendente]);

    foreach ($rows as $row) {
        expect(Gate::forUser($row->recipient)->allows('view', $event->pedido))->toBeTrue();
    }
})->with([
    'criacao_pedido' => ['criacao_pedido', EventTypeSlug::CriacaoPedido],
    'mudanca_status' => ['mudanca_status', EventTypeSlug::MudancaStatus],
    'entrega (Suprimentos)' => ['entrega', EventTypeSlug::Entrega],
    'entrega (Obra)' => ['entrega pela obra', EventTypeSlug::Entrega],
    'cancelamento' => ['cancelamento', EventTypeSlug::Cancelamento],
    'finalizacao' => ['finalizacao', EventTypeSlug::Finalizacao],
    'alteracao_responsavel' => ['alteracao_responsavel', EventTypeSlug::AlteracaoResponsavel],
    'alteracao_prioridade' => ['alteracao_prioridade', EventTypeSlug::AlteracaoPrioridade],
    'alteracao_previsao' => ['alteracao_previsao', EventTypeSlug::AlteracaoPrevisao],
    'observacao' => ['observacao', EventTypeSlug::Observacao],
    'romaneio_anexado' => ['romaneio_anexado', EventTypeSlug::RomaneioAnexado],
]);

test('the cases above cover every EventTypeSlug and every one is classified notifiable (RF-02)', function () {
    $covered = ['criacao_pedido', 'mudanca_status', 'entrega', 'cancelamento', 'finalizacao', 'alteracao_responsavel', 'alteracao_prioridade', 'alteracao_previsao', 'observacao', 'romaneio_anexado'];

    expect(array_map(fn (EventTypeSlug $slug): string => $slug->value, EventTypeSlug::cases()))->toEqualCanonicalizing($covered);

    foreach (EventTypeSlug::cases() as $slug) {
        expect(app(NotifiableEventTypes::class)->isNotifiable($slug->value))->toBeTrue();
    }
});

test('after the deferred callbacks each notification of a mutation becomes 1 e-mail to its recipient (RF-14)', function (string $case) {
    ['event' => $event] = generationRun($case);
    $rows = InternalNotification::query()->where('pedido_event_id', $event->id)->with('recipient')->get();

    app(DeferredCallbackCollection::class)->invoke();

    $addresses = $this->transport->messages()
        ->filter(fn (SentMessage $message) => str_contains((string) $message->getOriginalMessage()->getSubject(), $event->pedido->code))
        ->map(fn (SentMessage $message) => $message->getOriginalMessage()->getTo()[0]->getAddress())
        ->values()
        ->all();

    expect($addresses)->toEqualCanonicalizing($rows->pluck('recipient.email')->all());
})->with(['criacao_pedido', 'mudanca_status', 'observacao']);

test('2 active gestao users who are not the actor receive 1 notification each (RF-03)', function () {
    ['event' => $event] = generationRun('mudanca_status');

    foreach ([$this->g1, $this->g2] as $gestao) {
        expect(InternalNotification::query()->where('pedido_event_id', $event->id)->where('recipient_id', $gestao->id)->count())->toBe(1);
    }
});

test('the gestao user who changes the priority is not notified; the other gestao is (RF-05)', function () {
    app(UpdatePedidoPrioridadeAction::class)->execute($this->g1, $this->pedido, Priority::factory()->urgente()->create()->id);
    $event = generationLastEvent($this->pedido);

    $recipients = InternalNotification::query()->where('pedido_event_id', $event->id)->pluck('recipient_id')->all();

    expect($event->eventType->slug)->toBe(EventTypeSlug::AlteracaoPrioridade->value)
        ->and($recipients)->not->toContain($this->g1->id)
        ->and($recipients)->toContain($this->g2->id)
        ->and($recipients)->toEqualCanonicalizing(generationIds($this->o1, $this->s1, $this->g2));
});

test('the accessible Kanban control moveViaControl generates the notifications of the status change', function () {
    $this->actingAs($this->s1);

    Livewire::test(KanbanBoard::class)
        ->call('moveViaControl', $this->pedido->id, $this->statuses['aguardando_entrega']->id)
        ->assertOk();

    $event = generationLastEvent($this->pedido);

    expect($event->eventType->slug)->toBe(EventTypeSlug::MudancaStatus->value)
        ->and(InternalNotification::query()->where('pedido_event_id', $event->id)->pluck('recipient_id')->all())
        ->toEqualCanonicalizing(generationIds($this->o1, $this->g1, $this->g2));
});

test('an exception after the event INSERT rolls the mutation back: 0 notifications and 0 e-mails after invoke (RF-01, RF-17)', function (string $failurePoint) {
    $recorder = null;

    if ($failurePoint === 'after record') {
        $recorder = new class(app(NotifiableEventTypes::class), app(NotificationRecipientResolver::class), app(InternalNotificationMailer::class)) extends PedidoNotificationRecorder
        {
            public ?int $insertedBeforeFailure = null;

            public function record(PedidoEvent $event): void
            {
                parent::record($event);

                $this->insertedBeforeFailure = InternalNotification::query()->where('pedido_event_id', $event->id)->count();

                throw new RuntimeException('Falha forçada depois do registro.');
            }
        };

        app()->instance(PedidoNotificationRecorder::class, $recorder);
    } else {
        PedidoEvent::created(fn () => throw new RuntimeException('Falha forçada depois do INSERT do evento.'));
    }

    $eventsBefore = PedidoEvent::query()->count();

    expect(fn () => app(UpdatePedidoStatusAction::class)->execute($this->s1, $this->pedido, $this->statuses['aguardando_entrega']->id))
        ->toThrow(RuntimeException::class);

    if ($recorder !== null) {
        expect($recorder->insertedBeforeFailure)->toBe(3);
    }

    app(DeferredCallbackCollection::class)->invoke();

    expect(InternalNotification::query()->count())->toBe(0)
        ->and(PedidoEvent::query()->count())->toBe($eventsBefore)
        ->and($this->pedido->fresh()->status_id)->toBe($this->statuses['em_analise']->id)
        ->and($this->transport->messages())->toHaveCount(0);
})->with(['after record', 'in a created listener']);

test('with 5 recipients no e-mail leaves before the deferred callbacks and 5 leave after, one per recipient (RNF-01, RF-14)', function () {
    Notification::fake();
    $extraGestao = User::factory()->gestao()->count(2)->create();
    $recipients = collect([$this->o1, $this->g1, $this->g2, ...$extraGestao]);

    app(UpdatePedidoStatusAction::class)->execute($this->s1, $this->pedido, $this->statuses['aguardando_entrega']->id);

    expect(InternalNotification::query()->count())->toBe(5);
    Notification::assertNothingSent();

    app(DeferredCallbackCollection::class)->invoke();

    Notification::assertCount(5);

    foreach ($recipients as $recipient) {
        Notification::assertSentTo($recipient, PedidoEventNotification::class, 1);
    }

    Notification::assertNotSentTo($this->s1, PedidoEventNotification::class);
});
