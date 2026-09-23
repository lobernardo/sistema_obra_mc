<div>
    <h2 class="section-title mb-1 text-lg">Convite de obra</h2>

    @if ($invitationId === null)
        {{-- Pending: the fragment token is moved into the lookup POST body (RF-38). --}}
        <p data-invitation-state="pending" class="text-sm text-text-muted" role="status">Verificando convite…</p>

        @script
        <script>
            const token = window.location.hash.slice(1);
            history.replaceState(null, '', window.location.pathname);
            $wire.lookup(token);
        </script>
        @endscript
    @elseif ($viewer === null)
        <div data-invitation-state="guest">
            <p class="mb-4 text-sm text-text-muted">Obra: <strong class="text-text">{{ $obraName }}</strong></p>

            <form wire:submit="register" class="flex flex-col gap-4">
                <div class="flex flex-col gap-1">
                    <label for="name" class="form-label">Nome</label>
                    <input id="name" type="text" wire:model="name" autocomplete="name" required maxlength="255" class="form-control">
                    @error('name') <span role="alert" class="form-error">{{ $message }}</span> @enderror
                </div>

                <div class="flex flex-col gap-1">
                    <label for="email" class="form-label">E-mail</label>
                    <input id="email" type="email" wire:model="email" autocomplete="username" required maxlength="255" class="form-control">
                    @error('email') <span role="alert" class="form-error">{{ $message }}</span> @enderror
                </div>

                <div class="flex flex-col gap-1">
                    <label for="password" class="form-label">Senha</label>
                    <input id="password" type="password" wire:model="password" autocomplete="new-password" required class="form-control">
                    @error('password') <span role="alert" class="form-error">{{ $message }}</span> @enderror
                </div>

                <div class="flex flex-col gap-1">
                    <label for="password_confirmation" class="form-label">Confirmação de senha</label>
                    <input id="password_confirmation" type="password" wire:model="password_confirmation" autocomplete="new-password" required class="form-control">
                </div>

                <button type="submit" wire:loading.attr="disabled" class="btn-primary mt-2 w-full">
                    Criar conta e aceitar convite
                </button>
            </form>

            <button type="button" wire:click="useExistingAccount" class="btn-secondary mt-4 w-full">
                Já tenho conta
            </button>
        </div>
    @elseif ($isObraViewer)
        <div data-invitation-state="obra">
            <p class="mb-4 text-sm text-text-muted">Obra: <strong class="text-text">{{ $obraName }}</strong></p>

            @if ($notice !== null)
                <p role="status" class="mb-4 text-sm text-text">{{ $notice }}</p>

                <a href="{{ route('home') }}" class="btn-primary w-full">Ir para o início</a>
            @else
                @error('invitation') <p role="alert" class="form-error mb-4">{{ $message }}</p> @enderror

                <button type="button" wire:click="confirm" wire:loading.attr="disabled" class="btn-primary w-full">
                    Aceitar convite como {{ $viewer->name }}
                </button>

                <form method="POST" action="{{ route('logout') }}" class="mt-4">
                    @csrf
                    <button type="submit" class="btn-secondary w-full">Sair</button>
                </form>
            @endif
        </div>
    @else
        <div data-invitation-state="role-mismatch">
            <p role="alert" class="form-error mb-4">{{ $roleMismatchMessage }}</p>

            <a href="{{ route('home') }}" class="btn-secondary w-full">Ir para o início</a>
        </div>
    @endif
</div>
