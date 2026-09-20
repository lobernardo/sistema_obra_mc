<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * "Esqueci minha senha" page (`password.request`, CT-02). Issues a
 * `passwords.users` token only for an existing **active** account (RF-20)
 * and then shows one fixed confirmation whatever the broker answered —
 * unknown, inactive or throttled e-mails produce the same response, so the
 * form cannot be used to enumerate accounts (RF-21, RNF-04, RNF-05, Q-05).
 */
#[Layout('auth.login')]
class ForgotPassword extends Component
{
    public string $email = '';

    public bool $sent = false;

    /**
     * @return array<string, array<int, string>>
     */
    protected function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'email.required' => 'Informe o e-mail.',
            'email.email' => 'Informe um e-mail válido.',
        ];
    }

    public function sendResetLink(): void
    {
        $credentials = $this->validate();

        Password::broker('users')->sendResetLink([
            'email' => $credentials['email'],
            'is_active' => true,
        ]);

        $this->sent = true;
    }

    public function render()
    {
        return view('livewire.auth.forgot-password');
    }
}
