# Matriz de Rastreabilidade — V0 Next.js/Supabase → Laravel/Livewire

Artefato de auditoria exigido por **RF-25** / **AC-33** ("nenhum requisito relevante da V0 anterior
tiver sido silenciosamente perdido"). Cada linha parte de uma fonte AS IS já lida — as regras de
`docs/agents/domain_rules.md`, as ações de `docs/agents/api_contracts.md`, as entidades de
`docs/agents/data_model.md`, os RF do PRD e os requisitos RIGID desta SPEC — e aponta o
equivalente Laravel (arquivo) e o teste que o prova. Nenhuma lacuna é resolvida silenciosamente:
toda divergência entre o código AS IS (recuperado do histórico Git, commit `e320806`, último
antes da remoção em T54) e a reimplementação está registrada na [Seção 7](#7-divergências-documentadas-e-needs-clarification).

## Legenda de status

| Status | Significado |
|---|---|
| ✅ Implementado | Comportamento preservado; arquivo + teste indicados. |
| ⚠️ Implementado com divergência documentada | Comportamento preservado em essência, com diferença registrada na Seção 7 e justificada por decisão da SPEC (FLEXIBLE / Q-xx) ou pelo brief (§15, §40, §41). |
| ⛔ Fora de escopo | Não implementado por decisão explícita do brief §40 ou da SPEC (Scope → Out). |
| ❓ `[NEEDS CLARIFICATION]` | Divergência sem decisão registrada na SPEC — aberta para o produto decidir; nunca resolvida silenciosamente. |
| N/A | Mecanismo específico da stack anterior sem contrapartida necessária na nova stack (justificado na linha). |

Nenhuma linha desta matriz está marcada como "não implementado" sem justificativa referenciando
o brief §40 / SPEC Scope Out ou um `[NEEDS CLARIFICATION]` explícito.

---

## 1. Regras de domínio (`docs/agents/domain_rules.md`)

### 1.1 Overview

| # | Regra AS IS (`domain_rules.md`) | Equivalente Laravel | Teste(s) | Status |
|---|---|---|---|---|
| D-01 | Workflow fixo de status seedado em ordem: `solicitado → em_analise → em_compra_preparacao → aguardando_entrega → entregue` (+ `cancelado` de qualquer não-terminal) | `app/Enums/StatusSlug.php` (6 literais, `activeNonFinal()`, `isTerminal()`); `database/seeders/DemoSeeder.php::seedStatuses()` (`sort_order` 1–6) | `tests/Unit/Enums/SlugEnumsTest.php`; `tests/Feature/Seeders/DemoSeederIdempotencyTest.php` | ✅ |
| D-02 | Três papéis (`obra`, `suprimentos`, `gestao`) gateiam toda mutação (`requireRole`) e toda linha (RLS) | `app/Enums/RoleSlug.php`; Gates `is-obra`/`is-suprimentos`/`is-gestao` em `app/Providers/AppServiceProvider.php`; `app/Policies/PedidoPolicy.php`; `app/Actions/Pedidos/Concerns/GuardsOperationalMutation.php::ensureActorIsSuprimentos()`; middleware `auth` + `can:is-*` em `routes/web.php` | `tests/Feature/Authorization/RoleGatesTest.php`; `tests/Feature/Authorization/PedidoPolicyTest.php`; `tests/Feature/Authorization/BypassUiAuthorizationTest.php` | ⚠️ RLS substituída por Policies/Gates/middleware — ver Seção 7, item DV-01 |
| D-03 | Toda mutação grava um `pedido_event` pareado e append-only (`insertEvent`); histórico nunca é editado/excluído | Cada Action em `app/Actions/Pedidos/*` faz `$pedido->events()->create()` dentro de `DB::transaction`; `app/Models/PedidoEvent.php` (`UPDATED_AT = null`, `updating`/`deleting` lançam `LogicException`); `app/Policies/PedidoEventPolicy.php` (update/delete sempre `false`); nenhuma rota de update/delete em `routes/web.php` | `tests/Unit/Models/PedidoEventImmutabilityTest.php`; `tests/Feature/Actions/*` ("exactly 1 ... event"); `tests/Feature/MigrationSchemaTest.php` ("pedido_events table is append-only") | ✅ |
| D-04 | `atraso` e `pendente` derivados em tempo de leitura de `needed_at` e `status.slug` (`atraso.ts`, `pendente.ts`) | `app/Domain/Pedidos/AtrasoClassifier.php` (`isAtrasado`, `scopeAtrasado`); `app/Domain/Pedidos/PendenteClassifier.php` (`isPendente`, `scopePendente`) — únicos pontos de cálculo, consumidos por Kanban, listagens, filtros e dashboard | `tests/Unit/Domain/AtrasoClassifierTest.php`; `tests/Unit/Domain/PendenteClassifierTest.php`; `tests/Feature/Livewire/TodosPedidosFiltersTest.php` ("atraso filter matches AtrasoClassifier"); `tests/Feature/Livewire/DashboardIndicatorsTest.php` ("identical to the AtrasoClassifier-derived count") | ✅ |
| D-05 | Acesso aos pedidos de uma obra escopado pela pivot `obra_profile` no app (`isObraAcessivel`) e na RLS (`is_obra_member()`) | `app/Models/User.php::obras()` (`belongsToMany` via `obra_profile`); `app/Policies/PedidoPolicy.php::view()` (obra → `$user->obras()->whereKey($pedido->obra_id)->exists()`); `app/Actions/Pedidos/CreatePedidoAction.php` (mesma checagem na criação); `app/Livewire/Obra/Acompanhamento.php` (listagem escopada) | `tests/Feature/Authorization/PedidoPolicyTest.php` ("obra is denied reading a pedido from an obra it is not associated with", "obra associated with multiple obras can view pedidos from all of them"); `tests/Feature/Livewire/AcompanhamentoTest.php`; `tests/Feature/Livewire/PedidoDetalheObraTest.php` ("access to a pedido from an unassociated obra is denied"); `tests/Feature/ObraProfileCardinalityTest.php` | ⚠️ pivot renomeada `profile_id → user_id` (brief §15) — DV-02 |

### 1.2 Rule: pedido creation is Obra-only, scoped to its own obras

| # | Regra AS IS | Equivalente Laravel | Teste(s) | Status |
|---|---|---|---|---|
| D-06 | `obra_id`, `needed_at`, `items_description` obrigatórios → `ValidationError` | `CreatePedidoAction::execute()` — `Validator::make([...], ['obra_id' => required, 'needed_at' => required/date, 'items_description' => required])`; `app/Livewire/Obra/NovaSolicitacao.php::rules()` (mesma validação na UI) | `tests/Feature/Actions/CreatePedidoActionTest.php` (3 testes "missing ... rejects"); `tests/Feature/Livewire/NovaSolicitacaoTest.php` (3 testes "missing ... rejected server-side") | ✅ (mensagens reescritas — Q-03) |
| D-07 | Requester deve estar ligado ao `obra_id` via `obra_profile` → `ForbiddenError` ("A obra informada não está associada ao solicitante.") | `CreatePedidoAction::execute()` — `$requester->obras()->whereKey($obra_id)->exists()` senão `ValidationException` com a mesma mensagem; `PedidoPolicy::create()` (defesa em profundidade); `NovaSolicitacao::obras()` só lista obras associadas | `CreatePedidoActionTest` ("obra_id outside the requester obra_profile association is rejected even as a direct payload"); `NovaSolicitacaoTest` ("an obra_id outside the requester association is rejected even when set directly", "the obra select only lists the requester associated obras"); `BypassUiAuthorizationTest` ("createSolicitacao is rejected when the payload forges an obra_id..."); `PedidoPolicyTest` ("obra can create a pedido only for an obra it is associated with") | ✅ |
| D-08 | Insert via RPC `create_pedido`: status de menor `sort_order`, código via `next_pedido_code()` (`PED-000001`), evento `criacao_pedido` na mesma transação (rollback se o evento falhar) | `CreatePedidoAction` — `DB::transaction`: `Status::orderBy('sort_order')->firstOrFail()`, `app/Services/PedidoCodeGenerator.php` (`nextval('pedido_code_sequence')`, `sprintf('PED-%06d')`), `events()->create(criacao_pedido)`; sequence em `database/migrations/2026_09_18_230919_create_pedido_code_sequence.php` | `CreatePedidoActionTest` ("valid creation persists the pedido with exactly 1 criacao_pedido event", "a failure inserting the criacao_pedido event rolls back the pedido insert too"); `tests/Unit/Services/PedidoCodeGeneratorTest.php` (formato + concorrência) | ✅ |
| D-09 | RLS `pedidos_insert`: `current_role_slug() = 'obra'`, `is_obra_member(obra_id)`, `requester_id = auth.uid()` | `PedidoPolicy::create()` (papel `obra` + membership); `CreatePedidoAction` força `requester_id = $requester->id` (não vem do payload); rota `obra/nova-solicitacao` sob `can:is-obra`; `NovaSolicitacao::mount()/submit()` `authorize('is-obra')` | `PedidoPolicyTest` ("suprimentos and gestao are denied creating a pedido"); `NovaSolicitacaoTest` ("non-obra actors are denied access to the component") | ⚠️ RLS → Policy/Action (DV-01) |

### 1.3 Rule: only Suprimentos may mutate an existing pedido's operational fields

| # | Regra AS IS | Equivalente Laravel | Teste(s) | Status |
|---|---|---|---|---|
| D-10 | `updatePedidoResponsavel/Prioridade/Previsao/Status`, `cancelPedido` → `requireRole(actor, "suprimentos")` senão `ForbiddenError` | `GuardsOperationalMutation::ensureActorIsSuprimentos()` (usado pelas 5 Actions, lança `AuthorizationException` com a mesma mensagem); `PedidoPolicy::setResponsavel/setPrioridade/setPrevisao/updateStatus/cancelar` (`suprimentos` only); componentes chamam `$this->authorize(...)` antes da Action | `tests/Feature/Actions/*ActionTest.php` ("a non-suprimentos actor is rejected"/"an obra actor is rejected"/"a gestao actor is rejected"); `PedidoPolicyTest` ("obra is denied every operational mutation", "gestao is denied every operational mutation", "suprimentos is permitted every operational mutation"); `BypassUiAuthorizationTest` (5 mutações chamadas diretamente) | ✅ |
| D-11 | Cada mutação é no-op quando o novo valor é igual ao atual (sem evento) | `UpdatePedidoResponsavelAction` (`responsible_id === $responsibleId` → return); `UpdatePedidoPrioridadeAction` (`priority_id === $priorityId`); `UpdatePedidoPrevisaoAction` (data igual); `KanbanBoard::moveCard()` ignora drop na própria coluna; `UpdatePedidoStatusAction` rejeita alvo igual ao atual como transição inválida | `UpdatePedidoResponsavelActionTest` ("reassigning the same responsible is a no-op..."); `UpdatePedidoPrioridadeActionTest` ("setting the same priority is a no-op..."); `UpdatePedidoPrevisaoActionTest` ("setting the same date is a no-op..."); `KanbanBoardTest` ("dropping a card back into its own column is a no-op that writes no history"); `UpdatePedidoStatusActionTest` ("a self-transition to the current status is rejected as invalid") | ⚠️ status: no-op no Kanban, erro de validação na Action/detalhe — DV-03 |
| D-12 | RLS column-level: `authenticated` só pode dar UPDATE em `status_id, priority_id, responsible_id, expected_delivery_at`; Obra não altera `needed_at`, `items_description`, `obra_id` | Nenhuma Action/rota/componente escreve `needed_at`/`items_description`/`obra_id` após a criação; as 5 Actions só atualizam as 4 colunas operacionais; telas de Obra/Gestão não renderizam formulário de edição (UI-04/UI-05); mass assignment protegido por `#[Fillable]` | `tests/Feature/Livewire/PedidoDetalheObraTest.php` ("no edit form or mutation control is rendered"); `tests/Feature/Livewire/PedidoDetalheGestaoTest.php` (idem); `tests/Feature/Security/MassAssignmentTest.php`; `BypassUiAuthorizationTest` | ⚠️ grant SQL → ausência de caminho de escrita + Policy (DV-01) |

### 1.4 Rule: status transitions are restricted to the active workflow, plus a one-way path to terminal states

| # | Regra AS IS | Equivalente Laravel | Teste(s) | Status |
|---|---|---|---|---|
| D-13 | `updatePedidoStatus` rejeita `cancelado` ("Use cancelPedido...") — cancelamento é caminho separado | `UpdatePedidoStatusAction` — `$allowedTargets = [...activeNonFinal(), Entregue]`, `cancelado` fora do conjunto → `ValidationException('Transição de status inválida.')`; `KanbanBoard::columns()` e o select de status em `Suprimentos\PedidoDetalhe::render()` excluem `cancelado`; `CancelPedidoAction` é a única via para `cancelado` | `UpdatePedidoStatusActionTest` ("cancelado is rejected as a target via this action"); `tests/Feature/Livewire/KanbanForgedMoveTest.php` ("a forged move to cancelado ... is rejected and the pedido stays put") | ✅ |
| D-14 | Alvo deve estar em `ACTIVE_NON_FINAL_STATUSES` ∪ `entregue`; senão `ValidationError("Transição de status inválida.")` | `UpdatePedidoStatusAction` (`StatusSlug::activeNonFinal()` + `Entregue`) | `UpdatePedidoStatusActionTest` ("permitted transitions succeed and write the correct event type" — parametrizado 4 origens × alvos permitidos); `SlugEnumsTest` ("StatusSlug::activeNonFinal returns the 4 active non-final statuses") | ✅ |
| D-15 | Pedido em status terminal (`entregue`/`cancelado`) não pode ser movido → `ConflictError("Pedido em status terminal não pode ser alterado.")`; idem `cancelPedido` | `GuardsOperationalMutation::ensurePedidoIsNotTerminal()` → `app/Exceptions/Pedidos/PedidoTerminalStateException.php` (mesma mensagem, renderiza HTTP 409); aplicado nas 5 Actions; controles ocultos no detalhe quando terminal | `UpdatePedidoStatusActionTest` ("a terminal origin status rejects any transition"); `UpdatePedidoResponsavel/Prioridade/PrevisaoActionTest` ("a terminal pedido rejects the mutation"); `CancelPedidoActionTest` ("cancelling an already entregue pedido is rejected", "...already cancelado pedido is rejected (irreversible)"); `KanbanForgedMoveTest` ("a forged move against a terminal pedido is rejected..."); `PedidoDetalheSuprimentosTest` ("all 5 operational controls are absent for a terminal pedido") | ✅ |
| D-16 | `cancelPedido` é irreversível — nenhuma função move `cancelado` de volta | `CancelPedidoAction` (só de não-terminal); nenhuma Action aceita origem terminal (D-15); `cancelado` nunca é coluna/alvo | `CancelPedidoActionTest` ("...already cancelado pedido is rejected (irreversible)"); `KanbanBoardTest` ("a pedido in cancelado never appears in any column") | ✅ |
| D-17 | Matriz de transição (6 linhas × 2 caminhos) | Derivada, como no AS IS, de `StatusSlug::activeNonFinal()` + `isTerminal()` — não há tabela explícita | `UpdatePedidoStatusActionTest` (parametrizado) + `CancelPedidoActionTest` (linhas terminais) | ✅ |

### 1.5 Rule: an event type is recorded for every mutation kind (1:1)

| # | Função AS IS → `event_types.slug` | Equivalente Laravel | Teste(s) | Status |
|---|---|---|---|---|
| D-18 | `createPedido` → `criacao_pedido` | `CreatePedidoAction` → `EventTypeSlug::CriacaoPedido` | `CreatePedidoActionTest`; `PedidoDetalheObraTest` ("the criacao_pedido event is visible after the full creation flow") | ✅ |
| D-19 | `updatePedidoResponsavel` → `alteracao_responsavel` | `UpdatePedidoResponsavelAction` → `EventTypeSlug::AlteracaoResponsavel` (previous/new = ids) | `UpdatePedidoResponsavelActionTest` ("a valid change generates exactly 1 alteracao_responsavel event") | ⚠️ valores gravados como ids, não slugs/uuids — DV-04 |
| D-20 | `updatePedidoPrioridade` → `alteracao_prioridade` | `UpdatePedidoPrioridadeAction` → `EventTypeSlug::AlteracaoPrioridade` | `UpdatePedidoPrioridadeActionTest` ("a valid change generates exactly 1 alteracao_prioridade event") | ⚠️ DV-04 |
| D-21 | `updatePedidoPrevisao` → `alteracao_previsao` | `UpdatePedidoPrevisaoAction` → `EventTypeSlug::AlteracaoPrevisao` (previous/new = datas ISO) | `UpdatePedidoPrevisaoActionTest` ("a valid change generates an event with previous and new values") | ✅ |
| D-22 | `updatePedidoStatus` (alvo ≠ `entregue`) → `mudanca_status` | `UpdatePedidoStatusAction` → `EventTypeSlug::MudancaStatus` | `UpdatePedidoStatusActionTest` ("...write the correct event type") | ⚠️ DV-04 |
| D-23 | `updatePedidoStatus` (alvo = `entregue`) → `entrega` | `UpdatePedidoStatusAction` → `EventTypeSlug::Entrega` | `UpdatePedidoStatusActionTest`; `tests/Browser/DemoRoteiroTest.php` (passos 17–18) | ✅ |
| D-24 | `cancelPedido` → `cancelamento` | `CancelPedidoAction` → `EventTypeSlug::Cancelamento` | `CancelPedidoActionTest` ("...writes 1 cancelamento event"); `tests/Feature/Livewire/CancelPedidoControlTest.php` | ✅ |
| D-25 | Catálogo de 7 `event_types` seedado | `app/Enums/EventTypeSlug.php`; `DemoSeeder::seedEventTypes()` | `SlugEnumsTest` ("EventTypeSlug matches the 7 RIGID event type literals exactly") | ✅ |

### 1.6 Rule: atraso (overdue) and pendente classification

| # | Regra AS IS | Equivalente Laravel | Teste(s) | Status |
|---|---|---|---|---|
| D-26 | `isPedidoPendente`: true a menos que `status.slug` ∈ {`entregue`, `cancelado`} | `PendenteClassifier::isPendente()` (`! StatusSlug::isTerminal()`); `scopePendente()` para filtros/drill-down | `PendenteClassifierTest` ("isPendente is true unless the status is entregue or cancelado" — 7 status); `DashboardIndicatorsTest` ("pendentes indicator count is identical to the PendenteClassifier-derived count") | ✅ |
| D-27 | `isPedidoAtrasado`: false se terminal (`NAO_ATRASAVEL`); senão true quando `needed_at` < data UTC de hoje | `AtrasoClassifier::isAtrasado()` (`isTerminal()` → false; `Carbon::parse(needed_at)->startOfDay()->lt(Carbon::today())`, `app.timezone = UTC`); `scopeAtrasado()` equivalente em SQL | `AtrasoClassifierTest` ("isAtrasado follows the 4-combination decision table"); `TodosPedidosFiltersTest` ("the atraso filter matches AtrasoClassifier output on the same dataset"); `tests/Feature/Livewire/PedidoCardRenderTest.php` ("an atrasado pedido card carries the distinct CSS class") | ✅ |
| D-28 | `classificarPrazo`: `null` se não pendente; `atrasado` se atrasado; `vencendo_em_breve` se dias restantes ≤ `VENCENDO_EM_BREVE_DIAS` (3); senão `dentro_do_prazo` | `app/Domain/Pedidos/PrazoClassifier.php` (`const int VENCENDO_EM_BREVE_DIAS = 3`, `classificar()`); consumido por `app/Services/DashboardIndicatorsService.php` (indicador "prazos") | `PrazoClassifierTest` ("VENCENDO_EM_BREVE_DIAS is the RIGID constant value 3", "classificar follows the RIGID 3-day window decision table", "4 days out is above the window..."); `DashboardIndicatorsTest` ("prazos sums to the pendentes total") | ✅ |
| D-29 | Tabela de decisão (4 linhas: futuro >3d / futuro ≤3d / passado ativo / passado terminal) | Mesmos 3 classifiers | `AtrasoClassifierTest` + `PendenteClassifierTest` + `PrazoClassifierTest` (cobrem as 4 linhas) | ✅ |
| D-30 | Ponto único de extensão: `VENCENDO_EM_BREVE_DIAS` / conjuntos `NAO_ATRASAVEL`/`CONCLUSAO` | `PrazoClassifier::VENCENDO_EM_BREVE_DIAS` (constante nomeada, não configurável em runtime — RF-19c) / `StatusSlug::isTerminal()` | `PrazoClassifierTest`; `SlugEnumsTest` ("StatusSlug::isTerminal is true only for entregue and cancelado") | ✅ |

### 1.7 Rule: obra-scoped visibility for both application queries and RLS

| # | Regra AS IS | Equivalente Laravel | Teste(s) | Status |
|---|---|---|---|---|
| D-31 | `obra`: só vê obras/pedidos ligados via `obra_profile` (`listObrasAcessiveis`, `isObraAcessivel`, RLS) | `PedidoPolicy::view()`; `Acompanhamento::pedidos()` (`whereIn('obra_id', Auth::user()->obras()->pluck('obras.id'))`); `Obra\PedidoDetalhe::mount()` `authorize('view')`; `NovaSolicitacao::obras()` | `AcompanhamentoTest` ("only pedidos from the user's associated obras are listed"); `PedidoPolicyTest`; `PedidoDetalheObraTest` | ✅ |
| D-32 | `suprimentos`/`gestao`: veem toda obra e todo pedido, sem escopo | `PedidoPolicy::view()` → `true`; `Suprimentos\TodosPedidos`, `KanbanBoard`, `Gestao\TodosPedidos`, `Gestao\KanbanReadOnly`, `DashboardIndicatorsService` consultam `Pedido::query()` sem escopo | `PedidoPolicyTest` ("suprimentos and gestao can view any pedido regardless of obra association"); `PedidoDetalheGestaoTest` ("access to a pedido from any obra is allowed for gestao") | ✅ |
| D-33 | Funções SQL `SECURITY DEFINER` `current_role_slug()`/`is_obra_member()` para RLS sem recursão | N/A — sem RLS, a resolução de papel/membership é feita em PHP (`User::role`, `User::obras()`), com a mesma semântica | — | N/A (mecanismo específico de RLS; comportamento coberto por D-31/D-32 — DV-01) |

---

## 2. Contratos de ação (`docs/agents/api_contracts.md`)

| # | Server Action AS IS | Auth AS IS | Equivalente Laravel (contrato CT-01: auth+autorização, sucesso/erro estruturado, evento na mesma transação) | Teste(s) | Status |
|---|---|---|---|---|---|
| A-01 | `login(_prevState, formData{email,password})` — sucesso: redirect para `getRoleHomePath(role)`; erro `{error}` ("E-mail ou senha inválidos." / "Informe e-mail e senha.") | pública | `app/Livewire/Auth/LoginForm.php::authenticate()` (`Auth::guard('web')->attempt([... , 'is_active' => true])`, `Session::regenerate()`, redirect para `route('home')`); `routes/web.php` `/home` faz o match papel → tela inicial; erros como `ValidationException` ("E-mail ou senha inválidos.", "Informe o e-mail.", "Informe a senha.") | `tests/Feature/Auth/LoginTest.php` (7 testes); `tests/Feature/Livewire/LoginFormTest.php`; `tests/Feature/Auth/UnauthenticatedAccessTest.php` ("the home route lands each papel on its main screen") | ⚠️ checagem adicional de `is_active` no login — DV-05 |
| A-02 | `logout()` — qualquer perfil autenticado | autenticado | `routes/web.php` `POST /logout` (`Auth::guard('web')->logout()`, `session()->invalidate()`, `regenerateToken()`, redirect `/login`); botão "Sair" em `resources/views/layouts/app.blade.php` | `LoginTest` ("logout terminates the authenticated session"); `DemoRoteiroTest` (trocas de perfil via "Sair") | ✅ |
| A-03 | `createSolicitacao(formData{obra_id, needed_at, items_description})` — sucesso `{pedido:{code}}`; erros `ValidationError`/`ForbiddenError` com mensagem; genérico "Não foi possível criar a solicitação..."; sem sessão → "Sessão expirada..." | `obra` + membership | `app/Livewire/Obra/NovaSolicitacao.php::submit()` → `CreatePedidoAction::execute()`; sucesso exibe `$code`; validação/autorização como `ValidationException` (mensagens no formulário); sessão expirada → middleware `auth` redireciona para `/login` (Livewire 419 → recarregar) | `NovaSolicitacaoTest` ("a valid submission creates the pedido and shows the generated code" + validações); `CreatePedidoActionTest`; `tests/Feature/Security/CsrfProtectionTest.php` | ✅ (mensagens reescritas — Q-03) |
| A-04 | `setResponsavel(pedidoId, responsibleId: string \| null)` | `suprimentos` | `app/Livewire/Suprimentos/PedidoDetalhe.php::updateResponsavel()` → `PedidoPolicy::setResponsavel` → `UpdatePedidoResponsavelAction::execute(actor, pedido, ?int)`; `responsible_id` validado com `exists:users,id` + `app/Rules/ResponsibleMustBeSuprimentos.php` | `PedidoDetalheSuprimentosTest` ("the responsavel control persists a change and writes an event"); `UpdatePedidoResponsavelActionTest`; `tests/Feature/Rules/ResponsibleMustBeSuprimentosTest.php`; `BypassUiAuthorizationTest` ("setResponsavel is rejected when called directly...") | ❓ AS IS aceitava `null` ("Sem responsável"); Laravel exige um usuário `suprimentos` — DV-06 `[NEEDS CLARIFICATION]` |
| A-05 | `setPrioridade(pedidoId, priorityId)` — erro "Prioridade inválida." | `suprimentos` | `Suprimentos\PedidoDetalhe::updatePrioridade()` → `PedidoPolicy::setPrioridade` → `UpdatePedidoPrioridadeAction` (`exists:priorities,id` → "Prioridade inválida.") | `PedidoDetalheSuprimentosTest` ("the prioridade control persists a change..."); `UpdatePedidoPrioridadeActionTest` ("a value outside the 4 seeded priorities is rejected"); `BypassUiAuthorizationTest` | ✅ |
| A-06 | `setPrevisao(pedidoId, expectedDeliveryAt: string \| null)` | `suprimentos` | `Suprimentos\PedidoDetalhe::updatePrevisao()` → `PedidoPolicy::setPrevisao` → `UpdatePedidoPrevisaoAction` (`required`, `date`) | `PedidoDetalheSuprimentosTest` ("the previsao control persists a change..."); `UpdatePedidoPrevisaoActionTest`; `BypassUiAuthorizationTest` | ❓ AS IS permitia limpar (`null`); Laravel exige data — DV-07 `[NEEDS CLARIFICATION]` |
| A-07 | `moveStatus(pedidoId, statusId)` — Kanban drag-and-drop, select de status, "Marcar como Entregue"; erros "status_id inválido.", "Transição de status inválida.", "Use cancelPedido...", `ConflictError` | `suprimentos` | `app/Livewire/Kanban/KanbanBoard.php::moveCard()` (`wire:sort`) e `::moveViaControl()` (controle acessível, UI-08) → `PedidoPolicy::updateStatus` → `UpdatePedidoStatusAction`; `Suprimentos\PedidoDetalhe::updateStatus()` (select, inclui `Entregue`); `Status::findOrFail` (404 para id inexistente), "Transição de status inválida." (inclui `cancelado`), `PedidoTerminalStateException` (409) | `KanbanBoardTest`; `KanbanForgedMoveTest`; `tests/Feature/Livewire/AccessibleStatusControlTest.php` ("a pedido can be moved across the full workflow using only the accessible control"); `PedidoDetalheSuprimentosTest` ("the status control persists a change..."); `UpdatePedidoStatusActionTest`; `BypassUiAuthorizationTest` ("moveStatus is rejected...") | ⚠️ "Use cancelPedido" e "status_id inválido." colapsados em "Transição de status inválida."/404 (Q-03) — DV-03 |
| A-08 | `cancelarPedido(pedidoId)` — `ConflictError("...não pode ser cancelado.")` | `suprimentos` | `Suprimentos\PedidoDetalhe::confirmCancel()/abortCancel()/cancelarPedido()` (diálogo de confirmação) → `PedidoPolicy::cancelar` → `CancelPedidoAction` | `CancelPedidoControlTest` (3 testes); `CancelPedidoActionTest`; `BypassUiAuthorizationTest` ("cancelar is rejected...") | ✅ |
| A-09 | `PedidoActionState = {error?, pedido?}` + `runPedidoMutation` (erros verbatim; `NotFoundError` "Pedido {id} não encontrado."; genérico "Não foi possível concluir a ação.") | — | Componentes Livewire: sucesso → `$this->pedido` atualizado + `$feedback`; `ValidationException` → erros no formulário; `AuthorizationException` → 403; `PedidoTerminalStateException` → 409; `ModelNotFoundException` (route model binding/`findOrFail`) → 404; `APP_DEBUG=false` esconde stack trace | `PedidoDetalheSuprimentosTest`; `KanbanForgedMoveTest`; `SuprimentosScreensRouteTest` ("suprimentos screens are unreachable over http for non-suprimentos roles") | ✅ (formato de erro é FLEXIBLE em CT-01) |
| A-10 | `revalidatePath` em todas as telas `/obra`, `/suprimentos`, `/gestao` após mutação | — | N/A — Blade/Livewire renderiza a partir do banco a cada requisição; não há cache de rota a invalidar | `DemoRoteiroTest` (passos 12, 14, 18, 19 confirmam atualização entre perfis) | N/A (mecanismo Next.js sem contrapartida necessária) |
| A-11 | "No REST or GraphQL surface exists"; "Message formats: not applicable" (sem fila/broker) | — | Idem: nenhuma rota `api/*`, nenhuma fila/worker/cron (brief §6/§40) | `tests/Feature/Compliance/*` | ✅ |

---

## 3. Modelo de dados (`docs/agents/data_model.md`)

| # | Entidade / aspecto AS IS | Equivalente Laravel | Teste(s) | Status |
|---|---|---|---|---|
| M-01 | Lookups `roles`, `statuses`, `priorities`, `event_types`: `id uuid`, `name`, `slug unique`, `is_active`, timestamps; `sort_order unique` em statuses/priorities; `description` em roles/event_types; categóricos sempre FK, nunca `enum` Postgres | `database/migrations/2026_09_18_230107..230110_create_*_table.php` (mesmas colunas, `id` bigint); `app/Models/Role.php`, `Status.php` (`ordered()`), `Priority.php` (`ordered()`), `EventType.php`; nenhuma coluna `enum` | `tests/Feature/MigrationSchemaTest.php` (4 testes de lookups); `tests/Unit/Models/LookupModelsTest.php` | ⚠️ `uuid` → `bigint` auto-increment — DV-02 |
| M-02 | `profiles` (`id` ← `auth.users`, `full_name`, `role_id`, `is_active`, `is_demo`); provisionada só pelo trigger `handle_new_user()` — sem insert client-side | `users` absorve `profiles` (brief §15): `database/migrations/2026_09_18_230111_add_role_and_profile_fields_to_users_table.php` (`role_id` FK, `is_active`, `is_demo`); `app/Models/User.php` (`role()`, `obras()`, `requestedPedidos()`, `responsiblePedidos()`, `pedidoEvents()`, `scopeSuprimentos()`); provisionamento via `DemoSeeder`/administração — sem self-signup | `MigrationSchemaTest` ("users table has role_id foreign key, is_active and is_demo"); `DemoSeederIdempotencyTest`; `LoginTest` ("a deactivated user does not authenticate...") | ⚠️ `profiles` → `users`, `full_name` → `name` (brief §15) — DV-02; self-signup ⛔ SPEC Scope Out (brief §21) |
| M-03 | `obras` (`name`, `is_active`, `is_demo`, timestamps) | `2026_09_18_230112_create_obras_table.php`; `app/Models/Obra.php` (`users()`, `pedidos()`) | `MigrationSchemaTest` ("obras table has the expected columns") | ✅ |
| M-04 | `obra_profile`: PK composta `(obra_id, profile_id)`, `created_at`; N:N obras ↔ perfis `obra` | `2026_09_18_230113_create_obra_profile_table.php` (PK `(obra_id, user_id)`, `created_at`, cascade); `User::obras()`/`Obra::users()` `belongsToMany(..., 'obra_profile')` | `MigrationSchemaTest` ("obra_profile has a composite primary key and both foreign keys"); `tests/Feature/ObraProfileCardinalityTest.php` (2 testes) | ⚠️ `profile_id` → `user_id` — DV-02 |
| M-05 | `pedidos`: `code unique`, `obra_id`, `requester_id`, `requested_at default now()`, `needed_at date`, `items_description text`, `status_id`, `priority_id?`, `responsible_id?`, `expected_delivery_at? date`, `is_demo`, timestamps | `2026_09_18_230114_create_pedidos_table.php` (mesmo conjunto de colunas; `restrictOnDelete`/`nullOnDelete`); `app/Models/Pedido.php` (`#[Fillable]`, casts, 6 relações) | `MigrationSchemaTest` ("pedidos table has the complete column set and foreign keys"); `tests/Unit/Models/PedidoModelTest.php`; `MassAssignmentTest` | ✅ |
| M-06 | `code` via `next_pedido_code()` / sequence `pedidos_code_seq`, formato `PED-######` | `2026_09_18_230919_create_pedido_code_sequence.php` (`pedido_code_sequence`); `PedidoCodeGenerator` | `PedidoCodeGeneratorTest` (3 testes: formato, lote, conexões concorrentes) | ⚠️ pedidos seedados usam `PED-DEMO-000N` — DV-08 |
| M-07 | Invariantes: transições restritas; terminal imutável; `priority_id`/`responsible_id`/`expected_delivery_at` só por `suprimentos` | Actions + Policy (Seção 1.3/1.4) | Seção 1.3/1.4 | ✅ |
| M-08 | Joins de domínio `PedidoComRelacoes` / `PedidoComHistorico` (`PEDIDO_SELECT`) | Eager loading `with(['obra','status','priority','responsible'])` em todas as listagens/Kanban/dashboard; `events()->with(['eventType','actor'])` nos detalhes; `app/Services/PedidoEventValuePresenter.php` resolve ids → nomes na timeline | `tests/Feature/Performance/QueryCountTest.php` (4 telas, 5 vs 50 pedidos); `AcompanhamentoTest`/`TodosPedidosFiltersTest` ("query count stays constant...") | ✅ |
| M-09 | `pedido_events`: `pedido_id`, `event_type_id`, `previous_value? text`, `new_value? text`, `actor_id`, `created_at` — sem `updated_at`, insert-only | `2026_09_18_230115_create_pedido_events_table.php` (idêntico; `cascadeOnDelete` em `pedido_id`); `PedidoEvent` (`UPDATED_AT = null`, guards) | `MigrationSchemaTest` ("pedido_events table is append-only with no update-capable column"); `PedidoEventImmutabilityTest` | ✅ |
| M-10 | Índices `idx_pedidos_obra_id_status_id`, `idx_pedidos_needed_at`, `idx_pedido_events_pedido_id_created_at` | `2026_09_18_230116_add_pedidos_query_indexes.php` (mesmos 3 índices) | `MigrationSchemaTest` ("pedidos table has the query indexes required by RNF-07", "pedido_events has the query index required for the history timeline") | ✅ |
| M-11 | Seed: `supabase/seed.sql` (lookups idempotentes) + `lib/demo/{seed,data,reset}.ts` (dataset `is_demo=true`, prefixo `[DEMO]`) + `scripts/seed-demo.ts` / `scripts/reset-demo.ts` | `database/seeders/DemoSeeder.php` (lookups + dataset, idempotente por chave natural); `app/Console/Commands/ResetDemoData.php` (`demo:reset`, só `is_demo = true`) | `DemoSeederIdempotencyTest` (4 testes); `tests/Feature/Console/ResetDemoDataTest.php` (2 testes) | ⚠️ credenciais demo diferentes — DV-09 |
| M-12 | Migrations: 11 arquivos SQL do Supabase CLI | 13 migrations Laravel (`php artisan migrate` do zero, sem dependência das migrations Supabase) | `tests/Feature/FreshMigrationTest.php`; `MigrationSchemaTest` | ✅ |
| M-13 | Access control: RLS em toda tabela `public`; service-role key bypassa RLS nos caminhos server-trusted | Policies/Gates/middleware (DV-01); credencial única `DB_*` via env (RF-04) | `tests/Feature/DatabaseConnectionTest.php`; Seção 1 | ⚠️ DV-01 |
| M-14 | Generated types `lib/types/database.ts` | N/A — Eloquent Models tipados via PHPDoc/casts | `PedidoModelTest`, `LookupModelsTest` | N/A (artefato de tooling TS) |
| M-15 | Cache: nenhum cache de dados; só invalidação de rota Next (`revalidatePath`) | N/A — sem cache de dados; `CACHE_STORE=database` apenas para infraestrutura do framework | — | N/A (ver A-10) |

---

## 4. Requisitos do PRD (`docs/product/PRD-V1.md` §28)

| PRD | Requisito | IDs SPEC | Equivalente Laravel (resumo) | Status |
|---|---|---|---|---|
| RF-001 | Autenticação | RF-07 | `LoginForm`, guard `web`, `bcrypt` | ✅ |
| RF-002 | Autorização por perfil e obra | RF-08/08b/08c, RF-09, RF-10 | Gates + `PedidoPolicy` + middleware | ✅ |
| RF-003 | Nova solicitação (Obra) | RF-11, RF-11b, RF-11c | `NovaSolicitacao` → `CreatePedidoAction` | ✅ |
| RF-004 | Persistência da solicitação | RF-11, RF-05 | `pedidos` + `pedido_events` | ✅ |
| RF-005 | Workflow de status | RF-13 | `StatusSlug` + `UpdatePedidoStatusAction` | ✅ |
| RF-006 | Meus pedidos (Obra) | RF-11, UI-01 | `Acompanhamento` | ✅ |
| RF-007 | Todos os pedidos (Suprimentos) | RF-12, UI-02 | `Suprimentos\TodosPedidos` | ⚠️ filtros de select (obra/status/prioridade/responsável) do AS IS não presentes — DV-10 |
| RF-008 | Gestão de responsável | RF-14, RF-14b | `UpdatePedidoResponsavelAction` | ❓ DV-06 |
| RF-009 | Prioridade | RF-15 | `UpdatePedidoPrioridadeAction` | ✅ |
| RF-010 | Previsão de entrega | RF-16 | `UpdatePedidoPrevisaoAction` | ❓ DV-07 |
| RF-011 | Alteração de status | RF-13, RF-13b | `UpdatePedidoStatusAction` | ✅ |
| RF-012 | Kanban (Suprimentos) | UI-03, UI-07, UI-08 | `KanbanBoard` (`wire:sort` + controle acessível) | ✅ |
| RF-013 | Kanban Gestão (leitura) | RF-20, UI-05 | `Gestao\KanbanReadOnly` | ✅ |
| RF-014 | Histórico | RF-18 | 7 event types, `PedidoEvent` imutável | ✅ |
| RF-015 | Dashboard | RF-21, UI-06 | `Gestao\Dashboard` + `DashboardIndicatorsService` (6 indicadores) | ✅ |
| RF-016 | Atrasos | RF-19, RF-19b, RF-19c | 3 classifiers | ✅ |
| RF-017 | Filtros em listagens, Kanban e dashboard | RF-12, RF-20, RF-21 | Listagens: busca/atraso/2 intervalos; Dashboard: período/obra/status/prioridade/responsável; Kanban: sem filtros (idêntico ao AS IS `app/suprimentos/page.tsx`, que não tinha filtros) | ⚠️ DV-10 |
| RF-018 | Detalhe | RF-11, RF-12, RF-20 | `Obra\PedidoDetalhe`, `Suprimentos\PedidoDetalhe`, `Gestao\PedidoDetalhe` + `components/pedido-history-timeline.blade.php` | ✅ |
| RF-019 | Segurança de acesso | RF-09, RF-10, RNF-08 | Policies, middleware, CSRF, `#[Fillable]`, escaping | ✅ |
| RF-020 | Auditoria (autoria e data/hora) | RF-18 | `pedido_events.actor_id` + `created_at` | ✅ |

---

## 5. Requisitos RIGID desta SPEC → implementação e testes

| ID | Equivalente Laravel | Teste(s) / verificação | Status |
|---|---|---|---|
| RF-01 | `composer.json`, `artisan`, `.env.example`; README §Instalação | `tests/Feature/BootstrapTest.php`; walkthrough README (RNF-02) | ✅ |
| RF-02 | `laravel/boost` em `require-dev`; `boost.json`; `.mcp.json`; `CLAUDE.md`/`AGENTS.md`; README §Laravel Boost | `composer.json` (verificação manual desta auditoria: presente) | ✅ |
| RF-03 | Toda tela interativa é um componente Livewire (`app/Livewire/**`) | `tests/Feature/LivewireSmokeTest.php`; `tests/Feature/Livewire/*` (`Livewire::test(...)`) | ✅ |
| RF-04 | `config/database.php` default `pgsql`; `DB_*` via env; sem SDK Supabase | `DatabaseConnectionTest`; `NoSupabaseDependencyTest` | ✅ |
| RF-05 | 13 migrations | `FreshMigrationTest`; `MigrationSchemaTest` | ✅ |
| RF-06 | `DemoSeeder` (idempotente) + `demo:reset` | `DemoSeederIdempotencyTest`; `ResetDemoDataTest` | ✅ |
| RF-07 | `LoginForm`; `password => 'hashed'` cast (bcrypt) | `LoginTest` ("the stored password is hashed, never plaintext", credenciais inválidas) | ✅ |
| RF-08 | `PedidoPolicy` (obra: view escopado, sem mutações); rotas `obra/*` | `PedidoPolicyTest` ("obra is denied every operational mutation") | ✅ |
| RF-08b | `PedidoPolicy` (suprimentos: 5 mutações) | `PedidoPolicyTest` ("suprimentos is permitted every operational mutation"); `PedidoDetalheSuprimentosTest` | ✅ |
| RF-08c | `PedidoPolicy` (gestao: leitura, sem mutações); `KanbanReadOnly` sem controles | `PedidoPolicyTest` ("gestao is denied every operational mutation"); `GestaoKanbanReadOnlyTest` ("no mutation control is rendered...") | ✅ |
| RF-09 | Autorização em Policy/Gate/middleware + guard nas Actions (independe da UI) | `BypassUiAuthorizationTest` (6 testes); `SuprimentosScreensRouteTest` | ✅ |
| RF-10 | `PedidoPolicy::view()`; `Acompanhamento` escopada; multi-obra | `PedidoPolicyTest` (2 testes de isolamento); `PedidoDetalheObraTest` ("...denied"); `DemoSeederIdempotencyTest` ("demo dataset includes a multi-obra obra user") | ✅ |
| RF-11 | Login → `NovaSolicitacao` → `Acompanhamento` → `Obra\PedidoDetalhe` (histórico) | `PedidoDetalheObraTest` ("the criacao_pedido event is visible after the full creation flow"); `tests/Feature/Livewire/ObraScreensRouteTest.php`; `DemoRoteiroTest` (passos 1–4) | ✅ |
| RF-11b | Validação server-side dos 3 campos | `CreatePedidoActionTest`; `NovaSolicitacaoTest` | ✅ |
| RF-11c | `obra_id` ∈ `obra_profile` validado na Action | `CreatePedidoActionTest`; `NovaSolicitacaoTest`; `BypassUiAuthorizationTest` | ✅ |
| RF-12 | 5 controles em `Suprimentos\PedidoDetalhe` + Kanban; listagem com busca/"Atraso"/`neededAtFrom-To`/`requestedFrom-To` | `PedidoDetalheSuprimentosTest`; `TodosPedidosFiltersTest` (7 testes) | ✅ |
| RF-13 | `UpdatePedidoStatusAction` (matriz) | `UpdatePedidoStatusActionTest` (parametrizado) | ✅ |
| RF-13b | `ensurePedidoIsNotTerminal()` nas 5 Actions; `PedidoTerminalStateException` (409) | `*ActionTest` ("a terminal pedido rejects the mutation"); `KanbanForgedMoveTest` | ✅ |
| RF-14 | `UpdatePedidoResponsavelAction` (evento com anterior/novo; no-op) | `UpdatePedidoResponsavelActionTest` | ✅ (ver DV-06 para `null`) |
| RF-14b | `ResponsibleMustBeSuprimentos` + `User::scopeSuprimentos()` no seletor | `ResponsibleMustBeSuprimentosTest` (4 testes); `UpdatePedidoResponsavelActionTest` ("...rejected even via a tampered payload") | ✅ |
| RF-15 | `UpdatePedidoPrioridadeAction` (`exists:priorities,id`; 4 literais em `PrioritySlug`) | `UpdatePedidoPrioridadeActionTest`; `SlugEnumsTest` ("PrioritySlug matches the 4 RIGID priority literals exactly") | ✅ |
| RF-16 | `UpdatePedidoPrevisaoAction`; previsão exibida em `components/pedido-summary.blade.php` (3 perfis) e no card Kanban | `UpdatePedidoPrevisaoActionTest`; `PedidoCardRenderTest`; `DemoRoteiroTest` (passos 9, 12) | ✅ |
| RF-17 | `CancelPedidoAction` (irreversível) | `CancelPedidoActionTest`; `CancelPedidoControlTest` | ✅ |
| RF-17b | `ensureActorIsSuprimentos()` + `PedidoPolicy::cancelar` | `CancelPedidoActionTest` ("an obra actor is rejected", "a gestao actor is rejected") | ✅ |
| RF-18 | 7 mutações → 7 event types; imutabilidade; sem rota de update/delete | `PedidoEventImmutabilityTest`; `*ActionTest`; `MigrationSchemaTest` | ✅ |
| RF-19 | `AtrasoClassifier` | `AtrasoClassifierTest` (4 combinações) | ✅ |
| RF-19b | `PendenteClassifier` | `PendenteClassifierTest` (7 status) | ✅ |
| RF-19c | `PrazoClassifier::VENCENDO_EM_BREVE_DIAS = 3` | `PrazoClassifierTest` (3 testes) | ✅ |
| RF-20 | `Gestao\Dashboard`, `Gestao\TodosPedidos` (mesmo filtro de RF-12), `Gestao\KanbanReadOnly`, `Gestao\PedidoDetalhe` — sem escrita | `GestaoKanbanReadOnlyTest` (5 testes, inclui "the read-only listing filter set matches the Suprimentos listing filter set"); `PedidoDetalheGestaoTest`; `PedidoPolicyTest` | ✅ |
| RF-21 | `DashboardIndicatorsService::compute()` (6 indicadores, mesma fonte + classifiers) + 5 filtros em `Gestao\Dashboard` | `DashboardIndicatorsTest` (7 testes); `tests/Feature/Livewire/DashboardFiltersTest.php` (6 testes) | ✅ |
| RF-22 (opcional) | `Dashboard::drillDownUrl()` → `Gestao\TodosPedidos` com `atrasado=true`/`pendente=true` | `tests/Feature/Livewire/DashboardDrillDownTest.php` (3 testes) | ✅ |
| RF-23 | Suíte Pest (Unit/Feature/Browser); mapa de cobertura em `tests/README.md` | `composer test` exit 0 (T51); `tests/README.md` (18 temas do brief §30, sem item não coberto) | ✅ |
| RF-24 | `tests/Browser/DemoRoteiroTest.php` (19 passos do brief §31) | o próprio teste (Playwright/Chromium headless) | ✅ |
| RF-25 | Este documento | auditoria manual (AC-33) | ✅ |
| RF-26 | Models `Role`, `Status`, `Priority`, `EventType`, `User`, `Obra`, `Pedido`, `PedidoEvent`; pivot `obra_profile` | `LookupModelsTest`; `PedidoModelTest`; `ObraProfileCardinalityTest` | ✅ (adaptações DV-02) |
| UI-01 | `resources/views/auth/login.blade.php`, `livewire/obra/nova-solicitacao`, `acompanhamento`, `pedido-detalhe` | `ObraScreensRouteTest`; `LoginFormTest`; `AcompanhamentoTest` | ✅ |
| UI-02 | `livewire/suprimentos/todos-pedidos`, `pedido-detalhe`, `livewire/kanban/kanban-board` | `SuprimentosScreensRouteTest`; `PedidoDetalheSuprimentosTest`; `TodosPedidosFiltersTest` | ✅ |
| UI-03 | `KanbanBoard::columns()` (5, por `sort_order`, sem `cancelado`); `livewire/kanban/pedido-card.blade.php` (7 campos `data-field`, classe `pedido-atrasado`) | `KanbanBoardTest` ("the 5 active columns render in sort_order and cancelado is excluded"); `PedidoCardRenderTest` (2 testes) | ✅ |
| UI-04 | `livewire/obra/pedido-detalhe.blade.php` sem formulário de edição | `PedidoDetalheObraTest` ("no edit form or mutation control is rendered") | ✅ |
| UI-05 | `livewire/gestao/kanban-read-only.blade.php` + `pedido-card-read-only.blade.php` sem `wire:sort`/`wire:click` | `GestaoKanbanReadOnlyTest` ("no mutation control is rendered on the read-only kanban or listing") | ✅ |
| UI-06 | `livewire/gestao/dashboard.blade.php` (6 indicadores + 5 filtros) | `DashboardIndicatorsTest` ("...renders all 6 indicators for a gestao actor"); `DashboardFiltersTest` | ✅ |
| UI-07 | `KanbanBoard::moveCard()` → Policy + Action; rejeição não altera a UI | `KanbanForgedMoveTest` (3 testes: `cancelado`, terminal, ator não-suprimentos) | ✅ |
| UI-08 | `KanbanBoard::moveViaControl()` + select/botão no card | `AccessibleStatusControlTest` | ✅ |
| RNF-01 | `vite.config.js`, `package.json` `build` | `npm run build` exit 0 (verificado em T51/T54; sem `next build`) — `NoNextJsDependencyTest` | ✅ |
| RNF-02 | README §Instalação (10 passos do brief §43) + credenciais demo | walkthrough manual (T55) | ✅ |
| RNF-03 | Sem Supabase em `composer.json`/PHP/Blade/JS | `NoSupabaseDependencyTest` (3 testes) | ✅ |
| RNF-04 | Sem Next.js/React no runtime; `app/` Next removido em T54 | `NoNextJsDependencyTest` (4 testes) | ✅ |
| RNF-05 | README: instalação, `.env`, migrations, seed, execução, testes, credenciais demo (sem segredos reais) | walkthrough manual (T55); grep de segredos (T58, Fase 14) | ✅ |
| RNF-06 | `.env.example`; README §Produção; `.gitignore` cobre `.env` | Fase 14 (T57/T58) completa a documentação de deploy e o teste de segredos | ✅ (base) — detalhamento em T57/T58 |
| RNF-07 | `paginate(10)` em `Acompanhamento`/`TodosPedidos` (Suprimentos e Gestão); eager loading em todas as telas | `QueryCountTest` (4 telas); `AcompanhamentoTest`/`TodosPedidosFiltersTest` | ✅ |
| RNF-08 | CSRF nativo (Livewire + `POST /logout`); `#[Fillable]` em todos os Models; Blade `{{ }}`; `bcrypt` | `CsrfProtectionTest` (4); `MassAssignmentTest` (3); `tests/Feature/Security/BladeEscapingTest.php` (4); `LoginTest` | ✅ |
| CT-01 | 6 mutações expostas como ações Livewire autorizadas, com erro estruturado (422/403/409/404) e evento transacional | Seção 2 (A-03 a A-09) | ✅ |

---

## 6. Critérios de conclusão do brief §47 (AC-01 … AC-33)

| AC | Critério | Evidência | Status |
|---|---|---|---|
| AC-01 | aplicação Laravel instalar corretamente | README §Instalação; `BootstrapTest` | ✅ |
| AC-02 | Laravel Boost instalado/configurado | `composer.json` (`laravel/boost`), `boost.json`, `.mcp.json`, README §Boost | ✅ |
| AC-03 | Livewire funcional | `LivewireSmokeTest`; `tests/Feature/Livewire/*` | ✅ |
| AC-04 | PostgreSQL funcional | `DatabaseConnectionTest` | ✅ |
| AC-05 | migrations do zero | `FreshMigrationTest` | ✅ |
| AC-06 | seed demo | `DemoSeederIdempotencyTest` | ✅ |
| AC-07 | autenticação | `LoginTest`, `LoginFormTest` | ✅ |
| AC-08 | três perfis | `RoleGatesTest`, `PedidoPolicyTest`, `UnauthenticatedAccessTest` ("the home route lands each papel...") | ✅ |
| AC-09 | autorização backend | `BypassUiAuthorizationTest` | ✅ |
| AC-10 | isolamento por obra | `PedidoPolicyTest`, `AcompanhamentoTest`, `PedidoDetalheObraTest` | ✅ |
| AC-11 | fluxo Obra | `NovaSolicitacaoTest`, `AcompanhamentoTest`, `PedidoDetalheObraTest`, `ObraScreensRouteTest` | ✅ |
| AC-12 | fluxo Suprimentos | `TodosPedidosFiltersTest`, `PedidoDetalheSuprimentosTest`, `SuprimentosScreensRouteTest` | ✅ |
| AC-13 | Kanban | `KanbanBoardTest`, `PedidoCardRenderTest`, `AccessibleStatusControlTest` | ✅ |
| AC-14 | workflow protegido | `UpdatePedidoStatusActionTest`, `KanbanForgedMoveTest` | ✅ |
| AC-15 | responsável | `UpdatePedidoResponsavelActionTest`, `ResponsibleMustBeSuprimentosTest` | ✅ (DV-06 aberto) |
| AC-16 | prioridade | `UpdatePedidoPrioridadeActionTest` | ✅ |
| AC-17 | previsão | `UpdatePedidoPrevisaoActionTest` | ✅ (DV-07 aberto) |
| AC-18 | cancelamento | `CancelPedidoActionTest`, `CancelPedidoControlTest` | ✅ |
| AC-19 | histórico | `PedidoEventImmutabilityTest`, `PedidoDetalheObraTest`, `PedidoDetalheGestaoTest` | ✅ |
| AC-20 | cálculo de atraso | `AtrasoClassifierTest`, `PendenteClassifierTest`, `PrazoClassifierTest` | ✅ |
| AC-21 | Gestão | `GestaoKanbanReadOnlyTest`, `PedidoDetalheGestaoTest`, `DashboardIndicatorsTest` | ✅ |
| AC-22 | dashboard | `DashboardIndicatorsTest`, `DashboardFiltersTest`, `DashboardDrillDownTest` | ✅ |
| AC-23 | Kanban read-only Gestão | `GestaoKanbanReadOnlyTest` | ✅ |
| AC-24 | testes automatizados passarem | `composer test` — 289 testes / 808 asserções verdes (log `.phases/logs/phase-12.test-1.log`) e reexecutado nesta fase | ✅ |
| AC-25 | build frontend | `npm run build` exit 0 | ✅ |
| AC-26 | aplicação iniciar localmente | README §Instalação passo 8–9 (walkthrough) | ✅ |
| AC-27 | demo data disponível | `DemoSeeder` + README §Credenciais | ✅ |
| AC-28 | roteiro oficial ponta a ponta | `tests/Browser/DemoRoteiroTest.php` | ✅ |
| AC-29 | sem Supabase | `NoSupabaseDependencyTest` | ✅ |
| AC-30 | sem Next.js | `NoNextJsDependencyTest` | ✅ |
| AC-31 | documentação de execução atualizada | `README.md` (T55) | ✅ |
| AC-32 | preparada para Railway | `.env.example` + README §Produção (base); T57/T58 (Fase 14) completam | ✅ (base) — Fase 14 |
| AC-33 | nenhum requisito relevante perdido silenciosamente | este documento (Seções 1–5 completas; divergências na Seção 7) | ✅ |

---

## 7. Divergências documentadas e `[NEEDS CLARIFICATION]`

Registro explícito de toda diferença encontrada entre o código AS IS (Next.js/Supabase, commit
`e320806`) e a reimplementação Laravel. Nada abaixo foi resolvido silenciosamente a favor de um
dos lados: cada item aponta a decisão que o justifica ou permanece aberto.

| ID | Divergência | Onde (AS IS → Laravel) | Justificativa / decisão | Status |
|---|---|---|---|---|
| DV-01 | Autorização dupla (checks no app **e** RLS no Postgres, incl. grants por coluna e funções `SECURITY DEFINER`) substituída por autorização única no backend Laravel (middleware `auth` + Gates + `PedidoPolicy` + guards nas Actions), sem RLS | `supabase/migrations/20260916150500_add_rls_policies.sql` → `PedidoPolicy`, `AppServiceProvider`, `GuardsOperationalMutation`, `routes/web.php` | SPEC TO BE ("substitui `proxy.ts`+RLS por middleware `auth:web` + `PedidoPolicy`"); RF-09 exige Policies/Gates/middleware. A defesa "por coluna" da RLS é reproduzida pela inexistência de qualquer caminho de escrita para `needed_at`/`items_description`/`obra_id` pós-criação e pelo `#[Fillable]`. | ⚠️ documentada |
| DV-02 | Adaptações físicas de schema: `profiles` absorvida por `users` (`full_name` → `name`); pivot `obra_profile.profile_id` → `user_id`; chaves `uuid` → `bigint` auto-increment | `data_model.md` → migrations `2026_09_18_2301*` | Brief §15 (identidade users-centric permitida); SPEC RF-26 congela entidades/relacionamentos, não nomes físicos; PLAN Assumptions. Cardinalidade N:N preservada (`ObraProfileCardinalityTest`). | ⚠️ documentada |
| DV-03 | Semântica de "mesmo status": AS IS `updatePedidoStatus` não tratava alvo igual ao atual como no-op (gravava UPDATE + evento redundante); Laravel rejeita como "Transição de status inválida." na Action/detalhe e ignora silenciosamente o drop na própria coluna no Kanban (sem evento). As mensagens "status_id inválido." e "Use cancelPedido para cancelar um pedido." colapsam em 404 (`findOrFail`) e "Transição de status inválida." respectivamente | `lib/pedidos/service.ts::updatePedidoStatus` → `UpdatePedidoStatusAction`, `KanbanBoard::moveCard` | Q-03 (FLEXIBLE: preservar significado/condição de disparo, não texto literal); `domain_rules.md` documenta as 5 mutações como no-op quando o valor não muda — o comportamento Laravel é mais estrito que o código AS IS (evita evento espúrio) e igual à regra documentada no Kanban. Nenhum evento é gravado em nenhum dos dois casos. | ⚠️ documentada |
| DV-04 | Valores de `previous_value`/`new_value` em `pedido_events`: AS IS gravava slugs de status (`mudanca_status`/`entrega`/`cancelamento`) e uuids de perfis/prioridades; Laravel grava ids bigint (string) para status/prioridade/responsável e datas ISO para previsão, e resolve para nomes legíveis na timeline via `PedidoEventValuePresenter` | `service.ts::insertEvent` → Actions + `app/Services/PedidoEventValuePresenter.php` | Coluna `text` preservada (M-09); conteúdo é detalhe de representação (FLEXIBLE); a timeline exibe nomes em todos os perfis (`PedidoDetalhe*Test`). | ⚠️ documentada |
| DV-05 | Login rejeita usuário com `is_active = false` (mesma mensagem genérica); AS IS (`lib/auth/service.ts`) não verificava `is_active` no login | `signIn` → `LoginForm::authenticate()` | Hardening de segurança coerente com o campo `is_active` já existente no AS IS e com brief §44; não amplia escopo de produto. Coberto por `LoginTest` ("a deactivated user does not authenticate even with valid credentials"). | ⚠️ documentada (adição de segurança) |
| DV-06 | Remoção de responsável: AS IS `setResponsavel(pedidoId, null)` / opção "Sem responsável" no `responsavel-control.tsx` permitia desatribuir; Laravel `UpdatePedidoResponsavelAction` exige `responsible_id` (`required` + `ResponsibleMustBeSuprimentos`) — não há caminho para voltar a "sem responsável" | `components/pedidos/responsavel-control.tsx`, `service.ts::updatePedidoResponsavel` → `UpdatePedidoResponsavelAction` | SPEC v1.1 Q-04/RF-14b endureceu o seletor para "usuários com papel `suprimentos`" e RF-14 fala em "atribui ou altera"; `null` não é um usuário `suprimentos`, e a SPEC não decidiu explicitamente sobre desatribuição. Não resolvido silenciosamente. | ❓ `[NEEDS CLARIFICATION]` — a V0 Laravel deve permitir desatribuir responsável (voltar a "Sem responsável", gerando `alteracao_responsavel` com `new_value = null`)? |
| DV-07 | Limpeza de previsão: AS IS `setPrevisao(pedidoId, null)` (calendário permitia desmarcar) permitia limpar a previsão; Laravel `UpdatePedidoPrevisaoAction` exige data válida (`required`, `date`) | `components/pedidos/previsao-control.tsx`, `service.ts::updatePedidoPrevisao` → `UpdatePedidoPrevisaoAction` | RF-16 descreve "informa ou altera a previsão ... para uma data diferente"; a SPEC não decidiu sobre limpar a previsão. Não resolvido silenciosamente. | ❓ `[NEEDS CLARIFICATION]` — a V0 Laravel deve permitir limpar a previsão (gerando `alteracao_previsao` com `new_value = null`)? |
| DV-08 | Códigos dos pedidos seedados: `PED-DEMO-0001..0006` (fora do padrão `^PED-\d{6}$`), enquanto pedidos criados pela aplicação seguem `PED-000001` via sequence | `lib/demo/data.ts` → `DemoSeeder::seedPedidos()` | SPEC FLEXIBLE ("identificador legível ... manter o padrão atual é aceitável, mas não obrigatório, desde que ... único, estável e legível"); namespace `DEMO` evita colisão com a sequence e torna o dado demo reconhecível. O gerador da aplicação preserva o padrão (`PedidoCodeGeneratorTest`). | ⚠️ documentada |
| DV-09 | Credenciais de demonstração: AS IS `demo.obra1@sistema-obra.demo` … / `Demo@12345` (2 obra, 2 suprimentos, 1 gestão); Laravel `obra.demo@example.com`, `obra.multiobra.demo@example.com`, `suprimentos.demo@example.com`, `gestao.demo@example.com` / `password` | `lib/demo/data.ts` → `DemoSeeder::seedUsers()` | Brief §29 exige apenas usuários conhecidos por perfil, documentados (README §Credenciais); e-mails/senha demo são FLEXIBLE. Cobertura de perfis preservada (obra, obra multi-obra, suprimentos, gestão). Segundo usuário `suprimentos` do AS IS não reproduzido — sem requisito RIGID; o seletor de responsável e RF-14b são testados com usuários de factory. | ⚠️ documentada |
| DV-10 | Filtros da listagem "Todos os Pedidos": a barra AS IS (`components/pedidos/pedidos-filter-bar.tsx`) expunha Buscar, **Obra, Status, Prioridade, Responsável**, Data necessária (de/até), Solicitado (de/até) e Atraso; Laravel (`Suprimentos\TodosPedidos`, `Gestao\TodosPedidos`) expõe Buscar, Atraso, Data necessária (de/até) e Solicitado (de/até) — os 4 filtros de seleção não estão na listagem (permanecem no dashboard). Consequência: user story "Pedidos cancelados permanecem consultáveis via listagem/filtro por status `Cancelado`" (`user-stories.md:248`) é atendida por listagem/busca/detalhe, mas não por um filtro de status dedicado | `pedidos-filter-bar.tsx`, `lib/pedidos/filters.ts` → `Suprimentos\TodosPedidos`, `Gestao\TodosPedidos` | SPEC v1.1 Q-02 fixou (RIGID, RF-12/RF-20/UI-02) o conjunto de filtros da listagem como "busca livre, Atraso booleano, `neededAtFrom/To`, `requestedFrom/To`", "distinto do conjunto de filtros do dashboard"; a implementação segue a SPEC confirmada. A diferença em relação ao código AS IS é registrada aqui, não silenciada. | ❓ `[NEEDS CLARIFICATION]` — reincluir os filtros Obra/Status/Prioridade/Responsável na listagem "Todos os Pedidos" (paridade com a barra AS IS), ou manter a decisão Q-02? |
| DV-11 | Paginação: AS IS `listPedidos` retornava a lista completa; Laravel pagina (10/página) as listagens de Obra/Suprimentos/Gestão | `lib/pedidos/queries.ts` → `paginate(10)` | RNF-07 (RIGID) exige paginação; brief §45. Kanban e dashboard continuam agregando o conjunto completo. | ⚠️ documentada (exigência da SPEC) |
| DV-12 | Sessão expirada: AS IS retornava `{error: "Sessão expirada. Faça login novamente."}` na própria ação; Laravel redireciona para `/login` (middleware `auth`) e, em requisições Livewire, responde 419 (token/sessão expirada) exigindo recarregar | `runPedidoMutation` → middleware `auth`, CSRF nativo | CT-01 declara o formato de erro FLEXIBLE; a condição de disparo (sem sessão → não executa) é preservada (`UnauthenticatedAccessTest`, `CsrfProtectionTest`). | ⚠️ documentada |
| DV-13 | `AGENTS.md`/`CLAUDE.md`: no AS IS eram gerados pelo Next.js ("not the Next.js you know"); agora são as guidelines do Laravel Boost | PLAN Open Questions | Substituído em T03/T54 pelas guidelines do Boost (RF-02). | ✅ resolvido |
| DV-14 | Ferramenta E2E: PLAN assumia Laravel Dusk; implementado com `pestphp/pest-plugin-browser` (Playwright) | PLAN Open Questions/Assumptions → `tests/Browser/DemoRoteiroTest.php` | RF-24 aceita "script E2E automatizado"; ferramenta é FLEXIBLE; Pest já é o runner do projeto. | ✅ resolvido |

### Itens fora de escopo (brief §40 / SPEC Scope → Out) — não implementados por decisão

| Item | Referência |
|---|---|
| ERP, fornecedores, cotações, financeiro, pagamentos, catálogo/SKU obrigatório, entrega parcial, notificações externas, aprovações complexas, integrações externas, microserviços, Redis/filas/workers/cron | brief §40; SPEC Scope Out |
| Self-signup público (trigger `handle_new_user()` do AS IS não reproduzido — provisionamento via seed/administração) | brief §21, §9; SPEC Scope Out |
| Edição da solicitação pela Obra após o envio | brief §14; SPEC Scope Out; UI-04 |
| Reabertura de pedido terminal (`entregue`/`cancelado`) | `domain_rules.md` ("Irreversible"); SPEC Scope Out |

### Resumo

- Linhas auditadas: 33 regras de domínio (Seção 1), 11 contratos de ação (Seção 2), 15 aspectos
  do modelo de dados (Seção 3), 20 RF do PRD (Seção 4), 52 requisitos RIGID (Seção 5), 33 AC (Seção 6).
- Divergências registradas: 14 (DV-01 … DV-14), das quais **3 permanecem abertas como
  `[NEEDS CLARIFICATION]`** (DV-06 desatribuir responsável; DV-07 limpar previsão; DV-10 filtros de
  seleção na listagem) — todas com o comportamento atual claramente descrito e testado, aguardando
  decisão de produto antes de qualquer alteração.
- Nenhum requisito foi removido sem registro; itens não implementados constam apenas na tabela de
  fora de escopo, com referência ao brief §40 / SPEC Scope Out.
