<?php

namespace App\Domain\Pedidos;

use App\Enums\EventTypeSlug;

/**
 * Single definition of which history event types generate internal
 * notifications (RF-02, RF-09). No other class in `app/` decides whether a
 * type notifies: the recorder asks {@see self::isNotifiable()}.
 *
 * It is an instance resolved by the container, so making a new type
 * notifiable is one entry in {@see self::classification()} — or, in a test,
 * a subclass bound in the container — with no change to the recipient rule,
 * storage, page, bell or e-mail.
 */
class NotifiableEventTypes
{
    /**
     * Every {@see EventTypeSlug} value, classified as notifiable or not.
     *
     * @return array<string, bool>
     */
    public function classification(): array
    {
        return [
            EventTypeSlug::CriacaoPedido->value => true,
            EventTypeSlug::MudancaStatus->value => true,
            EventTypeSlug::Entrega->value => true,
            EventTypeSlug::Cancelamento->value => true,
            EventTypeSlug::Finalizacao->value => true,
            EventTypeSlug::AlteracaoResponsavel->value => true,
            EventTypeSlug::AlteracaoPrioridade->value => true,
            EventTypeSlug::AlteracaoPrevisao->value => true,
            EventTypeSlug::Observacao->value => true,
            EventTypeSlug::RomaneioAnexado->value => true,
        ];
    }

    /**
     * A slug outside {@see self::classification()} never notifies.
     */
    public function isNotifiable(string $slug): bool
    {
        return $this->classification()[$slug] ?? false;
    }
}
