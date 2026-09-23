<?php

namespace App\Livewire\Associacoes;

use App\Actions\Usuarios\AttachUserObrasAction;
use App\Actions\Usuarios\DetachUserObraAction;
use App\Enums\RoleSlug;
use App\Models\Obra;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Associações usuário × obra for Gestão and Suprimentos (RF-08, UI-06,
 * CT-03). Lists, paginated at 15, only the users whose papel admits
 * associations (`obra` and `suprimentos`, RF-11) — Gestão-papel users are
 * never listed — searched case-insensitively by nome or e-mail. Each row
 * shows papel, Ativo/Inativo and every associated obra (nome plus status
 * label) with a two-step "Remover", and a multi-select of the obras not
 * yet associated with "Adicionar". The full obra list is loaded once per
 * render and diffed in PHP, so the page never issues a query per row.
 *
 * Every mutating method authorizes `manageAssociations` before delegating
 * to `AttachUserObrasAction` / `DetachUserObraAction`; their PT-BR errors
 * land on `selectedObraIds.<userId>`. On the authenticated user's own row a
 * non-blocking notice is shown — self-association is intended (RF-11 v1.3,
 * F-13) and the controls stay enabled.
 */
#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    public string $search = '';

    /** @var array<int, list<int|string>> */
    public array $selectedObraIds = [];

    /** @var array{0: int, 1: int}|null */
    public ?array $confirmingRemoval = null;

    public ?string $feedback = null;

    public function mount(): void
    {
        $this->authorize('manageAssociations', Obra::class);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function attach(int $userId, AttachUserObrasAction $action): void
    {
        $this->feedback = null;
        $this->confirmingRemoval = null;
        $this->resetErrorBag();

        $this->authorize('manageAssociations', Obra::class);

        $user = User::query()->findOrFail($userId);
        $obraIds = array_values(array_map('intval', (array) ($this->selectedObraIds[$userId] ?? [])));

        try {
            $action->execute(Auth::user(), $user, ['obra_ids' => $obraIds]);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages([
                "selectedObraIds.{$userId}" => array_merge(...array_values($exception->errors())),
            ]);
        }

        unset($this->selectedObraIds[$userId]);

        $this->feedback = "Associações de {$user->name} atualizadas.";
    }

    public function askRemoval(int $userId, int $obraId): void
    {
        $this->feedback = null;
        $this->resetErrorBag();

        $this->authorize('manageAssociations', Obra::class);

        $this->confirmingRemoval = [$userId, $obraId];
    }

    public function cancelRemoval(): void
    {
        $this->confirmingRemoval = null;
    }

    public function confirmRemoval(DetachUserObraAction $action): void
    {
        $this->feedback = null;
        $this->resetErrorBag();

        $this->authorize('manageAssociations', Obra::class);

        if ($this->confirmingRemoval === null) {
            return;
        }

        [$userId, $obraId] = array_map('intval', $this->confirmingRemoval);
        $this->confirmingRemoval = null;

        $user = User::query()->findOrFail($userId);

        try {
            $action->execute(Auth::user(), $user, ['obra_id' => $obraId]);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages([
                "selectedObraIds.{$userId}" => array_merge(...array_values($exception->errors())),
            ]);
        }

        $this->feedback = "Associações de {$user->name} atualizadas.";
    }

    /**
     * @return LengthAwarePaginator<int, User>
     */
    public function users(): LengthAwarePaginator
    {
        $query = User::query()
            ->whereHas('role', fn (Builder $query) => $query->whereIn('slug', [RoleSlug::Obra->value, RoleSlug::Suprimentos->value]))
            ->with(['role', 'obras']);

        $term = mb_strtolower(trim($this->search));

        if ($term !== '') {
            $query->where(function (Builder $query) use ($term): void {
                $query->whereRaw('lower(name) like ?', ["%{$term}%"])
                    ->orWhereRaw('lower(email) like ?', ["%{$term}%"]);
            });
        }

        return $query->orderBy('name')->orderBy('id')->paginate(15);
    }

    /**
     * @return Collection<int, Obra>
     */
    public function allObras(): Collection
    {
        return Obra::query()->orderBy('name')->orderBy('id')->get();
    }

    public function render()
    {
        return view('livewire.associacoes.index', [
            'users' => $this->users(),
            'allObras' => $this->allObras(),
            'authenticatedUserId' => Auth::id(),
        ]);
    }
}
