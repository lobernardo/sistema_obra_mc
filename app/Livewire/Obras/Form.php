<?php

namespace App\Livewire\Obras;

use App\Actions\Obras\CreateObraAction;
use App\Actions\Obras\GenerateObraInvitationAction;
use App\Actions\Obras\RevokeObraInvitationAction;
use App\Actions\Obras\UpdateObraAction;
use App\Enums\ObraStatus;
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
 * `ObraStatus` options. Selecting Concluído shows that the obra stops
 * receiving new solicitações and that nothing is deleted. `save()`
 * re-authorizes through `ObraPolicy` and delegates to `CreateObraAction` /
 * `UpdateObraAction`. The Actions validate under the same keys as the bound
 * properties (`name`, `responsavel`, `status`), so their PT-BR errors land
 * on the matching inputs without re-keying.
 *
 * In edit mode the form also carries the "Convites" section (UI-05,
 * RF-26): the obra's convites with their derived state, "Gerar convite"
 * (hidden for a Concluído obra; `GenerateObraInvitationAction` still
 * refuses it) and a two-step "Revogar" on pending rows. The generated link
 * lives in `$generatedLink` only for the response that created it — every
 * other action clears it and a new GET never repopulates it (RF-23). The
 * token hash is never rendered (RF-38).
 */
#[Layout('layouts.app')]
class Form extends Component
{
    public ?Obra $obra = null;

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

    public function mount(?Obra $obra = null): void
    {
        $this->obra = $obra;

        if ($this->obra === null) {
            $this->authorize('create', Obra::class);

            return;
        }

        $this->authorize('update', $this->obra);

        $this->name = $this->obra->name;
        $this->responsavel = (string) $this->obra->responsavel;
        $this->status = $this->obra->status->value;
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

        if ($this->obra === null) {
            $this->authorize('create', Obra::class);

            $obra = $createObra->execute(Auth::user(), $data);

            session()->flash('status', "Obra {$obra->name} criada.");
        } else {
            $this->authorize('update', $this->obra);

            $obra = $updateObra->execute(Auth::user(), $this->obra, $data);

            session()->flash('status', "Obra {$obra->name} atualizada.");
        }

        $this->redirectRoute('obras.index');
    }

    public function generateInvitation(GenerateObraInvitationAction $action): void
    {
        $this->generatedLink = null;
        $this->confirmingRevokeId = null;
        $this->invitationFeedback = null;

        abort_if($this->obra === null, 404);

        $this->authorize('create', [ObraInvitation::class, $this->obra]);

        $this->generatedLink = $action->execute(Auth::user(), $this->obra)['url'];
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
        $this->generatedLink = null;
        $this->confirmingRevokeId = null;
        $this->invitationFeedback = null;

        abort_if($this->obra === null, 404);

        $invitation = $this->obra->invitations()->findOrFail($invitationId);

        $this->authorize('revoke', $invitation);

        $action->execute(Auth::user(), $invitation);

        $this->invitationFeedback = 'Convite revogado.';
    }

    /**
     * @return Collection<int, ObraInvitation>
     */
    public function invitations(): Collection
    {
        if ($this->obra === null) {
            return new Collection;
        }

        return $this->obra->invitations()
            ->with(['creator', 'revoker', 'user'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
    }

    public function isConcluidoSelected(): bool
    {
        return $this->status === ObraStatus::Concluido->value;
    }

    public function render()
    {
        return view('livewire.obras.form', [
            'statuses' => ObraStatus::cases(),
            'isConcluidoSelected' => $this->isConcluidoSelected(),
            'invitations' => $this->invitations(),
            'canGenerateInvitation' => $this->obra !== null && $this->obra->status->isActive(),
        ]);
    }
}
