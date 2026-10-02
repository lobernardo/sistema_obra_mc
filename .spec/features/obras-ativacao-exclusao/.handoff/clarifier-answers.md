# Clarifier answers — obras-ativacao-exclusao

Developer delegated non-critical decisions to the router ("resolve o resto ai"). Answers:

Q-01 (RF-19/RF-20 marker): Option B. Add non-FK column `obra_admin_events.subject_obra_id bigint NOT NULL`, backfilled from `obra_id` in the migration and written on every insert (all ObraAdminAction values). Keep the `obra_id` FK as nullOnDelete. The `obra_deleted` snapshot in `before` also includes `id`. The audit trail of a deleted obra stays groupable by `subject_obra_id`.

Default-resolvable items — apply the recommended defaults:
1. Deadlock deletion vs invitation accept: DeleteObraAction locks the obra's invitations FOR UPDATE before locking the obra row; SQLSTATE 40P01 is translated to 422 exactly like 23503.
2. Concurrent second deletion: no 404 page — flash "A obra informada não foi encontrada." and redirect to /obras.
3. RNF-03: user_admin_events `obra_access_changed` rows for affected users are written by one batched insert (extend the recorder if needed) so the ≤15 statements budget holds at N=50.
4. Deactivation vs pedido creation race: accepted, no lock; document it.
5. INATIVA visibility: in scope, minimal — show the "INATIVA" badge (same component as /obras) next to inactive obras in /associacoes current-association lists, and suffix " (inativa)" on inactive obras in the listing/dashboard obra filter selects. No behavior change there.
