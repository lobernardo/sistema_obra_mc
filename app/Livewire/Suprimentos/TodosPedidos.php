<?php

namespace App\Livewire\Suprimentos;

use App\Domain\Pedidos\AtrasoClassifier;
use App\Domain\Pedidos\PendenteClassifier;
use App\Livewire\Concerns\FiltersByRequestedPeriod;
use App\Models\Obra;
use App\Models\Pedido;
use App\Models\Priority;
use App\Models\Status;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
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
 *
 * Slice 3 (`navegacao-sidebar-listagens`): the query opens with
 * `Pedido::visibleTo()` in the same statement (RF-23, the identity for this
 * papel); the default order is `requested_at` ASC, `id` ASC (RF-14); the
 * "Solicitado" period goes only through {@see FiltersByRequestedPeriod}
 * (RF-16..RF-19, CT-03); "Somente obras ativas" keeps pedidos "Outra" and
 * drops only those whose obra is Concluída (RF-20, NC-02), as a read-only
 * restriction (RF-21).
 */
#[Layout('layouts.app')]
class TodosPedidos extends Component
{
    use FiltersByRequestedPeriod;
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

    /**
     * "Solicitado" preset (RF-15..RF-19); empty = neutral. Normalized in
     * {@see self::mount()} through {@see FiltersByRequestedPeriod}.
     */
    #[Url(as: 'solicitado', except: '')]
    public string $requestedPreset = '';

    #[Url(except: '')]
    public string $requestedFrom = '';

    #[Url(except: '')]
    public string $requestedTo = '';

    /**
     * "Somente obras ativas" (RF-20): off by default.
     */
    #[Url(as: 'obrasAtivas', except: false)]
    public bool $activeObrasOnly = false;

    public function mount(): void
    {
        $this->authorize('is-suprimentos');

        $this->normalizeRequestedPeriod();
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
            'requestedPreset',
            'requestedFrom',
            'requestedTo',
            'activeObrasOnly',
        ]);

        $this->resetPage();
    }

    /**
     * Number of active filter axes; each axis counts once ("Preciso para"
     * and "Solicitado" count once for either bound or preset).
     */
    public function activeFilterCount(): int
    {
        return count(array_filter([
            $this->search !== '',
            $this->obraId !== null,
            $this->statusId !== null,
            $this->priorityId !== null,
            $this->responsibleId !== null,
            $this->requestedPeriodIsActive(),
        ])) + $this->moreFiltersActiveCount();
    }

    /**
     * Active axes behind the "Mais filtros" disclosure: atraso, obras ativas
     * and "Preciso para".
     */
    public function moreFiltersActiveCount(): int
    {
        return count(array_filter([
            $this->atrasoOnly,
            $this->activeObrasOnly,
            $this->neededAtFrom !== '' || $this->neededAtTo !== '',
        ]));
    }

    /**
     * RF-21: the three indicators are computed from the very same filtered
     * builder the listing paginates, so a number can never contradict the
     * rows below it. Every clone is taken **before** `paginate()`.
     *
     * Pendência and atraso come exclusively from the domain classifiers
     * (`docs/agents/coding_guidelines.md` §5) — no formula is re-derived
     * here or in Blade.
     *
     * @return array{total: int, pendentes: int, atrasados: int}
     */
    public function indicators(): array
    {
        $builder = $this->filteredQuery();

        return [
            'total' => (clone $builder)->count(),
            'pendentes' => PendenteClassifier::scopePendente(clone $builder, true)->count(),
            'atrasados' => AtrasoClassifier::scopeAtrasado(clone $builder)->count(),
        ];
    }

    /**
     * @return LengthAwarePaginator<int, Pedido>
     */
    public function pedidos(): LengthAwarePaginator
    {
        return $this->filteredQuery()->orderBy('requested_at')->orderBy('id')->paginate(10);
    }

    /**
     * Single filtered builder shared by {@see self::pedidos()} and
     * {@see self::indicators()} (RF-21), without ordering or pagination.
     *
     * @return Builder<Pedido>
     */
    private function filteredQuery(): Builder
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

        if ($this->activeObrasOnly) {
            $query->where(fn (Builder $query) => $query
                ->whereNull('obra_id')
                ->orWhereHas('obra', fn (Builder $obra) => $obra->active()));
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

        $this->applyRequestedPeriod($query);

        return $query;
    }

    public function render()
    {
        return view('livewire.suprimentos.todos-pedidos', [
            'pedidos' => $this->pedidos(),
            'indicators' => $this->indicators(),
            /** RF-18: never `->active()` — a deactivated obra stays filterable. */
            'obras' => Obra::query()->orderBy('name')->get(),
            'statuses' => Status::ordered()->get(),
            'priorities' => Priority::ordered()->get(),
            'suprimentosUsers' => User::query()->suprimentos()->orderBy('name')->get(),
        ]);
    }
}
