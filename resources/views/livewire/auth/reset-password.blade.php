<div>
    <h2 class="mb-1 text-lg font-semibold text-slate-800">Redefinir senha</h2>
    <p class="mb-4 text-sm text-slate-500">Escolha uma nova senha para a sua conta.</p>

    <form wire:submit="resetPassword" class="flex flex-col gap-4">
        <div class="flex flex-col gap-1">
            <label for="email" class="form-label">E-mail</label>
            <input id="email" type="email" wire:model="email" autocomplete="username" required class="form-control">
            @error('email') <span role="alert" class="form-error">{{ $message }}</span> @enderror
        </div>

        <div class="flex flex-col gap-1">
            <label for="password" class="form-label">Nova senha</label>
            <input id="password" type="password" wire:model="password" autocomplete="new-password" required class="form-control">
            @error('password') <span role="alert" class="form-error">{{ $message }}</span> @enderror
        </div>

        <div class="flex flex-col gap-1">
            <label for="password_confirmation" class="form-label">Confirmar nova senha</label>
            <input id="password_confirmation" type="password" wire:model="password_confirmation" autocomplete="new-password" required class="form-control">
        </div>

        <button type="submit" wire:loading.attr="disabled" class="btn-primary mt-2">
            Redefinir senha
        </button>
    </form>

    <p class="mt-6 text-center text-sm">
        <a href="{{ route('login') }}" class="font-medium text-sky-700 hover:text-sky-800">Voltar ao login</a>
    </p>
</div>
