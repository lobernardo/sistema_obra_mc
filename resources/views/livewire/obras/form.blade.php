<div class="mx-auto flex max-w-2xl flex-col gap-5">
    <div>
        <div class="flex flex-wrap items-center gap-2">
            <h1 class="page-title">{{ $obra ? 'Editar obra' : 'Nova obra' }}</h1>
            @if ($obra)
                <x-obra-inativa-badge :obra="$obra" />
            @endif
        </div>
        <p class="text-sm text-text-muted">Informe nome, responsável (opcional) e status da obra.</p>
    </div>

    @if ($activityFeedback)
        <div role="status" class="alert-success" data-testid="activity-feedback">{{ $activityFeedback }}</div>
    @endif

    <form wire:submit="save" class="card flex flex-col gap-5">
        <div class="flex flex-col gap-1">
            <label for="name" class="form-label">Nome</label>
            <input id="name" type="text" wire:model="name" required maxlength="255" autocomplete="off" class="form-control">
            @error('name') <span role="alert" class="form-error">{{ $message }}</span> @enderror
        </div>

        <div class="flex flex-col gap-1">
            <label for="responsavel" class="form-label">Responsável <span class="font-normal text-text-muted">(opcional)</span></label>
            <input id="responsavel" type="text" wire:model="responsavel" maxlength="255" autocomplete="off" class="form-control">
            @error('responsavel') <span role="alert" class="form-error">{{ $message }}</span> @enderror
        </div>

        <div class="flex flex-col gap-1">
            <label for="status" class="form-label">Status</label>
            <select id="status" wire:model.live="status" required class="form-control sm:max-w-xs">
                @foreach ($statuses as $statusOption)
                    <option value="{{ $statusOption->value }}">{{ $statusOption->label() }}</option>
                @endforeach
            </select>
            @error('status') <span role="alert" class="form-error">{{ $message }}</span> @enderror
        </div>

        <div class="flex items-center justify-end gap-3 border-t border-border pt-4">
            <a href="{{ route('obras.index') }}" class="btn-secondary">Cancelar</a>
            <button type="submit" wire:loading.attr="disabled" class="btn-primary">{{ $obra ? 'Salvar alterações' : 'Criar obra' }}</button>
        </div>
    </form>

    @if ($obra)
        <section aria-label="Situação da obra" class="card flex flex-col gap-4" data-obra-activity-section>
            <div>
                <h2 class="section-title">Situação da obra</h2>
                <p class="text-sm text-text-muted">
                    @if ($obra->isActive())
                        Obra ativa: recebe novas solicitações, convites e associações.
                    @else
                        Obra inativa: não recebe novas solicitações, convites nem associações. Pedidos e histórico continuam visíveis.
                    @endif
                </p>
            </div>

            @error('excluir')
                <div role="alert" data-testid="delete-blocked" class="alert-error flex flex-col gap-2">
                    <span>{{ $message }}</span>
                    @if ($obra->isActive())
                        <div>
                            <button type="button" wire:click="deactivate" wire:loading.attr="disabled" data-testid="delete-blocked-deactivate" class="btn-secondary px-3 py-1.5">Desativar obra</button>
                        </div>
                    @endif
                </div>
            @enderror

            @if ($confirmingDelete)
                <div role="alertdialog" aria-label="Confirmar exclusão" data-testid="delete-confirm-dialog" class="flex flex-col gap-3 rounded-md border border-border p-3">
                    <p class="text-sm font-medium text-error">Excluir definitivamente a obra «{{ $obra->name }}»? Esta ação não pode ser desfeita.</p>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" wire:click="deleteObra" wire:loading.attr="disabled" data-testid="delete-confirm" class="btn-danger px-3 py-1.5 whitespace-nowrap">Confirmar exclusão</button>
                        <button type="button" wire:click="cancelDelete" data-testid="delete-cancel" class="btn-secondary px-3 py-1.5">Cancelar</button>
                    </div>
                </div>
            @else
                <div class="flex flex-wrap gap-2">
                    @if ($obra->isActive())
                        <button type="button" wire:click="deactivate" wire:loading.attr="disabled" data-testid="deactivate-obra" class="btn-secondary">Desativar obra</button>
                    @else
                        <button type="button" wire:click="reactivate" wire:loading.attr="disabled" data-testid="reactivate-obra" class="btn-secondary">Reativar obra</button>
                    @endif
                    <button type="button" wire:click="confirmDelete" data-testid="delete-obra" class="btn-danger">Excluir obra</button>
                </div>
            @endif
        </section>

        <section aria-label="Convites" class="card flex flex-col gap-4" data-convites-section>
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="section-title">Convites</h2>
                    <p class="text-sm text-text-muted">Links de uso único para vincular uma conta de obra a esta obra.</p>
                </div>
                @if ($canGenerateInvitation)
                    <button type="button" wire:click="generateInvitation" wire:loading.attr="disabled" data-testid="generate-invitation" class="btn-primary">Gerar convite</button>
                @endif
            </div>

            @error('obra') <div role="alert" class="form-error">{{ $message }}</div> @enderror
            @error('invitation') <div role="alert" class="form-error">{{ $message }}</div> @enderror

            @if ($invitationFeedback)
                <div role="status" class="alert-success">{{ $invitationFeedback }}</div>
            @endif

            @if ($generatedLink)
                <div wire:key="generated-link" x-data="{ copied: false }" data-testid="generated-link" class="alert-info flex flex-col gap-2">
                    <label for="generated-link-input" class="form-label">Link do convite</label>
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <input id="generated-link-input" type="text" readonly value="{{ $generatedLink }}" x-ref="link" x-on:focus="$el.select()" class="form-control">
                        <button type="button" data-testid="copy-invitation-link"
                            x-on:click="navigator.clipboard.writeText($refs.link.value).then(() => copied = true)"
                            class="btn-secondary whitespace-nowrap">Copiar link</button>
                    </div>
                    <p class="text-sm">Este link vale por 24 horas e não será exibido novamente.</p>
                    <p x-show="copied" style="display: none" role="status" class="text-sm font-medium">Link copiado.</p>
                </div>
            @endif

            <div class="overflow-x-auto rounded-lg border border-border bg-surface">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Criado por</th>
                            <th>Criado em</th>
                            <th>Expira em</th>
                            <th>Estado</th>
                            <th>Revogado por/em</th>
                            <th>Utilizado por/em</th>
                            {{-- `relative` keeps the absolutely-positioned sr-only label inside the scrollable wrapper (UI-21). --}}
                            <th class="relative"><span class="sr-only">Ações</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($invitations as $invitation)
                            @php($invitationState = $invitation->state())
                            <tr wire:key="invitation-{{ $invitation->id }}" data-invitation-id="{{ $invitation->id }}">
                                <td>{{ $invitation->creator?->name ?? '—' }}</td>
                                <td>{{ \App\Support\LocalTime::formatDateTime($invitation->created_at) }}</td>
                                <td>{{ \App\Support\LocalTime::formatDateTime($invitation->expires_at) }}</td>
                                <td data-invitation-state>{{ $invitationState === \App\Enums\ObraInvitationState::Pendente && ! $obra->isActive() ? 'Pendente (obra inativa)' : $invitationState->label() }}</td>
                                <td>
                                    @if ($invitation->revoked_at)
                                        {{ $invitation->revoker?->name ?? '—' }} · {{ \App\Support\LocalTime::formatDateTime($invitation->revoked_at) }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>
                                    @if ($invitation->used_at)
                                        {{ $invitation->user?->name ?? '—' }} · {{ \App\Support\LocalTime::formatDateTime($invitation->used_at) }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>
                                    @if ($invitationState === \App\Enums\ObraInvitationState::Pendente)
                                        <div class="flex justify-end gap-2">
                                            @if ($confirmingRevokeId === $invitation->id)
                                                <div role="alertdialog" aria-label="Confirmar revogação" data-testid="revoke-confirm-dialog" class="flex flex-wrap items-center gap-2">
                                                    <span class="text-sm font-medium text-error">Revogar este convite?</span>
                                                    <button type="button" wire:click="revokeInvitation({{ $invitation->id }})" data-testid="revoke-confirm" class="btn-danger px-3 py-1.5 whitespace-nowrap">Confirmar revogação</button>
                                                    <button type="button" wire:click="abortRevoke" data-testid="revoke-abort" class="btn-secondary px-3 py-1.5">Voltar</button>
                                                </div>
                                            @else
                                                <button type="button" wire:click="confirmRevoke({{ $invitation->id }})" data-testid="revoke-invitation" class="btn-secondary px-3 py-1.5 whitespace-nowrap">Revogar</button>
                                            @endif
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="p-3"><div class="empty-state">Nenhum convite gerado para esta obra.</div></td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</div>
