<?php

namespace App\Livewire\Obra;

use App\Domain\Pedidos\AtrasoClassifier;
use App\Models\Pedido;
use App\Models\Status;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Acompanhamento (RF-03, RF-11, UI-01, RNF-07): paginated listing of pedidos
 * restricted to the requester's associated obras, eager-loading the
 * relations displayed so query count stays constant regardless of dataset
 * size.
 *
 * RF-16: the filter set here is deliberately reduced to busca, obra, status
 * and atraso. Prioridade and responsável are Suprimentos-side workflow state
 * that this role neither sets nor owns, so they are not offered as filter
 * axes — `priorityId`/`responsibleId` in the query string are ignored.
 *
 * RF-17/RNF-08: `visibleTo` is applied in the same statement that opens the
 * `Pedido::` query and every filter is applied afterwards, so the obra filter
 * can only ever narrow the visible set, never widen it.
 */
#[Layout('layouts.app')]
class Acompanhamento extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: null)]
    public ?int $obraId = null;

    #[Url(except: null)]
    public ?int $statusId = null;

    #[Url(as: 'atrasado', except: false)]
    public bool $atrasoOnly = false;

    /**
     * RF-21: set once by Novo Cadastro and pulled here, so the notice shows
     * on the first listing render only.
     */
    public bool $showRegistrationNotice = false;

    public function mount(): void
    {
        $this->authorize('is-obra');

        $this->showRegistrationNotice = (bool) session()->pull('obra.registration_notice', false);
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
        $this->reset(['search', 'obraId', 'statusId', 'atrasoOnly']);

        $this->resetPage();
    }

    /**
     * Read through the centralized visibility scope (RF-02).
     *
     * @return LengthAwarePaginator<int, Pedido>
     */
    public function pedidos(): LengthAwarePaginator
    {
        $query = Pedido::query()->visibleTo(Auth::user())->with(['obra', 'requester', 'status', 'priority', 'responsible']);

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

        return $query->latest('requested_at')->paginate(10);
    }

    public function render()
    {
        return view('livewire.obra.acompanhamento', [
            'pedidos' => $this->pedidos(),
            /**
             * RF-18: the user's own obras only — an unscoped option set would
             * enumerate every obra name in the company to this role — and
             * never `->active()`, so a deactivated obra stays filterable.
             */
            'obras' => Auth::user()->obras()->orderBy('name')->get(),
            'statuses' => Status::ordered()->get(),
        ]);
    }
}
