<div>
    <form wire:submit="authenticate">
        <div>
            <label for="email">E-mail</label>
            <input id="email" type="email" wire:model="email" autocomplete="username" required>
            @error('email') <span role="alert">{{ $message }}</span> @enderror
        </div>

        <div>
            <label for="password">Senha</label>
            <input id="password" type="password" wire:model="password" autocomplete="current-password" required>
            @error('password') <span role="alert">{{ $message }}</span> @enderror
        </div>

        <button type="submit">Entrar</button>
    </form>
</div>
