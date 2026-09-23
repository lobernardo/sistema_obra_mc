<?php

namespace App\Livewire\Auth;

use App\Actions\Obras\AcceptObraInvitationAction;
use App\Enums\RoleSlug;
use App\Exceptions\ObraInvitations\ObraInvitationUnavailableException;
use App\Models\ObraInvitation;
use App\Services\AuthenticationEventRecorder;
use App\Services\AuthenticationRateLimiter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Convite de obra page (CT-05, UI-07, RF-38 — FINAL).
 *
 * The route `/convite` has no parameter: the token lives only in the URL
 * fragment, which the inline script of the pending state reads, removes
 * from the address bar with `history.replaceState` and hands to `lookup()`
 * in the `/livewire/update` POST body. After the lookup only the convite id
 * and the obra name are kept, both `#[Locked]`; the token is never assigned
 * to a property, the session, a log or an exception. There is deliberately
 * no `#[Url]` property.
 *
 * The RF-30 return from login is keyed by the integer id stored under
 * `RETURN_SESSION_KEY`, never by the token. Every invalid outcome — the six
 * RF-28 causes, an empty fragment or a convite lost to a concurrent
 * consumption — redirects to the fixed token-free 404 page.
 */
#[Layout('auth.login')]
#[Title('Convite')]
class ObraInvitationPage extends Component
{
    public const RETURN_SESSION_KEY = 'obra_invitation.return_id';

    public const ACCEPTED_NOTICE = 'Obra associada à sua conta.';

    public const ALREADY_ASSOCIATED_NOTICE = 'Você já estava associado a esta obra.';

    #[Locked]
    public ?int $invitationId = null;

    #[Locked]
    public ?string $obraName = null;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    #[Locked]
    public ?string $notice = null;

    /**
     * Only the RF-30 return resumes here; every other visit starts in the
     * pending state and waits for the fragment lookup.
     */
    public function mount(AcceptObraInvitationAction $action): void
    {
        if (! Auth::check()) {
            return;
        }

        $returnId = session()->pull(self::RETURN_SESSION_KEY);

        if (! is_int($returnId)) {
            return;
        }

        try {
            $this->hold($action->resolveById($returnId));
        } catch (ObraInvitationUnavailableException) {
            $this->redirectRoute('obra-invitation.unavailable');
        }
    }

    /**
     * Receives the fragment token (RF-38). The `invite-ip` limiter is
     * checked and hit before any hash or query (RF-19b).
     */
    public function lookup(#[\SensitiveParameter] mixed $token): void
    {
        if ($this->invitationId !== null) {
            return;
        }

        $limiter = app(AuthenticationRateLimiter::class);
        $ip = (string) request()->ip();

        if ($limiter->tooManyInviteLookups($ip)) {
            $this->redirectRoute('obra-invitation.throttled');

            return;
        }

        $limiter->hitInviteLookup($ip);

        try {
            $invitation = app(AcceptObraInvitationAction::class)->resolveByToken($token);
        } catch (ObraInvitationUnavailableException) {
            $this->redirectRoute('obra-invitation.unavailable');

            return;
        }

        $this->hold($invitation);
    }

    /**
     * New-account path (RF-29): guests only, sharing the Novo Cadastro
     * limiters (RF-19). On success the new user is authenticated (RF-21).
     */
    public function register(AcceptObraInvitationAction $action, AuthenticationRateLimiter $limiter, AuthenticationEventRecorder $recorder): void
    {
        abort_unless(Auth::guest(), 403);

        if ($this->invitationId === null) {
            $this->redirectRoute('obra-invitation.unavailable');

            return;
        }

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
            $user = $action->acceptAsNewAccount($this->invitationId, [
                'name' => $this->name,
                'email' => $this->email,
                'password' => $this->password,
                'password_confirmation' => $this->password_confirmation,
            ], $ip);
        } catch (ValidationException $exception) {
            $this->reset(['password', 'password_confirmation']);

            throw $exception;
        } catch (ObraInvitationUnavailableException) {
            $this->redirectRoute('obra-invitation.unavailable');

            return;
        }

        Auth::guard('web')->login($user);

        $recorder->loginSucceeded($user);

        Session::regenerate();

        $this->redirect(route('home'), navigate: true);
    }

    /**
     * "Já tenho conta" (RF-30): keeps only the integer convite id for the
     * post-login return — never the token (RF-38).
     */
    public function useExistingAccount(): void
    {
        if ($this->invitationId === null) {
            $this->redirectRoute('obra-invitation.unavailable');

            return;
        }

        session()->put(self::RETURN_SESSION_KEY, $this->invitationId);

        $this->redirectRoute('login');
    }

    /**
     * Existing-account confirmation (RF-30, RF-31).
     */
    public function confirm(AcceptObraInvitationAction $action): void
    {
        $user = Auth::user();

        abort_if($user === null, 403);

        if ($this->invitationId === null) {
            $this->redirectRoute('obra-invitation.unavailable');

            return;
        }

        try {
            $result = $action->acceptAsExistingAccount($user, $this->invitationId);
        } catch (ObraInvitationUnavailableException) {
            $this->redirectRoute('obra-invitation.unavailable');

            return;
        }

        $this->notice = $result['associated'] ? self::ACCEPTED_NOTICE : self::ALREADY_ASSOCIATED_NOTICE;
    }

    private function hold(ObraInvitation $invitation): void
    {
        $this->invitationId = (int) $invitation->getKey();
        $this->obraName = (string) $invitation->obra->name;
    }

    public function render()
    {
        $user = Auth::user();

        return view('livewire.auth.obra-invitation-page', [
            'viewer' => $user,
            'isObraViewer' => $user?->role?->slug === RoleSlug::Obra->value,
            'roleMismatchMessage' => AcceptObraInvitationAction::ROLE_MISMATCH_MESSAGE,
        ]);
    }
}
