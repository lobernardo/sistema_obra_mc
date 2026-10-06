<?php

namespace App\Services;

use App\Enums\InternalNotificationEmailStatus;
use App\Models\InternalNotification;
use App\Notifications\PedidoEventNotification;
use App\Support\EmailNormalizer;
use App\Support\PedidoDetailRoute;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Throwable;

/**
 * Sends the e-mails of internal notifications after the HTTP response
 * (RF-14, RF-16, RNF-01, RNF-08). Bound `scoped`: one instance per request.
 *
 * {@see self::queueEvent()} is called only from `DB::afterCommit` by
 * `PedidoNotificationRecorder`, so the mutation is already committed: it
 * accumulates event ids and registers a single `defer(..., always: true)`
 * per request. Under FrankenPHP the response is closed before the deferred
 * callbacks by `Response::send()` (alias `fastcgi_finish_request()`), so
 * {@see self::flush()} does not call `frankenphp_finish_request()` itself
 * (`.spec/features/notificacoes-internas/defer-verificacao.md`, §4).
 *
 * {@see self::flush()} processes the `pendente` notifications one by one.
 * Only a notification whose recipient address is in
 * `mail.notification_email.recipients` and whose event type slug is in
 * `mail.notification_email.events` is e-mailed (`enviado`/`falhou`); any
 * other is stored `ignorado` without touching the transport. The row
 * itself, the bell and the page never depend on these lists. A failure
 * never stops the other recipients. Every processed notification is logged
 * once, only with ids, the event type slug and the result — never the
 * address, the event content, the pedido code nor an exception message.
 * There is no retry (no scheduler nor worker).
 */
class InternalNotificationMailer
{
    /**
     * Pause between two sends through Resend, to stay under its request
     * rate limit (2 requests/s on the default plan).
     */
    public const SEND_INTERVAL_MS = 600;

    /**
     * @var list<int>
     */
    private array $pedidoEventIds = [];

    private bool $flushIsDeferred = false;

    public function __construct(private readonly PedidoEventValuePresenter $presenter) {}

    public function queueEvent(int $pedidoEventId): void
    {
        $this->pedidoEventIds[] = $pedidoEventId;

        if ($this->flushIsDeferred) {
            return;
        }

        $this->flushIsDeferred = true;

        defer(fn () => $this->flush(), always: true);
    }

    public function flush(): void
    {
        $pedidoEventIds = array_values(array_unique($this->pedidoEventIds));
        $this->pedidoEventIds = [];
        $this->flushIsDeferred = false;

        if ($pedidoEventIds === []) {
            return;
        }

        $notifications = InternalNotification::query()
            ->whereIn('pedido_event_id', $pedidoEventIds)
            ->where('email_status', InternalNotificationEmailStatus::Pendente)
            ->with(['recipient.role', 'event.eventType', 'event.actor', 'event.pedido.obra'])
            ->orderBy('id')
            ->get();

        $allowedRecipients = array_flip(array_filter(array_map(
            fn (string $email): string => EmailNormalizer::normalize($email),
            config('mail.notification_email.recipients', []),
        )));
        $allowedEventTypes = array_flip(config('mail.notification_email.events', []));

        $descriptions = $this->presenter->describeEach($notifications->pluck('event')->unique('id')->values());
        $effectiveSends = 0;

        foreach ($notifications as $notification) {
            if (! $this->shouldEmail($notification, $allowedRecipients, $allowedEventTypes)) {
                $this->store($notification, InternalNotificationEmailStatus::Ignorado);

                continue;
            }

            if (PedidoDetailRoute::nameFor($notification->recipient) === null) {
                $this->store($notification, InternalNotificationEmailStatus::Falhou, ['reason' => 'papel_sem_rota_de_detalhe']);

                continue;
            }

            if ($effectiveSends > 0 && config('mail.default') === 'resend') {
                Sleep::usleep(self::SEND_INTERVAL_MS * 1000);
            }

            $effectiveSends++;

            $this->send($notification, $descriptions[$notification->pedido_event_id]);
        }
    }

    /**
     * Whether the notification is e-mailed: its recipient's normalized
     * address and its event type slug are both in the configured lists.
     *
     * @param  array<string, int>  $allowedRecipients
     * @param  array<string, int>  $allowedEventTypes
     */
    private function shouldEmail(InternalNotification $notification, array $allowedRecipients, array $allowedEventTypes): bool
    {
        return isset($allowedRecipients[EmailNormalizer::normalize((string) $notification->recipient->email)])
            && isset($allowedEventTypes[$notification->event_type_slug]);
    }

    /**
     * Stores the final e-mail state of the notification and logs it once.
     *
     * @param  array{exception_class?: class-string, reason?: string}  $failure
     */
    private function store(InternalNotification $notification, InternalNotificationEmailStatus $status, array $failure = []): void
    {
        $notification->update([
            'email_status' => $status,
            'email_status_at' => now(),
        ]);

        $context = [
            'internal_notification_id' => $notification->id,
            'pedido_event_id' => $notification->pedido_event_id,
            'event_type_slug' => $notification->event_type_slug,
            'recipient_id' => $notification->recipient_id,
            'result' => $status->value,
            ...$failure,
        ];

        if ($status === InternalNotificationEmailStatus::Falhou) {
            Log::warning('Falha ao enviar o e-mail de uma notificação interna.', $context);

            return;
        }

        Log::info('E-mail de notificação interna processado.', $context);
    }

    /**
     * Hands the e-mail to the transport and stores the result.
     *
     * @param  array{action: string, context: ?string, at: string, actor: ?string}  $description
     */
    private function send(InternalNotification $notification, array $description): void
    {
        try {
            $notification->recipient->notify(new PedidoEventNotification($notification->event, $description));
        } catch (Throwable $exception) {
            $this->store($notification, InternalNotificationEmailStatus::Falhou, ['exception_class' => $exception::class]);

            return;
        }

        $this->store($notification, InternalNotificationEmailStatus::Enviado);
    }
}
