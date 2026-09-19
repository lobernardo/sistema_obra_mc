<?php

namespace App\Livewire\Obra;

use App\Models\Pedido;
use App\Models\PedidoEvent;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Detalhe do pedido, read-only for `obra` (RF-03, RF-11, UI-01, UI-04): no
 * edit controls are rendered, and access to a pedido from an obra the user
 * is not associated with is denied by `PedidoPolicy::view` (RF-10).
 */
#[Layout('layouts.app')]
class PedidoDetalhe extends Component
{
    public Pedido $pedido;

    public function mount(Pedido $pedido): void
    {
        $this->authorize('view', $pedido);

        $this->pedido = $pedido;
    }

    /**
     * @return Collection<int, PedidoEvent>
     */
    public function events(): Collection
    {
        return $this->pedido->events()
            ->with(['eventType', 'actor'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }

    public function render()
    {
        $this->pedido->loadMissing(['obra', 'status', 'priority', 'responsible']);

        return view('livewire.obra.pedido-detalhe', [
            'events' => $this->events(),
        ]);
    }
}
