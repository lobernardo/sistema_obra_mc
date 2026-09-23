<div class="flex flex-col gap-5">
    <div>
        <h1 class="page-title">Associações</h1>
        <p class="text-sm text-text-muted">Obras associadas aos usuários dos perfis Obra e Suprimentos.</p>
    </div>

    @if ($feedback)
        <div role="status" class="alert-success">
            {{ $feedback }}
        </div>
    @endif

    <form wire:submit.prevent class="card flex flex-col gap-1">
        <label for="search" class="form-label">Busca</label>
        <input id="search" type="search" wire:model.live.debounce.300ms="search" placeholder="Nome ou e-mail" class="form-control md:max-w-md">
    </form>

    <div wire:loading.class="opacity-60" class="flex flex-col gap-3 transition-opacity">
        @forelse ($users as $user)
            @php
                $associatedIds = $user->obras->pluck('id')->all();
                $availableObras = $allObras->reject(fn ($obra) => in_array($obra->id, $associatedIds, true));
            @endphp
            <section wire:key="association-user-{{ $user->id }}" data-user-email="{{ $user->email }}" class="card flex flex-col gap-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h2 class="font-semibold text-text">{{ $user->name }}</h2>
                        <p class="text-sm text-text-muted">{{ $user->email }}</p>
                    </div>
                    <div class="flex gap-2">
                        <span class="badge badge-info">{{ $user->role?->name ?? '—' }}</span>
                        @if ($user->is_active)
                            <span class="badge badge-success">Ativo</span>
                        @else
                            <span class="badge badge-neutral">Inativo</span>
                        @endif
                    </div>
                </div>

                @if ($user->id === $authenticatedUserId)
                    <div role="status" class="alert-info" data-self-association-notice>
                        Você está editando as suas próprias associações.
                    </div>
                @endif

                <ul class="flex flex-col gap-2" aria-label="Obras associadas a {{ $user->name }}">
                    @forelse ($user->obras->sortBy('name') as $obra)
                        <li wire:key="association-{{ $user->id }}-{{ $obra->id }}" data-associated-obra="{{ $obra->id }}" class="flex flex-wrap items-center justify-between gap-2 rounded-md border border-border px-3 py-2 text-sm">
                            <span>
                                <span class="font-medium text-text">{{ $obra->name }}</span>
                                <span class="text-text-muted">· {{ $obra->status->label() }}</span>
                            </span>
                            @if ($confirmingRemoval === [$user->id, $obra->id])
                                <div role="alertdialog" aria-label="Confirmar remoção" data-testid="removal-confirm-dialog" class="flex flex-wrap items-center gap-2">
                                    <span class="text-sm font-medium text-error">Remover esta associação?</span>
                                    <button type="button" wire:click="confirmRemoval" data-testid="removal-confirm" class="btn-danger px-3 py-1.5 whitespace-nowrap">Confirmar remoção</button>
                                    <button type="button" wire:click="cancelRemoval" class="btn-secondary px-3 py-1.5">Voltar</button>
                                </div>
                            @else
                                <button type="button" wire:click="askRemoval({{ $user->id }}, {{ $obra->id }})" data-testid="remove-association" class="btn-secondary px-3 py-1.5 whitespace-nowrap">Remover</button>
                            @endif
                        </li>
                    @empty
                        <li class="text-sm text-text-muted">Nenhuma obra associada.</li>
                    @endforelse
                </ul>

                <div class="flex flex-col gap-1">
                    <label for="obras-{{ $user->id }}" class="form-label">Adicionar obras</label>
                    <div class="flex flex-wrap items-start gap-2">
                        <select id="obras-{{ $user->id }}" multiple wire:model="selectedObraIds.{{ $user->id }}" class="form-control sm:max-w-md">
                            @foreach ($availableObras as $obra)
                                <option value="{{ $obra->id }}">{{ $obra->name }} · {{ $obra->status->label() }}</option>
                            @endforeach
                        </select>
                        <button type="button" wire:click="attach({{ $user->id }})" wire:loading.attr="disabled" class="btn-primary px-3 py-1.5 whitespace-nowrap">Adicionar</button>
                    </div>
                    @error("selectedObraIds.{$user->id}") <span role="alert" class="form-error">{{ $message }}</span> @enderror
                </div>
            </section>
        @empty
            <div class="empty-state">Nenhum usuário encontrado.</div>
        @endforelse
    </div>

    {{ $users->links() }}
</div>
