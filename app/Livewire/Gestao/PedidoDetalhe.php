<?php

namespace App\Livewire\Gestao;

use App\Models\Pedido;
use App\Models\PedidoEvent;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Detalhe do pedido, read-only for `gestao` (RF-20, UI-05): reuses the
 * read-only detail pattern from `obra`
 * ({@see \App\Livewire\Obra\PedidoDetalhe}) — no edit controls are
 * rendered. `PedidoPolicy::view` grants `gestao` unrestricted read access
 * across every obra (RF-08c).
 */
#[Layout('layouts.app')]
class PedidoDetalhe extends Component
{
    public Pedido $pedido;

    public function mount(Pedido $pedido): void
    {
        $this->authorize('is-gestao');
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
        $this->pedido->loadMissing(['obra', 'status', 'priority', 'responsible', 'requester', 'attachments.uploader']);

        return view('livewire.gestao.pedido-detalhe', [
            'events' => $this->events(),
        ]);
    }
}
