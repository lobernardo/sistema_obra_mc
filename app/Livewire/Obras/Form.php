<?php

namespace App\Livewire\Obras;

use App\Actions\Obras\CreateObraAction;
use App\Actions\Obras\UpdateObraAction;
use App\Enums\ObraStatus;
use App\Models\Obra;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Create/edit form of the Obras area (UI-04, RF-01, RF-02, CT-03): Nome,
 * Responsável (optional free text) and Status with exactly the 3
 * `ObraStatus` options. Selecting Concluído shows that the obra stops
 * receiving new solicitações and that nothing is deleted. `save()`
 * re-authorizes through `ObraPolicy` and delegates to `CreateObraAction` /
 * `UpdateObraAction`. The Actions validate under the same keys as the bound
 * properties (`name`, `responsavel`, `status`), so their PT-BR errors land
 * on the matching inputs without re-keying.
 */
#[Layout('layouts.app')]
class Form extends Component
{
    public ?Obra $obra = null;

    public string $name = '';

    public string $responsavel = '';

    public string $status = 'a_iniciar';

    public function mount(?Obra $obra = null): void
    {
        $this->obra = $obra;

        if ($this->obra === null) {
            $this->authorize('create', Obra::class);

            return;
        }

        $this->authorize('update', $this->obra);

        $this->name = $this->obra->name;
        $this->responsavel = (string) $this->obra->responsavel;
        $this->status = $this->obra->status->value;
    }

    public function save(CreateObraAction $createObra, UpdateObraAction $updateObra): void
    {
        $data = [
            'name' => $this->name,
            'responsavel' => $this->responsavel,
            'status' => $this->status,
        ];

        if ($this->obra === null) {
            $this->authorize('create', Obra::class);

            $obra = $createObra->execute(Auth::user(), $data);

            session()->flash('status', "Obra {$obra->name} criada.");
        } else {
            $this->authorize('update', $this->obra);

            $obra = $updateObra->execute(Auth::user(), $this->obra, $data);

            session()->flash('status', "Obra {$obra->name} atualizada.");
        }

        $this->redirectRoute('obras.index');
    }

    public function isConcluidoSelected(): bool
    {
        return $this->status === ObraStatus::Concluido->value;
    }

    public function render()
    {
        return view('livewire.obras.form', [
            'statuses' => ObraStatus::cases(),
            'isConcluidoSelected' => $this->isConcluidoSelected(),
        ]);
    }
}
