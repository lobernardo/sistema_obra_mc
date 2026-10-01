<?php

namespace App\Services;

use App\Enums\InternalNotificationEmailStatus;
use App\Models\InternalNotification;
use App\Notifications\PedidoEventNotification;
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
 * {@see self::flush()} sends the `pendente` notifications one by one and
 * stores `enviado`/`falhou` with its timestamp on each row right after its
 * send. A failure never stops the other recipients and is logged only with
 * ids and the exception class — never the exception message, the address
 * nor the event content. There is no retry (no scheduler nor worker).
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

        $descriptions = $this->presenter->describeEach($notifications->pluck('event')->unique('id')->values());

        foreach ($notifications->values() as $index => $notification) {
            if ($index > 0 && config('mail.default') === 'resend') {
                Sleep::usleep(self::SEND_INTERVAL_MS * 1000);
            }

            $notification->update([
                'email_status' => $this->send($notification, $descriptions[$notification->pedido_event_id]),
                'email_status_at' => now(),
            ]);
        }
    }

    /**
     * @param  array{action: string, context: ?string, at: string, actor: ?string}  $description
     */
    private function send(InternalNotification $notification, array $description): InternalNotificationEmailStatus
    {
        if (PedidoDetailRoute::nameFor($notification->recipient) === null) {
            $this->logFailure($notification, null);

            return InternalNotificationEmailStatus::Falhou;
        }

        try {
            $notification->recipient->notify(new PedidoEventNotification($notification->event, $description));
        } catch (Throwable $exception) {
            $this->logFailure($notification, $exception);

            return InternalNotificationEmailStatus::Falhou;
        }

        return InternalNotificationEmailStatus::Enviado;
    }

    private function logFailure(InternalNotification $notification, ?Throwable $exception): void
    {
        Log::warning('Falha ao enviar o e-mail de uma notificação interna.', [
            'internal_notification_id' => $notification->id,
            'pedido_event_id' => $notification->pedido_event_id,
            ...($exception === null ? ['reason' => 'papel_sem_rota_de_detalhe'] : ['exception_class' => $exception::class]),
        ]);
    }
}
