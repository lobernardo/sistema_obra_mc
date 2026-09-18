# Clarifier answers — reimplementacao-v0-laravel-livewire

Developer-confirmed resolutions for the clarifier's analyze-mode report. Apply in-place to SPEC.md and increment version.

## Q-01 (new gap, ref RF-10/RF-11b) — creation-time obra authorization
**Decision: Add explicit RF/AC.**
Add a RIGID requirement (e.g. RF-11c, or extend RF-10's AC) requiring the backend to reject pedido creation when the submitted `obra_id` is not present in the requester's `obra_profile` associations — mirroring current app-code + RLS `pedidos_insert` behavior. This must be backend-enforced, not just excluded from the obra picker UI.

## Q-02 (new gap, ref RF-12/UI-02/RF-20) — listing screen filter set
**Decision: Add explicit AC.**
Add an explicit AC to RF-12/UI-02 (and RF-20 for Gestão's listing) enumerating the "Todos os Pedidos" listing-specific filters as RIGID, distinct from RF-21/UI-06's dashboard filter set:
- free-text search (busca por identificador/obra/item)
- "Atraso" boolean filter
- two independent date ranges: `neededAtFrom/To` (Data necessária) and `requestedFrom/To` (Solicitado a partir de/até)

## Q-03 (pre-existing marker, SPEC line 286) — error/validation message preservation
**Decision: Equivalent preservation, not verbatim.**
User-facing error/validation messages must preserve meaning and trigger condition. Exact wording may be reworded during the idiomatic Laravel reconstruction (per brief §28/§41). Do not require verbatim string matches in tests or UI copy.

## Q-04 (pre-existing marker, SPEC line 287) — responsável selector role restriction
**Decision: Restrict to suprimentos role.**
The "responsável" selector must be backend-restricted to users with role `suprimentos`. This tightens the current implicit/unenforced behavior into an explicit RIGID authorization rule.

## Q-05 (new gap, ref RF-19/RF-21) — pendente + vencendo_em_breve threshold
**Decision: Both RIGID.**
- Add `pendente` as its own RIGID RF alongside RF-19: true unless status is `entregue` or `cancelado`.
- Freeze the 3-day "vencendo_em_breve" window as a RIGID literal (`VENCENDO_EM_BREVE_DIAS=3`) that must be preserved exactly, not treated as a tunable implementation detail.
