<?php

namespace App\Livewire\Auth;

use App\Models\User;
use App\Services\AuthenticationEventRecorder;
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
     * password is only ever handed to the guard — never to the limiter nor
     * to the recorder.
     *
     * Every outcome appends one authentication record (RF-26): a tripped
     * limiter and a refused `attempt` both write `login_failed` (D-08),
     * with `user_id` resolved by normalized e-mail — the guard's `Failed`
     * event is not used because it carries `user = null` for an inactive
     * account (`is_active` is part of the credentials).
     */
    public function authenticate(AuthenticationRateLimiter $limiter, AuthenticationEventRecorder $recorder): void
    {
        // RF-12: normalize before validation and before any limiter key is
        // built, independently of the `TrimStrings` HTTP middleware.
        $this->email = AuthenticationRateLimiter::normalizeEmail($this->email);

        $credentials = $this->validate();

        $ip = (string) request()->ip();

        if ($limiter->tooManyLoginAttempts($this->email, $ip)) {
            $recorder->loginFailed($this->email, $this->userMatchingEmail());

            throw ValidationException::withMessages([
                'email' => self::THROTTLED_MESSAGE,
            ]);
        }

        // A logically deactivated user (`is_active = false`) is refused with the
        // same generic message as bad credentials, so the flag is not leaked.
        if (! Auth::guard('web')->attempt([...$credentials, 'is_active' => true])) {
            $limiter->hitLogin($this->email, $ip);
            $recorder->loginFailed($this->email, $this->userMatchingEmail());

            throw ValidationException::withMessages([
                'email' => 'E-mail ou senha inválidos.',
            ]);
        }

        // RF-10: a successful login resets both counters for this e-mail.
        $limiter->clearLogin($this->email, $ip);

        $recorder->loginSucceeded(Auth::guard('web')->user());

        Session::regenerate();

        // RF-30: a pending convite return holds only the integer convite id
        // (never the token); the convite page pulls it on mount.
        if (is_int(session()->get(ObraInvitationPage::RETURN_SESSION_KEY))) {
            $this->redirect(route('obra-invitation.show'));

            return;
        }

        $this->redirect(route('home'), navigate: true);
    }

    /**
     * The account the normalized e-mail points to, regardless of
     * `is_active`, so a refused attempt is attributed to it (RF-26).
     */
    private function userMatchingEmail(): ?User
    {
        return User::query()->where('email', $this->email)->first();
    }

    public function render()
    {
        return view('livewire.auth.login-form');
    }
}
