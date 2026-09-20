<?php

namespace App\Livewire\Suprimentos;

use App\Actions\Pedidos\CancelPedidoAction;
use App\Actions\Pedidos\UpdatePedidoPrevisaoAction;
use App\Actions\Pedidos\UpdatePedidoPrioridadeAction;
use App\Actions\Pedidos\UpdatePedidoResponsavelAction;
use App\Actions\Pedidos\UpdatePedidoStatusAction;
use App\Enums\StatusSlug;
use App\Models\Pedido;
use App\Models\PedidoEvent;
use App\Models\Priority;
use App\Models\Status;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Detalhe do pedido (Suprimentos), with the 5 operational controls (RF-12,
 * UI-02): responsável, prioridade, previsão, status and cancelamento — each
 * wired to its own Action so authorization and the terminal-state guard
 * (RF-13b) are enforced identically to every other entry point. Controls are
 * hidden once the pedido reaches a terminal status (`entregue`/`cancelado`).
 */
#[Layout('layouts.app')]
class PedidoDetalhe extends Component
{
    public Pedido $pedido;

    public ?int $responsible_id = null;

    public ?int $priority_id = null;

    public string $expected_delivery_at = '';

    public ?int $status_id = null;

    public bool $confirmingCancel = false;

    public ?string $feedback = null;

    public function mount(Pedido $pedido): void
    {
        $this->authorize('is-suprimentos');
        $this->authorize('view', $pedido);

        $this->pedido = $pedido->loadMissing(['obra', 'status', 'priority', 'responsible', 'requester']);
        $this->responsible_id = $pedido->responsible_id;
        $this->priority_id = $pedido->priority_id;
        $this->expected_delivery_at = $pedido->expected_delivery_at?->toDateString() ?? '';
        $this->status_id = $pedido->status_id;
    }

    public function updateResponsavel(UpdatePedidoResponsavelAction $action): void
    {
        $this->authorize('setResponsavel', $this->pedido);

        $this->pedido = $action->execute(Auth::user(), $this->pedido, $this->responsible_id);
        $this->feedback = 'Responsável atualizado.';
    }

    public function updatePrioridade(UpdatePedidoPrioridadeAction $action): void
    {
        $this->authorize('setPrioridade', $this->pedido);

        $this->pedido = $action->execute(Auth::user(), $this->pedido, $this->priority_id);
        $this->feedback = 'Prioridade atualizada.';
    }

    public function updatePrevisao(UpdatePedidoPrevisaoAction $action): void
    {
        $this->authorize('setPrevisao', $this->pedido);

        $this->pedido = $action->execute(Auth::user(), $this->pedido, $this->expected_delivery_at);
        $this->feedback = 'Previsão de entrega atualizada.';
    }

    public function updateStatus(UpdatePedidoStatusAction $action): void
    {
        $this->authorize('updateStatus', $this->pedido);

        $this->pedido = $action->execute(Auth::user(), $this->pedido, $this->status_id);
        $this->status_id = $this->pedido->status_id;
        $this->feedback = 'Status atualizado para '.$this->pedido->status->name.'.';
    }

    public function confirmCancel(): void
    {
        $this->confirmingCancel = true;
    }

    public function abortCancel(): void
    {
        $this->confirmingCancel = false;
    }

    public function cancelarPedido(CancelPedidoAction $action): void
    {
        $this->authorize('cancelar', $this->pedido);

        $this->pedido = $action->execute(Auth::user(), $this->pedido);
        $this->confirmingCancel = false;
        $this->feedback = 'Pedido cancelado.';
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

        return view('livewire.suprimentos.pedido-detalhe', [
            'events' => $this->events(),
            'isTerminal' => StatusSlug::from($this->pedido->status->slug)->isTerminal(),
            'suprimentosUsers' => User::query()->suprimentos()->orderBy('name')->get(),
            'priorities' => Priority::ordered()->get(),
            'statuses' => Status::query()->where('slug', '!=', StatusSlug::Cancelado->value)->ordered()->get(),
        ]);
    }
}
