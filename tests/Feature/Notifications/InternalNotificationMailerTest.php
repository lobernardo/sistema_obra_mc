<?php

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
    $sent = mailerNotification($event, User::factory()->gestao()->create());
    $sent->update(['email_status' => InternalNotificationEmailStatus::Enviado, 'email_status_at' => now()->subHour()]);
    mailerNotification($event, User::factory()->gestao()->create());

    $this->mailer->queueEvent($event->id);
    app(DeferredCallbackCollection::class)->invoke();

    expect($this->transport->messages())->toHaveCount(1);
});

test('a transport failure for A marks only A as falhou; B still receives and is enviado (RF-16)', function () {
    $recipientA = User::factory()->gestao()->create(['email' => 'destinatario.a@example.com']);
    $recipientB = User::factory()->gestao()->create(['email' => 'destinatario.b@example.com']);
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
    mailerFailingFor('destinatario.a@example.com');

    $logged = [];
    Event::listen(MessageLogged::class, function (MessageLogged $message) use (&$logged): void {
        $logged[] = $message;
    });

    $event = mailerEvent();
    $notificationA = mailerNotification($event, $recipientA);

    $this->mailer->queueEvent($event->id);
    app(DeferredCallbackCollection::class)->invoke();

    expect($logged)->toHaveCount(1);
    expect($logged[0]->level)->toBe('warning');
    expect($logged[0]->context)->toBe([
        'internal_notification_id' => $notificationA->id,
        'pedido_event_id' => $event->id,
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
    $notification = mailerNotification($event, userForPapel('sem papel'));

    $this->mailer->queueEvent($event->id);
    app(DeferredCallbackCollection::class)->invoke();

    expect($this->transport->messages())->toHaveCount(0);
    expect($notification->fresh()->email_status)->toBe(InternalNotificationEmailStatus::Falhou);
});

test('sends through Resend are spaced by SEND_INTERVAL_MS; other transports are not', function () {
    Sleep::fake();

    $event = mailerEvent();
    foreach (User::factory()->gestao()->count(3)->create() as $recipient) {
        mailerNotification($event, $recipient);
    }

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
    mailerNotification($other, User::factory()->gestao()->create());
    mailerNotification($other, User::factory()->gestao()->create());

    $this->mailer->queueEvent($other->id);
    app(DeferredCallbackCollection::class)->invoke();

    Sleep::assertNeverSlept();
});
