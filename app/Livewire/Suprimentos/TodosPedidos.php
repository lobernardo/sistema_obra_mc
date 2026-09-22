<?php

namespace App\Livewire\Suprimentos;

use App\Domain\Pedidos\AtrasoClassifier;
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
 * "Todos os Pedidos" (RF-12, UI-02, RNF-07): the Suprimentos operational
 * listing, with a filter set distinct from the dashboard's (RF-21/UI-06) —
 * free-text search, a boolean "Atraso" filter reusing {@see AtrasoClassifier},
 * two independent date ranges and (RF-14) the obra, status, prioridade and
 * responsável selects. Eager-loads the relations it displays so query count
 * stays constant regardless of dataset size.
 *
 * RF-20/CT-02: every filter property is bound to the query string with
 * {@see Url}. This is the project convention for listing filter state —
 * reading parameters manually in `mount()` is prohibited, because `mount()`
 * does not re-run on a Livewire update and the parameter would survive
 * "Limpar filtros" (RF-19).
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

    public function mount(): void
    {
        $this->authorize('is-suprimentos');
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
        return view('livewire.suprimentos.todos-pedidos', [
            'pedidos' => $this->pedidos(),
            /** RF-18: never `->active()` — a deactivated obra stays filterable. */
            'obras' => Obra::query()->orderBy('name')->get(),
            'statuses' => Status::ordered()->get(),
            'priorities' => Priority::ordered()->get(),
            'suprimentosUsers' => User::query()->suprimentos()->orderBy('name')->get(),
        ]);
    }
}
