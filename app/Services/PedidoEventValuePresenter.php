<?php

namespace App\Services;

use App\Enums\EventTypeSlug;
use App\Models\Pedido;
use App\Models\PedidoEvent;
use App\Models\Priority;
use App\Models\Status;
use App\Models\User;
use App\Support\LocalTime;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The single history presentation of the three detail screens (RF-21,
 * RF-23, UI-04): each event becomes ação, contexto, local date/time and
 * autor.
 *
 * Raw `previous_value`/`new_value` (lookup ids for status/prioridade/
 * responsável, ISO dates for previsão) are translated into names. Lookups
 * are resolved once per collection of events, never per event, so the
 * detail screens issue a fixed number of queries regardless of history
 * length (RNF-03, RNF-07).
 *
 * The `criacao_pedido` context reads the canonical obra/referência snapshot
 * stored in the event's `new_value` at creation (F-09, CT-07); only a legacy
 * event with `new_value` null falls back to the pedido's current
 * `obraLabel()`.
 */
class PedidoEventValuePresenter
{
    /**
     * @param  Collection<int, PedidoEvent>  $events
     * @return array<int, array{action: string, context: ?string, at: string, actor: ?string}> keyed by event id
     */
    public function describeAll(Collection $events, Pedido $pedido): array
    {
        $labels = $this->labelsFor($events);

        return $events
            ->mapWithKeys(fn (PedidoEvent $event) => [
                $event->id => [
                    'action' => $this->action($event),
                    'context' => $this->context($event, $pedido, $labels[$event->id]),
                    'at' => LocalTime::formatDateTime($event->created_at),
                    'actor' => $event->actor?->name,
                ],
            ])
            ->all();
    }

    /**
     * @param  Collection<int, PedidoEvent>  $events
     * @return array<int, array{previous: ?string, new: ?string}> keyed by event id
     */
    public function labelsFor(Collection $events): array
    {
        $statuses = Status::query()->pluck('name', 'id');
        $priorities = Priority::query()->pluck('name', 'id');

        $responsibleIds = $events
            ->filter(fn (PedidoEvent $event) => $event->eventType->slug === EventTypeSlug::AlteracaoResponsavel->value)
            ->flatMap(fn (PedidoEvent $event) => [$event->previous_value, $event->new_value])
            ->filter()
            ->unique()
            ->values();

        $users = $responsibleIds->isEmpty()
            ? collect()
            : User::query()->whereIn('id', $responsibleIds)->pluck('name', 'id');

        return $events
            ->mapWithKeys(fn (PedidoEvent $event) => [
                $event->id => [
                    'previous' => $this->label($event, $event->previous_value, $statuses, $priorities, $users),
                    'new' => $this->label($event, $event->new_value, $statuses, $priorities, $users),
                ],
            ])
            ->all();
    }

    private function action(PedidoEvent $event): string
    {
        return match (EventTypeSlug::tryFrom($event->eventType->slug)) {
            EventTypeSlug::CriacaoPedido => 'Pedido criado',
            EventTypeSlug::Observacao => 'Observação adicionada',
            EventTypeSlug::RomaneioAnexado => 'Romaneio anexado',
            EventTypeSlug::Finalizacao => 'Pedido finalizado',
            default => $event->eventType->name,
        };
    }

    /**
     * @param  array{previous: ?string, new: ?string}  $labels
     */
    private function context(PedidoEvent $event, Pedido $pedido, array $labels): ?string
    {
        return match (EventTypeSlug::tryFrom($event->eventType->slug)) {
            EventTypeSlug::CriacaoPedido => 'Solicitação registrada para '
                .($this->filled($event->new_value) ? $event->new_value : $pedido->obraLabel()).'.',
            EventTypeSlug::Observacao,
            EventTypeSlug::RomaneioAnexado => $this->filled($event->new_value) ? $event->new_value : null,
            EventTypeSlug::Finalizacao => 'Pedido finalizado por Suprimentos.',
            default => $labels['previous'] === null && $labels['new'] === null
                ? null
                : ($labels['previous'] ?? '—').' → '.($labels['new'] ?? '—'),
        };
    }

    private function filled(?string $value): bool
    {
        return $value !== null && $value !== '';
    }

    /**
     * @param  Collection<int, string>  $statuses
     * @param  Collection<int, string>  $priorities
     * @param  Collection<int, string>  $users
     */
    private function label(PedidoEvent $event, ?string $value, Collection $statuses, Collection $priorities, Collection $users): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return match ($event->eventType->slug) {
            EventTypeSlug::MudancaStatus->value,
            EventTypeSlug::Cancelamento->value,
            EventTypeSlug::Entrega->value,
            EventTypeSlug::Finalizacao->value => $statuses->get((int) $value, $value),
            EventTypeSlug::AlteracaoPrioridade->value => $priorities->get((int) $value, $value),
            EventTypeSlug::AlteracaoResponsavel->value => $users->get((int) $value, $value),
            EventTypeSlug::AlteracaoPrevisao->value => $this->formatDate($value),
            default => $value,
        };
    }

    private function formatDate(string $value): string
    {
        try {
            return Carbon::parse($value)->format('d/m/Y');
        } catch (\Throwable) {
            return $value;
        }
    }
}
