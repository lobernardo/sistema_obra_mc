<?php

namespace App\Livewire\Gestao;

use App\Domain\Pedidos\AtrasoClassifier;
use App\Domain\Pedidos\PendenteClassifier;
use App\Models\Obra;
use App\Models\Pedido;
use App\Models\Priority;
use App\Models\Status;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * "Todos os Pedidos" for Gestão (RF-20, UI-05): the exact same filter set as
 * Suprimentos's listing ({@see \App\Livewire\Suprimentos\TodosPedidos}), but
 * read-only — no operational controls, only a link into the read-only
 * detail view. `pendenteOnly` is not rendered as a filter control (keeping
 * the visible filter set identical to Suprimentos's, per RF-20's AC); it
 * only exists so the dashboard's "pendentes" indicator (RF-22) can
 * pre-filter this listing via query string on first load.
 *
 * RF-20/CT-02: every filter property — including `pendenteOnly` — is bound to
 * the query string with {@see Url}. The manual `request()->` reads that used
 * to live in `mount()` are gone: `mount()` does not re-run on a Livewire
 * update, so a parameter read there would survive "Limpar filtros" (RF-19)
 * and come back on the next reload. The legacy drill-down parameter names
 * (`atrasado`, `pendente`, `requestedFrom`, `requestedTo`) are preserved.
 */
#[Layout('layouts.app')]
class TodosPedidos extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(as: 'atrasado', except: false)]
    public bool $atrasoOnly = false;

    #[Url(except: null)]
    public ?int $obraId = null;

    #[Url(except: null)]
    public ?int $statusId = null;

    #[Url(except: null)]
    public ?int $priorityId = null;

    #[Url(except: null)]
    public ?int $responsibleId = null;

    #[Url(except: '')]
    public string $neededAtFrom = '';

    #[Url(except: '')]
    public string $neededAtTo = '';

    #[Url(except: '')]
    public string $requestedFrom = '';

    #[Url(except: '')]
    public string $requestedTo = '';

    #[Url(as: 'pendente', except: null)]
    public ?bool $pendenteOnly = null;

    public function mount(): void
    {
        $this->authorize('is-gestao');
    }

    public function updating(string $name): void
    {
        if ($name !== 'page') {
            $this->resetPage();
        }
    }

    /**
     * RF-19: resets every filter of this screen to its declared default and
     * returns the listing to page 1.
     */
    public function limparFiltros(): void
    {
        $this->reset([
            'search',
            'atrasoOnly',
            'obraId',
            'statusId',
            'priorityId',
            'responsibleId',
            'neededAtFrom',
            'neededAtTo',
            'requestedFrom',
            'requestedTo',
            'pendenteOnly',
        ]);

        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, Pedido>
     */
    public function pedidos(): LengthAwarePaginator
    {
        $query = Pedido::query()->with(['obra', 'status', 'priority', 'responsible']);

        if ($this->search !== '') {
            $query->where(function (Builder $query): void {
                $query->where('code', 'like', "%{$this->search}%")
                    ->orWhere('items_description', 'like', "%{$this->search}%")
                    ->orWhereHas('obra', fn (Builder $query) => $query->where('name', 'like', "%{$this->search}%"));
            });
        }

        if ($this->atrasoOnly) {
            AtrasoClassifier::scopeAtrasado($query);
        }

        if ($this->pendenteOnly !== null) {
            PendenteClassifier::scopePendente($query, $this->pendenteOnly);
        }

        if ($this->obraId !== null) {
            $query->where('obra_id', $this->obraId);
        }

        if ($this->statusId !== null) {
            $query->where('status_id', $this->statusId);
        }

        if ($this->priorityId !== null) {
            $query->where('priority_id', $this->priorityId);
        }

        if ($this->responsibleId !== null) {
            $query->where('responsible_id', $this->responsibleId);
        }

        if ($this->neededAtFrom !== '') {
            $query->whereDate('needed_at', '>=', $this->neededAtFrom);
        }

        if ($this->neededAtTo !== '') {
            $query->whereDate('needed_at', '<=', $this->neededAtTo);
        }

        if ($this->requestedFrom !== '') {
            $query->whereDate('requested_at', '>=', $this->requestedFrom);
        }

        if ($this->requestedTo !== '') {
            $query->whereDate('requested_at', '<=', $this->requestedTo);
        }

        return $query->latest('requested_at')->paginate(10);
    }

    public function render()
    {
        return view('livewire.gestao.todos-pedidos', [
            'pedidos' => $this->pedidos(),
            /** RF-18: never `->active()` — a deactivated obra stays filterable. */
            'obras' => Obra::query()->orderBy('name')->get(),
            'statuses' => Status::ordered()->get(),
            'priorities' => Priority::ordered()->get(),
            'suprimentosUsers' => User::query()->suprimentos()->orderBy('name')->get(),
        ]);
    }
}
