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
 * Drill-down (RF-22, optional): "atrasados"/"pendentes" link into Gestão's
 * "Todos os Pedidos" ({@see TodosPedidos}) pre-filtered accordingly. Only
 * `requestedFrom`/`requestedTo` are carried over alongside the boolean
 * criterion — obra/status/prioridade/responsável have no counterpart in
 * that listing's filter set (RF-20's AC keeps it identical to
 * Suprimentos's), so a drill-down while those are active would silently
 * under-filter the target listing; carrying over only the filters both
 * screens actually support keeps the drill-down count exact.
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

    public function drillDownUrl(string $criterion): string
    {
        return route('gestao.pedidos.index', array_filter([
            'requestedFrom' => $this->requestedFrom !== '' ? $this->requestedFrom : null,
            'requestedTo' => $this->requestedTo !== '' ? $this->requestedTo : null,
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
        ]);
    }
}
