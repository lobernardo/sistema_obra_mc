<?php

namespace App\Livewire\Pedidos;

use App\Actions\Pedidos\CreatePedidoAction;
use App\Enums\RoleSlug;
use App\Models\Obra;
use App\Models\Pedido;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Nova Solicitação shared by Obra and Suprimentos (RF-01, RF-02, RF-04,
 * RF-07, UI-01, CT-01, CT-05).
 *
 * The obra select lists only the requester's associated active obras
 * (`Obra::active()`), by name, followed by "Outra"; with none, the view shows
 * the papel-aware empty state of `CreatePedidoAction::noActiveObraMessage()`
 * and no form. `CreatePedidoAction` owns validation and persistence and
 * re-checks everything server-side, so a forged `obra_selection` is refused
 * even when it bypasses the select's options.
 */
#[Layout('layouts.app')]
class NovaSolicitacao extends Component
{
    public string $obra_selection = '';

    public string $obra_reference = '';

    public string $descricao = '';

    public string $needed_at = '';

    public ?string $code = null;

    public ?string $createdDataPrevista = null;

    public function mount(): void
    {
        $this->authorize('create-pedido');
    }

    public function submit(CreatePedidoAction $action): void
    {
        $this->authorize('create', Pedido::class);

        $pedido = $action->execute(Auth::user(), [
            'obra_selection' => $this->obra_selection,
            'obra_reference' => $this->obra_reference,
            'descricao' => $this->descricao,
            'needed_at' => $this->needed_at,
        ]);

        $this->code = $pedido->code;
        $this->createdDataPrevista = $pedido->dataPrevistaLabel();

        $this->reset(['obra_selection', 'obra_reference', 'descricao', 'needed_at']);
    }

    /**
     * @return Collection<int, Obra>
     */
    public function obras(): Collection
    {
        return Auth::user()->obras()->active()->orderBy('name')->get();
    }

    /**
     * The requester's own pedido listing (UI-01): Acompanhamento for Obra,
     * Todos os Pedidos for Suprimentos.
     */
    public function listingRoute(): string
    {
        return Auth::user()->role?->slug === RoleSlug::Suprimentos->value
            ? route('suprimentos.pedidos.index')
            : route('obra.pedidos.index');
    }

    public function render()
    {
        return view('livewire.pedidos.nova-solicitacao', [
            'obras' => $this->obras(),
            'emptyStateMessage' => CreatePedidoAction::noActiveObraMessage(Auth::user()),
            'listingUrl' => $this->listingRoute(),
            'isOutra' => $this->obra_selection === CreatePedidoAction::OUTRA_SELECTION,
        ]);
    }
}
