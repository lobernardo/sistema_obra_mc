<?php

namespace App\Livewire\Gestao;

use App\Models\Obra;
use App\Models\Priority;
use App\Models\Status;
use App\Models\User;
use App\Services\DashboardIndicatorsService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Gestão dashboard (RF-21, UI-06): 5 combinable filters — período (via
 * `requested_at`), obra, status, prioridade e responsável — each one a
 * plain reactive Livewire property, so changing any of them re-renders
 * every indicator from the same {@see DashboardIndicatorsService} call
 * (RF-21's AC that all 6 indicators stay consistent with each other).
 *
 * Drill-down (RF-23/RF-24): "atrasados"/"pendentes"/"entregues" link into
 * Gestão's "Todos os Pedidos" ({@see TodosPedidos}) pre-filtered accordingly.
 * **Every** active dashboard filter is carried over — período, obra, status,
 * prioridade e responsável — because RF-15 gave that listing the same four
 * selects, which is what makes the drill-down count equal to the KPI value
 * that was clicked. The earlier restriction to período alone existed only
 * because those counterparts were missing.
 */
#[Layout('layouts.app')]
class Dashboard extends Component
{
    public string $requestedFrom = '';

    public string $requestedTo = '';

    public ?int $obraId = null;

    public ?int $statusId = null;

    public ?int $priorityId = null;

    public ?int $responsibleId = null;

    public function mount(): void
    {
        $this->authorize('is-gestao');
    }

    /**
     * @return array{
     *     volumeTotal: int,
     *     pendentes: int,
     *     atrasados: int,
     *     entregues: int,
     *     entreguesHoje: int,
     *     porStatus: Collection<int, array{status: Status, count: int}>,
     *     porObra: Collection<int, array{obra: Obra, count: int}>,
     *     prazos: Collection<int, array{situacao: string, count: int}>,
     * }
     */
    public function indicators(DashboardIndicatorsService $service): array
    {
        return $service->compute([
            'obraId' => $this->obraId,
            'statusId' => $this->statusId,
            'priorityId' => $this->priorityId,
            'responsibleId' => $this->responsibleId,
            'requestedFrom' => $this->requestedFrom !== '' ? $this->requestedFrom : null,
            'requestedTo' => $this->requestedTo !== '' ? $this->requestedTo : null,
        ]);
    }

    /**
     * RF-23/CT-03: builds the drill-down URL carrying every active filter the
     * target listing supports plus the boolean criterion, which must be one of
     * `atrasado`, `pendente` or `entregue`. Empty filters are dropped by
     * `array_filter` so the URL never carries a default value.
     */
    public function drillDownUrl(string $criterion): string
    {
        return route('gestao.pedidos.index', array_filter([
            'requestedFrom' => $this->requestedFrom !== '' ? $this->requestedFrom : null,
            'requestedTo' => $this->requestedTo !== '' ? $this->requestedTo : null,
            'obraId' => $this->obraId,
            'statusId' => $this->statusId,
            'priorityId' => $this->priorityId,
            'responsibleId' => $this->responsibleId,
            $criterion => 'true',
        ]));
    }

    public function render(DashboardIndicatorsService $service)
    {
        return view('livewire.gestao.dashboard', [
            'indicators' => $this->indicators($service),
            'obras' => Obra::query()->orderBy('name')->get(),
            'statuses' => Status::ordered()->get(),
            'priorities' => Priority::ordered()->get(),
            'suprimentosUsers' => User::query()->suprimentos()->orderBy('name')->get(),
            'atrasadosDrillDownUrl' => $this->drillDownUrl('atrasado'),
            'pendentesDrillDownUrl' => $this->drillDownUrl('pendente'),
            'entreguesDrillDownUrl' => $this->drillDownUrl('entregue'),
        ]);
    }
}
