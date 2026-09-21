<div class="mx-auto flex max-w-2xl flex-col gap-5">
    <div>
        <h1 class="page-title">{{ $user ? 'Editar usuário' : 'Novo usuário' }}</h1>
        <p class="text-sm text-text-muted">
            @if ($user)
                Altere nome, e-mail, perfil e, para o perfil Obra, as obras associadas.
            @else
                Informe nome, e-mail e perfil. O usuário define a própria senha pelo link de primeiro acesso.
            @endif
        </p>
    </div>

    @error('target')
        <div role="alert" class="alert-error">
            {{ $message }}
        </div>
    @enderror

    <form wire:submit="save" class="card flex flex-col gap-5">
        <div class="flex flex-col gap-1">
            <label for="name" class="form-label">Nome</label>
            <input id="name" type="text" wire:model="name" required autocomplete="off" class="form-control">
            @error('name') <span role="alert" class="form-error">{{ $message }}</span> @enderror
        </div>

        <div class="flex flex-col gap-1">
            <label for="email" class="form-label">E-mail</label>
            <input id="email" type="email" wire:model="email" required autocomplete="off" class="form-control">
            @error('email') <span role="alert" class="form-error">{{ $message }}</span> @enderror
        </div>

        <div class="flex flex-col gap-1">
            <label for="roleId" class="form-label">Perfil</label>
            <select id="roleId" wire:model.live="roleId" required class="form-control sm:max-w-xs">
                <option value="">Selecione o perfil</option>
                @foreach ($roles as $role)
                    <option value="{{ $role->id }}">{{ $role->name }}</option>
                @endforeach
            </select>
            @error('roleId') <span role="alert" class="form-error">{{ $message }}</span> @enderror
        </div>

        @if ($selectedRoleIsObra)
            <fieldset class="flex flex-col gap-2 rounded-lg border border-border p-3" data-obra-selector>
                <legend class="px-1 text-xs font-semibold tracking-wide text-text-muted uppercase">Obras</legend>
                <p class="text-xs text-text-muted">Selecione pelo menos uma obra para o perfil Obra.</p>
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                    @forelse ($obras as $obra)
                        <label wire:key="obra-{{ $obra->id }}" for="obra-{{ $obra->id }}" class="inline-flex items-center gap-2 rounded-md border border-border px-3 py-2 text-sm text-text">
                            <input id="obra-{{ $obra->id }}" type="checkbox" value="{{ $obra->id }}" wire:model="obraIds" class="h-4 w-4 rounded border-border accent-primary focus:ring-2 focus:ring-focus/40">
                            {{ $obra->name }}
                        </label>
                    @empty
                        <span class="text-sm text-text-muted">Nenhuma obra ativa cadastrada.</span>
                    @endforelse
                </div>
                @error('obraIds') <span role="alert" class="form-error">{{ $message }}</span> @enderror
            </fieldset>
        @endif

        <div class="flex items-center justify-end gap-3 border-t border-border pt-4">
            <a href="{{ route('gestao.usuarios.index') }}" class="btn-secondary">Cancelar</a>
            <button type="submit" wire:loading.attr="disabled" class="btn-primary">{{ $user ? 'Salvar alterações' : 'Criar usuário' }}</button>
        </div>
    </form>
</div>
