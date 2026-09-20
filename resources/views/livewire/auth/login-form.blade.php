<div>
    <h2 class="mb-4 text-lg font-semibold text-slate-800">Entrar no sistema</h2>

    <form wire:submit="authenticate" class="flex flex-col gap-4">
        <div class="flex flex-col gap-1">
            <label for="email" class="text-sm font-medium text-slate-700">E-mail</label>
            <input id="email" type="email" wire:model="email" autocomplete="username" required
                class="rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-sky-500 focus:ring-2 focus:ring-sky-200 focus:outline-none">
            @error('email') <span role="alert" class="text-sm text-red-600">{{ $message }}</span> @enderror
        </div>

        <div class="flex flex-col gap-1">
            <label for="password" class="text-sm font-medium text-slate-700">Senha</label>
            <input id="password" type="password" wire:model="password" autocomplete="current-password" required
                class="rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-sky-500 focus:ring-2 focus:ring-sky-200 focus:outline-none">
            @error('password') <span role="alert" class="text-sm text-red-600">{{ $message }}</span> @enderror
            <a href="{{ route('password.request') }}" class="self-end text-sm font-medium text-sky-700 hover:text-sky-800">Esqueci minha senha</a>
        </div>

        <button type="submit" wire:loading.attr="disabled"
            class="mt-2 inline-flex items-center justify-center rounded-md bg-sky-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-sky-700 focus:ring-2 focus:ring-sky-300 focus:outline-none disabled:opacity-60">
            Entrar
        </button>
    </form>
</div>
