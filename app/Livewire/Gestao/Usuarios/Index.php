<?php

namespace App\Livewire\Gestao\Usuarios;

use App\Actions\Usuarios\SendAccessLinkAction;
use App\Actions\Usuarios\SetUserActiveAction;
use App\Enums\RoleSlug;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Usuários listing for Gestão (RF-03, RF-04, RF-10, RF-11, CT-01). Every
 * row shows nome, e-mail, perfil, status and — for Obra users only — the
 * associated obras. The search filters by nome OR e-mail, case-insensitively.
 * Activation/deactivation authorizes through `UserPolicy` before delegating
 * to `SetUserActiveAction`, whose RF-30 lockout guards surface inline as a
 * PT-BR validation error. "Reenviar convite" re-issues the first-access
 * link through `SendAccessLinkAction` and — because this surface is
 * authenticated — tells Gestão explicitly when the broker throttled the
 * request (RF-14, Q-05). Nothing here reads or renders `users.password`
 * (RF-25).
 */
#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public ?string $feedback = null;

    public function mount(): void
    {
        $this->authorize('viewAny', User::class);
    }

    public function updating(string $name): void
    {
        if ($name !== 'page') {
            $this->resetPage();
        }
    }

    public function setActive(int $userId, bool $active, SetUserActiveAction $action): void
    {
        $this->feedback = null;
        $this->resetErrorBag();

        $user = User::query()->findOrFail($userId);

        $this->authorize($active ? 'activate' : 'deactivate', $user);

        $updated = $action->execute(Auth::user(), $user, $active);

        $this->feedback = $active
            ? "Usuário {$updated->name} ativado."
            : "Usuário {$updated->name} desativado.";
    }

    public function sendAccessLink(int $userId, SendAccessLinkAction $action): void
    {
        $this->feedback = null;
        $this->resetErrorBag();

        $user = User::query()->findOrFail($userId);

        $this->authorize('sendAccessLink', $user);

        $status = $action->execute(Auth::user(), $user);

        $this->feedback = match ($status) {
            Password::RESET_LINK_SENT => "Link de acesso enviado para {$user->email}.",
            Password::RESET_THROTTLED => 'Um link já foi enviado para este e-mail há menos de 1 minuto. Aguarde para reenviar.',
            default => 'Não foi possível enviar o link. Tente novamente.',
        };
    }

    /**
     * @return LengthAwarePaginator<int, User>
     */
    public function users(): LengthAwarePaginator
    {
        $query = User::query()->with(['role', 'obras']);

        $term = mb_strtolower(trim($this->search));

        if ($term !== '') {
            $query->where(function (Builder $query) use ($term): void {
                $query->whereRaw('lower(name) like ?', ["%{$term}%"])
                    ->orWhereRaw('lower(email) like ?', ["%{$term}%"]);
            });
        }

        return $query->orderBy('name')->orderBy('id')->paginate(15);
    }

    public function render()
    {
        return view('livewire.gestao.usuarios.index', [
            'users' => $this->users(),
            'obraRoleSlug' => RoleSlug::Obra->value,
        ]);
    }
}
