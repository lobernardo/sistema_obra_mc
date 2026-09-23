<?php

namespace App\Livewire\Auth;

use App\Actions\Usuarios\RegisterObraUserAction;
use App\Services\AuthenticationEventRecorder;
use App\Services\AuthenticationRateLimiter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Public "Novo Cadastro" (UI-02, CT-04): always creates an `obra` account
 * with zero obras (RF-16). The four properties below are the only input the
 * screen accepts — there is deliberately no papel or obra property, so a
 * forged payload has nothing to bind to (RF-17).
 *
 * Follows the `LoginForm` pattern: the e-mail is normalized first, both
 * account-creation limiters are checked before anything else (RF-19) and a
 * refusal travels as a 422 on `email`. Every submission counts, including
 * duplicates and invalid ones (RF-20). On success the new user is
 * authenticated, the session id is regenerated and one `login_success` is
 * recorded (RF-21).
 */
#[Layout('auth.login')]
class Register extends Component
{
    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function register(RegisterObraUserAction $action, AuthenticationRateLimiter $limiter, AuthenticationEventRecorder $recorder): void
    {
        $this->email = AuthenticationRateLimiter::normalizeEmail($this->email);

        $ip = (string) request()->ip();

        if ($limiter->tooManyRegistrationAttempts($this->email, $ip)) {
            $this->reset(['password', 'password_confirmation']);

            throw ValidationException::withMessages([
                'email' => LoginForm::THROTTLED_MESSAGE,
            ]);
        }

        $limiter->hitRegistration($this->email, $ip);

        try {
            $user = $action->execute([
                'name' => $this->name,
                'email' => $this->email,
                'password' => $this->password,
                'password_confirmation' => $this->password_confirmation,
            ], $ip);
        } catch (ValidationException $exception) {
            $this->reset(['password', 'password_confirmation']);

            throw $exception;
        }

        Auth::guard('web')->login($user);

        $recorder->loginSucceeded($user);

        Session::regenerate();

        // Not a flash: the `/home` redirect would consume it before the
        // obra listing renders.
        session()->put('obra.registration_notice', true);

        $this->redirect(route('home'), navigate: true);
    }

    public function render()
    {
        return view('livewire.auth.register');
    }
}
