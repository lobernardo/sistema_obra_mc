<?php

namespace App\Services;

use App\Domain\Pedidos\AtrasoClassifier;
use App\Domain\Pedidos\PendenteClassifier;
use App\Domain\Pedidos\PrazoClassifier;
use App\Domain\Pedidos\RequestedPeriodFilter;
use App\Enums\EventTypeSlug;
use App\Enums\StatusSlug;
use App\Models\Obra;
use App\Models\Pedido;
use App\Models\Status;
use App\Support\LocalTime;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection as SupportCollection;

/**
 * Single aggregation service for the Gestão dashboard (RF-21, UI-06):
 * fetches one filtered dataset and computes every indicator from it —
 * volume total, pendentes, atrasados, entregues, distribuição por status,
 * prazos and visão por obra — reusing T16's classifiers exclusively so these
 * numbers can never drift from what Kanban/listagens consider
 * atrasado/pendente.
 *
 * The service is deliberately role-agnostic and does **not** apply
 * {@see Pedido::scopeVisibleTo()}: every screen consuming it today
 * belongs to Suprimentos or Gestão, roles that see every pedido by policy.
 * Any future reuse from an Obra context MUST add the scope first, otherwise
 * the indicators would leak counts from other obras.
 *
 * ## Known performance debt (RNF-10 — accepted, not resolved)
 *
 * Every key but `entreguesHoje` is computed in PHP over the single dataset
 * loaded by {@see self::filteredPedidos()}, which issues a plain `->get()`.
 * That is deliberate at V0 volume and keeps the classifiers as the only
 * encoding of atraso/pendência/prazo. Above roughly **5 000 pedidos** in the
 * filtered scope, this must move to SQL aggregation (`GROUP BY` per status
 * and per obra, plus date predicates) instead of materialising every row.
 *
 * `entreguesHoje` is the **single authorized exception** to the one-dataset
 * rule (RNF-10 amendment for RF-29): the date of the `entrega` event cannot be
 * derived from the `pedidos` rows already loaded, so it adds exactly **one**
 * constant `whereExists` query — never one query per pedido.
 */
class DashboardIndicatorsService
{
    /**
     * @param  array{obraId?: int|null, statusId?: int|null, priorityId?: int|null, responsibleId?: int|null, requestedFrom?: string|null, requestedTo?: string|null}  $filters
     * @return array{
     *     volumeTotal: int,
     *     pendentes: int,
     *     atrasados: int,
     *     entregues: int,
     *     entreguesHoje: int,
     *     porStatus: SupportCollection<int, array{status: Status, count: int}>,
     *     porObra: SupportCollection<int, array{obra: ?Obra, label: string, count: int}>,
     *     prazos: SupportCollection<int, array{situacao: string, count: int}>,
     * }
     */
    public function compute(array $filters = []): array
    {
        $pedidos = $this->filteredPedidos($filters);
        $statuses = Status::query()->ordered()->get();
        $obras = Obra::query()->orderBy('name')->get();

        return [
            'volumeTotal' => $pedidos->count(),
            'pendentes' => $pedidos->filter(fn (Pedido $pedido) => PendenteClassifier::isPendente($pedido))->count(),
            'atrasados' => $pedidos->filter(fn (Pedido $pedido) => AtrasoClassifier::isAtrasado($pedido))->count(),
            /** RF-22: counted in PHP over the dataset already loaded — no extra query. Finalizado is not "entregue". */
            'entregues' => $pedidos->filter(fn (Pedido $pedido) => $pedido->status->slug === StatusSlug::Entregue->value)->count(),
            /** RF-29/RNF-10: the one authorized extra query — see the class docblock. */
            'entreguesHoje' => $this->entreguesHojeCount($filters),
            'porStatus' => $statuses->map(fn (Status $status) => [
                'status' => $status,
                'count' => $pedidos->where('status_id', $status->id)->count(),
            ]),
            'porObra' => $this->porObra($obras, $pedidos),
            'prazos' => collect(['dentro_do_prazo', 'vencendo_em_breve', 'atrasado'])
                ->map(fn (string $situacao) => [
                    'situacao' => $situacao,
                    'count' => $pedidos->filter(fn (Pedido $pedido) => PrazoClassifier::classificar($pedido) === $situacao)->count(),
                ]),
        ];
    }

    /**
     * One row per obra (label = obra name) plus, only when the dataset has at
     * least one pedido without an obra, a single "Outra" row appended last
     * (RF-41, CT-07): pedidos "Outra" are grouped by the null obra, never by
     * their free-text reference. Counted over the loaded dataset — no query.
     *
     * @param  Collection<int, Obra>  $obras
     * @param  Collection<int, Pedido>  $pedidos
     * @return SupportCollection<int, array{obra: ?Obra, label: string, count: int}>
     */
    private function porObra(Collection $obras, Collection $pedidos): SupportCollection
    {
        $rows = $obras->toBase()->map(fn (Obra $obra) => [
            'obra' => $obra,
            'label' => $obra->name,
            'count' => $pedidos->where('obra_id', $obra->id)->count(),
        ]);

        $outraCount = $pedidos->whereNull('obra_id')->count();

        if ($outraCount > 0) {
            $rows->push([
                'obra' => null,
                'label' => Pedido::OUTRA_LABEL,
                'count' => $outraCount,
            ]);
        }

        return $rows;
    }

    /**
     * RF-29: pedidos already delivered whose `entrega` event was recorded
     * today — the delivery that actually happened, never the forecast
     * (`pedidos.expected_delivery_at` is nullable and only Suprimentos fills
     * it, so a forecast-based rule would silently drop deliveries).
     *
     * Exactly one query, constant regardless of how many pedidos match: the
     * status test and the event test are both correlated sub-queries on the
     * same filtered set. Never call this per pedido.
     *
     * "Today" is the `America/Sao_Paulo` day (RF-45; router decision F-01,
     * reversible): the event's UTC `created_at` is compared against the UTC
     * half-open window of the local day from {@see LocalTime::todayWindowUtc()},
     * never with `whereDate` on the UTC value.
     *
     * @param  array{obraId?: int|null, statusId?: int|null, priorityId?: int|null, responsibleId?: int|null, requestedFrom?: string|null, requestedTo?: string|null}  $filters
     */
    private function entreguesHojeCount(array $filters): int
    {
        [$startOfTodayUtc, $startOfTomorrowUtc] = LocalTime::todayWindowUtc();

        return $this->filteredQuery($filters)
            ->whereHas('status', fn (EloquentBuilder $status) => $status->where('slug', StatusSlug::Entregue->value))
            ->whereExists(fn (QueryBuilder $event) => $event
                ->selectRaw('1')
                ->from('pedido_events')
                ->join('event_types', 'event_types.id', '=', 'pedido_events.event_type_id')
                ->whereColumn('pedido_events.pedido_id', 'pedidos.id')
                ->where('event_types.slug', EventTypeSlug::Entrega->value)
                ->where('pedido_events.created_at', '>=', $startOfTodayUtc)
                ->where('pedido_events.created_at', '<', $startOfTomorrowUtc))
            ->count();
    }

    /**
     * @param  array{obraId?: int|null, statusId?: int|null, priorityId?: int|null, responsibleId?: int|null, requestedFrom?: string|null, requestedTo?: string|null}  $filters
     * @return Collection<int, Pedido>
     */
    private function filteredPedidos(array $filters): Collection
    {
        return $this->filteredQuery($filters)->get();
    }

    /**
     * Single filtered builder shared by every key, so no consumer can apply a
     * different scope to the same indicator set.
     *
     * @param  array{obraId?: int|null, statusId?: int|null, priorityId?: int|null, responsibleId?: int|null, requestedFrom?: string|null, requestedTo?: string|null}  $filters
     * @return EloquentBuilder<Pedido>
     */
    private function filteredQuery(array $filters): EloquentBuilder
    {
        $query = Pedido::query()->with(['obra', 'status', 'priority', 'responsible']);

        if (! empty($filters['obraId'])) {
            $query->where('obra_id', $filters['obraId']);
        }

        if (! empty($filters['statusId'])) {
            $query->where('status_id', $filters['statusId']);
        }

        if (! empty($filters['priorityId'])) {
            $query->where('priority_id', $filters['priorityId']);
        }

        if (! empty($filters['responsibleId'])) {
            $query->where('responsible_id', $filters['responsibleId']);
        }

        return RequestedPeriodFilter::applyLocalRange($query, $filters['requestedFrom'] ?? null, $filters['requestedTo'] ?? null);
    }
}
