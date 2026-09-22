<?php

namespace App\Livewire\Suprimentos;

use App\Enums\StatusSlug;
use App\Models\Pedido;
use App\Models\Status;
use App\Services\DashboardIndicatorsService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * "Visão Geral" de Suprimentos (RF-27, RF-28, RF-29, UI-05): the operational
 * overview screen — three KPI cards, the count of each non-cancelled workflow
 * status, a shortcut into the Kanban and the five most recent pedidos.
 *
 * Every number comes from {@see DashboardIndicatorsService}, consumed
 * unfiltered and **without** altering its role-agnostic behaviour: no
 * classifier rule (atraso, pendência, prazo, entrega de hoje) is re-encoded
 * here or in the Blade view.
 */
#[Layout('layouts.app')]
class VisaoGeral extends Component
{
    /** Same number of rows the demo screen shows; also the eager-load budget. */
    public const PEDIDOS_RECENTES = 5;

    public function mount(): void
    {
        $this->authorize('is-suprimentos');
    }

    /**
     * RF-28: the 5 workflow statuses, `cancelado` excluded, straight from the
     * service's `porStatus` so the counts can never diverge from it.
     *
     * @param  Collection<int, array{status: Status, count: int}>  $porStatus
     * @return Collection<int, array{status: Status, count: int}>
     */
    public function statusCounts(Collection $porStatus): Collection
    {
        return $porStatus
            ->reject(fn (array $row) => $row['status']->slug === StatusSlug::Cancelado->value)
            ->values();
    }

    /**
     * @return EloquentCollection<int, Pedido>
     */
    public function pedidosRecentes(): EloquentCollection
    {
        return Pedido::query()
            ->with(['obra', 'status', 'priority', 'responsible'])
            ->latest('requested_at')
            ->take(self::PEDIDOS_RECENTES)
            ->get();
    }

    public function render(DashboardIndicatorsService $service)
    {
        $indicators = $service->compute([]);

        return view('livewire.suprimentos.visao-geral', [
            'indicators' => $indicators,
            'statusCounts' => $this->statusCounts($indicators['porStatus']),
            'pedidosRecentes' => $this->pedidosRecentes(),
        ]);
    }
}
