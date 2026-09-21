<div>
    <h2 class="section-title mb-1 text-lg">Esqueci minha senha</h2>
    <p class="mb-4 text-sm text-text-muted">Informe o e-mail da sua conta para receber um link de redefinição.</p>

    @if ($sent)
        <p role="status" class="alert-info">
            Se o e-mail informado estiver cadastrado e ativo, você receberá um link para redefinir a senha em instantes.
        </p>
    @else
        <form wire:submit="sendResetLink" class="flex flex-col gap-4">
            <div class="flex flex-col gap-1">
                <label for="email" class="form-label">E-mail</label>
                <input id="email" type="email" wire:model="email" autocomplete="username" required class="form-control">
                @error('email') <span role="alert" class="form-error">{{ $message }}</span> @enderror
            </div>

            <button type="submit" wire:loading.attr="disabled" class="btn-primary mt-2 w-full">
                Enviar link de redefinição
            </button>
        </form>
    @endif

    <p class="mt-6 text-center text-sm">
        <a href="{{ route('login') }}" class="font-medium text-primary hover:text-primary-hover">Voltar ao login</a>
    </p>
</div>
