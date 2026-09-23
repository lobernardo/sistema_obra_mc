# Cross-slice review — incremento "Evolução do Sistema Albuquerque" (fatias 1 → 2 → 3)

Reviewer: adversarial cross-slice review (read-only). Date: 2026-09-22.
Inputs: `solicitacao-historico-finalizacao/.handoff/master-plan.md` (§1–§49 + decisões transversais); S1 = `obras-associacoes-cadastro-convites` (SPEC v1.2, PLAN 28 T / 8 P, PHASES, `plan-decisions.md`); S2 = `solicitacao-historico-finalizacao` (SPEC v1.1, PLAN 34 T / 8 P, PHASES, `clarifier-answers.md`); S3 = `navegacao-sidebar-listagens` (SPEC v1.1, PLAN 22 T / 8 P, PHASES, `clarifier-answers.md`). Code spot-checks against the current tree (base `c987df8`).

Notation: `S1 P3/T09` = slice 1, phase 3, task T09. Phases: S1 P1 T01–04, P2 T05–07, P3 T08–10, P4 T11–12, P5 T13–16, P6 T17–19, P7 T20–22, P8 T23–28 · S2 P1 T01–06, P2 T07–12, P3 T13–16, P4 T17–20, P5 T21–23, P6 T24–27, P7 T28–31, P8 T32–34 · S3 P1 T01–03, P2 T04–07, P3 T08–09, P4 T10–12, P5 T13–15, P6 T16–19, P7 T20, P8 T21–22.

---

## 1. Findings

| ID | Sev. | Cat. | Evidence | Slice file(s) to change | Suggested fix |
|---|---|---|---|---|---|
| F-01 | MAJOR | e, h | **Suspect confirmed — it is an inconsistency, not just a documented trade-off.** S2 decided that the *day* of a solicitação and every date/time display use America/Sao_Paulo, with UTC storage (S2 SPEC RF-10 "Timezone", NC-02; S2 PLAN T01 `LocalTime`). S3 applies that rule to the presets (S3 SPEC RF-16; PLAN T08 `utcWindow`) and to the "Solicitado em" column (S3 PLAN T09). But "Personalizado" keeps `whereDate('requested_at', …)` on the UTC value (S3 PLAN T08 `apply`, Risks row "Timezone", Open Questions #1), and so does the Dashboard period filter (`app/Services/DashboardIndicatorsService.php:153-158`). Result inside **one** control: a pedido at 2026-09-22T02:30Z is shown as "Solicitado em 21/09/2026" and excluded from "Hoje" on 22/09, yet it is **included** by Personalizado De = Até = 22/09. Other "today" rules also stay UTC: `entreguesHoje` (`DashboardIndicatorsService.php:113` `today()`), atraso/prazo (`AtrasoClassifier.php:26,46`, `PrazoClassifier.php:30`). The listing badge "Atrasado" can therefore flip at 21:00 local, while the presets change day at 00:00 local | S3 SPEC RF-18 + PLAN T08, T11, Risks, Open Questions; S2 SPEC NC-02 / Q-11 (scope statement) | Make Personalizado compare local-day bounds converted to UTC through the single `RequestedPeriodFilter`, and route the Dashboard period filter through the same class. Both then move together, so `DashboardDrillDownTest` parity holds. Record one explicit cross-slice decision for the remaining "today" rules (atraso/prazo/`entreguesHoje`): either move them to `LocalTime` too, or list them as known UTC exceptions in S2 Q-11 and CLAUDE.md |
| F-02 | MAJOR | j, k | S2 T14 renames the `CreatePedidoAction` input keys (`obra_selection`, `descricao`), and S2 T15 replaces `Obra\NovaSolicitacao` with a component whose properties are `obra_selection`/`descricao`. Callers that are **not** in the task file lists still use `obra_id`/`items_description`: `tests/Feature/Livewire/PedidoDetalheObraTest.php:24,42`, `PedidoDetalheGestaoTest.php:32,51`, `ObraScreensRouteTest.php:20` (T13 lists the file for route middleware only), `tests/Feature/Authorization/BypassUiAuthorizationTest.php:73-78` (T15: "import/route only"), and `tests/Feature/Security/Adversarial/CrossObraTest.php:97-100` (T15: "import only", but `set('obra_id')`/`set('items_description')` target properties that will no longer exist). Also slice 1 T19 `ZeroObraUserTest` ("CreatePedidoAction with any obra_id → 422") will keep passing, but only vacuously, on "Selecione a obra.". S2 T34/PHASES header allow rewriting only the assertions they list, so Phase 3 goes red, or security tests are silently weakened (the forged-obra cases fail on a missing field instead of on the association rule) | S2 PLAN + PHASES T13/T14/T15, T34 allow-list | List these files in T14/T15 and migrate them to the CT-01 keys, keeping their intent: a forged `obra_selection` of a foreign obra must fail on the association message. Add them to T34's allowed-rewrite list |
| F-03 | MAJOR | f, g, a(§12, §29) | Before S1, Suprimentos users were **prohibited** from holding obras (`CreateUserAction::obraIdsRules`). S2 RF-02/RF-07 make the Suprimentos creation flow depend on ≥ 1 active association, "Outra" included. No task gives the existing or demo Suprimentos user any association: `DemoSeeder::seedObras()` (`database/seeders/DemoSeeder.php:170-190`) associates only `obra`/`obra_multi`; S1 T02 and S2 T04 do not change that; S2 T32's runbook has no step for it. After deploy, and in the demo, `/suprimentos/nova-solicitacao` shows the empty state, so §29/§49 ("Obra e Suprimentos poderão criar solicitações") cannot be exercised out of the box. S3 T21 also audits that screen "with 'Outra' selected", which needs such a fixture | S2 PLAN/PHASES T04 (or a new DemoSeeder task) and T32; S3 T21 fixture note | DemoSeeder: idempotently associate the demo Suprimentos user with the demo obras. Runbook (S2 T32): "associar os usuários Suprimentos às obras em /associacoes antes de anunciar a Nova Solicitação". S2 T30 flow: include one Suprimentos creation |
| F-04 | MAJOR | k | In S2, Phase 1 intentionally ends with a red suite. T05: "green except the Kanban/indicator assertions that T10/T11 update … record the exact failing list", because the migrated `finalizado` row adds a sixth Kanban column and a new `porStatus` entry. The `feat(phase-1)` commit, and any Ralph per-phase verification, then runs on known failures until Phase 2 (T10/T11). The within-phase pairing rule ("T04+T05 same commit") does not cover this cross-phase gap | S2 PLAN/PHASES Phase 1–2 (T05, T10, T11) | Either move the Finalizado-driven assertion updates of `KanbanBoardTest`/`GestaoKanbanReadOnlyTest`/`DashboardIndicatorsTest`/`VisaoGeralTest` into T05, or move T04/T05 into Phase 2 next to T10/T11, so every phase closes green |
| F-05 | MINOR | k | docs/agents gate: **present** in all three slices (S1 PLAN "Pré-requisito" + PHASES header; S2 G-1; S3 G-1), satisfying the master decision. However, S1 Phase 8 puts T27 (`/ai-context` regeneration + CLAUDE.md) in the same `feat(phase-8)` commit as code tasks (T23 layout, T24–T26 tests). That contradicts S1's own gate rationale (1): Ralph phase commits must not mix context regeneration with code. S2 Phase 8 (T32 README, T33 docs, T34 gates, no app code) and S3 Phase 7 (T20 alone) are fine. Also, `/ai-context` is a Claude skill; whether a headless Ralph run can invoke it is unverified in all three plans | S1 PLAN "Execution Phases" + PHASES Phase 8 | Move S1 T27 into its own phase after T28, as S3 does, or make it a developer step after Ralph. Confirm that Ralph can run `/ai-context` |
| F-06 | MINOR | a(§17), e | §17 "Itens → Descrição … consistente": the pedido summary keeps "Itens e quantidades" (`resources/views/components/pedido-summary.blade.php:45`). S2 T22 renames only "Data necessária" → "Preciso para", and S3 T09 only the table/card. The Obra detail, reached from Acompanhamento, keeps the old label while the form (S2 UI-01) and the listings (S3 RF-11) say "Descrição" | S2 SPEC UI-03 + PLAN T22 | Add "Descrição" to the summary in S2 T22 (and its AC) |
| F-07 | MINOR | e | "Previsão" has two meanings. In the listings it is the automatic Data prevista (S3 RF-12/T09). On the Kanban cards (`kanban/pedido-card.blade.php:27`, `gestao/pedido-card-read-only.blade.php:23`) it stays the manual `expected_delivery_at` (S2 PLAN Assumptions; S3 PLAN Assumptions) | S3 PLAN T09 (or S2 T11) | Relabel the card field "Previsão de entrega" (the S2 UI-03 label) |
| F-08 | MINOR | e | "Preciso para" vs "Data necessária": S3 T14 keeps the legend "Data necessária" on the needed-date De/Até fieldset inside "Mais filtros". S2 T14 keeps the messages "Informe a data necessária." / "Informe uma data necessária válida.", which S2 RF-09 already allows to be reworded | S3 PLAN T14; S2 PLAN T14 | Legend "Preciso para"; messages reworded to "Preciso para" |
| F-09 | MINOR | a(§42), h | S2 PLAN Assumption: the `criacao_pedido` context "Solicitação registrada para <obra>." is derived at render time from the pedido's *current* obra. An obra rename (S1 RF-02) therefore rewrites what the history says the request was registered for, which weakens §42 "o histórico deverá continuar rastreável" | S2 PLAN T21 / Assumptions | Store the `obraLabel()` snapshot in the `criacao_pedido` event's `new_value` for new pedidos, with the render fallback only for legacy events |
| F-10 | MINOR | b | Provisional navigation is built and then thrown away. S1 T23 (toolbar "Obras"/"Associações", S1 UI-08) and S2 T16 (toolbar "+ Nova Solicitação", S2 UI-02) are removed by S3 RF-08/T04, and the pinning tests (`LayoutIdentityTest`, `UsuariosIndexTest`) are rewritten three times. This is benign staging, but two slices own "nav entry for the same feature" (§10/§36). S2 marks its entry as provisional; S1 UI-08 does not | S1 SPEC UI-08 (wording only) | Keep; state in S1 UI-08 that the entries are provisional until S3 RF-08 |
| F-11 | MINOR | e | S3 SPEC is stale about upstream names. Metadata says slice 2 has "no PLAN yet"; CT-02 says the Suprimentos Nova Solicitação route has a "name fixed by slice 2 PLAN, not yet in code" and the obras routes are "not yet in code". S2 PLAN now fixes `suprimentos.nova-solicitacao` / `create-pedido`, and S3 PLAN already uses them | S3 SPEC Metadata + CT-02 | Replace the placeholders with the exact S1/S2 names (S3 PLAN "Upstream names consumed" is already correct) |
| F-12 | MINOR | e, i | No slice sets a timezone rule for the timestamps S1 introduces: the convite list's criado/expira/revogado/utilizado em (S1 T12), and `obra_admin_events`. S1 lands before `LocalTime` exists (S2 T01), so those render in UTC, while S2 history/summary render in São Paulo, three hours apart. The same applies to the Kanban/Dashboard dates | S3 PLAN T21 (or S2 T21) | Route the S1 convite list dates through `LocalTime::formatDateTime` in S2 or S3 |
| F-13 | MINOR | f | Suprimentos may associate **itself** (and any other Suprimentos user) with any obra (S1 RF-11, CT-06, T15), and that association is exactly what grants S2 creation eligibility (S2 RF-02). The master plan allows it (§1, §3). No slice records it as an intended consequence, and the Associações screen does not flag self-edits | S1 SPEC RF-11 (note) | Record that self-association is intended; if it is not, block `actor == target` for Suprimentos in `AttachUserObrasAction` |
| F-14 | MINOR | d | Migration details: (a) S1 T01 `down()` drops `status`/`responsavel`, losing the A iniciar/Em andamento distinction and responsável data; (b) S2 T04 `down()` deletes the lookup rows (guarded by the absence of references); (c) S2 T02's backfill calls live application code (`DataPrevistaCalculator`), so a future holiday-list change makes `migrate:fresh` produce different backfills. None is a hidden cleanup in `up()`, so §43 holds. Ordering is correct: S2 T06(g) asserts that S2 migrations sort after S1's; S3 has no migration (RNF-06, T22 item 5); no sequence restart and no second touch of the same column; `obras.is_active` has no reader left after S1 (S1 T26 scan, S2/S3 use `Obra::active()`) | S1 T01, S2 T02/T04 (docblocks) | Document (a)/(b) as data-losing rollbacks. For (c), inline a frozen copy of the rule in the migration, or state that the backfill is pinned to the calculator version at migration time |
| F-15 | MINOR | a(§26) | §26 "seguindo a segunda referência visual fornecida": the image is not captured in any slice artifact, and S3 UI-06 relies only on the textual example order | S3 SPEC UI-06 / `.handoff/description.md` | Attach or reference the image, or record the developer's acceptance that UI-06 fulfils it |
| F-16 | MINOR | j | No end-to-end test covers flow §45 (Novo Cadastro → Gestão/Suprimentos associates → user creates a pedido for that obra). Each step is tested in isolation (S1 T19, T15/T16; S2 T15). The S1 browser flow (T25) covers only the convite, and the S2 flow (T30) starts from an already associated user | S1 PLAN T22 (Feature) or S3 T22 checklist | Add one Feature flow test in S3 T22 (the incremental regression), or at least a manual checklist item |
| F-17 | MINOR | e | S2 T15 reuses S1's empty-state copy "… Fale com a Gestão ou com Suprimentos." verbatim, so a Suprimentos user is told to talk to Suprimentos | S2 PLAN T15 | Use papel-aware copy (Suprimentos: "Fale com a Gestão ou associe-se em Associações"), keeping the obra text byte-identical |

**Checked, no finding:**
- **Convite token secrecy (S1 RF-38) is not contradicted.** S3 keeps the auth layout out of the sidebar work (S3 SPEC Scope "Out"), so the convite page gets no sidebar. S3 T01 records only route names and middleware; `obra-invitation.show` has no parameter. S3 T19 edits only the logout/navigation lines of `ObraInvitationFlowTest`, and S3 T21 re-runs S1's `/convite` case without duplicating it. S2 adds no request logging. No S2/S3 compliance test asserts anything that conflicts with fragment transport, and the full-suite regressions (S2 T34, S3 T22) keep `ObraInvitationTokenLeakTest` and `ObraInvitationTokenTransportTest` in force.
- **Circular dependencies (c): none.** S1 depends on nothing later. S2 depends only on S1 (G-2). S3 depends on S1 and S2 (G-2). The S2 → S3 hand-offs (listing timezone, Kanban "Previsão") are forward hand-offs, not dependencies.
- **Obra status (g) is consistent.** "Ativa" is always `Obra::active()` (S1 RF-03; S2 RF-02; S3 RF-20 plus the static scan in T17(c)). Concluído blocks new pedidos (S1 RF-04, S2 RF-03), new convites and pending convites (S1 RF-27/RF-33), and is excluded by "Somente obras ativas" (S3). It still accepts associations (S1 NC-07) and stays in the obra filter selects (S1 RF-05; S3 keeps "never `->active()`").
- **Pedido status (h) is consistent.** The terminal set is {Entregue, Cancelado, Finalizado}, defined once (S2 T04/T08). The only exits from Entregue are Finalizar, romaneio and observação (S2 RF-37). The Kanban has 6 columns and no move target of Finalizado (S2 T11). `entregues` excludes Finalizado; `porStatus`, Visão Geral and S3's status filter include it (S2 RF-39, S3 RF-13). No slice-3 quick action for Entregue.
- **Authorization parity (f) holds.** S3 T02(d) asserts that each sidebar item's abilities equal its route's `can:`: obra NS = `is-obra`+`create-pedido` (S2 T13); Suprimentos NS = `is-suprimentos`+`create-pedido` (S2 T15); Obras/Associações = `manage-obras` (S1 T10/T16); Usuários = `is-gestao`+`manage-users`. `manage-users` stays Gestão-only in every slice. `visibleTo` opens every listing (S2 T09 for "Outra"; S3 RF-23 adds Suprimentos/Gestão).
- **Desktop/mobile (i).** Viewports are identical in all slices (1440×900, 820×1180, 390×844). The S3 sidebar breakpoint `lg` = 1024 with the desktop filter target ≥ 1280 is internally consistent, since UI-01 allows either rendering at 820.

---

## 2. Traceability matrix

Status legend: COVERED / PARTIAL / MISSING / DUPLICATE.

| # | Master requirement (atomic) | Slice | RF / UI / CT | Phase/Task | Status |
|---|---|---|---|---|---|
| 1.1 | Usuário pode ter múltiplas obras | S1 | RF-11 | S1 P5/T13, P5/T15 | COVERED |
| 1.2 | Zero, uma ou várias obras | S1 | RF-11, RF-14 | S1 P5/T13, P6/T19 | COVERED |
| 1.3 | Gestão e Suprimentos associam/desassociam | S1 | RF-09, RF-13, CT-06 | S1 P5/T15, P5/T16 | COVERED |
| 1.4 | Impedir associação duplicada | S1 | RF-10 | S1 P5/T15 | COVERED |
| 1.5 | Remover associação não apaga pedidos/histórico | S1 | RF-13 | S1 P5/T15 | COVERED |
| 1.6 | Obra só opera em obras com acesso (+ fluxo Outra) | S1, S2 | S1 RF-14, RF-15; S2 RF-03, RF-05, RF-40 | S1 P6/T19; S2 P2/T09, P3/T14 | COVERED |
| 1.7 | Suprimentos também pode ter múltiplas obras | S1 | RF-11, RF-13b | S1 P5/T13, T14 | COVERED |
| 1.8 | Autorização validada no backend | S1 | RF-07, RF-10, RF-17 | S1 P3/T08, P7/T22 | COVERED |
| 2.1 | Área Obras para Gestão e Suprimentos | S1 | RF-07, CT-03 | S1 P3/T08, P3/T10 | COVERED |
| 2.2 | Cadastrar e editar obras | S1 | RF-01, RF-02, UI-03, UI-04 | S1 P3/T09, P3/T10 | COVERED |
| 2.3 | Campos Obra, Responsável, Status | S1 | CT-01 | S1 P1/T01, P1/T02 | COVERED |
| 2.4 | Status A iniciar / Em andamento / Concluído | S1 | CT-01, UI-04 | S1 P1/T02 | COVERED |
| 2.5 | Obra ativa = status ≠ Concluído | S1 | RF-03 | S1 P1/T02, P1/T03, P8/T26 | COVERED |
| 2.6 | Concluído deixa de aparecer para novas solicitações | S1, S2 | S1 RF-04; S2 RF-02 | S1 P1/T03; S2 P3/T15 | COVERED |
| 2.7 | Concluído continua existindo | S1 | RF-06 | S1 P3/T08, P8/T26 | COVERED |
| 2.8 | Pedidos antigos permanecem acessíveis | S1 | RF-05 | S1 P1/T03 | COVERED |
| 2.9 | Histórico permanece disponível | S1 | RF-05 | S1 P1/T03 | COVERED |
| 2.10 | Nenhuma informação histórica apagada | S1 | RF-02 AC, RF-36 | S1 P1/T01, P3/T09 | COVERED |
| 3.1 | Interface G/S para administrar obras de cada usuário | S1 | UI-06, CT-03 | S1 P5/T16 | COVERED |
| 3.2 | Localizar usuário | S1 | RF-08 | S1 P5/T16 | COVERED |
| 3.3 | Visualizar obras associadas | S1 | RF-08 | S1 P5/T16 | COVERED |
| 3.4 | Adicionar uma associação | S1 | RF-09 | S1 P5/T15, T16 | COVERED |
| 3.5 | Adicionar várias obras ao mesmo usuário | S1 | RF-09 | S1 P5/T15, T16 | COVERED |
| 3.6 | Remover associação | S1 | RF-13, UI-06 | S1 P5/T15, T16 | COVERED |
| 3.7 | Impedir duplicidade | S1 | RF-10 | S1 P5/T15 | COVERED |
| 3.8 | Mecanismo para vincular usuários do Novo Cadastro | S1 | RF-08 (lista obra com 0 obras) | S1 P5/T16 | COVERED |
| 4.1 | Botão "Novo Cadastro" no login | S1 | UI-01 | S1 P6/T19 | COVERED |
| 4.2 | Cadastro público simplificado | S1 | CT-04 | S1 P6/T19 | COVERED |
| 4.3 | Campos Nome, E-mail, Senha, confirmação | S1 | RF-16, UI-02 | S1 P6/T18, T19 | COVERED |
| 4.4 | Sem campo obra/nome da obra/seleção/perfil/Gestão/Suprimentos | S1 | UI-02 | S1 P6/T19 | COVERED |
| 4.5 | Sempre perfil Obra | S1 | RF-16 | S1 P6/T18 | COVERED |
| 4.6 | Criado sem obra associada | S1 | RF-16 | S1 P6/T18 | COVERED |
| 4.7 | G/S associa depois | S1 | RF-08, RF-09 | S1 P5/T15, T16 | COVERED |
| 4.8 | Backend impede se cadastrar como Gestão/Suprimentos | S1 | RF-17 | S1 P6/T18, P7/T22 | COVERED |
| 5.1 | Obra sem associação é estado válido, não erro | S1 | RF-11, RF-14, UI-09 | S1 P1/T03, P6/T19 | COVERED |
| 5.2 | Pode autenticar | S1 | RF-14 | S1 P6/T19 | COVERED |
| 5.3 | Não recebe acesso automático a obras | S1 | RF-14 | S1 P6/T19 | COVERED |
| 5.4 | Não pode forjar ID de obra | S1, S2 | S1 RF-14; S2 RF-03, RF-07 | S1 P6/T19; S2 P3/T14 | COVERED |
| 5.5 | Pode receber associações depois | S1 | RF-09 | S1 P5/T15 | COVERED |
| 5.6 | Pode receber associação por convite válido | S1 | RF-30 | S1 P7/T20, T21 | COVERED |
| 6.1 | G/S geram convite a partir de uma obra | S1 | RF-23, UI-05 | S1 P4/T11, T12 | COVERED |
| 6.2 | Convite vinculado internamente à obra; pertence a uma única obra | S1 | CT-02 | S1 P2/T05 | COVERED |
| 6.3 | Token seguro e não previsível | S1 | RNF-01 | S1 P4/T11 | COVERED |
| 6.4 | Validade 24 h | S1 | RF-23, RF-27 | S1 P2/T05, P4/T11 | COVERED |
| 6.5 | Uso único | S1 | RF-32 | S1 P7/T20, T22 | COVERED |
| 6.6 | Revogável antes do uso | S1 | RF-25 | S1 P4/T11, T12 | COVERED |
| 6.7 | Consumido só após cadastro/associação com sucesso | S1 | RF-29, RF-30, RNF-02 | S1 P7/T20 | COVERED |
| 6.8 | Três convidados → três links diferentes | S1 | RF-24 | S1 P4/T11 | COVERED |
| 7.1 | Sem conta: informa Nome, E-mail, Senha | S1 | RF-29, UI-07 | S1 P7/T21 | COVERED |
| 7.2 | Sistema cria a conta com perfil Obra | S1 | RF-29 | S1 P7/T20 | COVERED |
| 7.3 | Associa automaticamente à obra do convite | S1 | RF-29 | S1 P7/T20 | COVERED |
| 7.4 | Convite marcado como utilizado | S1 | RF-29 | S1 P7/T20 | COVERED |
| 7.5 | Não pode escolher/alterar a obra | S1 | RF-29, UI-07 | S1 P7/T20, T22 | COVERED |
| 8.1 | Conta Obra existente: não duplicar, usar a existente | S1 | RF-20, RF-30 | S1 P7/T20, T21 | COVERED |
| 8.2 | Exigir autenticação/confirmação | S1 | RF-30 | S1 P7/T21, P8/T25 | COVERED |
| 8.3 | Adicionar associação; impedir duplicada | S1 | RF-30 | S1 P7/T20 | COVERED |
| 8.4 | Consumir só após sucesso | S1 | RF-30, RF-32 | S1 P7/T20 | COVERED |
| 8.5 | Novas obras via convites diferentes | S1 | RF-30 | S1 P7/T20 | COVERED |
| 8.6 | Conta Gestão/Suprimentos não convertida | S1 | RF-31 | S1 P7/T20, T21 | COVERED |
| 9.1 | Tratar válido / expirado / utilizado / revogado / inexistente | S1 | RF-27, RF-28 | S1 P2/T05, P7/T20, T21 | COVERED |
| 9.2 | Tentativa de reutilização; não reutilizável | S1 | RF-28, RF-32 | S1 P7/T20, T22 | COVERED |
| 9.3 | Tentativas simultâneas | S1 | RF-32, RNF-02 | S1 P7/T22 | COVERED |
| 9.4 | Registrar obra, quem gerou, quando, validade, quem revogou, quando usado, quem usou | S1 | CT-02, RF-34, CT-07 | S1 P2/T05, T06, P4/T11 | COVERED |
| X.1 | (Decisão transversal) Token em claro fora de logs de aplicação e access logs | S1 (S2/S3 não contradizem) | RF-38, RF-19b, RNF-01 | S1 P4/T11, P7/T20, T21, P8/T25, T26 | COVERED |
| 10.1 | Nova Solicitação para Obra e Suprimentos | S2 | RF-01, CT-05 | S2 P3/T13, T15 | COVERED |
| 10.2 | Ação destacada "+ Nova Solicitação" | S3 | RF-07, UI-03 | S3 P2/T04 | COVERED |
| 10.3 | Fácil de acessar durante a navegação | S2, S3 | S2 UI-02 (toolbar provisória); S3 RF-07, RF-08 | S2 P3/T16; S3 P2/T04, P6/T18 | DUPLICATE (F-10, benigno) |
| 10.4 | Gestão não recebe automaticamente | S2, S3 | S2 RF-01; S3 RF-03 | S2 P3/T13; S3 P1/T02 | COVERED |
| 11.1 | Obra seleciona entre obras elegíveis | S2 | RF-02 | S2 P3/T15 | COVERED |
| 11.2 | Não apresentar obras concluídas | S2 | RF-02 | S2 P3/T15 | COVERED |
| 11.3 | Backend valida associação; ID forjado não autoriza | S2 | RF-03 | S2 P3/T14, P6/T27 | COVERED |
| 12.1 | Suprimentos cria solicitações | S2 | RF-01 | S2 P3/T13–T15 | COVERED |
| 12.2 | Suprimentos vê suas obras associadas não concluídas | S2 | RF-02 | S2 P3/T15 | COVERED |
| 12.3 | Mesma regra no backend | S2 | RF-03, RF-07 | S2 P3/T14 | COVERED |
| 13.1 | Opção "Outra" no seletor | S2 | RF-02, UI-01 | S2 P3/T15 | COVERED |
| 13.2 | Abre campo de texto livre opcional | S2 | RF-04, UI-01 | S2 P3/T14, T15 | COVERED |
| 13.3 | Texto = referência do pedido | S2 | RF-04, CT-07 | S2 P1/T02, P2/T07 | COVERED |
| 13.4 | Não cria obra / não associa / não concede acesso / não é cadastro | S2 | RF-05, RF-40 | S2 P2/T09, P3/T14 | COVERED |
| 13.5 | Sem texto → representado como "Outra" | S2 | RF-04, CT-07 | S2 P2/T07 | COVERED |
| 14.1 | Data da solicitação (registro) | S2 | RF-09 | S2 P2/T07, P3/T14 | COVERED |
| 14.2 | Preciso para (necessidade) | S2 | RF-09, UI-01 | S2 P3/T14, T15 | COVERED |
| 14.3 | Data prevista = solicitação + 3 dias úteis (não 72 h, não corridos) | S2 | RF-10, CT-06 | S2 P1/T01, T02, P2/T07 | COVERED |
| 14.4 | Estrutura para futuro "3 dias", sem implementar | S2 | RF-11 | S2 P2/T07, P7/T31 | COVERED |
| 15.1 | Upload de imagens e documentos | S2 | RF-14, UI-01 | S2 P4/T17, T18 | COVERED |
| 15.2 | Anexos relacionados ao pedido | S2 | CT-03 | S2 P1/T03 | COVERED |
| 15.3 | Validar tipos e tamanho; arquivos inválidos | S2 | RF-14, RF-15 | S2 P4/T17, T18 | COVERED |
| 15.4 | Armazenamento, nome seguro | S2 | RF-16, RF-20 | S2 P4/T17, P8/T32 | COVERED |
| 15.5 | Acesso, download e autorização | S2 | RF-17 | S2 P4/T19 | COVERED |
| 15.6 | Não autorizado não acessa por URL direta | S2 | RF-18, RNF-09 | S2 P4/T19 | COVERED |
| 16.1 | Listagens mostram quem solicitou e obra/referência | S3 | RF-10, UI-05 | S3 P3/T09 | COVERED |
| 16.2 | Formato "João Silva / Residencial Aurora" | S3 | RF-10 | S3 P3/T09 | COVERED |
| 16.3 | Tabela desktop e cards mobile | S3 | RF-10, UI-05 | S3 P3/T09 | COVERED |
| 17.1 | Acompanhamento: "Itens" → "Descrição" | S3 (+S2) | S3 RF-11; S2 UI-01 (form) | S3 P3/T09; S2 P3/T15 | PARTIAL (F-06: detail summary keeps "Itens e quantidades") |
| 17.2 | Consistente desktop e mobile | S3 | RF-11 | S3 P3/T09 | COVERED |
| 18.1 | Coluna "Previsão" = Data prevista (3 dias úteis) | S3 | RF-12 (consumes S2 CT-06) | S3 P3/T09 | COVERED |
| 19.1 | Padronizar histórico: ação / descrição / data-hora / autor | S2 | RF-21, UI-04 | S2 P5/T21 | COVERED |
| 19.2 | Modelo "Pedido criado / Solicitação registrada para …" | S2 | RF-21 | S2 P5/T21 | COVERED |
| 19.3 | Eventos anteriores não destruídos | S2 | RF-22 | S2 P6/T27 | COVERED |
| 20.1 | Campo de observação livre no pedido para Obra e Suprimentos | S2 | RF-24, UI-05 | S2 P5/T23 | COVERED |
| 20.2 | Enviar cria novo evento no histórico | S2 | RF-24 | S2 P5/T23 | COVERED |
| 20.3 | Preserva conteúdo, autor, data/hora, pedido; nova não substitui anterior | S2 | RF-24, RF-22 | S2 P5/T23, P6/T27 | COVERED |
| 21.1 | Obra autorizado marca Entregue | S2 | RF-27, RF-29 | S2 P6/T24 | COVERED |
| 21.2 | Somente no detalhe/histórico, não como ação rápida na listagem | S2, S3 | S2 UI-06; S3 (no action added) | S2 P6/T24 | COVERED |
| 21.3 | Autorização no backend | S2 | RF-28 | S2 P6/T24, T27 | COVERED |
| 21.4 | Registrar no histórico com autor e data/hora | S2 | RF-27, RF-21 | S2 P6/T24, P5/T21 | COVERED |
| 22.1 | Home de Suprimentos = Pedidos | S3 | RF-09, CT-01 | S3 P2/T07 | COVERED |
| 22.2 | Visão Geral continua disponível | S3 | RF-09, RF-03 | S3 P1/T02, P2/T07 | COVERED |
| 23.1 | Home de Gestão = Pedidos | S3 | RF-09, CT-01 | S3 P2/T07 | COVERED |
| 23.2 | Demais funcionalidades acessíveis pela navegação | S3 | RF-03 | S3 P1/T02, P2/T06 | COVERED |
| 24.1 | Suprimentos: mais antigo → mais novo por padrão | S3 | RF-14 | S3 P4/T10 | COVERED |
| 24.2 | Ordenação determinística | S3 | RF-14 | S3 P4/T10 | COVERED |
| 25.1 | Filtro compacto "Solicitado" com Hoje / 3 / 7 dias / Último mês / Personalizado | S3 | RF-15, CT-03 | S3 P3/T08, P5/T13 | COVERED |
| 25.2 | Preset aplica o período automaticamente | S3 | RF-16 | S3 P3/T08, P4/T10–T12 | COVERED |
| 25.3 | De/Até só em Personalizado | S3 | RF-15, RF-17 | S3 P5/T13–T15 | COVERED (semântica de fuso: F-01) |
| 26.1 | Simplificar filtros em todas as listagens aplicáveis | S3 | RF-22, UI-06 (NC-04) | S3 P5/T13–T15 | COVERED |
| 26.2 | Desktop compacto horizontal, segunda referência visual | S3 | UI-06 | S3 P5/T13, P6/T18 | PARTIAL (F-15) |
| 26.3 | Exemplo [Obra][Status][Prioridade][Solicitado][Responsável][Limpar] | S3 | UI-06 | S3 P5/T14 | COVERED |
| 26.4 | Não remover funcionalidades | S3 | RF-22 | S3 P5/T14, T15, P6/T17 | COVERED |
| 26.5 | Diminuir significativamente o espaço vertical | S3 | UI-06 (≤ 2 linhas) | S3 P6/T18 | COVERED |
| 27.1 | Filtros mobile: reorganizar, sem overflow, legíveis, fáceis, sem ocupar a tela | S3 | UI-07 | S3 P5/T13, P6/T18 | COVERED |
| 28.1 | "Somente obras ativas" em Todos os Pedidos de Gestão e Suprimentos | S3 | RF-20, UI-08 | S3 P4/T10, T11, P5/T14 | COVERED |
| 28.2 | Definição = obra ≠ Concluído | S3 | RF-20 (consome S1 RF-03) | S3 P4/T10, T11, P6/T17 | COVERED |
| 28.3 | Não apaga/altera pedidos | S3 | RF-21 | S3 P4/T10 | COVERED |
| 28.4 | Tratamento coerente de "Outra" | S3 | RF-20 (NC-02) | S3 P4/T10, T11 | COVERED |
| 29.1 | Suprimentos no mesmo fluxo (obra, Outra, descrição, 3 datas, anexos, demais) | S2 | RF-01..RF-14, UI-01 | S2 P3/T13–T15, P4/T18 | PARTIAL (F-03: no demo/prod Suprimentos user has associations, so the flow shows the empty state) |
| 30.1 | Suprimentos anexa romaneio | S2 | RF-30, UI-07 | S2 P6/T25 | COVERED |
| 30.2 | Identificação técnica, não pelo nome | S2 | RF-30, RF-31, CT-03 kind | S2 P1/T03, P6/T25 | COVERED |
| 30.3 | Aparece no pedido e no histórico | S2 | UI-03, RF-21 | S2 P5/T21, T22 | COVERED |
| 31.1 | Status Finalizado, usado por Suprimentos para concluir | S2 | RF-33, RF-34 | S2 P1/T04, P6/T26 | COVERED |
| 31.2 | Entregue e Finalizado distintos | S2 | RF-33, RF-37, UI-08 | S2 P1/T04, P2/T12 | COVERED |
| 32.1 | Não finalizar sem romaneio; verificação no backend | S2 | RF-34, RF-35 | S2 P6/T26 | COVERED |
| 32.2 | Com romaneio: permitir, alterar para Finalizado, registrar histórico | S2 | RF-34 | S2 P6/T26 | COVERED |
| 33.1 | Sem romaneio: bloquear, não alterar status, sem estado parcial | S2 | RF-35 | S2 P6/T26 | COVERED |
| 33.2 | Erro visual com a mensagem conceitual | S2 | RF-35, UI-07 | S2 P6/T26, P7/T30 | COVERED |
| 33.3 | Botão desabilitado só como complemento | S2 | RF-35, UI-07 | S2 P6/T26, P7/T30 | COVERED |
| 34.1 | Evento "Romaneio anexado / <arquivo>" | S2 | RF-21, RF-30 | S2 P5/T21, P6/T25 | COVERED |
| 35.1 | Evento "Pedido finalizado / Pedido finalizado por Suprimentos." | S2 | RF-21, RF-34 | S2 P5/T21, P6/T26 | COVERED |
| 35.2 | Preservar todo o histórico anterior | S2 | RF-22, RF-34 | S2 P6/T27 | COVERED |
| 36.1 | Sidebar substitui a toolbar no sistema Albuquerque | S3 | RF-01, RF-08 | S3 P2/T04, T05 | COVERED |
| 36.2 | Funciona em desktop e mobile | S3 | UI-01, UI-02 | S3 P2/T04, P6/T18 | COVERED |
| 36.3 | Indica a seção atual | S3 | RF-04 | S3 P2/T04, T06 | COVERED |
| 36.4 | Respeita permissões / só itens autorizados | S3 | RF-02, RF-03 | S3 P1/T01, T02, P2/T06 | COVERED |
| 36.5 | Acesso às áreas existentes, Obras quando autorizado, administrativas | S3 | RF-03, CT-02 | S3 P1/T02 | COVERED |
| 36.6 | Mantém logout | S3 | RF-05 | S3 P2/T04, T06, P6/T18 | COVERED |
| 37.1 | "+ Nova Solicitação" destacado para Obra e Suprimentos | S3 | RF-07, UI-03 | S3 P2/T04, T06 | COVERED |
| 37.2 | Fácil de encontrar em qualquer página | S3 | RF-07 | S3 P6/T18 | COVERED |
| 38.1 | Sidebar Obra: Nova Solicitação + acompanhamento + autorizadas | S3 | RF-03, CT-02 | S3 P1/T02 | COVERED |
| 38.2 | Sem áreas administrativas indevidas | S3 | RF-03 | S3 P2/T06 | COVERED |
| 39.1 | Sidebar Suprimentos: NS, Pedidos, Visão Geral, Obras, Associações, demais | S3 | RF-03, CT-02 | S3 P1/T02 | COVERED |
| 39.2 | Landing de Suprimentos = Pedidos | S3 | RF-09 | S3 P2/T07 | COVERED |
| 40.1 | Sidebar Gestão: Pedidos, Obras, Usuários, Associações, Dashboard/Kanban | S3 | RF-03, CT-02 | S3 P1/T02 | COVERED |
| 40.2 | Landing de Gestão = Pedidos | S3 | RF-09 | S3 P2/T07 | COVERED |
| 41.1 | Responsivo: login | S1 (S3 re-run) | S1 RNF-03 | S1 P8/T25; S3 P8/T21 | COVERED |
| 41.2 | Responsivo: Novo Cadastro | S1 | RNF-03 | S1 P8/T25; S3 P8/T21 | COVERED |
| 41.3 | Responsivo: convite | S1 | RNF-03 | S1 P8/T25; S3 P8/T21 | COVERED |
| 41.4 | Responsivo: sidebar | S3 | UI-01, RNF-02 | S3 P6/T18, P8/T21 | COVERED |
| 41.5 | Responsivo: gerenciamento de obras | S1, S3 | S1 RNF-03; S3 RNF-02 | S1 P8/T25; S3 P8/T21 | COVERED |
| 41.6 | Responsivo: associação de usuários | S1, S3 | S1 RNF-03; S3 RNF-02 | S1 P8/T25; S3 P8/T21 | COVERED |
| 41.7 | Responsivo: Nova Solicitação | S2, S3 | S2 RNF-04; S3 RNF-02 | S2 P7/T30; S3 P8/T21 | COVERED |
| 41.8 | Responsivo: seleção de obra | S2 | RNF-04 | S2 P7/T30 | COVERED |
| 41.9 | Responsivo: Outra | S2, S3 | RNF-04; RNF-02 | S2 P7/T30; S3 P8/T21 | COVERED |
| 41.10 | Responsivo: upload | S2, S3 | RNF-04; RNF-02 | S2 P7/T30; S3 P8/T21 | COVERED |
| 41.11 | Responsivo: listagens | S3 | RNF-02 | S3 P6/T18, P8/T21 | COVERED |
| 41.12 | Responsivo: filtros | S3 | UI-06, UI-07 | S3 P6/T18 | COVERED |
| 41.13 | Responsivo: histórico | S2, S3 | RNF-04; RNF-02 | S2 P7/T30; S3 P8/T21 | COVERED |
| 41.14 | Responsivo: observações | S2, S3 | RNF-04; RNF-02 | S2 P7/T30; S3 P8/T21 | COVERED |
| 41.15 | Responsivo: Entregue | S2, S3 | RNF-04; RNF-02 | S2 P7/T30; S3 P8/T21 | COVERED |
| 41.16 | Responsivo: romaneio | S2, S3 | RNF-04; RNF-02 | S2 P7/T30; S3 P8/T21 | COVERED |
| 41.17 | Responsivo: Finalizado | S2, S3 | RNF-04; RNF-02 | S2 P7/T30; S3 P8/T21 | COVERED |
| 42.1 | Não destrói histórico: alterar status da obra | S1 | RF-02 | S1 P3/T09 | COVERED |
| 42.2 | … concluir obra | S1 | RF-05 | S1 P1/T03 | COVERED |
| 42.3 | … associar usuário | S1 | RF-09, RF-12 | S1 P5/T15 | COVERED |
| 42.4 | … desassociar usuário | S1 | RF-13 | S1 P5/T15 | COVERED |
| 42.5 | … alterar status do pedido | S2 | RF-22, RF-44 | S2 P6/T27 | COVERED |
| 42.6 | … marcar Entregue | S2 | RF-27, RF-22 | S2 P6/T24 | COVERED |
| 42.7 | … adicionar observação | S2 | RF-24, RF-22 | S2 P5/T23 | COVERED |
| 42.8 | … anexar romaneio | S2 | RF-32 | S2 P6/T25 | COVERED |
| 42.9 | … finalizar pedido | S2 | RF-34 | S2 P6/T26 | COVERED |
| 42.10 | Histórico continua rastreável | S2 | RF-21 | S2 P5/T21 | PARTIAL (F-09: the creation context follows obra renames) |
| 43.1 | Sem exclusões silenciosas | S1, S2, S3 | S1 RF-36; S2 RF-42; S3 RNF-06 | S1 P1/T04; S2 P1/T06; S3 P8/T22 | COVERED |
| 43.2 | Sem limpeza destrutiva escondida em migration | S1, S2 | S1 RF-36, RNF-06; S2 RF-42, RNF-06 | S1 P1/T01; S2 P1/T02–T04 | COVERED (F-14: down() notes) |
| 43.3 | Reset explícito e controlado | S1, S2 | S1 RF-35; S2 RF-43 | S1 P2/T07; S2 P7/T28 | COVERED |
| 43.4 | Preservar estrutura/configuração não descartável | S1, S2 | RF-36; RF-42 | S1 P1/T01; S2 P1/T02 | COVERED |
| 44.1 | Não regredir: autorização server-side | S1, S2, S3 | RF-37; RF-44; RF-24 | S1 P8/T28; S2 P8/T34; S3 P1/T01, P8/T22 | COVERED |
| 44.2 | … isolamento de obras | S1, S2, S3 | RF-15; RF-40; RF-23 | S1 P5/T15; S2 P2/T09; S3 P4/T10–T12 | COVERED |
| 44.3 | … usuário inativo bloqueado | S1, S2, S3 | RF-37; RF-44 (download route); RF-24 | S1 P8/T28; S2 P4/T19; S3 P8/T22 | COVERED |
| 44.4 | … restrição de novas solicitações em obras inativas/concluídas | S1, S2 | RF-04; RF-03, RF-44 | S1 P1/T03; S2 P3/T14 | COVERED |
| 44.5 | … histórico | S2 | RF-22 | S2 P6/T27 | COVERED |
| 44.6 | … rate limiting | S1, S2, S3 | RF-37, RF-19; RF-44; RF-24 | S1 P6/T17; S2 P8/T34; S3 P8/T22 | COVERED |
| 44.7 | … AuthenticateSession | S1, S2, S3 | RF-37; RF-44; RF-24 | final gates | COVERED |
| 44.8 | … normalização de e-mail | S1 | RF-18, RF-37 | S1 P6/T18 | COVERED |
| 44.9 | … unicidade case-insensitive | S1 | RF-18, RF-37 | S1 P6/T18 | COVERED |
| 44.10 | … auditoria | S1, S2 | RF-12, RF-22, RF-34; RF-22 | S1 P2/T06, P5/T15; S2 P6/T27 | COVERED |
| 44.11 | … pedido_events | S2 | RF-22, RF-44 | S2 P7/T28 | COVERED |
| 44.12 | … user_admin_events | S1 | RF-12, RF-37 | S1 P5/T13, T15 | COVERED |
| 44.13 | … authentication_events | S1 | RF-21, RF-30, RF-37 | S1 P6/T19, P7/T21 | COVERED |
| 44.14 | … policies/middlewares/scopes existentes | S1, S2, S3 | RF-37; RF-44; RF-02, RF-24 | S3 P1/T01 (baseline), final gates | COVERED |
| 45.1 | Fluxo: Login → Novo Cadastro | S1 | UI-01 | S1 P6/T19 | COVERED |
| 45.2 | → Nome + E-mail + Senha → conta Obra, zero obras | S1 | RF-16 | S1 P6/T18, T19 | COVERED |
| 45.3 | → G/S localiza o usuário | S1 | RF-08 | S1 P5/T16 | COVERED |
| 45.4 | → associa uma ou mais obras | S1 | RF-09 | S1 P5/T15, T16 | COVERED |
| 45.5 | → usuário opera nas obras autorizadas | S1, S2 | S1 RF-14/visibleTo; S2 RF-02 | S2 P3/T15 | COVERED |
| 45.6 | Verificação ponta a ponta do fluxo §45 | — | — | none | PARTIAL (F-16) |
| 46.1 | G/S → Obra → Gerar convite → link único 24 h | S1 | RF-23, RF-24 | S1 P4/T11, T12 | COVERED |
| 46.2 | Usuário acessa → Nome/E-mail/Senha → conta Obra → associação automática → convite consumido | S1 | RF-29, RF-38 | S1 P7/T20, T21 (AC-a flow) | COVERED |
| 46.3 | Conta Obra existente: convite → autenticação/confirmação → nova obra → consumido | S1 | RF-30 | S1 P7/T21, P8/T25 | COVERED |
| 47.1 | Obra ou Suprimentos → Nova Solicitação → obra associada ou Outra | S2 | RF-01, RF-02, RF-04 | S2 P3/T14, T15 | COVERED |
| 47.2 | → Descrição → Preciso para → anexos opcionais → pedido criado | S2 | RF-08, RF-09, RF-14 | S2 P3/T14, P4/T18 | COVERED |
| 47.3 | → Data da solicitação registrada → Data prevista +3 dias úteis | S2 | RF-09, RF-10 | S2 P2/T07 | COVERED |
| 47.4 | → histórico registra criação → Acompanhamento | S2, S3 | S2 RF-21, UI-01 (link); S3 RF-10..RF-12 | S2 P5/T21, P7/T30; S3 P3/T09 | COVERED |
| 48.1 | Suprimentos acompanha, mais antigos primeiro | S3 | RF-14 | S3 P4/T10 | COVERED |
| 48.2 | Obra/Suprimentos adicionam observações → histórico | S2 | RF-24 | S2 P5/T23 | COVERED |
| 48.3 | Romaneio anexado → Finalizar → sistema valida romaneio → Finalizado → histórico | S2 | RF-30, RF-34, RF-35 | S2 P6/T25, T26, P7/T30 | COVERED |
| 48.4 | Obra autorizado marca Entregue no detalhe | S2 | RF-27 | S2 P6/T24 | COVERED |
| 49.1 | G/S administram obras e seus usuários | S1 | RF-01..RF-13 | S1 P3–P5 | COVERED |
| 49.2 | Novos usuários Obra: Novo Cadastro sem associação e convite já associado | S1 | RF-16, RF-29 | S1 P6, P7 | COVERED |
| 49.3 | Um usuário participa de várias obras | S1 | RF-11, RF-30 | S1 P5, P7 | COVERED |
| 49.4 | Obra e Suprimentos criam com Outra, anexos, Preciso para, previsão 3 dias úteis | S2 | RF-01..RF-18 | S2 P1–P4 | PARTIAL (F-03, Suprimentos half) |
| 49.5 | Identificação do solicitante; histórico com observações e ações auditáveis | S2, S3 | S2 RF-21..RF-26; S3 RF-10 | S2 P5; S3 P3/T09 | COVERED |
| 49.6 | Suprimentos parte de Pedidos (antigos primeiro), anexa romaneio e só então finaliza | S2, S3 | S3 RF-09, RF-14; S2 RF-34/35 | S3 P2/T07, P4/T10; S2 P6 | COVERED |
| 49.7 | Gestão inicia em Pedidos | S3 | RF-09 | S3 P2/T07 | COVERED |
| 49.8 | Sidebar responsiva; Nova Solicitação destacada; filtros compactos | S3 | RF-01..RF-08, UI-01..UI-07 | S3 P2, P5, P6 | COVERED |
| X.2 | (Decisão transversal) Commit documental de docs/agents separado e anterior ao Ralph | S1, S2, S3 | S1 pre-exec gate; S2 G-1; S3 G-1 | gates | COVERED (F-05: S1 phase 8 mixes docs regen with code) |
| X.3 | (Decisão transversal) Ralph 1 → validar → Ralph 2 → validar → Ralph 3 → validar → regressão completa | S2, S3 | S2 G-2; S3 G-2, T22 | S3 P8/T22 | COVERED |
| X.4 | (Decisão transversal) Não duplicar responsabilidades entre fatias | all | — | — | COVERED (only F-10, provisional nav) |

---

## 3. Coverage summary

| Status | Count |
|---|---|
| COVERED | 218 |
| PARTIAL | 6 |
| MISSING | 0 |
| DUPLICATE | 1 |
| **Total rows** | **225** |

Coverage: 218/225 fully COVERED = **96.9 %**. Counting PARTIAL and DUPLICATE as "addressed", **100 %** of rows have an owner, and there are 0 MISSING.

Non-COVERED rows:
- 10.3 "+ Nova Solicitação" fácil de acessar: **DUPLICATE**. S2 toolbar (UI-02/T16) and S3 sidebar (RF-07/RF-08) both own it; benign, see F-10.
- 17.1 "Itens → Descrição": **PARTIAL**. The detail summary keeps "Itens e quantidades" (F-06).
- 26.2 "segunda referência visual": **PARTIAL**. The image is not captured in any artifact (F-15).
- 29.1 Suprimentos no mesmo fluxo: **PARTIAL**. No demo or production Suprimentos user has an association (F-03).
- 42.10 Histórico rastreável: **PARTIAL**. The creation context re-renders after an obra rename (F-09).
- 45.6 Verificação ponta a ponta §45: **PARTIAL**. There is no flow test (F-16).
- 49.4 Obra e Suprimentos criam…: **PARTIAL**. The Suprimentos half depends on F-03.

Severity totals: **BLOCKER 0 · MAJOR 4 (F-01..F-04) · MINOR 13 (F-05..F-17)**.

---

## 4. Execution order across slices (with gates)

1. **Gate D (master decision, S1 pre-exec gate):** a documentation-only commit of the 8 modified `docs/agents/*.md` files. Checks: `git status --short docs/agents` is empty, and `git show --stat --format= HEAD -- . ':!docs/agents'` is empty. `.spec/` folders of all three slices stay out of that commit.
2. **Ralph 1: S1 `obras-associacoes-cadastro-convites`**
   - Phases and their key ordering:
     - P1 (T01–T04; T02 and T03 in the same commit);
     - P2 (T05 → T06 → T07);
     - P3 (T08 → T09 → T10);
     - P4 (T11 → T12);
     - P5 (T13 → T14 ∥ T15 → T16);
     - P6 (T17 ∥ T18 → T19);
     - P7 (T20 → T21 → T22);
     - P8 (T23 ∥ T24 ∥ T26 → T25; T27 → T28). F-05: T27 should become its own phase.
   - Before the P1 merge: the read-only production query for obra-name collisions via Railway, run only with the developer's confirmation at that moment.
   - Close: T28 (Pint, build, Unit + Feature + Browser, one Pest process at a time, no dependency diff).
3. **Validate S1** manually (developer).
4. **Ralph 2: S2 `solicitacao-historico-finalizacao`**
   - Entry gates:
     - G-1: docs/agents clean;
     - G-2: all 8 S1 phases on HEAD, S1 names present, suite green, clean tree;
     - G-3: before the P1 merge, read-only production queries on `statuses.sort_order` and null `requested_at`.
   - Phases:
     - P1 (T01 → T02 < T03 < T04 migration order, all after S1's; T04 and T05 together; T06). F-04: P1 currently closes red;
     - P2 (T07 ∥ T08 ∥ T12 → T09 → T10, T11);
     - P3 (T13 → T14 + T15 together → T16). F-02: add the unlisted test files;
     - P4 (T17 → T18 → T19 → T20);
     - P5 (T21 → T22, T23);
     - P6 (T24 → T25 → T26 → T27);
     - P7 (T28 → T29 ∥ T31 → T30);
     - P8 (T32 → T33 → T34).
   - **G-4:** no `git push` before the T32 runbook is done (Railway Volume, `PEDIDO_ANEXOS_ROOT`, `PHP_INI_SCAN_DIR`). The F-03 runbook step, associating Suprimentos users, belongs here.
5. **Validate S2** manually.
6. **Ralph 3: S3 `navegacao-sidebar-listagens`**
   - Entry gates:
     - G-1;
     - G-2: all S1 and S2 phases on HEAD; S2 names `suprimentos.nova-solicitacao`, `create-pedido`, `obraLabel`/`dataPrevistaLabel`/`presentDataPrevista`/`OUTRA_LABEL`, `LocalTime`, `StatusSlug::Finalizado`/`terminal()`, `seedWorkflowStatuses()` present; suite green.
   - Phases:
     - P1 (T01 baseline first, then T02 ∥ T03);
     - P2 (T04 ∥ T07 → T05, with T04 and T05 in the same commit → T06);
     - P3 (T08 ∥ T09). F-01: fix Personalizado/Dashboard timezone here;
     - P4 (T10 ∥ T11 ∥ T12);
     - P5 (T13 → T14 ∥ T15);
     - P6 (T16 ∥ T17 ∥ T18 → T19);
     - P7 (T20 docs, alone);
     - P8 (T21 → T22).
   - **G-3:** push only after T22 is green and the developer has validated the increment. If S2 has not been pushed yet, S2's G-4 applies to this push.
7. **Full regression (S3 T22):**
   - Unit, Feature and the whole Browser suite (S1 + S2 + S3), one Pest process at a time;
   - no dependency diff;
   - no new migration, and `migrate` reports "Nothing to migrate";
   - no deleted test file and no decrease in case counts;
   - `migrate:fresh --seed` twice, then `demo:reset --force` + `db:seed`;
   - the developer's manual checklist.
8. **Validate the increment** (developer), then push and deploy (Railpack runs `migrate` on container start).
