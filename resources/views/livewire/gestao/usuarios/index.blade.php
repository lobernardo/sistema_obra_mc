<div class="flex flex-col gap-5">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="page-title">Usuários</h1>
            <p class="text-sm text-slate-500">Cadastro, perfis, obras e status de acesso dos usuários.</p>
        </div>
        <a href="{{ route('gestao.usuarios.create') }}" class="btn-primary">Novo usuário</a>
    </div>

    @if (session('status'))
        <div role="status" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ session('status') }}
        </div>
    @endif

    @if ($feedback)
        <div role="status" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ $feedback }}
        </div>
    @endif

    @error('target')
        <div role="alert" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ $message }}
        </div>
    @enderror

    <form wire:submit.prevent class="card flex flex-col gap-1">
        <label for="search" class="form-label">Busca</label>
        <input id="search" type="search" wire:model.live.debounce.300ms="search" placeholder="Nome ou e-mail" class="form-control md:max-w-md">
    </form>

    <div wire:loading.class="opacity-60" class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm transition-opacity">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>E-mail</th>
                    <th>Perfil</th>
                    <th>Status</th>
                    <th>Obras</th>
                    <th><span class="sr-only">Ações</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                    <tr wire:key="user-{{ $user->id }}" data-user-email="{{ $user->email }}">
                        <td class="font-medium">{{ $user->name }}</td>
                        <td>{{ $user->email }}</td>
                        <td>{{ $user->role?->name ?? '—' }}</td>
                        <td>
                            @if ($user->is_active)
                                <span class="badge bg-emerald-100 text-emerald-800">Ativo</span>
                            @else
                                <span class="badge bg-slate-200 text-slate-700">Inativo</span>
                            @endif
                        </td>
                        <td>
                            @if ($user->role?->slug === $obraRoleSlug)
                                {{ $user->obras->pluck('name')->sort()->implode(', ') ?: '—' }}
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            <div class="flex flex-wrap justify-end gap-2">
                                <a href="{{ route('gestao.usuarios.edit', $user) }}" class="btn-secondary px-3 py-1.5">Editar</a>
                                @if ($user->is_active)
                                    <button
                                        type="button"
                                        wire:click="setActive({{ $user->id }}, false)"
                                        wire:loading.attr="disabled"
                                        class="btn-secondary px-3 py-1.5"
                                    >Desativar</button>
                                @else
                                    <button
                                        type="button"
                                        wire:click="setActive({{ $user->id }}, true)"
                                        wire:loading.attr="disabled"
                                        class="btn-secondary px-3 py-1.5"
                                    >Ativar</button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="py-10 text-center text-slate-500">Nenhum usuário encontrado.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $users->links() }}
</div>
