@component('auth.login', ['title' => 'Convite indisponível'])
    <div>
        <h2 class="section-title mb-1 text-lg">Convite indisponível</h2>
        <p role="alert" class="mb-4 text-sm text-text-muted">{{ \App\Exceptions\ObraInvitations\ObraInvitationUnavailableException::MESSAGE }}</p>

        <p class="mt-6 text-center text-sm">
            <a href="{{ route('login') }}" class="font-medium text-primary hover:text-primary-hover">Ir para o login</a>
        </p>
    </div>
@endcomponent
