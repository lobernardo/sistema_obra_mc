# Confirmed input — obras-ativacao-exclusao (tier: complete)

Summary: decouple obra activity from Status (new independent `obras.is_active`), add Desativar/Reativar and safe physical Excluir obra for Gestão and Suprimentos.

## Current state (investigated)
- "Active obra" today = status != concluido: `ObraStatus::isActive()` (app/Enums/ObraStatus.php:24) and `Obra::scopeActive()` (app/Models/Obra.php:30-35). Consumers: CreatePedidoAction.php:127,295; Livewire/Pedidos/NovaSolicitacao.php:140; GenerateObraInvitationAction.php:43; Livewire/Obras/Form.php:172,181 (+ data-concluido-notice in form.blade.php:31); Models/ObraInvitation.php:83,97 (consumable); Livewire/Gestao/Usuarios/Form.php:127; Suprimentos/TodosPedidos.php:214 and Gestao/TodosPedidos.php:207 ("Somente obras ativas" filter). Single-definition compliance test: tests/Feature/Compliance/ObraActivityDefinitionTest.php (also forbids any obra deletion, lines 219-265; RF-06 of .spec/features/obras-associacoes-cadastro-convites/SPEC.md).
- FKs to obras: pedidos.obra_id RESTRICT (nullable); obra_invitations.obra_id RESTRICT; obra_admin_events.obra_id RESTRICT (every obra has >=1 row: obra_created); obra_profile.obra_id CASCADE. Also obra_admin_events.obra_invitation_id RESTRICT and account_registration_events.obra_invitation_id RESTRICT -> obra_invitations.
- ObraPolicy::delete always false (app/Policies/ObraPolicy.php). All obra abilities via gate `manage-obras` (gestao or suprimentos).
- System is deployed but not yet in real use (go-live tomorrow). Demo data will be wiped separately with `php artisan demo:reset --force` by the developer; no demo-specific deletion rule is needed.

## Decisions (confirmed by developer, delegated to router recommendation)
- D1 Migration: add `obras.is_active boolean NOT NULL DEFAULT true`; backfill `is_active = (status <> 'concluido')` once, preserving current behavior. Afterwards Status and is_active are fully independent: changing Status never changes is_active and vice-versa. Concluído no longer blocks anything by itself. Remove the "Concluída não recebe pedidos" notice from the form.
- D2 Deletion is blocked when the obra has ANY pedido (demo or real). No cascade over pedidos/events/notifications/attachments.
- D3 Preserve obra audit: `obra_admin_events.obra_id` becomes nullable with nullOnDelete; the `obra_deleted` event stores name/status/responsavel/is_active snapshot in `before`. Existing events survive deletion with obra_id null.
- D4 Invitations: any USED invitation blocks deletion (account history). Pending/expired/revoked invitations are deleted with the obra; `obra_admin_events.obra_invitation_id` becomes nullOnDelete so their audit rows survive.
- D5 Associations (obra_profile) do not block; removed by cascade; one `obra_access_changed` user_admin_event per affected user.
- D6 Inactive obra: excluded from Nova Solicitação select and rejected 422 by CreatePedidoAction (including message per role); cannot generate invitations; its pending invitations cannot be consumed; excluded from association selects (Associações and Gestão user form, except already-associated) and AttachUserObrasAction rejects it 422. Existing associations, pedidos, history remain and stay viewable/filterable.
- D7 Blocked deletion attempts recorded as `obra_delete_blocked` in obra_admin_events with the blocking counts in `after`. New ObraAdminAction values: obra_deactivated, obra_reactivated, obra_deleted, obra_delete_blocked.

## Confirmed ACs
1. New column `obras.is_active`; single definition of active obra is `Obra::scopeActive()`/`Obra::isActive()` reading only is_active; `ObraStatus::isActive()` removed; compliance test updated accordingly.
2. Desativar/Reativar (Gestão, Suprimentos) via new Action(s) with `manage-obras` guard; never change status, pedidos, history, audit, associations; no-op when unchanged; each change writes one obra_admin_event.
3. Inactive obra: behaviors in D6.
4. Concluída + ativa obra accepts pedidos and invitations normally.
5. /obras list and form show a clear "INATIVA" badge; inactive obras remain listed and editable; pedidos/history of inactive obra stay viewable; "Somente obras ativas" listing filter uses is_active.
6. Excluir obra (Gestão, Suprimentos) with two-step explicit confirmation in UI; physical delete when allowed (D2-D5), inside one transaction, re-checking dependencies with lock.
7. Blocked deletion -> 422 with clear PT-BR reason (counts of pedidos / used invitations) and UI offers "Desativar obra"; attempt audited (D7).
8. Obra role -> 403 on all three actions (route, mount, Action). ObraPolicy gains activate/deactivate (or setActive) and delete via manage-obras.
9. No real pedido/event/notification/attachment is ever deleted by obra deletion; demo:reset keeps working.
10. RF-06 of obras-associacoes-cadastro-convites superseded; deletion compliance test allows only DeleteObraAction.
11. Docs: CLAUDE.md sections referencing status-as-activity need updating (developer-owned file; plan a task to update it).
