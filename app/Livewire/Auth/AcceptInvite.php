<?php

namespace App\Livewire\Auth;

use App\Livewire\Auth\Concerns\DefinesPasswordFromToken;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * First-access page reached from the invite e-mail (`invite.show`, CT-03).
 * Consumes a `passwords.invites` token (72 h) so the invited user defines
 * their own password — no default password ever exists (RF-16, RF-17,
 * RF-18, UI-24).
 */
#[Layout('auth.login')]
class AcceptInvite extends Component
{
    use DefinesPasswordFromToken;

    public function acceptInvite(): void
    {
        $this->definePasswordThroughBroker('invites', 'Este link é inválido ou expirou. Peça um novo convite à Gestão.');

        session()->flash('status', 'Senha definida. Entre com seu e-mail e a nova senha.');

        $this->redirectRoute('login');
    }

    public function render()
    {
        return view('livewire.auth.accept-invite');
    }
}
