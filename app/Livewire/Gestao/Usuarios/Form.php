<?php

namespace App\Livewire\Gestao\Usuarios;

use App\Actions\Usuarios\CreateUserAction;
use App\Actions\Usuarios\UpdateUserAction;
use App\Enums\RoleSlug;
use App\Models\Obra;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Create/edit form for Gestão's Usuários area (RF-06..RF-09, CT-01). The
 * obra selector is only offered while the selected perfil is Obra (RF-09);
 * `save()` re-authorizes through `UserPolicy` and delegates to
 * `CreateUserAction` / `UpdateUserAction`, whose PT-BR validation messages
 * (RF-07) and RF-30 lockout errors surface inline per field. No password
 * is ever bound, rendered or logged by this component (RF-25).
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
            'email' => trim($this->email),
            'role_id' => $this->roleId,
        ];

        if ($this->selectedRoleIsObra()) {
            $data['obra_ids'] = array_values(array_map('intval', $this->obraIds));
        }

        try {
            if ($this->user === null) {
                $this->authorize('create', User::class);

                $result = $createUser->execute(Auth::user(), $data);

                session()->flash('status', "Usuário {$result['user']->name} criado.");
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

    public function selectedRoleIsObra(): bool
    {
        if ($this->roleId === null) {
            return false;
        }

        return Role::query()->whereKey($this->roleId)->value('slug') === RoleSlug::Obra->value;
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
                $query->where('is_active', true);

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
        return view('livewire.gestao.usuarios.form', [
            'roles' => $this->roles(),
            'obras' => $this->selectedRoleIsObra() ? $this->obras() : new Collection,
            'selectedRoleIsObra' => $this->selectedRoleIsObra(),
        ]);
    }
}
