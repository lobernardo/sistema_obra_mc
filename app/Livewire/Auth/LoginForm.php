<?php

namespace App\Livewire\Auth;

use App\Services\AuthenticationRateLimiter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('auth.login')]
class LoginForm extends Component
{
    /**
     * Shown when either login limiter has tripped (UI-02). Deliberately the
     * same wording for existing, inactive and unknown e-mails, with no
     * remaining-time hint, so the limiter cannot be used for enumeration.
     */
    public const THROTTLED_MESSAGE = 'Muitas tentativas. Aguarde alguns instantes e tente novamente.';

    public string $email = '';

    public string $password = '';

    /**
     * @return array<string, array<int, string>>
     */
    protected function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
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
            'password.required' => 'Informe a senha.',
        ];
    }

    /**
     * Both login limiters (RF-09) run before `Auth::attempt`; a refused
     * attempt raises the same `ValidationException` channel as bad
     * credentials (CT-04: 422 through `/livewire/update`, never 429). The
     * password is only ever handed to the guard — never to the limiter.
     */
    public function authenticate(AuthenticationRateLimiter $limiter): void
    {
        // RF-12: normalize before validation and before any limiter key is
        // built, independently of the `TrimStrings` HTTP middleware.
        $this->email = AuthenticationRateLimiter::normalizeEmail($this->email);

        $credentials = $this->validate();

        $ip = (string) request()->ip();

        if ($limiter->tooManyLoginAttempts($this->email, $ip)) {
            // RF-26: login_failed recorded here by T16
            throw ValidationException::withMessages([
                'email' => self::THROTTLED_MESSAGE,
            ]);
        }

        // A logically deactivated user (`is_active = false`) is refused with the
        // same generic message as bad credentials, so the flag is not leaked.
        if (! Auth::guard('web')->attempt([...$credentials, 'is_active' => true])) {
            $limiter->hitLogin($this->email, $ip);

            throw ValidationException::withMessages([
                'email' => 'E-mail ou senha inválidos.',
            ]);
        }

        // RF-10: a successful login resets both counters for this e-mail.
        $limiter->clearLogin($this->email, $ip);

        Session::regenerate();

        $this->redirect(route('home'), navigate: true);
    }

    public function render()
    {
        return view('livewire.auth.login-form');
    }
}
