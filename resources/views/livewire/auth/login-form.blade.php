<div>
    <h2 class="section-title mb-4 text-lg">Entrar no sistema</h2>

    <form wire:submit="authenticate" class="flex flex-col gap-4">
        <div class="flex flex-col gap-1">
            <label for="email" class="form-label">E-mail</label>
            <input id="email" type="email" wire:model="email" autocomplete="username" required class="form-control">
            @error('email') <span role="alert" class="form-error">{{ $message }}</span> @enderror
        </div>

        <div class="flex flex-col gap-1">
            <label for="password" class="form-label">Senha</label>
            <input id="password" type="password" wire:model="password" autocomplete="current-password" required class="form-control">
            @error('password') <span role="alert" class="form-error">{{ $message }}</span> @enderror
            <a href="{{ route('password.request') }}" class="self-end text-sm font-medium text-primary hover:text-primary-hover">Esqueci minha senha</a>
        </div>

        <button type="submit" wire:loading.attr="disabled" class="btn-primary mt-2 w-full">
            Entrar
        </button>
    </form>
</div>
