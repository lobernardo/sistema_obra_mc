<?php

namespace App\Livewire\Gestao\Usuarios;

use App\Actions\Usuarios\CreateUserAction;
use App\Actions\Usuarios\UpdateUserAction;
use App\Models\Obra;
use App\Models\Role;
use App\Models\User;
use App\Support\EmailNormalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Create/edit form for Gestão's Usuários area (RF-06..RF-09, CT-01). The
 * obra selector is offered while the selected perfil is Obra or Suprimentos,
 * both accepting 0..N obras (RF-13b, UI-10); `obra_ids` (possibly `[]`) is
 * sent only for those papéis and never for Gestão. `save()` re-authorizes through `UserPolicy` and delegates to
 * `CreateUserAction` / `UpdateUserAction`, whose PT-BR validation messages
 * (RF-07) and RF-30 lockout errors surface inline per field. After a
 * create, the flash tells honestly whether the first-access invite went
 * out (RF-29). No password is ever bound, rendered or logged by this
 * component (RF-25).
 */
#[Layout('layouts.app')]
class Form extends Component
{
    public ?User $user = null;

    public string $name = '';

    public string $email = '';

    public ?int $roleId = null;

    /** @var list<int> */
    public array $obraIds = [];

    public function mount(?User $user = null): void
    {
        $this->user = $user;

        if ($this->user === null) {
            $this->authorize('create', User::class);

            return;
        }

        $this->authorize('update', $this->user);

        $this->name = $this->user->name;
        $this->email = $this->user->email;
        $this->roleId = $this->user->role_id;
        $this->obraIds = $this->user->obras()->pluck('obras.id')->map(fn ($id) => (int) $id)->all();
    }

    public function save(CreateUserAction $createUser, UpdateUserAction $updateUser): void
    {
        $data = [
            'name' => trim($this->name),
            'email' => EmailNormalizer::normalize($this->email),
            'role_id' => $this->roleId,
        ];

        if ($this->selectedRoleAcceptsObras()) {
            $data['obra_ids'] = array_values(array_map('intval', $this->obraIds));
        }

        try {
            if ($this->user === null) {
                $this->authorize('create', User::class);

                $result = $createUser->execute(Auth::user(), $data);

                session()->flash('status', $result['invite_sent']
                    ? "Usuário criado. Convite enviado para {$result['user']->email}."
                    : 'Usuário criado, mas o convite não pôde ser enviado — use Reenviar convite.');
            } else {
                $this->authorize('update', $this->user);

                if ($this->roleId !== $this->user->role_id) {
                    $this->authorize('changeRole', $this->user);
                }

                $updated = $updateUser->execute(Auth::user(), $this->user, $data);

                session()->flash('status', "Usuário {$updated->name} atualizado.");
            }
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages($this->mapErrorKeys($exception->errors()));
        }

        $this->redirectRoute('gestao.usuarios.index');
    }

    /**
     * Whether the selected perfil may hold obra associations: Obra and
     * Suprimentos (RF-11, RF-13b).
     */
    public function selectedRoleAcceptsObras(): bool
    {
        return $this->roleId !== null && CreateUserAction::roleAcceptsObras($this->roleId);
    }

    /**
     * @return Collection<int, Role>
     */
    public function roles(): Collection
    {
        return Role::query()->orderBy('name')->get();
    }

    /**
     * Active obras plus any obra already associated with the user being
     * edited, so an existing association is never silently dropped.
     *
     * @return Collection<int, Obra>
     */
    public function obras(): Collection
    {
        return Obra::query()
            ->where(function (Builder $query): void {
                $query->active();

                if ($this->obraIds !== []) {
                    $query->orWhereIn('id', $this->obraIds);
                }
            })
            ->orderBy('name')
            ->get();
    }

    /**
     * The Actions validate against the snake_case payload keys; the form
     * binds camelCase properties, so the field errors are re-keyed to land
     * on the matching inputs.
     *
     * @param  array<string, list<string>>  $errors
     * @return array<string, list<string>>
     */
    private function mapErrorKeys(array $errors): array
    {
        $mapped = [];

        foreach ($errors as $key => $messages) {
            $property = match (true) {
                $key === 'role_id' => 'roleId',
                $key === 'obra_ids', str_starts_with($key, 'obra_ids.') => 'obraIds',
                default => $key,
            };

            $mapped[$property] = array_merge($mapped[$property] ?? [], $messages);
        }

        return $mapped;
    }

    public function render()
    {
        $selectedRoleAcceptsObras = $this->selectedRoleAcceptsObras();

        return view('livewire.gestao.usuarios.form', [
            'roles' => $this->roles(),
            'obras' => $selectedRoleAcceptsObras ? $this->obras() : new Collection,
            'selectedRoleAcceptsObras' => $selectedRoleAcceptsObras,
        ]);
    }
}
