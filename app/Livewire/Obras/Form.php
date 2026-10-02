<?php

namespace App\Livewire\Obras;

use App\Actions\Obras\CreateObraAction;
use App\Actions\Obras\DeleteObraAction;
use App\Actions\Obras\GenerateObraInvitationAction;
use App\Actions\Obras\RevokeObraInvitationAction;
use App\Actions\Obras\SetObraActiveAction;
use App\Actions\Obras\UpdateObraAction;
use App\Enums\ObraStatus;
use App\Exceptions\Obras\ObraNotFoundException;
use App\Models\Obra;
use App\Models\ObraInvitation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Create/edit form of the Obras area (UI-04, RF-01, RF-02, CT-03): Nome,
 * Responsável (optional free text) and Status with exactly the 3
 * `ObraStatus` options. Status is purely descriptive: it never changes
 * whether the obra is active (`obras.is_active`, RF-02). `save()`
 * re-authorizes through `ObraPolicy` and delegates to `CreateObraAction` /
 * `UpdateObraAction`. The Actions validate under the same keys as the bound
 * properties (`name`, `responsavel`, `status`), so their PT-BR errors land
 * on the matching inputs without re-keying.
 *
 * The edited obra is held only as the locked `$obraId` and re-read on every
 * request (`obras-ativacao-exclusao` RNF-01, D-2): a bound model property
 * would be re-fetched with `firstOrFail()` and turn an obra deleted by a
 * concurrent request into a 404. Every action that finds the obra gone —
 * or receives `ObraNotFoundException` from an Action — flashes "A obra
 * informada não foi encontrada." and redirects to `obras.index`.
 *
 * In edit mode the form also carries:
 * - Desativar / Reativar (UI-02): `deactivate()` / `reactivate()` authorize
 *   `setActive` and call `SetObraActiveAction`, with the inline feedback
 *   "Obra desativada." / "Obra reativada.";
 * - the two-step Excluir (UI-03): `confirmDelete()` only opens the
 *   confirmation, `cancelDelete()` closes it, and only `deleteObra()`
 *   authorizes `delete` and calls `DeleteObraAction`, then redirects to
 *   `obras.index` with "Obra «<nome>» excluída.". A blocked deletion lands
 *   in the `excluir` error bag and is shown with a "Desativar obra" shortcut
 *   while the obra is active (UI-04);
 * - the "Convites" section (UI-05, RF-26): the obra's convites with their
 *   derived state ("Pendente (obra inativa)" for a pending convite of an
 *   inactive obra — presentation only), "Gerar convite" (hidden for an
 *   inactive obra; `GenerateObraInvitationAction` still refuses it) and a
 *   two-step "Revogar" on pending rows. The generated link lives in
 *   `$generatedLink` only for the response that created it — every other
 *   action clears it and a new GET never repopulates it (RF-23). The token
 *   hash is never rendered (RF-38).
 */
#[Layout('layouts.app')]
class Form extends Component
{
    #[Locked]
    public ?int $obraId = null;

    public string $name = '';

    public string $responsavel = '';

    public string $status = 'a_iniciar';

    /**
     * Full convite link (`<APP_URL>/convite#<token>`), shown exactly once
     * right after generation.
     */
    #[Locked]
    public ?string $generatedLink = null;

    #[Locked]
    public ?int $confirmingRevokeId = null;

    public ?string $invitationFeedback = null;

    #[Locked]
    public ?string $activityFeedback = null;

    #[Locked]
    public bool $confirmingDelete = false;

    /**
     * Per-request memo of the edited obra; never part of the snapshot.
     */
    private ?Obra $resolvedObra = null;

    private bool $obraResolved = false;

    public function mount(?Obra $obra = null): void
    {
        if ($obra === null) {
            $this->authorize('create', Obra::class);

            return;
        }

        $this->authorize('update', $obra);

        $this->obraId = $obra->id;
        $this->rememberObra($obra);

        $this->name = $obra->name;
        $this->responsavel = (string) $obra->responsavel;
        $this->status = $obra->status->value;
    }

    /**
     * Any property update counts as another action and drops the one-time
     * link.
     */
    public function updated(): void
    {
        $this->generatedLink = null;
    }

    public function save(CreateObraAction $createObra, UpdateObraAction $updateObra): void
    {
        $this->generatedLink = null;

        $data = [
            'name' => $this->name,
            'responsavel' => $this->responsavel,
            'status' => $this->status,
        ];

        if ($this->obraId === null) {
            $this->authorize('create', Obra::class);

            $obra = $createObra->execute(Auth::user(), $data);

            session()->flash('status', "Obra {$obra->name} criada.");
        } else {
            $current = $this->existingObraOrRedirect();

            if ($current === null) {
                return;
            }

            $this->authorize('update', $current);

            $obra = $updateObra->execute(Auth::user(), $current, $data);

            session()->flash('status', "Obra {$obra->name} atualizada.");
        }

        $this->redirectRoute('obras.index');
    }

    public function deactivate(SetObraActiveAction $action): void
    {
        $this->changeActivity($action, false);
    }

    public function reactivate(SetObraActiveAction $action): void
    {
        $this->changeActivity($action, true);
    }

    public function confirmDelete(): void
    {
        $this->resetTransientState();

        $obra = $this->existingObraOrRedirect();

        if ($obra === null) {
            return;
        }

        $this->authorize('delete', $obra);

        $this->confirmingDelete = true;
    }

    public function cancelDelete(): void
    {
        $this->resetTransientState();
    }

    public function deleteObra(DeleteObraAction $action): void
    {
        $this->resetTransientState();

        $obra = $this->existingObraOrRedirect();

        if ($obra === null) {
            return;
        }

        $this->authorize('delete', $obra);

        try {
            $action->execute(Auth::user(), $obra);
        } catch (ObraNotFoundException) {
            $this->redirectObraNotFound();

            return;
        } finally {
            $this->forgetObra();
        }

        session()->flash('status', "Obra «{$obra->name}» excluída.");

        $this->redirectRoute('obras.index');
        $this->skipRender();
    }

    public function generateInvitation(GenerateObraInvitationAction $action): void
    {
        $this->resetTransientState();

        $obra = $this->existingObraOrRedirect();

        if ($obra === null) {
            return;
        }

        $this->authorize('create', [ObraInvitation::class, $obra]);

        $this->generatedLink = $action->execute(Auth::user(), $obra)['url'];
    }

    public function confirmRevoke(int $invitationId): void
    {
        $this->generatedLink = null;
        $this->confirmingRevokeId = $invitationId;
    }

    public function abortRevoke(): void
    {
        $this->generatedLink = null;
        $this->confirmingRevokeId = null;
    }

    public function revokeInvitation(int $invitationId, RevokeObraInvitationAction $action): void
    {
        $this->resetTransientState();

        $obra = $this->existingObraOrRedirect();

        if ($obra === null) {
            return;
        }

        $invitation = $obra->invitations()->findOrFail($invitationId);

        $this->authorize('revoke', $invitation);

        $action->execute(Auth::user(), $invitation);

        $this->invitationFeedback = 'Convite revogado.';
    }

    /**
     * @return Collection<int, ObraInvitation>
     */
    public function invitations(): Collection
    {
        $obra = $this->obra();

        if ($obra === null) {
            return new Collection;
        }

        return $obra->invitations()
            ->with(['creator', 'revoker', 'user'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
    }

    public function render()
    {
        $obra = $this->obra();

        if ($this->obraId !== null && $obra === null) {
            $this->redirectObraNotFound(skipRender: false);
        }

        return view('livewire.obras.form', [
            'obra' => $obra,
            'statuses' => ObraStatus::cases(),
            'invitations' => $this->invitations(),
            'canGenerateInvitation' => $obra?->isActive() ?? false,
        ]);
    }

    private function changeActivity(SetObraActiveAction $action, bool $active): void
    {
        $this->resetTransientState();

        $obra = $this->existingObraOrRedirect();

        if ($obra === null) {
            return;
        }

        $this->authorize('setActive', $obra);

        try {
            $this->rememberObra($action->execute(Auth::user(), $obra, $active));
        } catch (ObraNotFoundException) {
            $this->forgetObra();
            $this->redirectObraNotFound();

            return;
        }

        $this->activityFeedback = $active ? 'Obra reativada.' : 'Obra desativada.';
    }

    /**
     * Clears every one-response UI state: the one-time convite link, the
     * open confirmations and the inline feedback messages.
     */
    private function resetTransientState(): void
    {
        $this->generatedLink = null;
        $this->confirmingRevokeId = null;
        $this->invitationFeedback = null;
        $this->activityFeedback = null;
        $this->confirmingDelete = false;
    }

    /**
     * The edited obra as it is now, or null when it was deleted (or the
     * form is in create mode). Memoized for the current request only.
     */
    private function obra(): ?Obra
    {
        if (! $this->obraResolved) {
            $this->rememberObra($this->obraId === null ? null : Obra::query()->find($this->obraId));
        }

        return $this->resolvedObra;
    }

    private function rememberObra(?Obra $obra): void
    {
        $this->resolvedObra = $obra;
        $this->obraResolved = true;
    }

    private function forgetObra(): void
    {
        $this->resolvedObra = null;
        $this->obraResolved = false;
    }

    /**
     * The edited obra, or null after scheduling the "não encontrada"
     * redirect (RNF-01, D-2). In create mode there is no obra to act on: 404.
     */
    private function existingObraOrRedirect(): ?Obra
    {
        abort_if($this->obraId === null, 404);

        $obra = $this->obra();

        if ($obra === null) {
            $this->redirectObraNotFound();
        }

        return $obra;
    }

    private function redirectObraNotFound(bool $skipRender = true): void
    {
        session()->flash('status', ObraNotFoundException::MESSAGE);

        $this->redirectRoute('obras.index');

        if ($skipRender) {
            $this->skipRender();
        }
    }
}
