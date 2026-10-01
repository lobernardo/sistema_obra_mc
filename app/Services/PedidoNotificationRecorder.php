<?php

namespace App\Services;

use App\Domain\Pedidos\NotifiableEventTypes;
use App\Domain\Pedidos\NotificationRecipientResolver;
use App\Enums\InternalNotificationEmailStatus;
use App\Models\InternalNotification;
use App\Models\PedidoEvent;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * The single point where a history event becomes internal notifications
 * (RF-01, RF-09, RF-17, RNF-02). Every pedido Action calls
 * {@see self::record()} right after writing its `pedido_event`, inside its
 * own transaction.
 *
 * Whether the type notifies comes only from {@see NotifiableEventTypes}; who
 * receives it, only from {@see NotificationRecipientResolver}. The rows are
 * written in one batch INSERT (`email_status = pendente`), so the delta is
 * at most 3 queries — type slug, recipients, INSERT — whatever the number
 * of recipients. The e-mails are queued on {@see InternalNotificationMailer}
 * only after the commit: a rollback discards rows and e-mails alike.
 */
class PedidoNotificationRecorder
{
    public function __construct(
        private readonly NotifiableEventTypes $notifiableEventTypes,
        private readonly NotificationRecipientResolver $recipientResolver,
        private readonly InternalNotificationMailer $mailer,
    ) {}

    public function record(PedidoEvent $event): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('As notificações de um evento só podem ser registradas dentro da transação que grava o evento.');
        }

        $slug = $event->eventType->slug;

        if (! $this->notifiableEventTypes->isNotifiable($slug)) {
            return;
        }

        $recipientIds = $this->recipientResolver->recipientIdsFor($event);

        if ($recipientIds === []) {
            return;
        }

        $createdAt = now();

        InternalNotification::query()->insert(array_map(fn (int $recipientId): array => [
            'recipient_id' => $recipientId,
            'pedido_id' => $event->pedido_id,
            'pedido_event_id' => $event->id,
            'event_type_slug' => $slug,
            'actor_id' => $event->actor_id,
            'created_at' => $createdAt,
            'email_status' => InternalNotificationEmailStatus::Pendente->value,
        ], $recipientIds));

        DB::afterCommit(fn () => $this->mailer->queueEvent($event->id));
    }
}
