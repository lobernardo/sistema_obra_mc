<?php

namespace App\Services;

use App\Domain\Pedidos\AtrasoClassifier;
use App\Domain\Pedidos\PendenteClassifier;
use App\Domain\Pedidos\PrazoClassifier;
use App\Models\Obra;
use App\Models\Pedido;
use App\Models\Status;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

/**
 * Single aggregation service for the Gestão dashboard (RF-21, UI-06):
 * fetches one filtered dataset and computes all 6 indicators from it —
 * volume total, pendentes, atrasados, distribuição por status, prazos and
 * visão por obra — reusing T16's classifiers exclusively so these numbers
 * can never drift from what Kanban/listagens consider atrasado/pendente.
 */
class DashboardIndicatorsService
{
    /**
     * @param  array{obraId?: int|null, statusId?: int|null, priorityId?: int|null, responsibleId?: int|null, requestedFrom?: string|null, requestedTo?: string|null}  $filters
     * @return array{
     *     volumeTotal: int,
     *     pendentes: int,
     *     atrasados: int,
     *     porStatus: SupportCollection<int, array{status: Status, count: int}>,
     *     porObra: SupportCollection<int, array{obra: Obra, count: int}>,
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
            'porStatus' => $statuses->map(fn (Status $status) => [
                'status' => $status,
                'count' => $pedidos->where('status_id', $status->id)->count(),
            ]),
            'porObra' => $obras->map(fn (Obra $obra) => [
                'obra' => $obra,
                'count' => $pedidos->where('obra_id', $obra->id)->count(),
            ]),
            'prazos' => collect(['dentro_do_prazo', 'vencendo_em_breve', 'atrasado'])
                ->map(fn (string $situacao) => [
                    'situacao' => $situacao,
                    'count' => $pedidos->filter(fn (Pedido $pedido) => PrazoClassifier::classificar($pedido) === $situacao)->count(),
                ]),
        ];
    }

    /**
     * @param  array{obraId?: int|null, statusId?: int|null, priorityId?: int|null, responsibleId?: int|null, requestedFrom?: string|null, requestedTo?: string|null}  $filters
     * @return Collection<int, Pedido>
     */
    private function filteredPedidos(array $filters): Collection
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

        if (! empty($filters['requestedFrom'])) {
            $query->whereDate('requested_at', '>=', $filters['requestedFrom']);
        }

        if (! empty($filters['requestedTo'])) {
            $query->whereDate('requested_at', '<=', $filters['requestedTo']);
        }

        return $query->get();
    }
}
