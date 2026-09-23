# Cross-slice review v2 (second pass): "Evolução do Sistema Albuquerque" (slices 1 → 2 → 3)

## Third pass (final) — 2026-09-23

**Verdict: READY** (planning artifacts). Execution still requires the pending execution gates D-2 (docs/agents commit), D-3 and D-4, which are steps, not planning defects.

Checks performed (read-only against the 9 slice artifacts and the 5 decision records):
1. Ownership: every master-plan row has an owner (section C, 228 rows, 0 MISSING, 0 PARTIAL).
2. Duplicates: only 10.3; S2 T16 (PHASES and PLAN) and S2 SPEC UI-02 declare the Suprimentos toolbar entry provisional, S3 T04 removes it. Not a real duplicate.
3. SPEC → PLAN → PHASES: S1 28 T / 9 P, S2 39 T / 10 P, S3 24 T / 9 P. In each slice, the checkbox count in PHASES equals the `### T` count in PLAN (28/39/24). PHASES contain only `## Phase N: ` level-2 headings. The Execution Phases tables match the phase membership in PHASES. Header counts and SPEC versions in PHASES (S1 v1.3, S2 v1.2, S3 v1.2) are correct.
4. Gates: G-1 docs commit (all 3), G-2 upstream names (the S3 "Upstream names consumed" table and the S3 PHASES G-2 match the S2 "Outbound contract surface" and the S1 T10/T16/T19 names exactly), G-3/G-4 are consistent. S3 G-3 requires T24 green before push.
5. Conflicts: none found. Migrations: S1 4, S2 3, S3 0. `manage-users` stays Gestão-only; `manage-obras` = G+S; `create-pedido` = O+S. Terminal = {Entregue, Cancelado, Finalizado}; Entregue → Finalizado is the only exit. America/Sao_Paulo day rule via `LocalTime`, and the DB stays UTC. `RequestedPeriodFilter` is created only in S2 T38 and extended in S3 T08. Attachments and history are append-only.
6. Decisions reflected: D-1 (S2 T21/T23/T24 + T34 allow-list), N-01 (S3 T20), N-02 (= D-1), N-03 (Regras S1/S2/S3), N-04 (S1 T19 via `GET route('obra.nova-solicitacao')`), N-05 (S1 T27/S3 T20 all Compliance + S3 T24), N-06 (S2 T10/T11, S3 T14/T17(e), S3 RF-22), F-01..F-17 (section A, unchanged), RF-38 token secrecy (S1 T11/T20/T21/T25/T26, not contradicted by S2/S3), D-5/D-6/D-8/D-9/D-10.
7. S3 T24 / Phase 9: the PLAN task, the PHASES checkbox, the Execution Phases row (9 — T24), G-2 (lists the S1 P9 and S2 P10 docs commits), G-3 (push only after T24 green), and the dependencies (T20 + developer validation) are all consistent.

**Edit log (direct documentary fixes, 4):**

| # | File:line | Before → After |
|---|---|---|
| 1 | `solicitacao-historico-finalizacao/SPEC.md:9` | upstream "`obras-associacoes-cadastro-convites/SPEC.md` v1.2" → "v1.3" |
| 2 | `navegacao-sidebar-listagens/SPEC.md:10` | slice 1 "`SPEC.md` v1.2" → "v1.3" |
| 3 | `navegacao-sidebar-listagens/SPEC.md:11` | slice 2 "`SPEC.md` v1.1" → "v1.2" |
| 4 | `navegacao-sidebar-listagens/SPEC.md:11` | "name fixed by the slice 2 PLAN, `RequestedPeriodFilter` or equivalent" → "name fixed by the slice 2 PLAN: `App\Domain\Pedidos\RequestedPeriodFilter`, created in slice 2 T38 (CT-10), extended here, never recreated" |

**BLOCKING-DECISION items: 0.**

**Final coverage:** 228 rows. 227 COVERED + 1 DUPLICATE (10.3, provisional and accepted) + 0 PARTIAL + 0 MISSING. That is 100 % owned.

Sections A and B below are the second-pass record and are kept for history. Sections C, D and E have been rebuilt with the final numbering.

---


Reviewer: adversarial cross-slice review, second pass. Read-only. Date: 2026-09-23.

**Inputs**
- Master plan: `solicitacao-historico-finalizacao/.handoff/master-plan.md` (§1–§49 and the cross-cutting decisions).
- First pass: `navegacao-sidebar-listagens/.handoff/cross-review.md` (F-01..F-17).
- Router fixes: `navegacao-sidebar-listagens/.handoff/cross-review-fixes.md`.
- Slice artifacts:
  - S1 `obras-associacoes-cadastro-convites`: SPEC v1.3, PLAN and PHASES, 28 T in 9 P.
  - S2 `solicitacao-historico-finalizacao`: SPEC v1.2, PLAN and PHASES, 39 T in 10 P.
  - S3 `navegacao-sidebar-listagens`: SPEC v1.2, PLAN and PHASES, 23 T in 8 P (second pass; final: 24 T in 9 P, see Third pass).
- Code spot-checks against the current tree (base `c987df8`).

**Notation.** `S2 P6/T21` means slice 2, phase 6, task T21. Phase numbering is the **current** numbering in each PHASES.md: (second-pass numbering below; the final numbering is in the Third pass and in section C.)

| Slice | Phase → tasks |
|---|---|
| S1 | P1 T01–T04 · P2 T05–T07 · P3 T08–T10 · P4 T11–T12 · P5 T13–T16 · P6 T17–T19 · P7 T20–T22 · P8 T23, T24, T25, T26, T28 · **P9 T27 (docs only)** |
| S2 | P1 T01, T02, T35, T03, T06 · P2 T04, T05, T12, T08, T11, T07, T09, T10, T36 · P3 T37, T38, T39 · P4 T13–T16 · P5 T17–T20 · P6 T21–T23 · P7 T24–T27 · P8 T28–T31 · P9 T32, T34 · **P10 T33 (docs only)** |
| S3 | P1 T01–T03 · P2 T04, T07, T05, T06 · P3 T08, T09 · P4 T10–T12 · P5 T13–T15 · P6 T16, T17, T18, T23, T19 · P7 T21, T22 (final regression) · **P8 T20 (docs only)** |

---

## A. Status of F-01..F-17

| ID | Status | Evidence |
|---|---|---|
| F-01 (timezone) | **VERIFIED-FIXED** | S2 PHASES "Regras" (local calendar through `LocalTime`, never `whereDate` on timestamps). S2 P3/T37 moves atraso, prazo and `entreguesHoje` to the São Paulo day (RF-45). S2 P3/T38 creates `RequestedPeriodFilter` and moves the Dashboard and both TodosPedidos onto it (RF-46, CT-10). The boundary case is added to `DashboardDrillDownTest`. S3 P3/T08 routes every Personalizado path through `applyLocalRange` (SPEC RF-18). S3 P4/T10–T12 have boundary ACs. S3 PLAN Open Questions records the decision as a non-blocking router decision, and S3 P7/T22 reminds the developer of it. |
| F-02 (renamed input keys) | **VERIFIED-FIXED** | S2 P4/T14 lists `PedidoDetalheObraTest`, `PedidoDetalheGestaoTest`, `ObraScreensRouteTest`, `BypassUiAuthorizationTest`, `CrossObraTest` and S1 `ZeroObraUserTest`. Its AC requires the forged-obra cases to fail on the association message, never on "Selecione a obra.". S2 P4/T15 covers CrossObraTest G-04. The T34 allow-list names every F-02 caller. Residual risk: see N-04. |
| F-03 (Suprimentos demo association) | **VERIFIED-FIXED** | S2 P8/T28: `DemoSeeder` runs `syncWithoutDetaching` for demo Suprimentos × active demo obras (RF-48). Its AC is that `/suprimentos/nova-solicitacao` shows the form. S2 P9/T32 adds runbook step (6) with the exact text. G-4 includes the association. S2 P8/T30 step (5) creates a pedido as Suprimentos. |
| F-04 (phase closes red) | **VERIFIED-FIXED** (for the Finalizado gap) | T04 and T05 moved into S2 P2 together with T10, T11 and T12. The T05 AC says Phase 2 closes fully green. The PHASES "Regras" now say "Toda fase fecha verde (F-04)". **But** S2 P6/P7 have a new red gap (N-02). |
| F-05 (S1 docs mixed with code) | **VERIFIED-FIXED** | S1 P9/T27 is its own docs-only phase after T28. The fallback when `/ai-context` cannot run headless is explicit: do not edit `docs/agents` by hand, leave the task unchecked, and hand the step to the developer. S2 P10/T33 and S3 P8/T20 mirror this. |
| F-06 ("Itens e quantidades") | **VERIFIED-FIXED** | S2 P6/T22 renames it to "Descrição". The AC requires "Itens e quantidades" to be absent on the 3 detail screens. |
| F-07 (two meanings of "Previsão") | **VERIFIED-FIXED** | S2 P2/T11: both Kanban cards show "Previsão de entrega". The AC requires that no field is labelled "Previsão" alone. |
| F-08 ("Preciso para") | **VERIFIED-FIXED** | S2 P4/T14 changes the messages to "Informe a data em Preciso para." and "Informe uma data válida em Preciso para.". S3 P5/T14 changes the legend to "Preciso para". Residual copy remains, see N-06. |
| F-09 (history follows obra rename) | **VERIFIED-FIXED** | S2 P4/T14 stores the `obraLabel()` snapshot in `new_value`. S2 P6/T21 reads the snapshot and falls back only for legacy events, with a rename AC. S2 P8/T28 also writes the snapshot for seeded events. |
| F-10 (provisional navigation) | **VERIFIED-FIXED** | S1 SPEC v1.3 UI-08 and S1 P8/T23 add a Blade comment marking the entries provisional until S3 RF-08. S2 P4/T16 is marked provisional. S3 P2/T04 removes both. |
| F-11 (stale upstream names) | **VERIFIED-FIXED** | S3 SPEC v1.2 no longer contains "no PLAN yet" or "not yet in code". The S3 PHASES G-2 list names the exact S1 and S2 symbols. |
| F-12 (S1 timestamps in UTC) | **VERIFIED-FIXED** | S2 P3/T39 moves the convite list and any `obra_admin_events` view to `LocalTime`. The S2 P8/T31 RF-47 scan covers the S1 views. Kanban and Dashboard only show `date` columns, which need no shift. |
| F-13 (Suprimentos self-association) | **VERIFIED-FIXED** | S1 SPEC v1.3 RF-11 and UI-06. S1 P5/T15 has a docblock and an AC for self-association. S1 P5/T16 shows a non-blocking notice `data-self-association-notice`. |
| F-14 (migration rollbacks) | **VERIFIED-FIXED** | (a) S1 P1/T01 docblock and T04 assertion document the data-losing `down()`. (b) S2 P2/T04 docblock says the rollback is conditionally destructive, and S2 P2/T36 asserts it. (c) S2 P1/T02 uses a frozen copy `frozenDataPrevista()` with no `App\*` import, and S2 P1/T06 checks parity over every day of 2026. |
| F-15 (second visual reference) | **ACCEPTED-AS-IS** | S3 SPEC UI-06 records that the image is absent, so UI-06 plus NC-05 is the contract. S3 PLAN Open Questions says an image attached later is a change request. The developer has not recorded acceptance yet (non-blocking decision D-6). |
| F-16 (§45 flow test) | **VERIFIED-FIXED** | S3 P6/T23 `MasterPlanFlowTest` walks register → empty state → forged create fails → association (Suprimentos and Gestão dataset) → login → create → Acompanhamento. S3 P7/T22 runs it. |
| F-17 (Suprimentos empty-state copy) | **VERIFIED-FIXED** | S2 P4/T14 adds `CreatePedidoAction::noActiveObraMessage(User)`, which keeps the obra text byte-identical and gives Suprimentos its own text. S2 P4/T15 renders it per papel. S3 P6/T23 step 2 consumes it. |

**Result:** 16 of 17 VERIFIED-FIXED. 1 ACCEPTED-AS-IS (F-15). None PARTIAL or NOT-FIXED.

---

## B. New findings (second pass)

| ID | Sev. | Cat. | Evidence | Where to fix | Suggested fix |
|---|---|---|---|---|---|
| N-01 | **MAJOR** | e (concept/doc drift), ownership gap | **Nobody updates `docs/onboarding-albuquerque.md`.** A grep for "onboarding" over the 9 slice artifacts returns 0 hits. After the increment, the guide contradicts the product in many places: obra needs ≥ 1 obra and Suprimentos cannot hold obras (`:220`); Suprimentos lands on Kanban/Visão Geral and Gestão on Dashboard (`:75`); the Kanban has "Cinco colunas" (`:143`); there are no anexos or comentários (`:17`); "+ Nova Solicitação no menu do topo" (`:111`); only Gestão creates users (`:83`). Worse, `tests/Feature/Compliance/DocumentationParityTest.php` (`onboardingKnownLimitsSection`) **requires** the text "Cadastro de obras só por via técnica", which becomes false at S1 P3. The suite then pins a false statement, and whoever fixes the guide must also rewrite a test that is on no allow-list. | S3 P8/T20 (single owner, end of increment), or each slice's docs phase for its own part | Add `docs/onboarding-albuquerque.md` to S3 T20's allowed paths (and to S1 T27 / S2 T33 if done per slice). Rewrite the `DocumentationParityTest` onboarding assertions in the same docs phase (replace "Cadastro de obras só por via técnica" with the new limits), and list that rewrite explicitly. Mirror the change in `tests/README.md`. |
| N-02 | **MAJOR** | k (phase cannot close green), j | **Two pre-existing assertions contradict S2 Phase 6/7 and are on no task list or allow-list.** (1) `tests/Feature/Livewire/PedidoDetalheObraTest.php:36-53` ("no edit form or mutation control is rendered") asserts `assertDontSee('<form')` and `assertDontSee('wire:click')` on the **Obra** detail. S2 P6/T23 adds the observação form there, and S2 P7/T24 adds "Marcar como entregue" (`wire:click`, two-step confirm). (2) `PedidoDetalheObraTest.php:34` and `PedidoDetalheGestaoTest.php:43` `assertSee('Criação do pedido')`. S2 P6/T21 changes the timeline action label of `criacao_pedido` to "Pedido criado", and today's timeline prints `eventType->name` (`resources/views/components/pedido-history-timeline.blade.php:19`). T21's own AC says "existing detail tests stay green", which is impossible. T21, T23 and T24 are not in the T34 allow-list (T05, T06, T08, T10–T16, T37, T28, T30). T14 is on the list, but only for the F-02 key migration. Result: P6 closes red (breaking the "Toda fase fecha verde" rule), or Ralph edits security-relevant intent without authorization. Side note: from P4 (T14 writes the snapshot) until P6 (T21), the old timeline shows "— → Residencial Aurora" under "Criação do pedido". This is cosmetic and transient. | S2 PLAN/PHASES T21, T23, T24 and the T34 allow-list | T21: list `PedidoDetalheObraTest:34` and `PedidoDetalheGestaoTest:43` and change them to "Pedido criado". T23: narrow `PedidoDetalheObraTest:36-53` to its original intent (US-2.2: no control that edits obra, Descrição or Preciso para, i.e. no field bound to those properties and no status/responsável/prioridade/previsão/cancel control). Keep an assertion that the only forms are observação and entregue. Keep `PedidoDetalheGestaoTest:61-62` unchanged, since Gestão gets no controls. Add T21, T23 and T24 (these hunks only) to the allow-list. The developer must approve the narrowed intent (D-1). |
| N-03 | MINOR | k | **The browser suite is knowingly red across several phase commits.** "Every phase closes green" is defined as Feature + Unit only. S2 says so explicitly. S1 and S3 do not say it, but their tasks behave that way. Two windows: (1) `tests/Browser/DemoRoteiroTest.php:59-62` uses `select('obra_id')` / `type('items_description')` and breaks at S2 P4/T15; it is fixed only in S2 P8/T30. (2) The sidebar and new home pages (S3 P2/T04, T07) break the navigation, landing and logout lines of 5 browser files until S3 P6/T19. S3 P2/T05 also accepts an open list of "testes Feature que falharem só pelo texto da sidebar (listados no log da fase)". | S2 PHASES/S3 PHASES "Regras" (wording), or move the edits earlier | Either state in the S1 and S3 "Regras" that per-phase green means Feature + Unit and that `tests/Browser` is only guaranteed at S1 P8/T28, S2 P9/T34 and S3 P7/T22, or move the `DemoRoteiroTest` selector update into S2 P4/T15 and the S3 T19 navigation edits into S3 P2. Recommended: the wording, since it is cheaper and T34/T22 already gate it. |
| N-04 | MINOR | j (latent break) | S1 P6/T19 `ZeroObraUserTest` must show "o estado vazio da Nova Solicitação", but the plan does not fix the mechanism. If it is written as `Livewire::test(\App\Livewire\Obra\NovaSolicitacao::class)`, S2 P4/T15 **deletes that class**. T15's file list lacks `ZeroObraUserTest`, and T14 may touch it only for "o caso da Action". | S1 PLAN T19 (preferred) or S2 PLAN T15 | S1 T19: assert the empty state via `GET route('obra.nova-solicitacao')`, since the obra text is kept byte-identical by S2. Or add `ZeroObraUserTest` (import/class only) to S2 T15's files and allow-list. |
| N-05 | MINOR | k (gate order) | **The docs phases re-run only part of the tests that read the files they edit, and the "final" regression is not on the final HEAD.** Tests that read `README.md`: `EnvExampleTest`, `NoCommittedSecretsTest.php:145` and `DocumentationParityTest` (CLAUDE.md, `docs/agents`, onboarding). S1 P9/T27 may edit `README.md` but re-runs only `DocumentationParityTest`. S3 P8/T20 edits `README.md` and re-runs `DocumentationParityTest` and `EnvExampleTest`, but not `NoCommittedSecretsTest`. S2 P10/T33 is fine, since its README change is in P9 before T34. S3 P7/T22, the "regressão completa", runs **before** the S3 docs commit and before the developer validates slice 3. The master order is "Ralph 3 → validar → regressão completa". | S1 T27, S3 T20 ACs; the E execution order | Docs phases run all of `tests/Feature/Compliance` (fast, and it covers every doc-reading test). After the S3 docs commit and the developer's validation, re-run Unit + Feature + Browser once on the final HEAD as the real master-plan "regressão completa" (gate E-9 below). |
| N-06 | MINOR | e (naming) | **"Preciso para" is not applied everywhere.** Residual names for `needed_at`: "Necessário em" on both Kanban cards (`kanban/pedido-card.blade.php:23`, `gestao/pedido-card-read-only.blade.php:19`), and the KPI captions "data necessária vencida e não entregues" (`gestao/dashboard.blade.php:154`, `suprimentos/visao-geral.blade.php:21`, `suprimentos/todos-pedidos.blade.php:29`). The lowercase caption on the Suprimentos listing survives the S3 P6/T17(e) scan because that scan is case-sensitive. The S3 SPEC RF-22 list still says "Data necessária De/Até" (`SPEC.md:234`), although PLAN/PHASES use "Preciso para". The captions also say "não entregues" while Finalizado exists. Not required by §17 (which is about "Itens"), but it is the same concept under three names. | S2 P2/T11 (cards) and S2 P2/T10 (Dashboard/Visão Geral), S3 P5/T14 (listing caption), S3 SPEC RF-22 (wording) | Relabel to "Preciso para" and "Preciso para vencido e não concluídos" (or equivalent). Make the T17(e) scan case-insensitive. Pure copy, no logic. |

**Checked, no new finding**
- **`RequestedPeriodFilter` single ownership.** It is created **only** in S2 P3/T38 (`php artisan make:class …`, "novo"). S3 P3/T08 marks the file "**alterado** … nunca recriar nem rodar `make:class`" and adds only methods. `COLUMN`, `utcBoundsForLocalRange` and `applyLocalRange` stay byte-identical, and the S2 test cases must pass unedited. S1 never mentions it. The guards are consistent:
  - S2 `RequestedPeriodSingleDefinitionTest`: `where('requested_at', >=|<)` appears only in this file. S3 adds `orderBy('requested_at')`, which is not a `where`.
  - S3 T17 scan: the only `*PeriodFilter.php`.
  - S3 T22: `git diff --name-status` gives `M`, never `A`.
  - S3 G-2 requires the S2 symbols and tests to exist.
- **Convite token secrecy (S1 RF-38/NC-08) is not contradicted.**
  - S2 adds no request logging. Its new controller (`pedidos.anexos.download`) is unrelated.
  - S2 P3/T39 edits `obras/form.blade.php` for views only ("nenhuma lógica, estado ou consulta"), so `$generatedLink` is untouched.
  - S3 keeps the auth layout out of the sidebar. S3 P1/T01 baselines route middleware; `obra-invitation.show` has no parameter.
  - S3 P6/T19 edits only the navigation lines of `ObraInvitationFlowTest`. S3 P7/T21 re-runs `/convite` without duplicating it.
  - `ObraInvitationTokenLeakTest` and `ObraInvitationTokenTransportTest` stay in force at S2 T34 and S3 T22.
- **docs/agents separate-commit gate** is present in the S1 PHASES header gate, S2 G-1 and S3 G-1. Each slice now ends with a docs-only phase whose diff is restricted and whose `/ai-context` headless fallback is explicit. Current tree: `git status --short docs/agents` still lists 8 modified files, so gate D is **not yet satisfied** and must be committed before Ralph 1.
- **Circular dependencies: none.** S1 depends on nothing later. S2 depends on S1 (G-2). S3 depends on S1 and S2 (G-2). Cross-slice edits are forward-only and named:
  - S2 T14 edits S1's `ZeroObraUserTest`;
  - S2 T39 edits S1's convites view;
  - S3 T08 extends S2's class;
  - S3 T17 removes S2's `x-pedido-table` exclusion;
  - S3 T19 edits the S1 and S2 browser tests.
- **Tasks depending on later tasks: none found.** Examples: S3 T19 comes after T18 in the same phase, S3 T21 comes after T18, S2 T30 (P8) uses T16 (P4), S2 T35 is in the same commit as T02, S2 T11 comes after T08, and S2 T38 comes after T37.
- **Migrations.**
  - S1 adds 4, all in `database/migrations` (T01 obras status, T05 invitations, T06 ×2 audit trails). S2 adds 3 (T02 pedidos, T03 attachments, T04 lookups). S3 adds 0 (asserted in T22).
  - Order: S2 T06 and T36 assert that they sort after S1.
  - No column is touched twice: S1 touches `obras`, S2 touches `pedidos`.
  - Lookup inserts happen only in S2 T04: idempotent, abort if sort 7 is taken, never update or delete in `up()`.
  - No sequence restart is added. The existing `pedido_code_sequence` migration is not re-run on a live DB.
  - `up()` drops only `obras.is_active`, after a lossless backfill into `status`. That is not a hidden cleanup (§43).
  - Existing migration tests call `up()`/`down()` on the instance (`EmailNormalizationMigrationTest.php:20-24`), so later-slice migrations do not interfere.
- **Obra status.** "Ativa" = `ObraStatus::isActive()` / `Obra::active()`, defined once (S1 T02, scanned by S1 T26). S2 T14/T15 and S3 T10/T11 consume it, and S3 T17 forbids `ObraStatus::`/`'concluido'` in the listings. Concluído blocks new pedidos and convites and still accepts associations. "Somente obras ativas" keeps "Outra".
- **Pedido status and transitions.**
  - Terminal = {Entregue, Cancelado, Finalizado}, defined once (S2 T04, with `terminalValues()` in the SQL scopes in T08).
  - Entregue → Finalizado is the only exit, plus romaneio and observação.
  - Finalizado is reachable only through `FinalizePedidoAction`: the Kanban drop gives 422 and `UpdatePedidoStatusAction` gives 422/409.
  - The Kanban has 6 columns. `entregues` excludes Finalizado; `porStatus` and Visão Geral include it.
  - Obra "Entregue" is only in the detail.
- **Authorization parity.**
  - `create-pedido` = obra + suprimentos (S2 T13). Both Nova Solicitação routes carry the papel gate plus `create-pedido`.
  - The S3 catalogue abilities equal each route's `can:` (T02 AC, T06 hidden-URL 403s).
  - `manage-obras` = gestao + suprimentos; `manage-users` stays Gestão-only in every slice.
  - Observação: obra with `view`, or suprimentos. Download: `view`, including the "Outra" requester (S2 T09 visibility).
- **Desktop/mobile.** Viewports 1440×900 / 820×1180 / 390×844 in all slices. The sidebar breakpoint is `lg` and the filter bar targets ≥ 1280, which is consistent. §41 items are covered by S1 T25, S2 T30 and S3 T18/T21.
- **Tests in the right slice.** The §45 flow (S3 T23) spans S1 and S2, so it belongs in the last slice. S2 T39 edits an S1 view by router decision F-12.

**New severity totals: BLOCKER 0 · MAJOR 2 (N-01, N-02) · MINOR 4 (N-03..N-06).**

---

## C. Traceability matrix (FINAL phase/task numbering, third pass)

Final numbering (PHASES.md = PLAN Execution Phases, verified third pass):

| Slice | Phase → tasks |
|---|---|
| S1 (28 T / 9 P) | P1 T01–T04 · P2 T05–T07 · P3 T08–T10 · P4 T11–T12 · P5 T13–T16 · P6 T17–T19 · P7 T20–T22 · P8 T23, T24, T25, T26, T28 · P9 T27 (docs only) |
| S2 (39 T / 10 P) | P1 T01, T02, T35, T03, T06 · P2 T04, T05, T12, T08, T11, T07, T09, T10, T36 · P3 T37, T38, T39 · P4 T13–T16 · P5 T17–T20 · P6 T21–T23 · P7 T24–T27 · P8 T28–T31 · P9 T32, T34 · P10 T33 (docs only) |
| S3 (24 T / 9 P) | P1 T01–T03 · P2 T04, T07, T05, T06 · P3 T08, T09 · P4 T10–T12 · P5 T13–T15 · P6 T16, T17, T18, T23, T19 · P7 T21, T22 (incremental regression) · P8 T20 (docs only) · P9 T24 (final-HEAD regression) |

Legend: COVERED / PARTIAL / MISSING / DUPLICATE. For an execution-level caveat on a COVERED row, see the N-id.

| # | Master requirement (atomic) | Slice | RF / UI / CT | Phase/Task | Status |
|---|---|---|---|---|---|
| 1.1 | Usuário pode ter múltiplas obras | S1 | RF-11 | S1 P5/T13, P5/T15 | COVERED |
| 1.2 | Zero, uma ou várias obras | S1 | RF-11, RF-14 | S1 P5/T13, P6/T19 | COVERED |
| 1.3 | Gestão e Suprimentos associam/desassociam | S1 | RF-09, RF-13, CT-06 | S1 P5/T15, P5/T16 | COVERED |
| 1.4 | Impedir associação duplicada | S1 | RF-10 | S1 P5/T15 | COVERED |
| 1.5 | Remover associação não apaga pedidos/histórico | S1 | RF-13 | S1 P5/T15 | COVERED |
| 1.6 | Obra só opera em obras com acesso (+ fluxo Outra) | S1, S2 | S1 RF-14, RF-15; S2 RF-03, RF-05, RF-40 | S1 P6/T19; S2 P2/T09, P4/T14 | COVERED |
| 1.7 | Suprimentos também pode ter múltiplas obras | S1 | RF-11, RF-13b | S1 P5/T13, P5/T14 | COVERED |
| 1.8 | Autorização validada no backend | S1 | RF-07, RF-10, RF-17 | S1 P3/T08, P7/T22 | COVERED |
| 2.1 | Área Obras para Gestão e Suprimentos | S1 | RF-07, CT-03 | S1 P3/T08, P3/T10 | COVERED |
| 2.2 | Cadastrar e editar obras | S1 | RF-01, RF-02, UI-03, UI-04 | S1 P3/T09, P3/T10 | COVERED |
| 2.3 | Campos Obra, Responsável, Status | S1 | CT-01 | S1 P1/T01, P1/T02 | COVERED |
| 2.4 | Status A iniciar / Em andamento / Concluído | S1 | CT-01, UI-04 | S1 P1/T02, P3/T10 | COVERED |
| 2.5 | Obra ativa = status ≠ Concluído | S1 | RF-03 | S1 P1/T02, P1/T03, P8/T26 | COVERED |
| 2.6 | Concluído deixa de aparecer para novas solicitações | S1, S2 | S1 RF-04; S2 RF-02 | S1 P1/T03; S2 P4/T15 | COVERED |
| 2.7 | Concluído continua existindo | S1 | RF-06 | S1 P3/T08, P8/T26 | COVERED |
| 2.8 | Pedidos antigos permanecem acessíveis | S1 | RF-05 | S1 P1/T03 | COVERED |
| 2.9 | Histórico permanece disponível | S1 | RF-05 | S1 P1/T03 | COVERED |
| 2.10 | Nenhuma informação histórica apagada | S1 | RF-02, RF-36 | S1 P1/T01, P1/T04, P3/T09 | COVERED |
| 3.1 | Interface G/S para administrar obras de cada usuário | S1 | UI-06, CT-03 | S1 P5/T16 | COVERED |
| 3.2 | Localizar usuário | S1 | RF-08 | S1 P5/T16 | COVERED |
| 3.3 | Visualizar obras associadas | S1 | RF-08 | S1 P5/T16 | COVERED |
| 3.4 | Adicionar uma associação | S1 | RF-09 | S1 P5/T15, P5/T16 | COVERED |
| 3.5 | Adicionar várias obras ao mesmo usuário | S1 | RF-09 | S1 P5/T15, P5/T16 | COVERED |
| 3.6 | Remover associação | S1 | RF-13, UI-06 | S1 P5/T15, P5/T16 | COVERED |
| 3.7 | Impedir duplicidade | S1 | RF-10 | S1 P5/T15 | COVERED |
| 3.8 | Mecanismo para vincular usuários do Novo Cadastro | S1 (+S3 flow) | RF-08; S3 RF-25 | S1 P5/T16; S3 P6/T23 | COVERED |
| 4.1 | Botão "Novo Cadastro" no login | S1 | UI-01 | S1 P6/T19 | COVERED |
| 4.2 | Cadastro público simplificado | S1 | CT-04 | S1 P6/T19 | COVERED |
| 4.3 | Campos Nome, E-mail, Senha, confirmação | S1 | RF-16, UI-02 | S1 P6/T18, P6/T19 | COVERED |
| 4.4 | Sem campo obra/nome/seleção/perfil/Gestão/Suprimentos | S1 | UI-02 | S1 P6/T19 | COVERED |
| 4.5 | Sempre perfil Obra | S1 | RF-16 | S1 P6/T18 | COVERED |
| 4.6 | Criado sem obra associada | S1 | RF-16 | S1 P6/T18 | COVERED |
| 4.7 | G/S associa depois | S1 | RF-08, RF-09 | S1 P5/T15, P5/T16 | COVERED |
| 4.8 | Backend impede cadastro como Gestão/Suprimentos | S1 | RF-17 | S1 P6/T18, P7/T22 | COVERED |
| 5.1 | Obra sem associação é estado válido | S1 | RF-11, RF-14, UI-09 | S1 P1/T03, P6/T19 | COVERED |
| 5.2 | Pode autenticar | S1 | RF-14 | S1 P6/T19 | COVERED |
| 5.3 | Não recebe acesso automático | S1 | RF-14 | S1 P6/T19 | COVERED |
| 5.4 | Não pode forjar ID de obra | S1, S2 | S1 RF-14; S2 RF-03, RF-07 | S1 P6/T19; S2 P4/T14; S3 P6/T23 | COVERED |
| 5.5 | Pode receber associações depois | S1 | RF-09 | S1 P5/T15 | COVERED |
| 5.6 | Pode receber associação por convite válido | S1 | RF-30 | S1 P7/T20, P7/T21 | COVERED |
| 6.1 | G/S geram convite a partir de uma obra | S1 | RF-23, UI-05 | S1 P4/T11, P4/T12 | COVERED |
| 6.2 | Convite vinculado a uma única obra | S1 | CT-02 | S1 P2/T05 | COVERED |
| 6.3 | Token seguro e não previsível | S1 | RNF-01 | S1 P4/T11 | COVERED |
| 6.4 | Validade 24 h | S1 | RF-23, RF-27 | S1 P2/T05, P4/T11 | COVERED |
| 6.5 | Uso único | S1 | RF-32 | S1 P7/T20, P7/T22 | COVERED |
| 6.6 | Revogável antes do uso | S1 | RF-25 | S1 P4/T11, P4/T12 | COVERED |
| 6.7 | Consumido só após sucesso | S1 | RF-29, RF-30, RNF-02 | S1 P7/T20 | COVERED |
| 6.8 | Três convidados → três links | S1 | RF-24 | S1 P4/T11 | COVERED |
| 7.1 | Sem conta: Nome, E-mail, Senha | S1 | RF-29, UI-07 | S1 P7/T21 | COVERED |
| 7.2 | Cria conta perfil Obra | S1 | RF-29 | S1 P7/T20 | COVERED |
| 7.3 | Associa à obra do convite | S1 | RF-29 | S1 P7/T20 | COVERED |
| 7.4 | Convite marcado como utilizado | S1 | RF-29 | S1 P7/T20 | COVERED |
| 7.5 | Não pode escolher/alterar a obra | S1 | RF-29, UI-07 | S1 P7/T20, P7/T22 | COVERED |
| 8.1 | Conta Obra existente: não duplicar | S1 | RF-20, RF-30 | S1 P7/T20, P7/T21 | COVERED |
| 8.2 | Exigir autenticação/confirmação | S1 | RF-30 | S1 P7/T21, P8/T25 | COVERED |
| 8.3 | Adicionar associação; impedir duplicada | S1 | RF-30 | S1 P7/T20 | COVERED |
| 8.4 | Consumir só após sucesso | S1 | RF-30, RF-32 | S1 P7/T20 | COVERED |
| 8.5 | Novas obras via convites diferentes | S1 | RF-30 | S1 P7/T20 | COVERED |
| 8.6 | Gestão/Suprimentos não convertida | S1 | RF-31 | S1 P7/T20, P7/T21 | COVERED |
| 9.1 | Válido/expirado/utilizado/revogado/inexistente | S1 | RF-27, RF-28 | S1 P2/T05, P7/T20, P7/T21 | COVERED |
| 9.2 | Reutilização bloqueada | S1 | RF-28, RF-32 | S1 P7/T20, P7/T22 | COVERED |
| 9.3 | Tentativas simultâneas | S1 | RF-32, RNF-02 | S1 P7/T22 | COVERED |
| 9.4 | Registrar obra, gerador, quando, validade, revogador, uso, usuário | S1 | CT-02, RF-34, CT-07 | S1 P2/T05, P2/T06, P4/T11, P7/T20 | COVERED |
| X.1 | (Decisão) Token fora de logs de aplicação e access logs | S1 (S2/S3 not contradicting) | RF-38, RF-19b, RNF-01, NC-08 | S1 P4/T11, P7/T20, P7/T21, P8/T25, P8/T26 | COVERED |
| 10.1 | Nova Solicitação para Obra e Suprimentos | S2 | RF-01, CT-05 | S2 P4/T13, P4/T15 | COVERED |
| 10.2 | Ação destacada "+ Nova Solicitação" | S3 | RF-07, UI-03 | S3 P1/T02, P2/T04 | COVERED |
| 10.3 | Fácil de acessar durante a navegação | S2, S3 | S2 UI-02 (provisional toolbar); S3 RF-07, RF-08 | S2 P4/T16; S3 P2/T04, P6/T18 | DUPLICATE (staged, declared provisional; benign) |
| 10.4 | Gestão não recebe automaticamente | S2, S3 | S2 RF-01; S3 RF-03 | S2 P4/T13; S3 P1/T02, P2/T06 | COVERED |
| 11.1 | Obra seleciona entre obras elegíveis | S2 | RF-02 | S2 P4/T15 | COVERED |
| 11.2 | Não apresentar obras concluídas | S2 | RF-02 | S2 P4/T15 | COVERED |
| 11.3 | Backend valida; ID forjado não autoriza | S2 | RF-03 | S2 P4/T14, P7/T27 | COVERED |
| 12.1 | Suprimentos cria solicitações | S2 | RF-01 | S2 P4/T13–T15 | COVERED |
| 12.2 | Suprimentos vê obras associadas não concluídas | S2 | RF-02 | S2 P4/T15 | COVERED |
| 12.3 | Mesma regra no backend | S2 | RF-03, RF-07 | S2 P4/T14 | COVERED |
| 13.1 | Opção "Outra" no seletor | S2 | RF-02, UI-01 | S2 P4/T15 | COVERED |
| 13.2 | Campo de texto livre opcional | S2 | RF-04, UI-01 | S2 P4/T14, P4/T15 | COVERED |
| 13.3 | Texto = referência do pedido | S2 | RF-04, CT-07 | S2 P1/T02, P2/T07 | COVERED |
| 13.4 | Não cria obra / não associa / não concede acesso | S2 | RF-05, RF-40 | S2 P2/T09, P4/T14 | COVERED |
| 13.5 | Sem texto → "Outra" | S2 | RF-04, CT-07 | S2 P2/T07, P4/T14 | COVERED |
| 14.1 | Data da solicitação (registro) | S2 | RF-09 | S2 P1/T35, P4/T14 | COVERED |
| 14.2 | Preciso para | S2 | RF-09, UI-01 | S2 P4/T14, P4/T15 | COVERED |
| 14.3 | Data prevista = +3 dias úteis | S2 | RF-10, CT-06 | S2 P1/T01, P1/T02, P1/T35 | COVERED |
| 14.4 | Estrutura para futuro "3 dias", sem implementar | S2 | RF-11 | S2 P2/T07, P8/T31 | COVERED |
| 15.1 | Upload de imagens e documentos | S2 | RF-14, UI-01 | S2 P5/T17, P5/T18 | COVERED |
| 15.2 | Anexos relacionados ao pedido | S2 | CT-03 | S2 P1/T03 | COVERED |
| 15.3 | Tipos, tamanho, arquivos inválidos | S2 | RF-14, RF-15 | S2 P5/T17, P5/T18 | COVERED |
| 15.4 | Armazenamento, nome seguro | S2 | RF-16, RF-20 | S2 P5/T17, P5/T20, P9/T32 | COVERED |
| 15.5 | Acesso, download, autorização | S2 | RF-17 | S2 P5/T19 | COVERED |
| 15.6 | URL direta não autorizada bloqueada | S2 | RF-18, RNF-09 | S2 P5/T19 | COVERED |
| 16.1 | Listagens mostram solicitante e obra/referência | S3 | RF-10, UI-05 | S3 P3/T09 | COVERED |
| 16.2 | Formato "João Silva / Residencial Aurora" | S3 | RF-10 | S3 P3/T09 | COVERED |
| 16.3 | Tabela desktop e cards mobile | S3 | RF-10, UI-05 | S3 P3/T09 | COVERED |
| 17.1 | Acompanhamento: "Itens" → "Descrição" | S3, S2 | S3 RF-11; S2 UI-01, UI-03 | S3 P3/T09; S2 P4/T15, P6/T22 | COVERED |
| 17.2 | Consistente desktop e mobile | S3 | RF-11 | S3 P3/T09 | COVERED |
| 18.1 | "Previsão" = Data prevista (3 dias úteis) | S3 | RF-12 (S2 CT-06) | S3 P3/T09 | COVERED |
| 19.1 | Histórico: ação/descrição/data-hora/autor | S2 | RF-21, UI-04 | S2 P6/T21 | COVERED (D-1 applied in T21) |
| 19.2 | Modelo "Pedido criado / Solicitação registrada para …" | S2 | RF-21 | S2 P4/T14 (snapshot), P6/T21 | COVERED |
| 19.3 | Eventos anteriores não destruídos | S2 | RF-22 | S2 P7/T27 | COVERED |
| 20.1 | Observação livre para Obra e Suprimentos | S2 | RF-24, UI-05 | S2 P6/T23 | COVERED (D-1 applied in T23) |
| 20.2 | Enviar cria novo evento | S2 | RF-24 | S2 P6/T23 | COVERED |
| 20.3 | Preserva conteúdo/autor/data/pedido; não substitui | S2 | RF-24, RF-22 | S2 P6/T23, P7/T27 | COVERED |
| 21.1 | Obra autorizado marca Entregue | S2 | RF-27, RF-29 | S2 P7/T24 | COVERED (D-1 applied in T24) |
| 21.2 | Só no detalhe, não na listagem | S2, S3 | S2 UI-06; S3 (no action) | S2 P7/T24 | COVERED |
| 21.3 | Autorização no backend | S2 | RF-28 | S2 P7/T24, P7/T27 | COVERED |
| 21.4 | Histórico com autor e data/hora | S2 | RF-27, RF-21 | S2 P7/T24, P6/T21 | COVERED |
| 22.1 | Home Suprimentos = Pedidos | S3 | RF-09, CT-01 | S3 P2/T07 | COVERED |
| 22.2 | Visão Geral continua disponível | S3 | RF-03, RF-09 | S3 P1/T02, P2/T07 | COVERED |
| 23.1 | Home Gestão = Pedidos | S3 | RF-09, CT-01 | S3 P2/T07 | COVERED |
| 23.2 | Demais funcionalidades pela navegação | S3 | RF-03 | S3 P1/T02, P2/T06 | COVERED |
| 24.1 | Suprimentos: mais antigo → mais novo | S3 | RF-14 | S3 P4/T10 | COVERED |
| 24.2 | Ordenação determinística | S3 | RF-14 | S3 P4/T10 | COVERED |
| 25.1 | "Solicitado": Hoje/3/7/Último mês/Personalizado | S3 | RF-15, CT-03 | S3 P3/T08, P5/T13 | COVERED |
| 25.2 | Preset aplica período automaticamente | S3 | RF-16 | S3 P3/T08, P4/T10–T12 | COVERED |
| 25.3 | De/Até só em Personalizado (dia local, classe única) | S3 (S2 class) | RF-15, RF-17, RF-18; S2 CT-10 | S2 P3/T38; S3 P3/T08, P5/T13–T15 | COVERED |
| 26.1 | Simplificar filtros em todas as listagens aplicáveis | S3 | RF-22, UI-06 (NC-04) | S3 P5/T13–T15 | COVERED |
| 26.2 | Desktop compacto horizontal, "segunda referência visual" | S3 | UI-06 (F-15 note) | S3 P5/T13, P6/T18 | COVERED (D-6 confirmed: UI-06/NC-05 is the contract) |
| 26.3 | [Obra][Status][Prioridade][Solicitado][Responsável][Limpar] | S3 | UI-06 | S3 P5/T14 | COVERED |
| 26.4 | Não remover funcionalidades | S3 | RF-22 | S3 P5/T14, P5/T15, P6/T17 | COVERED |
| 26.5 | Reduzir significativamente o espaço vertical | S3 | UI-06 (≤ 2 rows) | S3 P6/T18 | COVERED |
| 27.1 | Filtros mobile: reorganizar, sem overflow, legíveis | S3 | UI-07 | S3 P5/T13, P6/T18 | COVERED |
| 28.1 | "Somente obras ativas" em Todos os Pedidos G/S | S3 | RF-20, UI-08 | S3 P4/T10, P4/T11, P5/T14 | COVERED |
| 28.2 | Definição = obra ≠ Concluído | S3 | RF-20 (S1 RF-03) | S3 P4/T10, P4/T11, P6/T17 | COVERED |
| 28.3 | Não apaga/altera pedidos | S3 | RF-21 | S3 P4/T10 | COVERED |
| 28.4 | "Outra" tratado coerentemente | S3 | RF-20 (NC-02) | S3 P4/T10, P4/T11 | COVERED |
| 29.1 | Suprimentos no mesmo fluxo (obra, Outra, descrição, 3 datas, anexos) | S2 | RF-01..RF-14, RF-48, UI-01 | S2 P4/T13–T15, P5/T18, P8/T28, P8/T30 | COVERED |
| 30.1 | Suprimentos anexa romaneio | S2 | RF-30, UI-07 | S2 P7/T25 | COVERED |
| 30.2 | Identificação técnica, não pelo nome | S2 | RF-30, RF-31, CT-03 | S2 P1/T03, P7/T25 | COVERED |
| 30.3 | Aparece no pedido e no histórico | S2 | UI-03, RF-21 | S2 P6/T21, P6/T22, P7/T25 | COVERED |
| 31.1 | Status Finalizado usado por Suprimentos | S2 | RF-33, RF-34 | S2 P2/T04, P7/T26 | COVERED |
| 31.2 | Entregue ≠ Finalizado | S2 | RF-33, RF-37, UI-08 | S2 P2/T04, P2/T12, P2/T10 | COVERED |
| 32.1 | Sem romaneio não finaliza; verificação backend | S2 | RF-34, RF-35 | S2 P7/T26 | COVERED |
| 32.2 | Com romaneio: permite, Finalizado, histórico | S2 | RF-34 | S2 P7/T26 | COVERED |
| 33.1 | Sem romaneio: bloqueia, sem mudança, sem parcial | S2 | RF-35 | S2 P7/T26 | COVERED |
| 33.2 | Erro visual com a mensagem conceitual | S2 | RF-35, UI-07 | S2 P7/T26, P8/T30 | COVERED |
| 33.3 | Botão desabilitado só complemento | S2 | RF-35, UI-07 | S2 P7/T26, P7/T27 | COVERED |
| 34.1 | Evento "Romaneio anexado / <arquivo>" | S2 | RF-21, RF-30 | S2 P6/T21, P7/T25 | COVERED |
| 35.1 | Evento "Pedido finalizado / … por Suprimentos." | S2 | RF-21, RF-34 | S2 P6/T21, P7/T26 | COVERED |
| 35.2 | Preservar histórico anterior | S2 | RF-22, RF-34 | S2 P7/T26, P7/T27 | COVERED |
| 36.1 | Sidebar substitui toolbar | S3 | RF-01, RF-08 | S3 P2/T04, P2/T05 | COVERED |
| 36.2 | Desktop e mobile | S3 | UI-01, UI-02 | S3 P2/T04, P6/T18 | COVERED |
| 36.3 | Indica seção atual | S3 | RF-04 | S3 P2/T04, P2/T06 | COVERED |
| 36.4 | Respeita permissões / só autorizados | S3 | RF-02, RF-03 | S3 P1/T01, P1/T02, P2/T06 | COVERED |
| 36.5 | Áreas existentes, Obras quando autorizado, administrativas | S3 | RF-03, CT-02 | S3 P1/T02 | COVERED |
| 36.6 | Mantém logout | S3 | RF-05 | S3 P2/T04, P2/T06, P6/T18 | COVERED |
| 37.1 | "+ Nova Solicitação" destacado (Obra, Suprimentos) | S3 | RF-07, UI-03 | S3 P2/T04, P2/T06 | COVERED |
| 37.2 | Fácil de encontrar em qualquer página | S3 | RF-07 | S3 P6/T18 | COVERED |
| 38.1 | Sidebar Obra: NS + acompanhamento + autorizadas | S3 | RF-03, CT-02 | S3 P1/T02 | COVERED |
| 38.2 | Sem áreas administrativas indevidas | S3 | RF-03 | S3 P2/T06 | COVERED |
| 39.1 | Sidebar Suprimentos: NS, Pedidos, Visão Geral, Obras, Associações, demais | S3 | RF-03, CT-02 | S3 P1/T02 | COVERED |
| 39.2 | Landing Suprimentos = Pedidos | S3 | RF-09 | S3 P2/T07 | COVERED |
| 40.1 | Sidebar Gestão: Pedidos, Obras, Usuários, Associações, Dashboard/Kanban | S3 | RF-03, CT-02 | S3 P1/T02 | COVERED |
| 40.2 | Landing Gestão = Pedidos | S3 | RF-09 | S3 P2/T07 | COVERED |
| 41.1 | Responsivo: login | S1 (S3 re-run) | S1 RNF-03 | S1 P8/T25; S3 P7/T21, P9/T24 | COVERED |
| 41.2 | Responsivo: Novo Cadastro | S1 | RNF-03 | S1 P8/T25; S3 P7/T21 | COVERED |
| 41.3 | Responsivo: convite | S1 | RNF-03 | S1 P8/T25; S3 P7/T21 | COVERED |
| 41.4 | Responsivo: sidebar | S3 | UI-01, RNF-02 | S3 P6/T18, P7/T21 | COVERED |
| 41.5 | Responsivo: gerenciamento de obras | S1, S3 | S1 RNF-03; S3 RNF-02 | S1 P8/T25; S3 P7/T21 | COVERED |
| 41.6 | Responsivo: associação de usuários | S1, S3 | S1 RNF-03; S3 RNF-02 | S1 P8/T25; S3 P7/T21 | COVERED |
| 41.7 | Responsivo: Nova Solicitação | S2, S3 | S2 RNF-04; S3 RNF-02 | S2 P8/T30; S3 P7/T21 | COVERED |
| 41.8 | Responsivo: seleção de obra | S2, S3 | RNF-04; RNF-02 | S2 P8/T30; S3 P7/T21 | COVERED |
| 41.9 | Responsivo: Outra | S2, S3 | RNF-04; RNF-02 | S2 P8/T30; S3 P7/T21 | COVERED |
| 41.10 | Responsivo: upload | S2, S3 | RNF-04; RNF-02 | S2 P8/T30; S3 P7/T21 | COVERED |
| 41.11 | Responsivo: listagens | S3 | RNF-02 | S3 P6/T18, P7/T21 | COVERED |
| 41.12 | Responsivo: filtros | S3 | UI-06, UI-07 | S3 P6/T18, P7/T21 | COVERED |
| 41.13 | Responsivo: histórico | S2, S3 | RNF-04; RNF-02 | S2 P8/T30; S3 P7/T21 | COVERED |
| 41.14 | Responsivo: observações | S2, S3 | RNF-04; RNF-02 | S2 P8/T30; S3 P7/T21 | COVERED |
| 41.15 | Responsivo: Entregue | S2, S3 | RNF-04; RNF-02 | S2 P8/T30; S3 P7/T21 | COVERED |
| 41.16 | Responsivo: romaneio | S2, S3 | RNF-04; RNF-02 | S2 P8/T30; S3 P7/T21 | COVERED |
| 41.17 | Responsivo: Finalizado | S2, S3 | RNF-04; RNF-02 | S2 P8/T30; S3 P7/T21 | COVERED |
| 42.1 | Não destrói: alterar status da obra | S1 | RF-02 | S1 P3/T09 | COVERED |
| 42.2 | … concluir obra | S1 | RF-05 | S1 P1/T03, P3/T09 | COVERED |
| 42.3 | … associar usuário | S1 | RF-09, RF-12 | S1 P5/T15 | COVERED |
| 42.4 | … desassociar usuário | S1 | RF-13 | S1 P5/T15 | COVERED |
| 42.5 | … alterar status do pedido | S2 | RF-22, RF-44 | S2 P7/T27 | COVERED |
| 42.6 | … marcar Entregue | S2 | RF-27, RF-22 | S2 P7/T24 | COVERED |
| 42.7 | … adicionar observação | S2 | RF-24, RF-22 | S2 P6/T23 | COVERED |
| 42.8 | … anexar romaneio | S2 | RF-32 | S2 P7/T25 | COVERED |
| 42.9 | … finalizar pedido | S2 | RF-34 | S2 P7/T26 | COVERED |
| 42.10 | Histórico continua rastreável (snapshot de criação) | S2 | RF-21, CT-07 | S2 P4/T14, P6/T21, P8/T28 | COVERED |
| 43.1 | Sem exclusões silenciosas | S1, S2, S3 | S1 RF-36; S2 RF-42; S3 RNF-06 | S1 P1/T04; S2 P1/T06, P2/T36; S3 P7/T22 | COVERED |
| 43.2 | Sem limpeza destrutiva escondida em migration | S1, S2 | S1 RF-36, RNF-06; S2 RF-42, RNF-06 | S1 P1/T01; S2 P1/T02, P1/T03, P2/T04 | COVERED |
| 43.3 | Reset explícito e controlado | S1, S2 | S1 RF-35; S2 RF-43 | S1 P2/T07; S2 P8/T28 | COVERED |
| 43.4 | Preservar estrutura/configuração | S1, S2 | RF-36; RF-42 | S1 P1/T01; S2 P1/T02 | COVERED |
| 44.1 | Não regredir: autorização server-side | S1, S2, S3 | RF-37; RF-44; RF-24 | S1 P8/T28; S2 P9/T34; S3 P1/T01, P7/T22 | COVERED |
| 44.2 | … isolamento de obras | S1, S2, S3 | RF-15; RF-40; RF-23 | S1 P5/T15; S2 P2/T09; S3 P4/T10–T12, P6/T17 | COVERED |
| 44.3 | … usuário inativo bloqueado | S1, S2, S3 | RF-37; RF-44; RF-24 | S1 P8/T28; S2 P5/T19; S3 P7/T22 | COVERED |
| 44.4 | … restrição de novas solicitações em obras concluídas | S1, S2 | RF-04; RF-03 | S1 P1/T03; S2 P4/T14 | COVERED |
| 44.5 | … histórico | S2 | RF-22 | S2 P7/T27 | COVERED |
| 44.6 | … rate limiting | S1, S2, S3 | RF-19, RF-37; RF-44; RF-24 | S1 P6/T17; S2 P9/T34; S3 P7/T22 | COVERED |
| 44.7 | … AuthenticateSession | S1, S2, S3 | RF-37; RF-44; RF-24 | S1 P8/T28; S2 P9/T34; S3 P1/T01, P7/T22 | COVERED |
| 44.8 | … normalização de e-mail | S1 | RF-18, RF-37 | S1 P6/T18 | COVERED |
| 44.9 | … unicidade case-insensitive | S1 | RF-18, RF-37 | S1 P6/T18 | COVERED |
| 44.10 | … auditoria | S1, S2 | RF-12, RF-22, RF-34; RF-22 | S1 P2/T06, P5/T15; S2 P7/T27, P8/T28 | COVERED |
| 44.11 | … pedido_events | S2 | RF-22, RF-44 | S2 P7/T27, P8/T28 | COVERED |
| 44.12 | … user_admin_events | S1 | RF-12, RF-37 | S1 P5/T13, P5/T15 | COVERED |
| 44.13 | … authentication_events | S1 | RF-21, RF-30, RF-37 | S1 P6/T19, P7/T21 | COVERED |
| 44.14 | … policies/middlewares/scopes existentes | S1, S2, S3 | RF-37; RF-44; RF-02, RF-24 | S3 P1/T01 (baseline); S1 P8/T28; S2 P9/T34; S3 P7/T22 | COVERED |
| 45.1 | Login → Novo Cadastro | S1 | UI-01 | S1 P6/T19 | COVERED |
| 45.2 | Nome + E-mail + Senha → conta Obra, zero obras | S1 | RF-16 | S1 P6/T18, P6/T19 | COVERED |
| 45.3 | G/S localiza usuário | S1 | RF-08 | S1 P5/T16 | COVERED |
| 45.4 | Associa uma ou mais obras | S1 | RF-09 | S1 P5/T15, P5/T16 | COVERED |
| 45.5 | Usuário opera nas obras autorizadas | S1, S2 | S1 RF-14; S2 RF-02 | S2 P4/T15 | COVERED |
| 45.6 | Verificação ponta a ponta do §45 | S3 | RF-25 | S3 P6/T23, P7/T22 | COVERED |
| 46.1 | G/S → Obra → Gerar convite → link único 24 h | S1 | RF-23, RF-24 | S1 P4/T11, P4/T12 | COVERED |
| 46.2 | Acessa → dados → conta Obra → associação → consumido | S1 | RF-29, RF-38 | S1 P7/T20, P7/T21, P8/T25 | COVERED |
| 46.3 | Conta Obra existente: autenticação → nova obra → consumido | S1 | RF-30 | S1 P7/T21, P8/T25 | COVERED |
| 47.1 | Obra/Suprimentos → NS → obra associada ou Outra | S2 | RF-01, RF-02, RF-04 | S2 P4/T14, P4/T15 | COVERED |
| 47.2 | → Descrição → Preciso para → anexos opcionais → criado | S2 | RF-08, RF-09, RF-14 | S2 P4/T14, P5/T18 | COVERED |
| 47.3 | → Data da solicitação → Data prevista +3 dias úteis | S2 | RF-09, RF-10 | S2 P1/T35, P2/T07 | COVERED |
| 47.4 | → histórico registra criação → Acompanhamento | S2, S3 | S2 RF-21, UI-01; S3 RF-10..RF-12 | S2 P6/T21, P8/T30; S3 P3/T09 | COVERED |
| 48.1 | Suprimentos acompanha, mais antigos primeiro | S3 | RF-14 | S3 P4/T10 | COVERED |
| 48.2 | Obra/Suprimentos observações → histórico | S2 | RF-24 | S2 P6/T23 | COVERED |
| 48.3 | Romaneio → Finalizar → valida → Finalizado → histórico | S2 | RF-30, RF-34, RF-35 | S2 P7/T25, P7/T26, P8/T30 | COVERED |
| 48.4 | Obra autorizado marca Entregue no detalhe | S2 | RF-27 | S2 P7/T24 | COVERED |
| 49.1 | G/S administram obras e usuários | S1 | RF-01..RF-13 | S1 P3–P5 | COVERED |
| 49.2 | Novo Cadastro sem associação e convite associado | S1 | RF-16, RF-29 | S1 P6, P7 | COVERED |
| 49.3 | Um usuário em várias obras | S1 | RF-11, RF-30 | S1 P5, P7 | COVERED |
| 49.4 | Obra e Suprimentos criam com Outra, anexos, Preciso para, previsão | S2 | RF-01..RF-18, RF-48 | S2 P1–P5, P8/T28 | COVERED |
| 49.5 | Identificação do solicitante; histórico com observações e ações auditáveis | S2, S3 | S2 RF-21..RF-26; S3 RF-10 | S2 P6; S3 P3/T09 | COVERED |
| 49.6 | Suprimentos parte de Pedidos (antigos primeiro), romaneio e só então finaliza | S2, S3 | S3 RF-09, RF-14; S2 RF-34/35 | S3 P2/T07, P4/T10; S2 P7 | COVERED |
| 49.7 | Gestão inicia em Pedidos | S3 | RF-09 | S3 P2/T07 | COVERED |
| 49.8 | Sidebar responsiva; NS destacada; filtros compactos | S3 | RF-01..RF-08, UI-01..UI-07 | S3 P2, P5, P6 | COVERED |
| X.2 | (Decisão) Commit documental docs/agents separado e anterior ao Ralph | S1, S2, S3 | S1 pre-exec gate; S2 G-1; S3 G-1; docs-only phases | gate D; S1 P9/T27; S2 P10/T33; S3 P8/T20 | COVERED (plan side; gate D is an execution step still pending: 8 `docs/agents/*.md` modified in the tree) |
| X.3 | (Decisão) Ralph 1 → validar → Ralph 2 → validar → Ralph 3 → validar → regressão completa | S1, S2, S3 | S2 G-2; S3 G-2, G-3 | S3 P7/T22 (incremental), P8/T20 (docs), developer validation, P9/T24 (final HEAD) | COVERED (N-05 closed by S3 T24; S3 G-3 requires T24 green) |
| X.4 | (Decisão) Não duplicar responsabilidades | all | — | S2 T38 / S3 T08 single class; S3 T17, T22 | COVERED (only 10.3, declared provisional) |
| X.5 | (Decisão) Não reinterpretar requisitos decididos | all | clarifier answers S2/S3, plan-decisions S1 | — | COVERED |
| X.6 | (Decisão N-01) Guia de onboarding e `DocumentationParityTest` alinhados ao produto pós-incremento | S3 | RNF-05, RF-24 | S3 P8/T20 | COVERED |
| X.7 | (Decisão N-03/N-05) Verde por fase = Unit + Feature; Browser só nos gates; fases de docs rodam toda Compliance | S1, S2, S3 | "Regras" dos 3 PHASES | S1 P8/T28, P9/T27; S2 P9/T34, P10/T33; S3 P7/T22, P8/T20, P9/T24 | COVERED |

---

## D. Coverage summary (final)

| Status | Count |
|---|---|
| COVERED | 227 |
| PARTIAL | 0 |
| MISSING | 0 |
| DUPLICATE | 1 (10.3, declared provisional: S2 T16 toolbar entry, removed by S3 T04) |
| **Total rows** | **228** |

Every row has exactly one owning slice/task set; 0 MISSING, 0 PARTIAL. Coverage = 227/228 COVERED + 1 accepted provisional staging = **100 % owned**. Changes vs second pass: 26.2 PARTIAL → COVERED (D-6 confirmed), X.3 PARTIAL → COVERED (S3 T24), caveats on 19.1/20.1/21.1 removed (D-1 applied), rows X.6 (N-01) and X.7 (N-03/N-05) added.

### Decisions (status after the developer's cross-review-v2 decisions)

| ID | Status |
|---|---|
| D-1 (N-02) | APPROVED and applied: S2 T21 (`:34`, `:43` → "Pedido criado"), T23 (narrowed `:36-53`), T24 (click set), T34 allow-list; `PedidoDetalheGestaoTest:61-62` unchanged |
| D-2 (gate D) | Execution step, still pending: 8 `docs/agents/*.md` modified in the tree; commit before Ralph 1 |
| D-3 | Execution step: production read-only queries (S1 P1 merge; S2 G-3 P1/P2 merges) |
| D-4 | Execution step: S2 G-4 runbook before the first S2 push |
| D-5, D-6, D-8, D-9, D-10 | Confirmed defaults, reflected in S2 T01/T02/T14/T37/T38 and S3 T08/T13–T15, UI-06 |
| D-7 (N-01) | Resolved: S3 P8/T20 owns onboarding + `DocumentationParityTest` rewrite + `tests/README.md` |
| D-11 | Fallback kept in the 3 docs phases (developer step, docs-only commit) |
| D-12 (N-03) | Resolved: stated in the "Regras" of S1/S2/S3 |
| D-13 (N-06) | Resolved: S2 T10/T11, S3 T14/T17(e) case-insensitive, S3 SPEC RF-22 |

---

## E. Execution order with all gates (final)

1. **Gate D (D-2):** commit the 8 `docs/agents/*.md` alone (`git status --short docs/agents` empty; `git show --stat --format= HEAD -- . ':!docs/agents'` empty; `.spec/` out of that commit).
2. **Ralph 1: S1.** P1 (T01–T04; T02+T03 one commit; obra-name collision query before merge, D-3) → P2 (T05 → T06 → T07) → P3 (T08 → T09 → T10) → P4 (T11 → T12) → P5 (T13 → T14 ∥ T15 → T16) → P6 (T17 ∥ T18 → T19) → P7 (T20 → T21 → T22) → P8 (T23 ∥ T24 ∥ T26 → T25 → **T28 gate**: Pint, build, Unit + Feature + Browser, no dependency diff) → **P9 T27 docs only** (all of Compliance; `/ai-context` headless fallback = developer step).
3. **Validate S1** (developer).
4. **Ralph 2: S2.** Entry: G-1 (docs/agents clean), G-2 (8 S1 code commits + S1 docs commit, S1 names present, suite green, tree clean). P1 (T01 → T02+T35 one commit → T03 → T06; G-3 `requested_at` nulls before merge) → P2 (T04+T05 → T12 ∥ T08 ∥ T07 → T11 → T09 → T10 → T36; G-3 sort 7 free before merge) → P3 (T37 → T38 ∥ T39) → P4 (T13 → T14+T15 one commit → T16) → P5 (T17 → T18 → T19 → T20) → P6 (T21 → T22, T23) → P7 (T24 → T25 → T26 → T27) → P8 (T28 → T29 ∥ T31 → T30) → P9 (T32 → **T34 gate**, allow-list incl. D-1, `migrate:fresh --seed` twice) → **P10 T33 docs only**.
5. **Validate S2** (developer). **G-4 (D-4)** before any S2 push.
6. **Ralph 3: S3.** Entry: G-1, G-2 (all S1/S2 phases, the exact upstream names incl. `RequestedPeriodFilter` + its guard tests, no `whereDate('requested_at'`, suite green). P1 (T01 baseline first → T02 ∥ T03) → P2 (T04 ∥ T07 → T05, T04+T05 one commit → T06) → P3 (T08 extends S2 class, never recreates ∥ T09) → P4 (T10 ∥ T11 ∥ T12) → P5 (T13 → T14 ∥ T15) → P6 (T16 ∥ T17 ∥ T18 ∥ T23 → T19) → P7 (T21 → **T22 incremental regression** + manual checklist) → **P8 T20 docs only** (README, CLAUDE.md, onboarding, `DocumentationParityTest`, `tests/README.md`, `/ai-context`; all of Compliance).
7. **Validate the increment** (developer, T22 checklist, after the T20 commit).
8. **S3 P9/T24: full regression on the final HEAD** (clean tree, SHA recorded, Pint no-op, build, Unit → Feature → Browser one Pest process at a time on 5434, `migrate:fresh --seed` then "Nothing to migrate", empty dependency diff).
9. **S3 G-3 / S2 G-4:** push and deploy only after T24 green. Railpack runs `migrate` on every container start; S1 and S2 migrations run in production on the first start.
