<?php

use App\Domain\Pedidos\NotifiableEventTypes;
use App\Enums\EventTypeSlug;
use App\Enums\InternalNotificationEmailStatus;
use App\Models\EventType;
use App\Models\InternalNotification;
use App\Models\Obra;
use App\Models\Pedido;
use App\Models\PedidoEvent;
use App\Models\User;
use App\Services\InternalNotificationMailer;
use App\Services\PedidoEventValuePresenter;
use App\Services\PedidoNotificationRecorder;
use Illuminate\Support\Defer\DeferredCallbackCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\SentMessage;

/**
 * T07 — RF-01, RF-09, RF-17, RNF-02: the single point where a history
 * event becomes notifications — inside the transaction, discarded on
 * rollback, classified by `NotifiableEventTypes` alone.
 */
beforeEach(function () {
    $this->statuses = seedWorkflowStatuses();
    $this->eventTypes = seedHistoryEventTypes();
    $this->actor = User::factory()->suprimentos()->create();
    $this->obra = Obra::factory()->create(['name' => 'Residencial Aurora']);
    $this->pedido = Pedido::factory()->create([
        'obra_id' => $this->obra->id,
        'requester_id' => $this->actor->id,
        'status_id' => $this->statuses['em_analise']->id,
    ]);
    $this->transport = Mail::mailer()->getSymfonyTransport();
    $this->transport->flush();
});

/**
 * Writes the event inside the caller's transaction, as an Action does.
 */
function recorderEvent(?EventType $type = null): PedidoEvent
{
    return PedidoEvent::query()->create([
        'pedido_id' => test()->pedido->id,
        'event_type_id' => ($type ?? test()->eventTypes[EventTypeSlug::Observacao->value])->id,
        'new_value' => 'Troca do material entregue.',
        'actor_id' => test()->actor->id,
    ]);
}

function recorder(): PedidoNotificationRecorder
{
    return app(PedidoNotificationRecorder::class);
}

/**
 * Replaces the scoped mailer with one that only records the queued ids.
 */
function spyMailer(): InternalNotificationMailer
{
    $spy = new class(app(PedidoEventValuePresenter::class)) extends InternalNotificationMailer
    {
        /** @var list<int> */
        public array $queued = [];

        public function queueEvent(int $pedidoEventId): void
        {
            $this->queued[] = $pedidoEventId;
        }
    };

    app()->instance(InternalNotificationMailer::class, $spy);

    return $spy;
}

function bindClassification(array $overrides): void
{
    app()->instance(NotifiableEventTypes::class, new class($overrides) extends NotifiableEventTypes
    {
        public function __construct(private array $overrides) {}

        public function classification(): array
        {
            return [...parent::classification(), ...$this->overrides];
        }
    });
}

test('outside a transaction it throws LogicException before touching the database (RF-01)', function () {
    $event = new PedidoEvent(['pedido_id' => $this->pedido->id, 'actor_id' => $this->actor->id]);

    // Leave the RefreshDatabase wrapper transaction to reach level 0, then restore it.
    DB::rollBack();

    try {
        expect(DB::transactionLevel())->toBe(0);
        expect(fn () => recorder()->record($event))->toThrow(LogicException::class);
    } finally {
        DB::beginTransaction();
    }
});

test('inside a transaction with 3 recipients it writes 3 pendente rows and queues the event on the mailer only after the commit (RF-01)', function () {
    $mailer = spyMailer();
    $recipients = User::factory()->gestao()->count(3)->create();

    $event = DB::transaction(function () use ($mailer) {
        $event = recorderEvent();
        recorder()->record($event);

        expect($mailer->queued)->toBe([]);

        return $event;
    });

    expect($mailer->queued)->toBe([$event->id]);

    $rows = InternalNotification::query()->where('pedido_event_id', $event->id)->get();

    expect($rows->pluck('recipient_id')->all())->toEqualCanonicalizing($recipients->pluck('id')->all());
    expect($rows->pluck('pedido_id')->unique()->all())->toBe([$this->pedido->id]);
    expect($rows->pluck('actor_id')->unique()->all())->toBe([$this->actor->id]);
    expect($rows->pluck('event_type_slug')->unique()->all())->toBe(['observacao']);
    expect($rows->pluck('email_status')->unique()->all())->toBe([InternalNotificationEmailStatus::Pendente]);
    expect($rows->pluck('read_at')->filter()->all())->toBe([]);
    expect($rows->pluck('created_at')->filter())->toHaveCount(3);
});

test('end to end: after the commit and the deferred callbacks, each recipient gets one e-mail', function () {
    $recipients = User::factory()->gestao()->count(3)->create();
    allowNotificationEmailsFor(...$recipients);

    DB::transaction(fn () => recorder()->record(recorderEvent()));

    expect($this->transport->messages())->toHaveCount(0);

    app(DeferredCallbackCollection::class)->invoke();

    expect($this->transport->messages())->toHaveCount(3);
    expect($this->transport->messages()->map(fn (SentMessage $message) => $message->getOriginalMessage()->getTo()[0]->getAddress())->all())
        ->toEqualCanonicalizing($recipients->pluck('email')->all());
});

test('an exception after record rolls back: 0 notifications and 0 e-mails after invoke (RF-01, RF-17)', function () {
    User::factory()->gestao()->count(3)->create();
    $eventsBefore = PedidoEvent::query()->count();

    expect(fn () => DB::transaction(function () {
        recorder()->record(recorderEvent());

        throw new RuntimeException('Falha depois do registro.');
    }))->toThrow(RuntimeException::class);

    app(DeferredCallbackCollection::class)->invoke();

    expect(InternalNotification::query()->count())->toBe(0);
    expect(PedidoEvent::query()->count())->toBe($eventsBefore);
    expect($this->transport->messages())->toHaveCount(0);
});

test('a type classified as not notifiable writes nothing and queues nothing', function () {
    bindClassification([EventTypeSlug::Observacao->value => false]);
    $mailer = spyMailer();
    User::factory()->gestao()->count(2)->create();

    DB::transaction(fn () => recorder()->record(recorderEvent()));

    expect(InternalNotification::query()->count())->toBe(0);
    expect($mailer->queued)->toBe([]);
});

test('without any recipient it writes nothing and queues nothing', function () {
    $mailer = spyMailer();

    DB::transaction(fn () => recorder()->record(recorderEvent()));

    expect(InternalNotification::query()->count())->toBe(0);
    expect($mailer->queued)->toBe([]);
});

test('a fictitious slug registered as notifiable notifies with no other change (RF-09)', function () {
    $fictitious = EventType::query()->create(['slug' => 'ocorrencia_ficticia', 'name' => 'Ocorrência fictícia']);
    bindClassification(['ocorrencia_ficticia' => true]);
    $recipients = User::factory()->gestao()->count(2)->create();
    allowNotificationEmailsFor(...$recipients);
    config()->push('mail.notification_email.events', 'ocorrencia_ficticia');

    $event = DB::transaction(function () use ($fictitious) {
        $event = recorderEvent($fictitious);
        recorder()->record($event);

        return $event;
    });

    $rows = InternalNotification::query()->where('pedido_event_id', $event->id)->get();

    expect($rows->pluck('recipient_id')->all())->toEqualCanonicalizing($recipients->pluck('id')->all());
    expect($rows->pluck('event_type_slug')->unique()->all())->toBe(['ocorrencia_ficticia']);

    app(DeferredCallbackCollection::class)->invoke();

    expect($this->transport->messages())->toHaveCount(2);
    expect($this->transport->messages()->first()->getOriginalMessage()->getSubject())
        ->toBe("[{$this->pedido->code}] Ocorrência fictícia — Residencial Aurora");
});

test('the query delta is at most 3 and the same for 1 and 20 recipients (RNF-02)', function () {
    spyMailer();

    $measure = function (int $recipientCount): int {
        DB::table('internal_notifications')->delete();
        User::query()->whereHas('role', fn ($role) => $role->where('slug', 'gestao'))->update(['is_active' => false]);
        User::factory()->gestao()->count($recipientCount)->create();

        return DB::transaction(function () use ($recipientCount) {
            $event = PedidoEvent::query()->findOrFail(recorderEvent()->id);

            DB::flushQueryLog();
            DB::enableQueryLog();
            recorder()->record($event);
            $queries = count(DB::getQueryLog());
            DB::disableQueryLog();

            expect(InternalNotification::query()->where('pedido_event_id', $event->id)->count())->toBe($recipientCount);

            return $queries;
        });
    };

    $one = $measure(1);
    $twenty = $measure(20);

    expect($one)->toBe($twenty)->toBeLessThanOrEqual(3);
});
