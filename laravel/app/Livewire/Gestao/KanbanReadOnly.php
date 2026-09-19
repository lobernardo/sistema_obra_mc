<?php

namespace App\Livewire\Gestao;

use App\Domain\Pedidos\AtrasoClassifier;
use App\Enums\StatusSlug;
use App\Livewire\Kanban\KanbanBoard;
use App\Models\Pedido;
use App\Models\Status;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Kanban board for Gestão (RF-20, UI-05): the same 5 active-workflow
 * columns as Suprimentos's interactive board
 * ({@see KanbanBoard}), but with drag-and-drop and the
 * "Mover para" control both stripped — Gestão only reads the board.
 */
#[Layout('layouts.app')]
class KanbanReadOnly extends Component
{
    public function mount(): void
    {
        $this->authorize('is-gestao');
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

    public function render()
    {
        $pedidosByStatus = $this->pedidos()->groupBy('status_id');

        return view('livewire.gestao.kanban-read-only', [
            'columns' => $this->columns(),
            'pedidosByStatus' => $pedidosByStatus,
            'atrasoClassifier' => AtrasoClassifier::class,
        ]);
    }
}
