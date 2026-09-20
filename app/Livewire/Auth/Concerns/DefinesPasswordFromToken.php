<?php

namespace App\Livewire\Auth\Concerns;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

/**
 * Shared token-based password definition for the reset (`passwords.users`)
 * and first-access (`passwords.invites`) pages (RF-16, RF-22, RNF-06). The
 * broker validates e-mail + token, the model cast hashes the new password
 * (RNF-02), the remember token is rotated and the used token is deleted by
 * the broker (RNF-01). Any non-success status collapses into one generic
 * field error so the page never reveals whether the e-mail exists (RF-17,
 * RF-23).
 */
trait DefinesPasswordFromToken
{
    public string $token = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->email = (string) request()->query('email', '');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', 'confirmed', PasswordRule::defaults()],
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
            'password.required' => 'Informe a nova senha.',
            'password.confirmed' => 'A confirmação não confere com a senha.',
            'password.min' => 'A senha deve ter pelo menos :min caracteres.',
        ];
    }

    /**
     * @throws ValidationException
     */
    protected function definePasswordThroughBroker(string $broker, string $failureMessage): void
    {
        $validated = $this->validate();

        $status = Password::broker($broker)->reset([
            'email' => $validated['email'],
            'password' => $validated['password'],
            'password_confirmation' => $this->password_confirmation,
            'token' => $this->token,
        ], function (User $user, string $password): void {
            $user->forceFill([
                'password' => $password,
                'remember_token' => Str::random(60),
            ])->save();

            event(new PasswordReset($user));
        });

        $this->reset('password', 'password_confirmation');

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => $failureMessage]);
        }
    }
}
