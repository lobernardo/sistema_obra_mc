<div class="flex flex-col gap-5">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="page-title">Usuários</h1>
            <p class="text-sm text-text-muted">Cadastro, perfis, obras e status de acesso dos usuários.</p>
        </div>
        <a href="{{ route('gestao.usuarios.create') }}" class="btn-primary">Novo usuário</a>
    </div>

    @if (session('status'))
        <div role="status" class="alert-success">
            {{ session('status') }}
        </div>
    @endif

    @if ($feedback)
        <div role="status" class="alert-success">
            {{ $feedback }}
        </div>
    @endif

    @error('target')
        <div role="alert" class="alert-error">
            {{ $message }}
        </div>
    @enderror

    <form wire:submit.prevent class="card flex flex-col gap-1">
        <label for="search" class="form-label">Busca</label>
        <input id="search" type="search" wire:model.live.debounce.300ms="search" placeholder="Nome ou e-mail" class="form-control md:max-w-md">
    </form>

    <div wire:loading.class="opacity-60" class="overflow-x-auto rounded-lg border border-border bg-surface shadow-sm transition-opacity">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>E-mail</th>
                    <th>Perfil</th>
                    <th>Status</th>
                    <th>Obras</th>
                    {{-- `relative` keeps the absolutely-positioned sr-only label inside the scrollable wrapper (no document overflow on mobile, UI-21). --}}
                    <th class="relative"><span class="sr-only">Ações</span></th>
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
                                <span class="badge badge-success">Ativo</span>
                            @else
                                <span class="badge badge-neutral">Inativo</span>
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
                            {{-- Compact two-line action group (§46 alinhamento/spacing): short actions side by side, the long access-link action below. --}}
                            <div class="flex flex-col items-end gap-2">
                                <div class="flex gap-2">
                                    <a href="{{ route('gestao.usuarios.edit', $user) }}" class="btn-secondary px-3 py-1.5 whitespace-nowrap">Editar</a>
                                    @if ($user->is_active)
                                        <button
                                            type="button"
                                            wire:click="setActive({{ $user->id }}, false)"
                                            wire:loading.attr="disabled"
                                            class="btn-secondary px-3 py-1.5 whitespace-nowrap"
                                        >Desativar</button>
                                    @else
                                        <button
                                            type="button"
                                            wire:click="setActive({{ $user->id }}, true)"
                                            wire:loading.attr="disabled"
                                            class="btn-secondary px-3 py-1.5 whitespace-nowrap"
                                        >Ativar</button>
                                    @endif
                                </div>
                                <button
                                    type="button"
                                    wire:click="sendAccessLink({{ $user->id }})"
                                    wire:loading.attr="disabled"
                                    class="btn-secondary px-3 py-1.5 whitespace-nowrap"
                                    title="Envia ao e-mail do usuário um link para definir ou redefinir a senha"
                                >Reenviar convite / Enviar link de redefinição</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="p-3"><div class="empty-state">Nenhum usuário encontrado.</div></td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $users->links() }}
</div>
