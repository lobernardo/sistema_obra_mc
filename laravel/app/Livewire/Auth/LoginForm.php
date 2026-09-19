<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('auth.login')]
class LoginForm extends Component
{
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

    public function authenticate(): void
    {
        $credentials = $this->validate();

        if (! Auth::guard('web')->attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        Session::regenerate();

        $this->redirect(route('home'), navigate: true);
    }

    public function render()
    {
        return view('livewire.auth.login-form');
    }
}
