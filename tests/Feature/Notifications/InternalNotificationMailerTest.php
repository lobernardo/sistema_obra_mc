<?php

use App\Actions\Pedidos\MarkPedidoEntregueByObraAction;
use App\Actions\Pedidos\UpdatePedidoStatusAction;
use App\Enums\EventTypeSlug;
use App\Enums\InternalNotificationEmailStatus;
use App\Models\InternalNotification;
use App\Models\Obra;
use App\Models\Pedido;
use App\Models\PedidoEvent;
use App\Models\User;
use App\Services\InternalNotificationMailer;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Defer\DeferredCallbackCollection;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Sleep;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

/**
 * T06 — RF-14, RF-16, RNF-01, RNF-08: the e-mails leave only when the
 * deferred callbacks run, each row records its own delivery state, one
 * failure never stops the others and the log carries no PII.
 */
const MAILER_OBSERVACAO = 'Falta de cimento no canteiro, ligar para o fornecedor.';

beforeEach(function () {
    $this->statuses = seedWorkflowStatuses();
    $this->eventTypes = seedHistoryEventTypes();
    $this->actor = User::factory()->suprimentos()->create(['name' => 'Maria Souza']);
    $this->pedido = Pedido::factory()->create([
        'obra_id' => Obra::factory()->create(['name' => 'Residencial Aurora'])->id,
        'status_id' => $this->statuses['em_analise']->id,
    ]);
    $this->mailer = app(InternalNotificationMailer::class);
    $this->transport = Mail::mailer()->getSymfonyTransport();
    $this->transport->flush();
});

function mailerEvent(): PedidoEvent
{
    return PedidoEvent::query()->create([
        'pedido_id' => test()->pedido->id,
        'event_type_id' => test()->eventTypes[EventTypeSlug::Observacao->value]->id,
        'new_value' => MAILER_OBSERVACAO,
        'actor_id' => test()->actor->id,
    ]);
}

/**
 * One `pendente` notification of the event for the recipient.
 */
function mailerNotification(PedidoEvent $event, User $recipient): InternalNotification
{
    return InternalNotification::factory()->create([
        'pedido_event_id' => $event->id,
        'recipient_id' => $recipient->id,
    ]);
}

/**
 * Installs, as the default mailer, a transport that throws for the given
 * address — with the address and a token-like string in the exception
 * message — and records every other message.
 */
function mailerFailingFor(string $failingAddress): ArrayObject
{
    $delivered = new ArrayObject;

    Mail::extend('falha-para', fn () => new class($failingAddress, $delivered) extends AbstractTransport
    {
        public function __construct(private string $failingAddress, private ArrayObject $delivered)
        {
            parent::__construct();
        }

        protected function doSend(SentMessage $message): void
        {
            foreach ($message->getEnvelope()->getRecipients() as $recipient) {
                if ($recipient->getAddress() === $this->failingAddress) {
                    throw new TransportException("Recusado para {$this->failingAddress} (token=segredo-do-provedor)");
                }
            }

            $this->delivered[] = $message;
        }

        public function __toString(): string
        {
            return 'falha-para';
        }
    });

    config(['mail.mailers.falha-para' => ['transport' => 'falha-para'], 'mail.default' => 'falha-para']);

    return $delivered;
}

test('two queueEvent calls in the same request register a single deferred callback', function () {
    $deferred = app(DeferredCallbackCollection::class);
    $before = count($deferred);

    $this->mailer->queueEvent(mailerEvent()->id);
    $this->mailer->queueEvent(mailerEvent()->id);

    expect(count($deferred))->toBe($before + 1);
});

test('the mailer is scoped: the same instance within a request', function () {
    expect(app(InternalNotificationMailer::class))->toBe($this->mailer);
});

test('no message leaves before the deferred callbacks run; then one per notification, each marked enviado (RNF-01, RF-14)', function () {
    $first = mailerEvent();
    $second = mailerEvent();
    $recipients = User::factory()->gestao()->count(3)->create();
    allowNotificationEmailsFor(...$recipients);

    foreach ($recipients as $recipient) {
        mailerNotification($first, $recipient);
    }
    mailerNotification($second, $recipients[0]);

    $this->mailer->queueEvent($first->id);
    $this->mailer->queueEvent($second->id);

    expect($this->transport->messages())->toHaveCount(0);
    expect(InternalNotification::query()->where('email_status', 'pendente')->count())->toBe(4);

    $this->freezeSecond();
    app(DeferredCallbackCollection::class)->invoke();

    expect($this->transport->messages())->toHaveCount(4);

    $addresses = $this->transport->messages()->map(fn (SentMessage $message) => $message->getOriginalMessage()->getTo()[0]->getAddress())->all();

    expect($addresses)->toEqualCanonicalizing([...$recipients->pluck('email')->all(), $recipients[0]->email]);

    foreach (InternalNotification::query()->get() as $notification) {
        expect($notification->email_status)->toBe(InternalNotificationEmailStatus::Enviado);
        expect($notification->email_status_at?->toDateTimeString())->toBe(now()->toDateTimeString());
    }
});

test('only pendente rows are sent: an already sent notification is not sent again', function () {
    $event = mailerEvent();
    $sent = mailerNotification($event, $sentRecipient = User::factory()->gestao()->create());
    $sent->update(['email_status' => InternalNotificationEmailStatus::Enviado, 'email_status_at' => now()->subHour()]);
    mailerNotification($event, $pendingRecipient = User::factory()->gestao()->create());
    allowNotificationEmailsFor($sentRecipient, $pendingRecipient);

    $this->mailer->queueEvent($event->id);
    app(DeferredCallbackCollection::class)->invoke();

    expect($this->transport->messages())->toHaveCount(1);
});

test('a transport failure for A marks only A as falhou; B still receives and is enviado (RF-16)', function () {
    $recipientA = User::factory()->gestao()->create(['email' => 'destinatario.a@example.com']);
    $recipientB = User::factory()->gestao()->create(['email' => 'destinatario.b@example.com']);
    allowNotificationEmailsFor($recipientA, $recipientB);
    $delivered = mailerFailingFor('destinatario.a@example.com');

    $event = mailerEvent();
    $notificationA = mailerNotification($event, $recipientA);
    $notificationB = mailerNotification($event, $recipientB);

    $this->mailer->queueEvent($event->id);
    app(DeferredCallbackCollection::class)->invoke();

    expect($notificationA->fresh()->email_status)->toBe(InternalNotificationEmailStatus::Falhou);
    expect($notificationA->fresh()->email_status_at)->not->toBeNull();
    expect($notificationB->fresh()->email_status)->toBe(InternalNotificationEmailStatus::Enviado);
    expect($notificationB->fresh()->email_status_at)->not->toBeNull();

    expect($delivered)->toHaveCount(1);
    expect($delivered[0]->getEnvelope()->getRecipients()[0]->getAddress())->toBe('destinatario.b@example.com');

    expect(PedidoEvent::query()->whereKey($event->id)->exists())->toBeTrue();
});

test('the failure log carries ids and the exception class, never the address, the content nor a token (RNF-08)', function () {
    $recipientA = User::factory()->gestao()->create(['email' => 'destinatario.a@example.com']);
    allowNotificationEmailsFor($recipientA);
    mailerFailingFor('destinatario.a@example.com');

    $logged = [];
    Event::listen(MessageLogged::class, function (MessageLogged $message) use (&$logged): void {
        $logged[] = $message;
    });

    $event = mailerEvent();
    $notificationA = mailerNotification($event, $recipientA);

    $this->mailer->queueEvent($event->id);
    app(DeferredCallbackCollection::class)->invoke();

    $logged = array_values(array_filter($logged, fn (MessageLogged $message): bool => $message->level === 'warning'));

    expect($logged)->toHaveCount(1);
    expect($logged[0]->level)->toBe('warning');
    expect($logged[0]->context)->toBe([
        'internal_notification_id' => $notificationA->id,
        'pedido_event_id' => $event->id,
        'event_type_slug' => EventTypeSlug::Observacao->value,
        'recipient_id' => $recipientA->id,
        'result' => 'falhou',
        'exception_class' => TransportException::class,
    ]);

    $serialized = json_encode([$logged[0]->message, $logged[0]->context], JSON_UNESCAPED_UNICODE);

    expect($serialized)
        ->not->toContain('destinatario.a@example.com')
        ->not->toContain(MAILER_OBSERVACAO)
        ->not->toContain('segredo-do-provedor')
        ->not->toContain('token');
});

test('a recipient whose papel has no detail route is marked falhou without sending', function () {
    $event = mailerEvent();
    $notification = mailerNotification($event, $recipient = userForPapel('sem papel'));
    allowNotificationEmailsFor($recipient);

    $this->mailer->queueEvent($event->id);
    app(DeferredCallbackCollection::class)->invoke();

    expect($this->transport->messages())->toHaveCount(0);
    expect($notification->fresh()->email_status)->toBe(InternalNotificationEmailStatus::Falhou);
});

test('sends through Resend are spaced by SEND_INTERVAL_MS; other transports are not', function () {
    Sleep::fake();

    $event = mailerEvent();
    $recipients = User::factory()->gestao()->count(3)->create();
    foreach ($recipients as $recipient) {
        mailerNotification($event, $recipient);
    }

    allowNotificationEmailsFor(...$recipients);
    config(['mail.mailers.resend' => ['transport' => 'array'], 'mail.default' => 'resend']);

    $this->mailer->queueEvent($event->id);
    app(DeferredCallbackCollection::class)->invoke();

    Sleep::assertSleptTimes(2);
    Sleep::assertSequence([
        Sleep::usleep(InternalNotificationMailer::SEND_INTERVAL_MS * 1000),
        Sleep::usleep(InternalNotificationMailer::SEND_INTERVAL_MS * 1000),
    ]);

    Sleep::fake();
    config(['mail.default' => 'array']);
    $other = mailerEvent();
    $otherRecipients = User::factory()->gestao()->count(2)->create();
    allowNotificationEmailsFor(...$otherRecipients);
    mailerNotification($other, $otherRecipients[0]);
    mailerNotification($other, $otherRecipients[1]);

    $this->mailer->queueEvent($other->id);
    app(DeferredCallbackCollection::class)->invoke();

    Sleep::assertNeverSlept();
});

/*
| email-notificacoes-enxutas T02 — RF-01..RF-04, RNF-02, CT-01, CT-02, CT-04:
| only an allowed recipient and an allowed event type are e-mailed; every
| other pending notification becomes `ignorado` without the transport, and
| each processed notification is logged once without PII.
*/

const MAILER_DEFAULT_EVENTS = ['criacao_pedido', 'observacao', 'cancelamento', 'entrega'];

/**
 * A history event of the given type on the test pedido; only `observacao`
 * carries a text, so the presenter never reads a status id from it.
 */
function mailerEventOfType(string $slug): PedidoEvent
{
    return PedidoEvent::query()->create([
        'pedido_id' => test()->pedido->id,
        'event_type_id' => test()->eventTypes[$slug]->id,
        'new_value' => $slug === EventTypeSlug::Observacao->value ? MAILER_OBSERVACAO : null,
        'actor_id' => test()->actor->id,
    ]);
}

/**
 * Captures every log entry written while the deferred callbacks run.
 *
 * @return ArrayObject<int, MessageLogged>
 */
function mailerLogSpy(): ArrayObject
{
    $logged = new ArrayObject;

    Event::listen(MessageLogged::class, function (MessageLogged $message) use ($logged): void {
        $logged[] = $message;
    });

    return $logged;
}

/**
 * @return list<string>
 */
function mailerSentAddresses(): array
{
    return test()->transport->messages()
        ->map(fn (SentMessage $message) => $message->getOriginalMessage()->getTo()[0]->getAddress())
        ->values()
        ->all();
}

test('only a recipient in the normalized list receives; another is ignorado with its timestamp (RF-01, RF-03)', function () {
    $allowed = User::factory()->gestao()->create(['email' => 'a@example.com']);
    $other = User::factory()->gestao()->create(['email' => 'c@example.org']);
    config(['mail.notification_email.recipients' => array_values(array_filter(array_map('trim', explode(',', ' A@EXAMPLE.COM , ,b@example.net'))))]);

    $event = mailerEvent();
    $allowedNotification = mailerNotification($event, $allowed);
    $otherNotification = mailerNotification($event, $other);

    $this->freezeSecond();
    $this->mailer->queueEvent($event->id);
    app(DeferredCallbackCollection::class)->invoke();

    expect(mailerSentAddresses())->toBe(['a@example.com'])
        ->and($allowedNotification->fresh()->email_status)->toBe(InternalNotificationEmailStatus::Enviado)
        ->and($otherNotification->fresh()->email_status)->toBe(InternalNotificationEmailStatus::Ignorado)
        ->and($otherNotification->fresh()->email_status_at?->toDateTimeString())->toBe(now()->toDateTimeString());
});

test('an empty recipients list e-mails nobody and every notification is ignorado (RF-01, RF-03)', function (array $recipients) {
    config(['mail.notification_email.recipients' => $recipients]);

    $event = mailerEvent();
    foreach (User::factory()->gestao()->count(3)->create() as $recipient) {
        mailerNotification($event, $recipient);
    }

    $this->mailer->queueEvent($event->id);
    app(DeferredCallbackCollection::class)->invoke();

    expect($this->transport->messages())->toHaveCount(0)
        ->and(InternalNotification::query()->pluck('email_status')->unique()->all())->toBe([InternalNotificationEmailStatus::Ignorado])
        ->and(InternalNotification::query()->whereNull('email_status_at')->count())->toBe(0);
})->with([
    'empty list' => [[]],
    'only blanks, as parsed from ", , "' => [array_values(array_filter(array_map('trim', explode(',', ' , , '))))],
]);

test('with the default events only the 4 default types are e-mailed; the other 6 are ignorado (RF-02)', function () {
    $recipient = User::factory()->gestao()->create();
    allowNotificationEmailsFor($recipient);
    config(['mail.notification_email.events' => MAILER_DEFAULT_EVENTS]);

    $notifications = [];
    foreach (EventTypeSlug::cases() as $slug) {
        $event = mailerEventOfType($slug->value);
        $notifications[$slug->value] = mailerNotification($event, $recipient);
        $this->mailer->queueEvent($event->id);
    }

    app(DeferredCallbackCollection::class)->invoke();

    expect($this->transport->messages())->toHaveCount(4);

    foreach ($notifications as $slug => $notification) {
        expect($notification->fresh()->email_status)->toBe(
            in_array($slug, MAILER_DEFAULT_EVENTS, true) ? InternalNotificationEmailStatus::Enviado : InternalNotificationEmailStatus::Ignorado,
            "Unexpected e-mail state for {$slug}",
        );
    }
});

test('the events list decides which types are e-mailed; an unknown slug neither fails nor enables a send (RF-02)', function (array $events, array $sentSlugs) {
    $recipient = User::factory()->gestao()->create();
    allowNotificationEmailsFor($recipient);
    config(['mail.notification_email.events' => $events]);

    foreach ([EventTypeSlug::Observacao, EventTypeSlug::CriacaoPedido, EventTypeSlug::MudancaStatus] as $slug) {
        $event = mailerEventOfType($slug->value);
        mailerNotification($event, $recipient);
        $this->mailer->queueEvent($event->id);
    }

    app(DeferredCallbackCollection::class)->invoke();

    expect($this->transport->messages())->toHaveCount(count($sentSlugs))
        ->and(InternalNotification::query()->where('email_status', 'enviado')->pluck('event_type_slug')->all())->toEqualCanonicalizing($sentSlugs)
        ->and(InternalNotification::query()->where('email_status', 'pendente')->count())->toBe(0);
})->with([
    'only observacao' => [['observacao'], ['observacao']],
    'empty' => [[], []],
    'unknown slug only' => [['tipo_inexistente'], []],
    'unknown slug with observacao' => [['tipo_inexistente', 'observacao'], ['observacao']],
]);

test('every processed notification is logged once with the exact CT-04 context and no PII (RF-04)', function () {
    $sent = User::factory()->gestao()->create(['email' => 'enviado@example.com']);
    $failing = User::factory()->gestao()->create(['email' => 'falha@example.com']);
    $ignored = User::factory()->gestao()->create(['email' => 'ignorado@example.com']);
    $noRoute = userForPapel('sem papel');
    allowNotificationEmailsFor($sent, $failing, $noRoute);
    mailerFailingFor('falha@example.com');
    $logged = mailerLogSpy();

    $event = mailerEvent();
    $notifications = collect([$sent, $failing, $ignored, $noRoute])->map(fn (User $user) => mailerNotification($event, $user));

    $this->mailer->queueEvent($event->id);
    app(DeferredCallbackCollection::class)->invoke();

    expect($logged)->toHaveCount(4);

    $byId = collect($logged->getArrayCopy())->keyBy(fn (MessageLogged $message) => $message->context['internal_notification_id']);
    $extra = [
        $notifications[0]->id => [],
        $notifications[1]->id => ['exception_class' => TransportException::class],
        $notifications[2]->id => [],
        $notifications[3]->id => ['reason' => 'papel_sem_rota_de_detalhe'],
    ];

    foreach ($notifications as $notification) {
        $fresh = $notification->fresh();
        $entry = $byId[$notification->id];

        expect($entry->context)->toBe([
            'internal_notification_id' => $notification->id,
            'pedido_event_id' => $event->id,
            'event_type_slug' => EventTypeSlug::Observacao->value,
            'recipient_id' => $notification->recipient_id,
            'result' => $fresh->email_status->value,
            ...$extra[$notification->id],
        ])->and($entry->level)->toBe($fresh->email_status === InternalNotificationEmailStatus::Falhou ? 'warning' : 'info');
    }

    expect($notifications->map(fn (InternalNotification $notification) => $notification->fresh()->email_status->value)->all())
        ->toBe(['enviado', 'falhou', 'ignorado', 'falhou']);

    $serialized = json_encode(array_map(fn (MessageLogged $message): array => [$message->message, $message->context], $logged->getArrayCopy()), JSON_UNESCAPED_UNICODE);

    expect($serialized)
        ->not->toContain('@example.com')
        ->not->toContain(MAILER_OBSERVACAO)
        ->not->toContain($this->pedido->code)
        ->not->toContain('segredo-do-provedor');
});

test('under Resend 1 send among 9 ignorado notifications never sleeps (RNF-02)', function () {
    Sleep::fake();

    $event = mailerEvent();
    $recipients = User::factory()->gestao()->count(10)->create();
    foreach ($recipients as $recipient) {
        mailerNotification($event, $recipient);
    }

    allowNotificationEmailsFor($recipients[4]);
    config(['mail.mailers.resend' => ['transport' => 'array'], 'mail.default' => 'resend']);

    $this->mailer->queueEvent($event->id);
    app(DeferredCallbackCollection::class)->invoke();

    Sleep::assertNeverSlept();
    expect(InternalNotification::query()->where('email_status', 'enviado')->count())->toBe(1)
        ->and(InternalNotification::query()->where('email_status', 'ignorado')->count())->toBe(9);
});

test('the filter neither creates nor removes internal_notifications rows', function () {
    $recipients = User::factory()->gestao()->count(3)->create();
    allowNotificationEmailsFor($recipients[0]);

    $event = mailerEvent();
    foreach ($recipients as $recipient) {
        mailerNotification($event, $recipient);
    }
    $before = InternalNotification::query()->orderBy('id')->pluck('recipient_id', 'id')->all();

    $this->mailer->queueEvent($event->id);
    app(DeferredCallbackCollection::class)->invoke();

    expect(InternalNotification::query()->orderBy('id')->pluck('recipient_id', 'id')->all())->toBe($before);
});

test('config/mail.php parses both variables: absent gives the defaults, empty gives empty lists (CT-01, CT-02)', function (?string $recipients, ?string $events, array $expectedRecipients, array $expectedEvents) {
    $original = ['NOTIFICATION_EMAIL_RECIPIENTS' => getenv('NOTIFICATION_EMAIL_RECIPIENTS'), 'NOTIFICATION_EMAIL_EVENTS' => getenv('NOTIFICATION_EMAIL_EVENTS')];

    $set = function (string $name, string|false|null $value): void {
        if ($value === null || $value === false) {
            putenv($name);
            unset($_ENV[$name], $_SERVER[$name]);

            return;
        }

        putenv("{$name}={$value}");
        $_ENV[$name] = $_SERVER[$name] = $value;
    };

    try {
        $set('NOTIFICATION_EMAIL_RECIPIENTS', $recipients);
        $set('NOTIFICATION_EMAIL_EVENTS', $events);

        $config = require config_path('mail.php');

        expect($config['notification_email'])->toBe(['recipients' => $expectedRecipients, 'events' => $expectedEvents]);
    } finally {
        foreach ($original as $name => $value) {
            $set($name, $value);
        }
    }
})->with([
    'absent' => [null, null, [], MAILER_DEFAULT_EVENTS],
    'empty' => ['', '', [], []],
    'only commas and spaces' => [' , ,', ' ,, ', [], []],
    'lists with blanks' => [' A@EXAMPLE.COM , ,b@example.net', ' observacao , ,entrega ', ['A@EXAMPLE.COM', 'b@example.net'], ['observacao', 'entrega']],
]);

test('marking Entregue through either Action e-mails the allowed recipient with the default events (RF-02)', function (string $via) {
    $obraUser = User::factory()->obra()->create();
    $obraUser->obras()->attach($this->pedido->obra_id);
    $gestao = User::factory()->gestao()->create();
    config([
        'mail.notification_email.recipients' => [$gestao->email],
        'mail.notification_email.events' => MAILER_DEFAULT_EVENTS,
    ]);

    $via === 'UpdatePedidoStatusAction'
        ? app(UpdatePedidoStatusAction::class)->execute($this->actor, $this->pedido, $this->statuses['entregue']->id)
        : app(MarkPedidoEntregueByObraAction::class)->execute($obraUser, $this->pedido);

    app(DeferredCallbackCollection::class)->invoke();

    $notification = InternalNotification::query()->where('recipient_id', $gestao->id)->sole();

    expect($notification->event_type_slug)->toBe(EventTypeSlug::Entrega->value)
        ->and($notification->email_status)->toBe(InternalNotificationEmailStatus::Enviado)
        ->and(mailerSentAddresses())->toBe([$gestao->email]);
})->with(['UpdatePedidoStatusAction', 'MarkPedidoEntregueByObraAction']);
