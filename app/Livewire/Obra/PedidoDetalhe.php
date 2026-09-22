<?php

namespace App\Livewire\Obra;

use App\Models\Pedido;
use App\Models\PedidoEvent;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
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

    /**
     * RF-03: binding preserves 404 for missing ids; the scope denies
     * out-of-scope pedidos with 403 before the independent policy barrier.
     */
    public function mount(Pedido $pedido): void
    {
        throw_unless(
            Pedido::query()->visibleTo(Auth::user())->whereKey($pedido->getKey())->exists(),
            AuthorizationException::class,
            'Pedido fora do escopo do solicitante.',
        );

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
        $this->pedido->loadMissing(['obra', 'status', 'priority', 'responsible', 'requester']);

        return view('livewire.obra.pedido-detalhe', [
            'events' => $this->events(),
        ]);
    }
}
