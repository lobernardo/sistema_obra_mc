<?php

namespace App\Livewire\Gestao;

use App\Domain\Pedidos\AtrasoClassifier;
use App\Domain\Pedidos\PendenteClassifier;
use App\Models\Pedido;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
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
 */
#[Layout('layouts.app')]
class TodosPedidos extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $atrasoOnly = false;

    public string $neededAtFrom = '';

    public string $neededAtTo = '';

    public string $requestedFrom = '';

    public string $requestedTo = '';

    public ?bool $pendenteOnly = null;

    public function mount(): void
    {
        $this->authorize('is-gestao');

        $this->atrasoOnly = request()->boolean('atrasado');
        $this->pendenteOnly = request()->has('pendente') ? request()->boolean('pendente') : null;
        $this->requestedFrom = (string) request()->query('requestedFrom', '');
        $this->requestedTo = (string) request()->query('requestedTo', '');
    }

    public function updating(string $name): void
    {
        if ($name !== 'page') {
            $this->resetPage();
        }
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
            'atrasoClassifier' => AtrasoClassifier::class,
        ]);
    }
}
