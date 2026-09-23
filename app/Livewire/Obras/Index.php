<?php

namespace App\Livewire\Obras;

use App\Models\Obra;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Obras listing for Gestão and Suprimentos (UI-03, CT-03): Nome,
 * Responsável and the PT-BR Status label, paginated at 15 and ordered by
 * name, with "Nova obra" and "Editar". There is deliberately no delete
 * control — no obra is ever deleted through the application (RF-06).
 */
#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    public function mount(): void
    {
        $this->authorize('viewAny', Obra::class);
    }

    /**
     * @return LengthAwarePaginator<int, Obra>
     */
    public function obras(): LengthAwarePaginator
    {
        return Obra::query()->orderBy('name')->orderBy('id')->paginate(15);
    }

    public function render()
    {
        return view('livewire.obras.index', [
            'obras' => $this->obras(),
        ]);
    }
}
