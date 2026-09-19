<?php

namespace App\Livewire\Suprimentos;

use App\Domain\Pedidos\AtrasoClassifier;
use App\Models\Pedido;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * "Todos os Pedidos" (RF-12, UI-02, RNF-07): the Suprimentos operational
 * listing, with a filter set distinct from the dashboard's (RF-21/UI-06) —
 * free-text search, a boolean "Atraso" filter reusing {@see AtrasoClassifier},
 * and two independent date ranges. Eager-loads the relations it displays so
 * query count stays constant regardless of dataset size.
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
            'atrasoClassifier' => AtrasoClassifier::class,
        ]);
    }
}
