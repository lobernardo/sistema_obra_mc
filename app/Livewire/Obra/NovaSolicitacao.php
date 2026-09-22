<?php

namespace App\Livewire\Obra;

use App\Actions\Pedidos\CreatePedidoAction;
use App\Models\Obra;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Nova Solicitação (RF-03, RF-11, RF-11b, RF-11c, UI-01). The obra select is
 * populated exclusively from active `obra_profile` associations (security
 * hardening RF-06/UI-01), with an empty-state notice when none are available;
 * `CreatePedidoAction` re-validates `obra_id` against that same association
 * server-side (RF-11c), so a tampered `obra_id` is rejected even when it
 * bypasses the select's options.
 */
#[Layout('layouts.app')]
class NovaSolicitacao extends Component
{
    public ?int $obra_id = null;

    public string $needed_at = '';

    public string $items_description = '';

    public ?string $code = null;

    public function mount(): void
    {
        $this->authorize('is-obra');
    }

    /**
     * @return array<string, array<int, string>>
     */
    protected function rules(): array
    {
        return [
            'obra_id' => ['required', 'integer'],
            'needed_at' => ['required', 'date'],
            'items_description' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'obra_id.required' => 'Selecione a obra.',
            'needed_at.required' => 'Informe a data necessária.',
            'needed_at.date' => 'Informe uma data necessária válida.',
            'items_description.required' => 'Descreva os itens e quantidades.',
        ];
    }

    public function submit(CreatePedidoAction $action): void
    {
        $this->authorize('is-obra');

        $validated = $this->validate();

        $pedido = $action->execute(Auth::user(), $validated);

        $this->code = $pedido->code;

        $this->reset(['obra_id', 'needed_at', 'items_description']);
    }

    /**
     * @return Collection<int, Obra>
     */
    public function obras(): Collection
    {
        return Auth::user()->obras()->active()->orderBy('name')->get();
    }

    public function render()
    {
        return view('livewire.obra.nova-solicitacao', [
            'obras' => $this->obras(),
        ]);
    }
}
