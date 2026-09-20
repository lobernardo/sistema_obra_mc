<?php

namespace App\Livewire\Auth;

use App\Livewire\Auth\Concerns\DefinesPasswordFromToken;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Reset page reached from the recovery e-mail (`password.reset`, CT-02).
 * Consumes a `passwords.users` token (60 min) and sends the user back to
 * the login with a status flash (RF-22, RF-23, RF-24, UI-24).
 */
#[Layout('auth.login')]
class ResetPassword extends Component
{
    use DefinesPasswordFromToken;

    public function resetPassword(): void
    {
        $this->definePasswordThroughBroker('users', 'Este link é inválido ou expirou. Solicite um novo.');

        session()->flash('status', 'Senha redefinida. Entre com a nova senha.');

        $this->redirectRoute('login');
    }

    public function render()
    {
        return view('livewire.auth.reset-password');
    }
}
