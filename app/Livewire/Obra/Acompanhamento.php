<?php

namespace App\Livewire\Obra;

use App\Domain\Pedidos\AtrasoClassifier;
use App\Models\Pedido;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Acompanhamento (RF-03, RF-11, UI-01, RNF-07): paginated listing of pedidos
 * restricted to the requester's associated obras, eager-loading the
 * relations displayed so query count stays constant regardless of dataset
 * size.
 */
#[Layout('layouts.app')]
class Acompanhamento extends Component
{
    use WithPagination;

    public function mount(): void
    {
        $this->authorize('is-obra');
    }

    /**
     * @return LengthAwarePaginator<int, Pedido>
     */
    public function pedidos(): LengthAwarePaginator
    {
        $obraIds = Auth::user()->obras()->pluck('obras.id');

        return Pedido::query()
            ->whereIn('obra_id', $obraIds)
            ->with(['obra', 'status', 'priority', 'responsible'])
            ->latest('requested_at')
            ->paginate(10);
    }

    public function render()
    {
        return view('livewire.obra.acompanhamento', [
            'pedidos' => $this->pedidos(),
            'atrasoClassifier' => AtrasoClassifier::class,
        ]);
    }
}
