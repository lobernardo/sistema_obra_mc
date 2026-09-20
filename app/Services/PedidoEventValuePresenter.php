<?php

namespace App\Services;

use App\Enums\EventTypeSlug;
use App\Models\PedidoEvent;
use App\Models\Priority;
use App\Models\Status;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Turns the raw `previous_value`/`new_value` stored on a history event
 * (lookup ids for status/prioridade/responsável, ISO dates for previsão —
 * RF-18) into the human-readable label shown on the pedido timeline.
 * Lookups are resolved once per collection of events, never per event, so
 * the detail screens issue a fixed number of queries regardless of history
 * length (RNF-07).
 */
class PedidoEventValuePresenter
{
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
            EventTypeSlug::Entrega->value => $statuses->get((int) $value, $value),
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
