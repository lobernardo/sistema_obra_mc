<div>
    <h2 class="section-title mb-1 text-lg">Novo Cadastro</h2>
    <p class="mb-4 text-sm text-text-muted">Crie sua conta de acesso. A associação às obras é feita pela Gestão ou por Suprimentos.</p>

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
            Criar conta
        </button>
    </form>

    <p class="mt-6 text-center text-sm">
        <a href="{{ route('login') }}" class="font-medium text-primary hover:text-primary-hover">Voltar para o login</a>
    </p>
</div>
