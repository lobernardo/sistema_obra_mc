<?php

namespace App\Livewire\Kanban;

use App\Actions\Pedidos\UpdatePedidoStatusAction;
use App\Domain\Pedidos\AtrasoClassifier;
use App\Enums\StatusSlug;
use App\Models\Pedido;
use App\Models\Status;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Kanban board (RF-03, RF-12, UI-02, UI-03): the 5 active workflow statuses
 * as columns, ordered by `statuses.sort_order` — `cancelado` is never a
 * column (RF-13 excludes it as a drag-and-drop target). Both the
 * drag-and-drop handler (`moveCard`, backed by `wire:sort`) and the
 * accessible non-drag control (`moveViaControl`, UI-08) funnel through the
 * same policy check and {@see UpdatePedidoStatusAction}, so neither path can
 * bypass the workflow matrix (RF-13/RF-13b, UI-07).
 */
#[Layout('layouts.app')]
class KanbanBoard extends Component
{
    public function mount(): void
    {
        $this->authorize('is-suprimentos');
    }

    /**
     * @return Collection<int, Status>
     */
    public function columns(): Collection
    {
        return Status::query()
            ->where('slug', '!=', StatusSlug::Cancelado->value)
            ->ordered()
            ->get();
    }

    /**
     * @return Collection<int, Pedido>
     */
    public function pedidos(): Collection
    {
        return Pedido::query()
            ->whereHas('status', fn ($query) => $query->where('slug', '!=', StatusSlug::Cancelado->value))
            ->with(['obra', 'status', 'priority', 'responsible'])
            ->get();
    }

    /**
     * Drag-and-drop handler (`wire:sort`). Reordering a card inside its own
     * column is not a workflow event: it is ignored before any authorization
     * or transition check, so it neither writes history nor raises an error.
     */
    public function moveCard(int $pedidoId, int $position, int $statusId): void
    {
        $pedido = Pedido::query()->with('status')->findOrFail($pedidoId);

        if ($pedido->status_id === $statusId) {
            return;
        }

        $this->moveViaControl($pedidoId, $statusId);
    }

    public function moveViaControl(int $pedidoId, int $statusId): void
    {
        $pedido = Pedido::query()->with('status')->findOrFail($pedidoId);

        $this->authorize('updateStatus', $pedido);

        app(UpdatePedidoStatusAction::class)->execute(Auth::user(), $pedido, $statusId);
    }

    public function render()
    {
        $pedidosByStatus = $this->pedidos()->groupBy('status_id');

        return view('livewire.kanban.kanban-board', [
            'columns' => $this->columns(),
            'pedidosByStatus' => $pedidosByStatus,
            'atrasoClassifier' => AtrasoClassifier::class,
        ]);
    }
}
