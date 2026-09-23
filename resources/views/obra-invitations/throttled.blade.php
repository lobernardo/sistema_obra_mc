@component('auth.login', ['title' => 'Muitas tentativas'])
    <div>
        <h2 class="section-title mb-1 text-lg">Muitas tentativas</h2>
        <p role="alert" class="mb-4 text-sm text-text-muted">{{ \App\Livewire\Auth\LoginForm::THROTTLED_MESSAGE }}</p>

        <p class="mt-6 text-center text-sm">
            <a href="{{ route('login') }}" class="font-medium text-primary hover:text-primary-hover">Ir para o login</a>
        </p>
    </div>
@endcomponent
