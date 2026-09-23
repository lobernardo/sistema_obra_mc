<?php

namespace App\Livewire\Obra;

use App\Actions\Pedidos\AddPedidoObservacaoAction;
use App\Actions\Pedidos\MarkPedidoEntregueByObraAction;
use App\Enums\StatusSlug;
use App\Models\Pedido;
use App\Models\PedidoEvent;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Detalhe do pedido for `obra` (RF-03, RF-11, UI-01, UI-04): the pedido
 * itself is never editable (US-2.2); the only controls are "Adicionar
 * observação" (RF-24, UI-05), offered in every status, and "Marcar como
 * entregue" (RF-27, UI-06), offered with a two-step confirmation while the
 * status is active. Access to a pedido
 * from an obra the user is not associated with is denied by
 * `PedidoPolicy::view` (RF-10).
 */
#[Layout('layouts.app')]
class PedidoDetalhe extends Component
{
    public Pedido $pedido;

    public string $observacao = '';

    public bool $confirmingEntrega = false;

    public ?string $feedback = null;

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

    public function adicionarObservacao(AddPedidoObservacaoAction $action): void
    {
        $this->authorize('addObservacao', $this->pedido);

        $action->execute(Auth::user(), $this->pedido, $this->observacao);
        $this->reset('observacao');
    }

    public function confirmarEntrega(): void
    {
        $this->confirmingEntrega = true;
    }

    public function abortarEntrega(): void
    {
        $this->confirmingEntrega = false;
    }

    public function marcarComoEntregue(MarkPedidoEntregueByObraAction $action): void
    {
        $this->authorize('marcarEntregue', $this->pedido);

        $this->pedido = $action->execute(Auth::user(), $this->pedido);
        $this->confirmingEntrega = false;
        $this->feedback = 'Pedido marcado como entregue.';
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

        return view('livewire.obra.pedido-detalhe', [
            'events' => $this->events(),
            'canMarkEntregue' => in_array(StatusSlug::from($this->pedido->status->slug), StatusSlug::activeNonFinal(), true),
        ]);
    }
}
