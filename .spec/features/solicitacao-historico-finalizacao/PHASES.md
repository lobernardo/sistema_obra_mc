# Phases: solicitacao-historico-finalizacao

Gerado por /plan a partir de PLAN.md — view executável para `./ralph.sh .spec/features/solicitacao-historico-finalizacao/PHASES.md`.
Branch: `build/v0-demo-laravel` · Base: commit que fecha a fatia 1 · 39 tarefas em 10 fases · fatia 2 de 3 (a fatia 1 é pré-requisito, a fatia 3 fica fora de escopo). SPEC v1.2 (correções F-01..F-17 da revisão cruzada), sem marcadores abertos. Revisão cruzada v2 (2026-09-23): D-1/N-02 aprovado (T21, T23, T24 + allow-list de T34), N-06 (T10, T11); defaults D-5, D-8 e D-9 confirmados.

**Gates de execução (não são tarefas). Detalhes no topo do PLAN.md:**
- **G-1 (commit documental, por referência ao gate da fatia 1):** `git status --short docs/agents` precisa estar vazio antes do Ralph. Qualquer pendência vai para um commit só de `docs/agents/*.md`.
- **G-2 (fatia 1 mergeada e validada):** as 9 fases da fatia 1 estão no HEAD (8 commits `feat(phase-N)` + o commit somente de documentação da Fase 9, ou o equivalente feito pelo desenvolvedor). `ObraStatus`, `Obra::active()` por status, as rotas `obras.*`/`associacoes.*`, o gate `manage-obras`, as 4 migrations da fatia 1, a seção "Convites" em `resources/views/livewire/obras/form.blade.php` e `tests/Feature/Auth/ZeroObraUserTest.php` existem. A suíte da fatia 1 está verde, o desenvolvedor validou a fatia 1 e a árvore está limpa.
- **G-3 (antes do merge das Fases 1 e 2):** o Claude executa em produção, via Railway e somente leitura, `select count(*) from pedidos where requested_at is null;` (Fase 1) e `select slug, sort_order from statuses order by sort_order;` (Fase 2), apenas depois de o desenvolvedor confirmar naquele momento.
- **G-4 (antes do push):** o desenvolvedor faz o `git push` desta fatia somente depois do runbook de T32: Volume Railway montado, `PEDIDO_ANEXOS_ROOT` e `PHP_INI_SCAN_DIR` definidos, limites conferidos e usuários Suprimentos associados às obras. Nenhum deploy acontece durante o Ralph.

Regras válidas para todas as fases:
- **Toda fase fecha verde** (F-04): ao fim de cada fase, `php artisan test --compact --testsuite=Feature` e `--testsuite=Unit` passam sem falhas conhecidas.
- Código PHP compatível com **8.4**. Rodar `vendor/bin/pint --dirty --format agent` antes de finalizar qualquer mudança PHP.
- Criar arquivos com `php artisan make:* --no-interaction`. **Nenhuma dependência nova** (Composer/npm). Não exigir `ext-calendar`. Nenhuma pasta-base nova.
- Componentes Livewire nunca gravam direto: `mount()` re-checa a habilidade, cada método mutante chama `authorize()` e delega a uma Action. A Action valida (PT-BR), guarda o ator e o estado terminal, e grava mutação + exatamente 1 `pedido_events` no mesmo `DB::transaction`.
- Validações e guardas rodam **antes** da transação e antes de `PedidoCodeGenerator::generate()`, porque o `nextval` não volta atrás.
- `Pedido::visibleTo` abre toda consulta de listagem na mesma instrução. "Obra ativa" é consumida da fatia 1 (`Obra::active()`), nunca reimplementada.
- Calendário local: todo "dia" e toda exibição de data/hora passam por `App\Support\LocalTime` (`America/Sao_Paulo`); `config('app.timezone')` continua `UTC`; timestamps são comparados em intervalos UTC semiabertos, nunca com `whereDate`; colunas `date` nunca são deslocadas.
- Textos de interface em PT-BR, identificadores em inglês com substantivos de domínio em português, segmentos de URL em PT-BR. Classes Tailwind literais, sem `{!! !!}`, marca só via `config('app.name')`.
- Um processo Pest por vez contra o PostgreSQL de teste em `127.0.0.1:5434`. `--filter` sem `--testsuite=Feature` arrasta `tests/Browser`.
- Testes são **atualizados, nunca apagados**. Só mudam as asserções e fixtures que o PLAN lista explicitamente (allow-list de T34: T05, T06, T08, T10, T11, T12, T13, T14, T15, T16, T21, T23, T24, T37, T28, T30). T21/T23/T24 entram só com os trechos do D-1/N-02: `PedidoDetalheObraTest:34`, `PedidoDetalheGestaoTest:43` e o teste de `PedidoDetalheObraTest:36-53`; `PedidoDetalheGestaoTest:61-62` fica inalterado.

## Phase 1: Fundação de dados — calendário, LocalTime, colunas de pedidos, hook da Data prevista e anexos

Antes de implementar, leia:
1. `.spec/features/solicitacao-historico-finalizacao/SPEC.md` — requisitos RIGID que esta fase cobre (RF-06, RF-09, RF-10, RF-11, RF-12, RF-19, RF-30, RF-42, RF-45..RF-47 (ponto de conversão), CT-02, CT-03, CT-06, CT-10, RNF-05, RNF-06)
2. `.spec/features/solicitacao-historico-finalizacao/PLAN.md` — decomposição completa, dependências e riscos

Antes do merge desta fase vale o gate G-3 (`requested_at` sem nulos): o Railpack executa `migrate` a cada start do container. As duas migrations são criadas nesta ordem, T02 < T03, e ambas depois das migrations da fatia 1. T02 e T35 vão no mesmo commit (sem o hook, o NOT NULL quebra todo insert). Esta fase **não** cria as linhas de lookup (ficam na Fase 2).

- [ ] T01 — Calendário de dias úteis, `DataPrevistaCalculator` e ponto único de conversão `LocalTime` (PHP puro)
      Arquivos: `app/Domain/Pedidos/BrazilianNationalHolidays.php`, `app/Domain/Pedidos/DataPrevistaCalculator.php`, `app/Support/LocalTime.php` (novos) e os testes `tests/Unit/Domain/BrazilianNationalHolidaysTest.php`, `tests/Unit/Domain/DataPrevistaCalculatorTest.php`, `tests/Unit/Support/LocalTimeTest.php` (novos)
      Mudança:
      - `LocalTime` (final, estática): `TIMEZONE = 'America/Sao_Paulo'`, `toLocal()`, `formatDateTime()` (`d/m/Y H:i`, `'—'` para null), `formatDate()` (`d/m/Y`), `today()` (dia local às 00:00), `localDayStartUtc($dataLocal)` e `todayWindowUtc()` (intervalo UTC semiaberto do dia local). `app.timezone` continua `UTC`.
      - Feriados nacionais fixos (01/01, 21/04, 01/05, 07/09, 12/10, 02/11, 15/11, 20/11, 25/12) e Sexta-feira da Paixão = Páscoa − 2, com Páscoa pelo algoritmo gregoriano anônimo (Meeus/Jones/Butcher) em aritmética inteira, sem `easter_date`/`easter_days`.
      - `DataPrevistaCalculator::DIAS_UTEIS = 3` e `forRequestedAt()`: converte via `LocalTime`, pega a data e conta 3 dias úteis estritamente depois. É o único cálculo vivo de dias úteis em `app/`.
      Cobre: RF-10, RF-11, RF-45/RF-46/RF-47 (ponto de conversão), RNF-05, CT-10
      Acceptance criteria: a tabela do RF-10 bate linha a linha (seg 21/09/2026 → 24/09; sex 25/09, sáb 26/09 e dom 27/09 → 30/09; sex 09/10 → 15/10; `2026-09-25T01:30Z` → 29/09). Páscoa 2024/2025/2026/2027/2030/2038 = 31/03, 20/04, 05/04, 28/03, 21/04, 25/04. Qua 01/04/2026 → ter 07/04. Carnaval 17/02/2026 não é feriado. `LocalTime::formatDateTime(2026-09-25T01:30Z)` = `24/09/2026 22:30`; com relógio em `2026-09-22T01:30Z`, `today()` = 21/09/2026 e `todayWindowUtc()` = [`2026-09-21T03:00Z`, `2026-09-22T03:00Z`); `localDayStartUtc('2026-09-22')` = `2026-09-22T03:00Z`. `config('app.timezone')` segue `UTC`.
      Testes: os três arquivos de teste acima.

- [ ] T02 — Migration de `pedidos`: `obra_id` nullable, `obra_reference`, `data_prevista` com backfill por regra congelada
      Arquivos: `database/migrations/<timestamp>_add_outra_reference_and_data_prevista_to_pedidos.php` (novo)
      Mudança:
      - A classe anônima da migration carrega uma **cópia congelada** da regra (3 dias úteis, 9 feriados fixos, Páscoa/Sexta-feira Santa, fuso `America/Sao_Paulo` literal), exposta como `public function frozenDataPrevista(string $requestedAtUtc): string`. Não importa nenhuma classe `App\Domain\*`, `App\Support\*` ou `App\Models\*`; o docblock explica o congelamento (F-14c).
      - Tudo num único `DB::transaction`: (1) `obra_id` aceita NULL, mantendo a FK restrict; (2) `obra_reference varchar(255) NULL` + checks `pedidos_obra_reference_only_without_obra` e `pedidos_obra_reference_not_blank`; (3) `data_prevista date NULL`; (4) backfill por `chunkById` com `$this->frozenDataPrevista(requested_at)`, via query builder; (5) NOT NULL; (6) índice `pedidos_data_prevista_index`.
      - Sem DELETE/TRUNCATE/drop. `expected_delivery_at` e `pedido_events` intactos. O `down()` recusa com `RuntimeException` PT-BR se existir pedido sem obra.
      Cobre: RF-06, RF-12, RF-42, CT-02, CT-06, RNF-06
      Acceptance criteria: depois de `migrate`, todo pedido tem `data_prevista` não nula e igual à regra aplicada a `requested_at`. O arquivo não referencia `App\Domain\`/`App\Support\`/`App\Models\`. O nome ordena depois de todas as migrations da fatia 1. Inserir `obra_id` + `obra_reference` juntos, ou `obra_reference` em branco, é rejeitado pelo banco.
      Testes: cobertos por T06 (mesma fase).

- [ ] T35 — Hook de criação do `Pedido`: `requested_at` e `data_prevista` preenchidos em todo insert, e imutabilidade da Data prevista
      Arquivos: `app/Models/Pedido.php` (`booted()` e `casts()` apenas), `tests/Unit/Models/PedidoDataPrevistaHookTest.php` (novo)
      Mudança: cast `data_prevista` → `date`. `creating` preenche `requested_at = now()` quando nulo e `data_prevista = DataPrevistaCalculator::forRequestedAt(requested_at)` quando nulo (factories, `DemoSeeder` e `CreatePedidoAction` passam pela mesma regra). `updating` lança `LogicException` ("A data prevista é fixada na criação e não pode ser recalculada.") se `data_prevista` estiver suja. Existe para a Fase 1 fechar verde depois do NOT NULL de T02.
      Cobre: RF-09, RF-12, CT-06
      Acceptance criteria: com `travelTo('2026-09-21 12:00')`, `Pedido::factory()->create()` tem `requested_at` = relógio de teste e `data_prevista` = 24/09/2026; com `requested_at = 2026-09-25T01:30Z`, 29/09/2026. Mudar `expected_delivery_at` ou `status_id` não altera `data_prevista`; forçar `data_prevista` num update lança exceção. A suíte Feature + Unit fica verde ao fim da fase.
      Testes: `tests/Unit/Models/PedidoDataPrevistaHookTest.php`.

- [ ] T03 — Tabela `pedido_attachments`, modelo `PedidoAttachment` (append-only) e enum de tipo
      Arquivos: `database/migrations/<timestamp>_create_pedido_attachments_table.php`, `app/Models/PedidoAttachment.php`, `database/factories/PedidoAttachmentFactory.php`, `app/Enums/PedidoAttachmentKind.php`, `app/Policies/PedidoAttachmentPolicy.php`, `tests/Unit/Models/PedidoAttachmentImmutabilityTest.php` (novos); `tests/Feature/Security/MassAssignmentTest.php` (adição ao dataset)
      Mudança:
      - Tabela: `pedido_id` FK cascade, `kind` com check (`anexo`/`romaneio`), `path` único, `original_name`, `mime_type`, `size_bytes > 0`, `uploaded_by` FK restrict, `created_at`, sem `updated_at`, índice `(pedido_id, kind)`.
      - Enum `Anexo`/`Romaneio` com `label()`.
      - Modelo copia `PedidoEvent`: `UPDATED_AT = null`, `updating`/`deleting` lançam `LogicException`, `#[Fillable]` explícito, `#[Hidden(['path'])]`, relações `pedido()`/`uploader()`.
      - A policy nega `update`/`delete`. A factory ganha o estado `romaneio()`.
      Cobre: CT-03, RF-19, RF-30, RF-42
      Acceptance criteria: `update()`, `save()` num registro existente e `delete()` lançam exceção. O banco rejeita `kind = 'outro'` e `size_bytes = 0`. Apagar o pedido via `DB::table` apaga o anexo em cascata. `MassAssignmentTest` lista `PedidoAttachment` com o fillable exato.
      Testes: `tests/Unit/Models/PedidoAttachmentImmutabilityTest.php`, `tests/Feature/Security/MassAssignmentTest.php` (adição).

- [ ] T06 — Testes das migrations de `pedidos` e `pedido_attachments` e paridade da regra congelada
      Arquivos: `tests/Feature/Migrations/PedidoOutraDataPrevistaMigrationTest.php` (novo), `tests/Feature/MigrationSchemaTest.php`, `tests/Feature/FreshMigrationTest.php`
      Mudança: só verificação, no estilo de `EmailNormalizationMigrationTest` (rollback até antes de T02, inserir via `DB::table`, re-executar). As asserções de esquema forçadas por T02/T03 mudam aqui, na fase que as causa: `MigrationSchemaTest` (`pedidos` com `obra_reference`, `data_prevista`, `obra_id` nullable com FK restrict; tabela `pedido_attachments` sem `updated_at`) e `FreshMigrationTest` (inclui `pedido_attachments`).
      Cobre: RF-06, RF-12, RF-42, RNF-06, CT-02
      Acceptance criteria:
      - `requested_at` 21/09 12:00Z, 25/09 01:30Z e 09/10 15:00Z viram `data_prevista` 24/09, 29/09 e 15/10; nenhuma linha fica nula; `expected_delivery_at` idêntico.
      - Paridade (F-14c): `(require <arquivo de T02>)->frozenDataPrevista()` = `DataPrevistaCalculator::forRequestedAt()` nas 6 linhas do RF-10, em 01/04/2026 e em todos os dias de 2026 às 12:00Z e 01:30Z; o arquivo de T02 não referencia `App\Domain\`/`App\Support\`/`App\Models\`.
      - Contagens de `users`, `obras`, `obra_profile`, `pedidos`, `pedido_events`, `statuses`, `event_types`, `user_admin_events`, `authentication_events`, `obra_invitations`, `obra_admin_events`, `account_registration_events` iguais antes e depois.
      - `select … where data_prevista >= ? order by data_prevista` funciona. Os dois checks disparam; `obra_id = null` é aceito e a FK rejeita obra inexistente.
      - `migrate:rollback` + `migrate` repetível; o `down()` de T02 com pedido "Outra" lança e não muda nada. Os dois nomes de arquivo ordenam depois de todas as migrations da fatia 1.
      - A suíte Feature + Unit está verde ao fim da Fase 1.
      Testes: os três arquivos acima.

## Phase 2: Finalizado e regras compartilhadas — lookups, terminal único, badge, Kanban, Pedido, visibilidade e indicadores

Antes de implementar, leia:
1. `.spec/features/solicitacao-historico-finalizacao/SPEC.md` — requisitos RIGID que esta fase cobre (RF-05, RF-11, RF-12, RF-13, RF-18, RF-33, RF-36, RF-39, RF-40, RF-41, RF-42, RF-43, RF-44, UI-07, UI-08, UI-09, CT-06, CT-07, CT-08, CT-09, RNF-06)
2. `.spec/features/solicitacao-historico-finalizacao/PLAN.md` — decomposição completa, dependências e riscos

Antes do merge desta fase vale o gate G-3 (`sort_order = 7` livre em produção). A migration de lookups (T04) ordena depois de T03. T04 e T05 vão juntos e primeiro. Todas as asserções dirigidas por Finalizado (colunas do Kanban, selects de status, `porStatus`, Visão Geral, badge) mudam **nesta** fase (F-04), para que ela feche verde. Ordem: T04+T05 → T12, T08, T07 (paralelos) → T11 (após T08) → T09 (após T07) → T10 (após T07/T08) → T36.

- [ ] T04 — Linhas de lookup por migration e enums (`finalizado`, 3 tipos de evento, terminal único)
      Arquivos: `database/migrations/<timestamp>_insert_finalizado_status_and_history_event_types.php` (novo), `app/Enums/StatusSlug.php`, `app/Enums/EventTypeSlug.php`, `database/factories/StatusFactory.php`, `database/factories/EventTypeFactory.php`, `database/seeders/DemoSeeder.php`
      Mudança:
      - Migration idempotente num `DB::transaction`: insere `finalizado` (sort 7) se ausente, e aborta com `RuntimeException` PT-BR se o sort 7 estiver ocupado por outro slug. Insere `observacao`, `romaneio_anexado` e `finalizacao` se ausentes. Nunca atualiza nem apaga linha existente.
      - O `down()` só remove essas 4 linhas quando não referenciadas; o docblock declara explicitamente que é um rollback **condicionalmente destrutivo** (F-14b).
      - `StatusSlug::Finalizado` entra por último. Novos `terminal()`, `terminalValues()` e `finalizableFrom()` (ativos + Entregue). `isTerminal()` passa a usar `terminal()`.
      - `EventTypeSlug` passa a ter 10 casos. Novos estados nas factories. O `DemoSeeder` inclui as 4 linhas via `firstOrCreate`.
      Cobre: RF-33, RF-39, RF-42, RF-43, CT-08, CT-09
      Acceptance criteria: sem `db:seed`, existem `finalizado` (sort 7) e os 3 tipos de evento. Rodar a migration duas vezes mantém uma linha de cada. Com o sort 7 ocupado, ela lança e não insere nada. `isTerminal()` é verdadeiro para `entregue`, `cancelado` e `finalizado`. O docblock do `down()` diz que é destrutivo-condicional.
      Testes: `tests/Unit/Enums/SlugEnumsTest.php` (via T05) e `tests/Feature/Migrations/HistoryLookupRowsMigrationTest.php` (via T36).

- [ ] T05 — Compatibilidade das fixtures de teste com as linhas de lookup migradas
      Arquivos: `tests/Pest.php` e os 16 arquivos com laço de `StatusSlug::cases()` + `Status::factory()->create` (`QueryCountTest`, `UpdatePedidoStatusActionTest`, `SuprimentosScreensRouteTest`, `CancelPedidoControlTest`, `PedidoCardRenderTest`, `AccessibleStatusControlTest`, `DashboardIndicatorsTest`, `PedidoDetalheSuprimentosTest`, `DashboardDonutTest`, `LayoutIdentityTest`, `GestaoKanbanReadOnlyTest`, `KanbanBoardTest`, `VisaoGeralTest`, `KanbanForgedMoveTest`, `SemanticBadgeTest`, `SlugEnumsTest`), além de `DemoSeederIdempotencyTest`
      Mudança: helpers `seedWorkflowStatuses()` e `seedHistoryEventTypes()` com `firstOrCreate` por slug. Troca **somente** os laços de fixture (mesmas chaves e `sort_order`). Asserções forçadas pelas linhas de lookup mudam só em `SlugEnumsTest` (terminais, 10 tipos, listas exatas de `terminal()`/`finalizableFrom()`) e `DemoSeederIdempotencyTest` (7 status, 10 tipos). As asserções de comportamento dirigidas por Finalizado mudam em T10, T11 e T12 **desta mesma fase**. Nenhuma asserção é enfraquecida nem apagada.
      Cobre: RF-44, RF-42
      Acceptance criteria: ao fim da Fase 2 (depois de T04, T05, T12, T08, T11, T07, T09, T10 e T36), as suítes Feature e Unit estão totalmente verdes. Nenhuma asserção fora das listadas mudou.
      Testes: `php artisan test --compact --testsuite=Feature` e `--testsuite=Unit`.

- [ ] T12 — Badge distinto para Finalizado
      Arquivos: `resources/views/components/status-badge.blade.php`, `tests/Feature/Livewire/SemanticBadgeTest.php`
      Mudança: `StatusSlug::Finalizado->value => 'badge-success'` no `match` literal (classe já existente, diferente de `badge-concluido` de Entregue). Sem CSS novo, sem interpolação. Sem isso o braço `default` colide com Solicitado assim que T04 entra, por isso fica nesta fase.
      Cobre: UI-08
      Acceptance criteria: o badge de `finalizado` tem classes diferentes das de `entregue`. A asserção "cada slug tem um conjunto distinto" cobre 7 slugs. `BuiltAssetsUtilitiesTest` passa depois de `npm run build`.
      Testes: `tests/Feature/Livewire/SemanticBadgeTest.php`, `tests/Feature/Compliance/BuiltAssetsUtilitiesTest.php`.

- [ ] T08 — Definição terminal única nos classificadores (PHP e SQL)
      Arquivos: `app/Domain/Pedidos/AtrasoClassifier.php`, `app/Domain/Pedidos/PendenteClassifier.php`, `tests/Unit/Domain/{AtrasoClassifierTest,PendenteClassifierTest,PrazoClassifierTest}.php`, `tests/Feature/Compliance/TerminalStatusDefinitionTest.php` (novo)
      Mudança: os dois scopes SQL trocam a lista literal por `StatusSlug::terminalValues()`. Tudo continua medido por `needed_at`; `data_prevista` não aparece nos classificadores. O "hoje" local fica para T37 (Fase 3).
      Cobre: RF-39, RF-12, CT-08
      Acceptance criteria: um pedido Finalizado com Preciso para vencido não é atrasado (PHP e SQL), não é pendente e `PrazoClassifier` devolve null. `needed_at` futuro + `data_prevista` passada → não atrasado; `needed_at` passado + `data_prevista` futura (não terminal) → atrasado. A varredura não encontra lista terminal literal fora de `StatusSlug::terminal()`.
      Testes: os quatro arquivos acima.

- [ ] T11 — Kanban com coluna Finalizado, rótulo "Previsão de entrega" nos cards e nenhum caminho genérico até Finalizado
      Arquivos: `app/Livewire/Kanban/KanbanBoard.php`, `app/Livewire/Gestao/KanbanReadOnly.php`, `resources/views/livewire/kanban/{kanban-board,pedido-card}.blade.php`, `resources/views/livewire/gestao/{kanban-read-only,pedido-card-read-only}.blade.php`, `app/Livewire/Suprimentos/PedidoDetalhe.php` (`statuses` do `render()`), `app/Actions/Pedidos/UpdatePedidoStatusAction.php` (docblock), `tests/Feature/Livewire/{KanbanBoardTest,GestaoKanbanReadOnlyTest,KanbanForgedMoveTest,AccessibleStatusControlTest,PedidoCardRenderTest}.php`, `tests/Feature/Actions/UpdatePedidoStatusActionTest.php`
      Mudança:
      - 6 colunas em ordem (4 ativos, Entregue, Finalizado), grid literal `xl:grid-cols-6`.
      - `moveTargets` = ativos + Entregue; o "Mover para" nunca oferece Finalizado. Soltar na coluna Finalizado → 422 "Transição de status inválida.". O select de status do detalhe exclui `cancelado` e `finalizado`.
      - UI-09 / F-07: nos dois cards, o `<dt>` da linha `data-field="expected_delivery_at"` passa de "Previsão" para "Previsão de entrega" (valor inalterado).
      - N-06: nos dois cards, o `<dt>` da linha `data-field="needed_at"` passa de "Necessário em" (`kanban/pedido-card.blade.php:23`, `gestao/pedido-card-read-only.blade.php:19`) para "Preciso para" (valor inalterado, nunca deslocado). Só texto.
      Cobre: RF-36, RF-39, UI-07, UI-09, CT-06, CT-08; N-06 (alinhamento de rótulo, sem RF próprio)
      Acceptance criteria: os dois Kanbans mostram as 6 colunas na ordem, sem Cancelado. Card Finalizado aparece na coluna Finalizado sem "Mover para". Movimento forjado para o id de `finalizado` → erro em `status_id`, 0 eventos. `UpdatePedidoStatusAction` com alvo `finalizado` → 422 (ativo) ou 409 (entregue). O select do detalhe não tem "Finalizado". Os dois cards mostram "Previsão de entrega" e nenhum campo chamado só "Previsão". Os dois cards mostram "Preciso para" na linha `needed_at` e o HTML não contém "Necessário em" (asserção nova em `PedidoCardRenderTest`). Reordenar na mesma coluna segue sem gravar nada.
      Testes: os seis arquivos de teste acima.

- [ ] T07 — Modelo `Pedido`: representação canônica, apresentação da Data prevista, anexos e factory
      Arquivos: `app/Models/Pedido.php`, `database/factories/PedidoFactory.php`, `tests/Unit/Models/PedidoModelTest.php`, `tests/Feature/Security/MassAssignmentTest.php`
      Mudança: `obra_reference` fillable (`data_prevista` nunca). `OUTRA_LABEL = 'Outra'`, `obraLabel()`, `presentDataPrevista()` estático (`d/m/Y`) e `dataPrevistaLabel()` (ponto único de apresentação). Relações `attachments()` e `romaneios()`. A factory ganha o estado `outra(?string $reference)` e não associa `obra_profile` sem obra. O hook de `data_prevista` já existe (T35).
      Cobre: RF-11, RF-13, CT-06, CT-07
      Acceptance criteria: `obraLabel()` devolve "Residencial Aurora", "Outra" e "Outra — Galpão provisório" nas três formas. Com `travelTo('2026-09-21 12:00')`, `dataPrevistaLabel()` = "24/09/2026". `factory()->outra('X')` não cria `obra_profile`. `MassAssignmentTest` lista `obra_reference` e não `data_prevista`.
      Testes: `tests/Unit/Models/PedidoModelTest.php`, `tests/Feature/Security/MassAssignmentTest.php`.

- [ ] T09 — Visibilidade de pedidos "Outra": `visibleTo` e `PedidoPolicy::view`
      Arquivos: `app/Models/Pedido.php` (`visibleTo`), `app/Policies/PedidoPolicy.php` (`view`), `tests/Feature/Authorization/{PedidoVisibleToScopeTest,PedidoPolicyTest}.php`, `tests/Feature/Compliance/ObraVisibleToGuardTest.php`
      Mudança: para `obra`, um `where` agrupado: obras do `obra_profile` **ou** (`obra_id` nulo **e** `requester_id` = usuário). Suprimentos e Gestão sem mudança; papel desconhecido continua `1 = 0`. A policy espelha a mesma regra.
      Cobre: RF-40, RF-05, RF-18, CT-08
      Acceptance criteria: U1 vê e abre o próprio pedido "Outra". U2 (qualquer associação) não vê, e `view` é falso. Suprimentos e Gestão veem. Um filtro `obraId` nunca amplia o conjunto. Um "Outra" com referência igual ao nome da obra X não aparece para os usuários de X.
      Testes: os três arquivos acima (só adições).

- [ ] T10 — Representação canônica nos pontos de renderização e indicadores (`porObra` "Outra", `porStatus` com Finalizado)
      Arquivos: `resources/views/components/{pedido-table,pedido-summary}.blade.php`, `resources/views/livewire/kanban/pedido-card.blade.php`, `resources/views/livewire/gestao/{pedido-card-read-only,dashboard}.blade.php`, `resources/views/livewire/suprimentos/visao-geral.blade.php` (só a legenda, N-06), `app/Services/DashboardIndicatorsService.php`, `tests/Feature/Livewire/{DashboardIndicatorsTest,VisaoGeralTest}.php`, `tests/Feature/Livewire/DashboardDonutTest.php` (só se fixar a lista de status), `tests/Feature/Livewire/OutraRenderSitesTest.php` (novo)
      Mudança: todo `$pedido->obra->name` vira `$pedido->obraLabel()`. As linhas de `porObra` passam a ter o formato `{obra: ?Obra, label, count}`, com uma única linha "Outra" no fim, só quando existir pedido sem obra. `entregues` exclui Finalizado. `porStatus` inclui Finalizado. Continuam 8 chaves e nenhuma consulta extra. N-06: a legenda do KPI de atrasados "data necessária vencida e não entregues" vira "Preciso para vencido e não concluídos" em `gestao/dashboard.blade.php:154` (mantém " — clique para ver") e `suprimentos/visao-geral.blade.php:21`. A mesma legenda em `suprimentos/todos-pedidos.blade.php:29` **não** é tocada aqui (fatia 3, T14).
      Cobre: RF-39, RF-41, CT-07, CT-08; N-06 (alinhamento de rótulo, sem RF próprio)
      Acceptance criteria: com um "Outra" sem referência e outro com "Galpão provisório", as 3 listagens, os 2 Kanbans, os 3 detalhes, o Dashboard e a Visão Geral respondem 200 e mostram "Outra" (e a referência onde o pedido aparece). 1 Entregue + 1 Finalizado → `entregues` = 1, e `porStatus` mostra os dois. 2 "Outra" (referência "X" e sem referência) → uma linha "Outra" com 2 e nenhuma "X". As chaves de `compute()` não mudam. A Visão Geral lista Finalizado e não Cancelado. Dashboard e Visão Geral mostram "Preciso para vencido e não concluídos" e não contêm "data necessária" (sem diferenciar maiúsculas); `suprimentos/todos-pedidos.blade.php` fica inalterado.
      Testes: os arquivos de teste acima.

- [ ] T36 — Testes da migration de linhas de lookup
      Arquivos: `tests/Feature/Migrations/HistoryLookupRowsMigrationTest.php` (novo)
      Mudança: só verificação da migration de T04 (separada do antigo T06(e) para a Fase 1 não depender de T04).
      Cobre: RF-42, RNF-06, CT-09
      Acceptance criteria: sem `db:seed`, `finalizado` (sort 7) e os 3 tipos existem. `up()` duas vezes → uma linha de cada. Sort 7 ocupado → lança e não insere nada. As contagens das tabelas com dados ficam iguais, exceto +1 `statuses` e +3 `event_types`. O `down()` remove as 4 linhas quando não referenciadas e lança sem mudar nada quando referenciadas. O docblock contém "destrutivo" ou "destructive". O arquivo ordena depois de T03 e da fatia 1. `migrate:rollback` + `migrate` das três migrations da fatia é repetível.
      Testes: `tests/Feature/Migrations/HistoryLookupRowsMigrationTest.php`.

## Phase 3: Calendário local — "hoje", período e exibição

Antes de implementar, leia:
1. `.spec/features/solicitacao-historico-finalizacao/SPEC.md` — requisitos RIGID que esta fase cobre (RF-12, RF-27, RF-44, RF-45, RF-46, RF-47, CT-08, CT-10)
2. `.spec/features/solicitacao-historico-finalizacao/PLAN.md` — decomposição completa, dependências e riscos (seção "Outbound contract surface": o nome e a API de `RequestedPeriodFilter` são contrato com a fatia 3)

Decisão do router (F-01, reversível): atraso, prazo, `entreguesHoje` e o período passam a virar o dia à meia-noite de São Paulo. T39 roda em paralelo com T37; T38 vem depois de T37 (mesmo serviço).

- [ ] T37 — "Hoje" no dia de São Paulo: atraso, prazo e `entreguesHoje`
      Arquivos: `app/Domain/Pedidos/{AtrasoClassifier,PrazoClassifier}.php`, `app/Domain/Pedidos/PendenteClassifier.php` (docblock), `app/Services/DashboardIndicatorsService.php` (`entreguesHojeCount()`), `tests/Unit/Domain/{AtrasoClassifierTest,PrazoClassifierTest}.php` (só relógio), `tests/Unit/Domain/LocalDayClassifiersTest.php` (novo), `tests/Feature/Livewire/DashboardIndicatorsTest.php` (adições) e, só no relógio de fixture quando a asserção estiver numa fronteira de 1 ou 3 dias relativa a `now()`, `BypassUiAuthorizationTest`, `AcompanhamentoTest`, `DashboardDonutTest`, `DashboardDrillDownTest`, `PedidoCardRenderTest`, `TodosPedidosFiltersTest`, `TodosPedidosIndicadoresTest`, `VisaoGeralTest`, `CrossObraTest`
      Mudança:
      - `AtrasoClassifier` (PHP e `scopeAtrasado`) compara `needed_at` com `LocalTime::today()` como data (sem `whereDate`, sem deslocar a coluna `date`). `PrazoClassifier` conta os dias a partir de `LocalTime::today()` com datas puras no mesmo fuso. `PendenteClassifier` não usa data (docblock diz isso).
      - `entreguesHojeCount()` usa `LocalTime::todayWindowUtc()` com `>=`/`<` em `pedido_events.created_at`, dentro do **mesmo e único** `whereExists`.
      - Determinismo dos testes: `AtrasoClassifierTest`/`PrazoClassifierTest` congelam em `2026-06-15 00:00Z` (= 14/06 21:00 local); o congelamento passa a `2026-06-15 15:00` sem mudar datasets nem expectativas. Os arquivos Feature listados são auditados; só os que têm asserção numa fronteira de 1 ou 3 dias ganham `travelTo(<data> 15:00 UTC)` no `beforeEach`. O log da fase lista quais mudaram.
      Cobre: RF-45, RF-12, RF-27, CT-08
      Acceptance criteria: com relógio em `2026-09-22T01:30Z` (21/09 22:30 local), um pedido não terminal com `needed_at` = 21/09/2026 **não** está atrasado (PHP e SQL concordam); em `2026-09-22T03:30Z`, está atrasado nos dois. `PrazoClassifier` devolve `vencendo_em_breve` para 24/09 e `dentro_do_prazo` para 25/09 às 22:30 local. Um evento `entrega` em `2026-09-22T01:30Z` conta em `entreguesHoje` com relógio em `2026-09-22T02:00Z` e não conta em `2026-09-22T04:00Z`. `QueryCountTest` inalterado. `config('app.timezone')` = `UTC`. Todos os casos existentes de classificadores, `DashboardIndicatorsTest`, `DashboardDrillDownTest` e `QueryCountTest` passam.
      Testes: `tests/Unit/Domain/LocalDayClassifiersTest.php`, `tests/Feature/Livewire/DashboardIndicatorsTest.php` e os arquivos auditados.

- [ ] T38 — Classe única de período em dia local (`RequestedPeriodFilter`, CT-10) no Dashboard e nos drill-downs
      Arquivos: `app/Domain/Pedidos/RequestedPeriodFilter.php` (novo, `php artisan make:class Domain/Pedidos/RequestedPeriodFilter --no-interaction`), `app/Services/DashboardIndicatorsService.php` (`filteredQuery()`), `app/Livewire/Gestao/TodosPedidos.php`, `app/Livewire/Suprimentos/TodosPedidos.php` (só o par `requestedFrom`/`requestedTo`), `tests/Unit/Domain/RequestedPeriodFilterTest.php` (novo), `tests/Feature/Livewire/{DashboardDrillDownTest,TodosPedidosFiltersTest}.php` (adições), `tests/Feature/Compliance/RequestedPeriodSingleDefinitionTest.php` (novo)
      Mudança:
      - `final class RequestedPeriodFilter` (API estática): `COLUMN = 'requested_at'`; `utcBoundsForLocalRange(?string $localFrom, ?string $localTo): array{from: ?CarbonImmutable, until: ?CarbonImmutable}` (De → 00:00 local em UTC, inclusivo; Até → 00:00 local do dia seguinte em UTC, exclusivo; vazio ou inválido → null); `applyLocalRange(Builder $query, ?string $localFrom, ?string $localTo): Builder`.
      - O Dashboard e as duas listagens trocam o par `whereDate('requested_at', …)` por `applyLocalRange` (depois de `visibleTo`, estado `#[Url]` inalterado). Os pares de `needed_at` (coluna `date`) ficam como estão.
      - O docblock diz que a fatia 3 acrescenta seus presets **a esta classe** e usa `applyLocalRange` no Personalizado; nunca uma segunda classe, nunca `whereDate` em `requested_at`.
      Cobre: RF-46, CT-10, RF-44
      Acceptance criteria: De = Até = 22/09/2026 → [`2026-09-22T03:00Z`, `2026-09-23T03:00Z`). Pedido A em `2026-09-22T02:30Z` e B em `2026-09-22T03:30Z`: com De = Até = 22/09, o Dashboard mostra `volumeTotal` 1 e o drill-down lista só B (mesma contagem); com 21/09, só A; vazio → ambos. O mesmo limite vale em `/suprimentos/pedidos` e `/gestao/pedidos`. A varredura não encontra `whereDate('requested_at'` nem `whereDate` em `created_at` em `app/`; `where('requested_at', '>='|'<'` só aparece em `RequestedPeriodFilter.php`; o literal `'America/Sao_Paulo'` só aparece em `LocalTime.php` dentro de `app/`.
      Testes: os quatro arquivos de teste acima.

- [ ] T39 — Horários da fatia 1 no calendário local: lista de convites e `obra_admin_events`
      Arquivos: `resources/views/livewire/obras/form.blade.php` (lista "Convites" da fatia 1), qualquer view que renderize timestamp de `ObraAdminEvent` (achada com `grep -rn "ObraAdminEvent\|obra_admin_events\|adminEvents" resources/views app/Livewire`), `tests/Feature/Livewire/ObraConvitesSectionTest.php` (adições), `tests/Feature/Livewire/LocalTimeDisplayTest.php` (novo)
      Mudança: criado em, expira em, revogado em e utilizado em passam por `LocalTime::formatDateTime(...)`; o mesmo para qualquer ponto de `obra_admin_events` encontrado. Só views; nenhuma lógica, estado ou consulta dos componentes da fatia 1 muda.
      Cobre: RF-47, F-12
      Acceptance criteria: um convite criado em `2026-09-25T01:30Z` mostra criado em "24/09/2026 22:30" e expira em "25/09/2026 22:30". Revogado/utilizado em também aparecem em horário local. Se existir ponto de `obra_admin_events`, uma linha no mesmo instante mostra "24/09/2026 22:30"; se não existir, o teste confirma a ausência pelo mesmo grep. Um pedido com `needed_at` = 25/09/2026 mostra 25/09/2026 em todas as telas.
      Testes: `tests/Feature/Livewire/ObraConvitesSectionTest.php`, `tests/Feature/Livewire/LocalTimeDisplayTest.php`.

## Phase 4: Nova Solicitação para Obra e Suprimentos

Antes de implementar, leia:
1. `.spec/features/solicitacao-historico-finalizacao/SPEC.md` — requisitos RIGID que esta fase cobre (RF-01..RF-09, RF-44, UI-01, UI-02, CT-01, CT-05, CT-07, CT-09)
2. `.spec/features/solicitacao-historico-finalizacao/PLAN.md` — decomposição completa, dependências e riscos

T14 e T15 vão no mesmo commit: T14 muda as chaves de entrada que o antigo `Obra\NovaSolicitacao` e os chamadores listados (F-02) ainda enviam.

- [ ] T13 — Habilidade `create-pedido` e `PedidoPolicy::create(User)`
      Arquivos: `app/Providers/AppServiceProvider.php`, `app/Policies/PedidoPolicy.php`, `routes/web.php`, `tests/Feature/Authorization/{RoleGatesTest,PedidoPolicyTest}.php`, `tests/Feature/Livewire/ObraScreensRouteTest.php` (só middleware da rota)
      Mudança: gate `create-pedido` = `obra` ou `suprimentos`. `PedidoPolicy::create(User)` delega ao gate, e o parâmetro `Obra` sai. `obra.nova-solicitacao` ganha `can:create-pedido`. Os testes de "só Obra cria" são atualizados, nunca apagados.
      Cobre: RF-01, CT-05
      Acceptance criteria: `create-pedido` é concedido a obra e suprimentos e negado a gestão e a usuário sem papel. `can('create', Pedido::class)` segue a mesma regra. A rota da obra tem `auth`, `active`, `can:is-obra` e `can:create-pedido`. GET como obra → 200, como gestão → 403, visitante → `/login`.
      Testes: os três arquivos acima.

- [ ] T14 — `CreatePedidoAction` generalizada: Obra e Suprimentos, "Outra", datas do servidor, snapshot do histórico, e migração dos chamadores
      Arquivos: `app/Actions/Pedidos/CreatePedidoAction.php`, `tests/Feature/Actions/CreatePedidoActionTest.php` e os chamadores migrados para as chaves de CT-01 (F-02): `tests/Feature/Livewire/PedidoDetalheObraTest.php`, `tests/Feature/Livewire/PedidoDetalheGestaoTest.php`, `tests/Feature/Livewire/ObraScreensRouteTest.php`, `tests/Feature/Authorization/BypassUiAuthorizationTest.php`, `tests/Feature/Security/Adversarial/CrossObraTest.php` (G-14 e as chamadas da Action), `tests/Feature/Auth/ZeroObraUserTest.php` (fatia 1, só o caso da Action)
      Mudança: entrada CT-01 (`obra_selection`, `obra_reference`, `descricao`, `needed_at`). Antes da transação e antes do código:
      - guarda de ator via `create-pedido`;
      - `Validator` PT-BR com os erros de obra na chave `obra_id`; campos extras ignorados (`requested_at`, `data_prevista`, `code`, `status_id` e `requester_id` forjados não têm efeito); mensagens de data: **"Informe a data em Preciso para."** / **"Informe uma data válida em Preciso para."** (F-08);
      - RF-07: sem obra ativa associada → 422 em `obra_id`, também para "Outra", com `CreatePedidoAction::noActiveObraMessage(User)` por papel (F-17): obra = texto da fatia 1 byte a byte; suprimentos = "Nenhuma obra ativa está associada ao seu usuário. Fale com a Gestão ou associe-se em Associações.";
      - obra não associada/inexistente ou Concluída → mensagens específicas;
      - `'outra'` → sem obra e referência aparada ou null; obra real → referência null.
      Na transação: status inicial escolhido entre `activeNonFinal()` pelo menor sort, `requested_at = now()` (o hook deriva `data_prevista`), 1 evento `criacao_pedido` com `new_value = $pedido->obraLabel()` (snapshot, F-09). Chamadores (F-02): trocam `obra_id`/`items_description` por `obra_selection`/`descricao` mantendo a intenção. Os casos de obra forjada passam a afirmar a mensagem específica de associação, Concluído ou zero obras, nunca um "Selecione a obra." vazio.
      Cobre: RF-01, RF-03..RF-09, CT-01, CT-07, CT-09
      Acceptance criteria:
      - Para obra e para suprimentos: sucesso com obra ativa associada, e o `new_value` do evento é "Residencial Aurora". Os 3 casos do RF-03 → 422 em `obra_id` com a mensagem exata, 0 linhas e sequência intacta.
      - "Outra" + "  Galpão provisório  " → sem obra, referência aparada e `new_value` "Outra — Galpão provisório". "Outra" vazia → referência null e `new_value` "Outra". Referência de 256 caracteres → 422. Obra + referência → referência null.
      - Zero obras + "Outra" → 422 com o texto do papel. Preciso para ausente ou inválido → as duas mensagens novas, e nenhuma mensagem diz "data necessária".
      - Datas forjadas são ignoradas. Ator gestão → `AuthorizationException`. Falha no insert do evento → 0 pedidos e 0 eventos.
      - Os chamadores migrados passam com a intenção original.
      Testes: todos os arquivos acima.

- [ ] T15 — Componente papel-neutro `Pedidos\NovaSolicitacao` e rotas (Obra e Suprimentos)
      Arquivos: `app/Livewire/Pedidos/NovaSolicitacao.php`, `resources/views/livewire/pedidos/nova-solicitacao.blade.php` (novos), `routes/web.php`, `app/Livewire/Obra/NovaSolicitacao.php` e sua view (removidos), `tests/Feature/Livewire/{NovaSolicitacaoTest,SuprimentosScreensRouteTest}.php`, `tests/Feature/Security/Adversarial/CrossObraTest.php` (import + G-04: `set('obra_selection')`/`set('descricao')`), `tests/Feature/Authorization/BypassUiAuthorizationTest.php` (imports/rotas), `tests/Feature/Compliance/ObraVisibleToGuardTest.php`
      Mudança: `obra.nova-solicitacao` e a nova `suprimentos.nova-solicitacao` (`can:create-pedido`) apontam para o componente papel-neutro. `mount()` autoriza `create-pedido`; `submit()` autoriza `create` e delega à Action. O select tem as obras ativas associadas por nome + "Outra" por último; "Referência" só com "Outra"; "Descrição"; "Preciso para"; Data da solicitação (`LocalTime::today()`) e Data prevista somente leitura; nota gerada a partir de `DIAS_UTEIS`. O estado vazio é **por papel**, via `CreatePedidoAction::noActiveObraMessage()` (F-17), sem formulário. O sucesso mostra o código, "Data prevista: dd/mm/aaaa" e o link para a listagem do papel. CrossObraTest G-04 mantém a intenção: `obra_selection` forjada de obra alheia falha em `obra_id` com a mensagem de associação.
      Cobre: RF-01, RF-02, RF-04, RF-07, UI-01, CT-01, CT-05
      Acceptance criteria: para obra e suprimentos associados a A (Em andamento), B (A iniciar) e C (Concluído), e não a D, as opções são exatamente [A, B] + "Outra". "Outra" mostra Referência, A esconde. Os rótulos são exatos. A prévia com 21/09 12:00 mostra 24/09/2026; em `2026-09-22T01:30Z` a Data da solicitação mostra 21/09/2026. Com zero obras, obra vê o texto da fatia 1 byte a byte e suprimentos vê o texto de Suprimentos, sem `<form>` e sem "Outra". Submit forjado → erro em `obra_id` e sequência intacta. Gestão → 403 no mount e no submit. As duas rotas → 200 para o papel certo, 403 para os outros, `/login` para visitante. A rota de suprimentos tem `auth`, `active`, `can:is-suprimentos` e `can:create-pedido`.
      Testes: os arquivos de teste acima.

- [ ] T16 — Entrada "+ Nova Solicitação" na toolbar de Suprimentos
      Arquivos: `resources/views/layouts/app.blade.php`, `tests/Feature/Livewire/LayoutIdentityTest.php`
      Mudança: último item do braço de Suprimentos aponta para `suprimentos.nova-solicitacao`. É provisório: a fatia 3 troca a toolbar pela sidebar. Obra e Gestão não mudam.
      Cobre: UI-02, CT-05
      Acceptance criteria: navegação de Suprimentos = Visão Geral, Kanban, Todos os Pedidos, Obras, Associações, + Nova Solicitação. Gestão não tem "Nova Solicitação". Obra fica igual à da fatia 1. A entrada tem `aria-current="page"` na própria rota.
      Testes: `tests/Feature/Livewire/LayoutIdentityTest.php`.

## Phase 5: Anexos — armazenamento privado, criação, download e limites de upload

Antes de implementar, leia:
1. `.spec/features/solicitacao-historico-finalizacao/SPEC.md` — requisitos RIGID que esta fase cobre (RF-08, RF-14..RF-20, CT-01, CT-03, RNF-01, RNF-02, RNF-07, RNF-09)
2. `.spec/features/solicitacao-historico-finalizacao/PLAN.md` — decomposição completa, dependências e riscos

- [ ] T17 — Disco privado `pedido_anexos` e serviço `PedidoAttachmentStorage`
      Arquivos: `config/filesystems.php` (só o disco novo), `app/Services/PedidoAttachmentStorage.php` (novo), `app/Enums/PedidoAttachmentKind.php`, `tests/Pest.php` (construtores de bytes reais), `tests/Feature/Services/PedidoAttachmentStorageTest.php` (novo)
      Mudança: disco `pedido_anexos` com raiz `env('PEDIDO_ANEXOS_ROOT', storage_path('app/pedido-anexos'))`, sem `serve` e sem `url`. Allow-lists por tipo (Anexo: jpg/png/webp/pdf/docx/xlsx; Romaneio: pdf/jpg/png). `inspect()` detecta o MIME por `finfo->buffer($file->get())`, nunca por `getMimeType()`, e exige MIME + extensão na lista e coerentes entre si, com tamanho entre 1 e 10 MB. `sanitizeDisplayName()` limpa o nome. `store()` grava `<pedido_id>/<40 hex aleatórios>.<ext>`; também há `exists()` e `deleteQuietly()`.
      Cobre: RF-14, RF-16, RF-20, RNF-01, CT-03
      Acceptance criteria: cada tipo permitido é aceito. `.pdf` com bytes PNG, `.png` com HTML, `.svg`, `.txt`, `.heic`, `.zip`, `.exe` e `.html` → 422. 10 485 760 bytes aceitos e 10 485 761 recusados. `../../etc/passwd.pdf` vira `passwd.pdf`. O caminho casa `/^\d+\/[0-9a-f]{40}\.(jpg|png|webp|pdf|docx|xlsx)$/` e não contém o nome original. A raiz padrão fica fora de `storage/app/private`, `storage/app/public` e `public/`.
      Testes: `tests/Feature/Services/PedidoAttachmentStorageTest.php`.

- [ ] T18 — Anexos na criação: Action atômica e upload arquivo a arquivo no componente
      Arquivos: `app/Actions/Pedidos/CreatePedidoAction.php`, `app/Livewire/Pedidos/NovaSolicitacao.php`, `resources/views/livewire/pedidos/nova-solicitacao.blade.php`, `tests/Feature/Actions/CreatePedidoAttachmentsTest.php`, `tests/Feature/Livewire/NovaSolicitacaoAnexosTest.php` (novos)
      Mudança: `anexos` (máx. 10) são inspecionados antes da transação; um arquivo inválido recusa tudo e é nomeado no erro. Na transação: pedido, arquivos, linhas `anexo` e 1 evento com o snapshot. Em erro, `deleteQuietly` dos caminhos gravados. O componente sobe um arquivo por requisição (`$wire.upload('novoAnexo', …)` em sequência via Alpine), mostra a lista com "Remover" e o texto de limites exato, e nunca faz `temporaryUrl()`/preview.
      Cobre: RF-08, RF-14, RF-15, UI-01, RNF-02, RNF-07, CT-01
      Acceptance criteria: 3 válidos → 1 pedido, 1 evento, 3 linhas `anexo`, 3 arquivos. 2 válidos + 1 inválido → 422 em `anexos.2`, sem nada gravado e sequência intacta. 11 arquivos → 422. Falha no evento → 0 linhas e 0 arquivos. Um anexo "romaneio.pdf" fica como `anexo`. No componente, `removerAnexo` funciona, o 11º é recusado e o HTML não contém `livewire/preview-file`.
      Testes: os dois arquivos acima.

- [ ] T19 — Download autorizado de anexos
      Arquivos: `app/Http/Controllers/PedidoAttachmentDownloadController.php` (novo, invokable), `routes/web.php`, `tests/Feature/Http/PedidoAttachmentDownloadTest.php` (novo)
      Mudança: `GET /pedidos/{pedido}/anexos/{attachment}` (`pedidos.anexos.download`) dentro de `['auth','active']`, com `scopeBindings()`. Autoriza `view` a cada requisição e devolve 404 se o arquivo sumiu. Faz stream com `Content-Disposition: attachment`, o MIME gravado, `nosniff` e `Cache-Control: private, no-store`. Não grava nada.
      Cobre: RF-16, RF-17, RF-18, RNF-01, RNF-09, CT-03
      Acceptance criteria: obra associada, solicitante de "Outra", suprimentos e gestão → 200 com os bytes e cabeçalhos exatos. Obra de outra obra e outro usuário obra num "Outra" → 403. Visitante → `/login`. Inativo → deslogado. Id desconhecido, anexo de outro pedido ou arquivo ausente → 404, sem bytes nem nome. `/storage/<caminho>` e a rota assinada `storage.local` não entregam bytes. A rota tem `auth` e `active`.
      Testes: `tests/Feature/Http/PedidoAttachmentDownloadTest.php`.

- [ ] T20 — Limites de upload do PHP versionados (inertes até o runbook) e verificação de consistência
      Arquivos: `config/php/uploads.ini` (novo), `tests/Feature/Compliance/UploadLimitsConsistencyTest.php` (novo)
      Mudança: `upload_max_filesize = 12M`, `post_max_size = 16M`, `max_file_uploads = 20`. O arquivo só vale quando `PHP_INI_SCAN_DIR` o incluir (runbook de T32). Nenhum `Caddyfile`, `railpack.json` ou config de deploy.
      Cobre: RNF-07, RF-20
      Acceptance criteria: o ini tem `upload_max_filesize ≥ 10M`, `post_max_size > upload_max_filesize`, e ambos ≥ `PedidoAttachmentStorage::MAX_BYTES`. A regra temporária do Livewire (KB) ≥ `MAX_BYTES / 1024`. `config/livewire.php` não reduz a regra. Não existe `Caddyfile`/`railpack.json`/`Dockerfile` na raiz.
      Testes: `tests/Feature/Compliance/UploadLimitsConsistencyTest.php`.

## Phase 6: Histórico padronizado, resumo e observações

Antes de implementar, leia:
1. `.spec/features/solicitacao-historico-finalizacao/SPEC.md` — requisitos RIGID que esta fase cobre (RF-13, RF-17, RF-21..RF-26, RF-47, UI-03, UI-04, UI-05, CT-04, CT-07, CT-09, RNF-08)
2. `.spec/features/solicitacao-historico-finalizacao/PLAN.md` — decomposição completa, dependências e riscos

- [ ] T21 — Histórico padronizado: apresentador, horário local, snapshot da criação e componente de timeline
      Arquivos: `app/Services/PedidoEventValuePresenter.php`, `resources/views/components/pedido-history-timeline.blade.php`, as views de detalhe de obra, suprimentos e gestão, `tests/Unit/Services/PedidoEventValuePresenterTest.php`, `tests/Feature/Livewire/PedidoHistoryPresentationTest.php` (novos), `tests/Feature/Livewire/PedidoDetalheObraTest.php` (`:34`) e `tests/Feature/Livewire/PedidoDetalheGestaoTest.php` (`:43`)
      Mudança: `describeAll()` devolve ação, contexto, data/hora local (`LocalTime::formatDateTime`) e autor, com consultas constantes. Rótulos e contextos seguem a tabela do RF-21. O contexto de `criacao_pedido` usa o **snapshot** em `new_value` (F-09), com fallback para o `obraLabel()` atual só em evento legado com `new_value` nulo. A timeline mostra ação (negrito) / contexto / "dd/mm/aaaa HH:MM · Autor", e as 3 views passam `:pedido`. D-1/N-02 (aprovado): `->assertSee('Criação do pedido')` em `PedidoDetalheObraTest:34` e `PedidoDetalheGestaoTest:43` vira `->assertSee('Pedido criado')`; nenhuma outra linha desses arquivos muda em T21. O nome do lookup em `EventTypeFactory`/`DemoSeeder` continua "Criação do pedido".
      Cobre: RF-21, RF-23, RF-47, UI-04, RNF-08, CT-07, CT-09
      Acceptance criteria: um pedido criado pela Action para "Residencial Aurora" por "João Silva" em `2026-09-16 13:05Z` mostra, nesta ordem, "Pedido criado", "Solicitação registrada para Residencial Aurora." e "16/09/2026 10:05 · João Silva". Depois de renomear a obra para "Aurora II", o histórico continua dizendo "Residencial Aurora"; um evento legado com `new_value` nulo mostra "Aurora II". "Outra" → "Solicitação registrada para Outra.". As 3 telas renderizam o mesmo texto. O número de consultas é igual com 3 e com 30 eventos. `PedidoDetalheObraTest` e `PedidoDetalheGestaoTest` passam com "Pedido criado" e as demais asserções intactas.
      Testes: os dois arquivos novos acima, mais `PedidoDetalheObraTest` e `PedidoDetalheGestaoTest`.

- [ ] T22 — Resumo do pedido: "Descrição", quatro datas distintas e seção de anexos
      Arquivos: `resources/views/components/pedido-summary.blade.php`, os 3 componentes de detalhe (só eager loads), `tests/Feature/Livewire/PedidoSummaryDatesAndAttachmentsTest.php` (novo)
      Mudança: Obra via `obraLabel()`. "Itens e quantidades" → **"Descrição"** (F-06). Data da solicitação via `LocalTime::formatDateTime`; Preciso para, Data prevista (`dataPrevistaLabel()`) e Previsão de entrega ("—" quando vazia). Nova seção Anexos (link de download, marcador "Romaneio", tamanho, autor, data local, "Nenhum anexo."). `loadMissing('attachments.uploader')`.
      Cobre: UI-03, RF-13, RF-17, RF-47
      Acceptance criteria: nas 3 telas, "Descrição" está presente e "Itens e quantidades" ausente. Os 4 rótulos de data estão presentes, distintos e exatos. Pedido solicitado em `2026-09-25T01:30Z` mostra "24/09/2026 22:30". Um pedido novo mostra Data prevista `dd/mm/aaaa` e "—" em Previsão de entrega. Definir Previsão de entrega não muda a Data prevista e grava 1 `alteracao_previsao`. 1 anexo + 1 romaneio aparecem com o marcador só no romaneio, e os links vão para `pedidos.anexos.download`.
      Testes: `tests/Feature/Livewire/PedidoSummaryDatesAndAttachmentsTest.php`.

- [ ] T23 — Observações: Action, habilidade e controles nos detalhes de Obra e Suprimentos
      Arquivos: `app/Actions/Pedidos/AddPedidoObservacaoAction.php`, `app/Actions/Pedidos/Concerns/GuardsObraPedidoMutation.php` (novos), `app/Policies/PedidoPolicy.php`, os componentes e views de detalhe de obra e suprimentos, `tests/Feature/Actions/AddPedidoObservacaoActionTest.php`, `tests/Feature/Livewire/PedidoObservacaoControlTest.php` (novos), `tests/Feature/Livewire/PedidoDetalheObraTest.php` (só o teste de `:36-53`)
      Mudança: guarda `obra` com `view` ou `suprimentos`. Texto aparado, `required|max:2000`, com mensagens PT-BR. Sem guarda terminal. 1 evento `observacao` com o texto em `new_value`; o pedido não é tocado. O formulário aparece em todos os status nas telas de Obra e Suprimentos, e nunca na de Gestão.
      D-1/N-02 (aprovado): o teste "no edit form or mutation control is rendered" de `PedidoDetalheObraTest:36-53` é estreitado para a intenção da US-2.2. Ele coleta do HTML os alvos de `wire:submit*`, `wire:click*` e `wire:model*` e afirma **positivamente** submit = exatamente `['adicionarObservacao']`, model = exatamente `['observacao']` e click = vazio. Afirma também que nenhum alvo nem `name=` se refere a `obra_id`, `obra_selection`, `obra_reference`, `descricao`, `items_description`, `needed_at`, `status_id`, `responsible_id`, `priority_id`, `expected_delivery_at`, `updateStatus`, `cancelarPedido`, romaneio ou `finalizar*`. `PedidoDetalheGestaoTest:61-62` não muda.
      Cobre: RF-22, RF-24, RF-25, RF-26, UI-05, CT-04
      Acceptance criteria: 2 observações → 2 eventos, a primeira intacta, com `updated_at` e status do pedido inalterados. Em branco → 422. 2000 caracteres aceitos e 2001 → 422. Em Entregue, Cancelado e Finalizado → aceita, sem 409. Gestão e obra de outra obra → negado, 0 eventos. O controle existe para obra e suprimentos (inclusive terminais) e não para gestão. Depois do envio a textarea fica vazia. `PedidoDetalheObraTest` estreitado passa com as asserções positivas e negativas acima; `PedidoDetalheGestaoTest:61-62` passa sem mudança; a Fase 6 fecha verde.
      Testes: os dois arquivos novos acima, mais `PedidoDetalheObraTest` e `PedidoDetalheGestaoTest`.

## Phase 7: Entregue pela Obra, romaneio e Finalizado

Antes de implementar, leia:
1. `.spec/features/solicitacao-historico-finalizacao/SPEC.md` — requisitos RIGID que esta fase cobre (RF-01, RF-22, RF-25, RF-27..RF-38, UI-06, UI-07, CT-04, CT-08, RNF-02)
2. `.spec/features/solicitacao-historico-finalizacao/PLAN.md` — decomposição completa, dependências e riscos

- [ ] T24 — Obra "Marcar como entregue" no detalhe
      Arquivos: `app/Actions/Pedidos/MarkPedidoEntregueByObraAction.php` (novo), `app/Policies/PedidoPolicy.php` (`marcarEntregue`), `app/Livewire/Obra/PedidoDetalhe.php` e sua view, `tests/Feature/Actions/MarkPedidoEntregueByObraActionTest.php`, `tests/Feature/Livewire/ObraMarcarEntregueTest.php` (novos), `tests/Feature/Livewire/PedidoDetalheObraTest.php` (só o teste estreitado por T23)
      Mudança: guarda obra com `view` + `ensurePedidoIsNotTerminal`. Na transação, releitura com `lockForUpdate`, nova checagem, status `entregue` e 1 evento `entrega` com o ator obra. O botão só aparece em status ativo, com confirmação em 2 etapas; nada muda em listagens ou cards. D-1/N-02: o teste estreitado de `PedidoDetalheObraTest` passa a esperar click = exatamente `['confirmarEntrega']` num pedido ativo e, depois de `->call('confirmarEntrega')`, exatamente `['marcarComoEntregue', 'abortarEntrega']`; submit/model e a lista negativa não mudam.
      Cobre: RF-27, RF-28, RF-29, UI-06, RNF-02, CT-04
      Acceptance criteria: de cada um dos 4 ativos → `entregue` + 1 `entrega`, contado em `entreguesHoje` no mesmo dia local (relógio fixo ao meio-dia). De Entregue, Cancelado e Finalizado → 409 e 0 eventos. Obra alheia, gestão e suprimentos → negado. Instância velha depois de um cancelamento → 409. O botão fica ausente em pedido terminal, e `/obra/pedidos` não tem a ação. Em `PedidoDetalheObraTest`, os únicos formulários/ações do detalhe da Obra são observação e "Marcar como entregue" (igualdade de conjuntos); `PedidoDetalheGestaoTest:61-62` segue inalterado e verde; a Fase 7 fecha verde.
      Testes: os dois arquivos novos acima, mais `PedidoDetalheObraTest` e `PedidoDetalheGestaoTest`.

- [ ] T25 — Upload de romaneio por Suprimentos
      Arquivos: `app/Actions/Pedidos/AttachRomaneioAction.php` (novo), `app/Policies/PedidoPolicy.php` (`anexarRomaneio`), `app/Livewire/Suprimentos/PedidoDetalhe.php` e sua view, `tests/Feature/Actions/AttachRomaneioActionTest.php`, `tests/Feature/Livewire/RomaneioUploadControlTest.php` (novos)
      Mudança: só Suprimentos; status em `finalizableFrom()`, senão 409. `inspect(..., Romaneio)` antes de gravar. Na transação: lock, nova checagem, arquivo, linha `romaneio` e 1 evento `romaneio_anexado` com o nome exibido. Em erro, o arquivo é removido. O controle aparece em ativos e Entregue.
      Cobre: RF-30, RF-31, RF-32, UI-07, RNF-02, CT-04
      Acceptance criteria: "qualquer-nome.pdf" → 1 linha `romaneio` + 1 evento com esse contexto, listado com o marcador. 2 uploads → 2 linhas + 2 eventos. Em Entregue é aceito; em Cancelado/Finalizado → 409, sem linhas nem arquivos novos. `.docx`/`.webp` → 422. Obra e gestão → negado. Um anexo de criação chamado "romaneio.pdf" nunca conta como romaneio.
      Testes: os dois arquivos acima.

- [ ] T26 — Finalizar pedido (exige romaneio válido)
      Arquivos: `app/Actions/Pedidos/FinalizePedidoAction.php` (novo), `app/Policies/PedidoPolicy.php` (`finalizar`), `app/Livewire/Suprimentos/PedidoDetalhe.php` e sua view, `tests/Feature/Actions/FinalizePedidoActionTest.php`, `tests/Feature/Livewire/FinalizarPedidoControlTest.php` (novos)
      Mudança: só Suprimentos, status em `finalizableFrom()`. Na transação: `lockForUpdate` e nova checagem (409). Um romaneio é válido se a linha existe e o arquivo está presente no disco. Sem romaneio válido → 422 "Não foi possível finalizar o pedido. Anexe o romaneio antes de finalizar.". Com romaneio → `finalizado` + 1 evento `finalizacao`. O botão fica desabilitado com dica quando não há romaneio, tem confirmação em 2 etapas e mostra o erro em `role="alert"`.
      Cobre: RF-33..RF-38, UI-07, RNF-02, CT-04, CT-08
      Acceptance criteria: com romaneio, dos 4 ativos e de Entregue → `finalizado` + 1 evento, com os anteriores intactos. De Cancelado/Finalizado → 409. Sem anexos, só com `anexo`, ou com o arquivo do romaneio ausente → 422 com o texto exato, sem mudança. Obra e gestão → negado. Uma chamada forjada mostra o texto em `role="alert"`. Em Entregue, os controles operacionais ficam ocultos e romaneio + Finalizar aparecem.
      Testes: os dois arquivos acima.

- [ ] T27 — Testes adversariais, matriz terminal e concorrência da finalização
      Arquivos: `tests/Feature/Security/Adversarial/{PedidoFinalizationConcurrencyTest,PedidoOperationsAuthorizationTest,FinalizadoTerminalMatrixTest}.php` (novos), `tests/Feature/Authorization/BypassUiAuthorizationTest.php` (só adições)
      Mudança: só testes. Concorrência simulada com instâncias velhas (método da fatia 1 T22). Matriz terminal de Finalizado e Entregue. Cada método Livewire novo é chamado por papel proibido via `/livewire/update`. Cada Action nova é chamada com ator proibido. Tentativa de criação por gestão em todas as camadas. Trilha append-only.
      Cobre: RF-01, RF-22, RF-25, RF-28, RF-31, RF-33, RF-36, RF-37, RF-38, RNF-02
      Acceptance criteria: duas finalizações → 1 sucesso, 1 `PedidoTerminalStateException` e 1 `finalizacao`; o mesmo para Obra Entregue vs cancelamento. Em Finalizado, as 6 mutações + romaneio + finalizar → 409 e a observação é aceita. Em Entregue, as 5 Actions de Suprimentos + Obra entregue → 409, e romaneio, finalizar e observação são aceitos. Papel proibido → 403 e 0 linhas. Gestão nunca avança `pedido_code_sequence`. Criação + 2 observações + romaneio + finalização → exatamente 5 eventos em ordem.
      Testes: os quatro arquivos acima.

## Phase 8: Demo, desempenho, navegador e conformidade

Antes de implementar, leia:
1. `.spec/features/solicitacao-historico-finalizacao/SPEC.md` — requisitos RIGID que esta fase cobre (RF-01, RF-11, RF-12, RF-14, RF-16, RF-19, RF-35, RF-43, RF-47, RF-48, RNF-01, RNF-03, RNF-04, RNF-06, UI-01, UI-02, UI-05, UI-06, UI-07)
2. `.spec/features/solicitacao-historico-finalizacao/PLAN.md` — decomposição completa, dependências e riscos

- [ ] T28 — `demo:reset` com anexos, trilha append-only dos anexos e associações do Suprimentos demo
      Arquivos: `app/Console/Commands/ResetDemoData.php`, `database/seeders/DemoSeeder.php` (`seedObras()` e o evento `criacao_pedido` de `seedPedidos()`), `tests/Feature/Console/ResetDemoDataTest.php`, `tests/Feature/Compliance/AuditTrailsAppendOnlyTest.php` (adições), `tests/Feature/Seeders/DemoSeederIdempotencyTest.php` (adições)
      Mudança:
      - `demo:reset`: coleta os caminhos dos anexos demo antes do delete; as linhas caem por cascade; os arquivos são apagados só depois do commit. Anexos reais nunca são tocados.
      - `DemoSeeder` (F-03): associa, de forma idempotente (`syncWithoutDetaching`), o usuário Suprimentos demo a toda obra demo `Obra::active()`, só usuário demo × obra demo, nunca usuários reais (RF-48). Os eventos `criacao_pedido` do seeder levam o snapshot `obraLabel()` em `new_value`.
      - `PedidoAttachment` entra em `AUDIT_MODELS`, com `ResetDemoData` como única exceção.
      Cobre: RF-19, RF-43, RF-48, RNF-06
      Acceptance criteria: seed + anexo em pedido demo e em pedido real → `demo:reset --force` sai com 0; a linha e o arquivo demo somem e os reais ficam. Uma falha dentro da transação não apaga arquivo. `db:seed` duas vezes → 7 status, 10 tipos, e o Suprimentos demo tem exatamente uma linha de `obra_profile` por obra demo ativa (nenhuma para Concluída nem para usuário não demo). GET `/suprimentos/nova-solicitacao` como Suprimentos demo mostra o formulário, não o estado vazio.
      Testes: os três arquivos acima.

- [ ] T29 — Orçamento de consultas dos detalhes e da Nova Solicitação
      Arquivos: `tests/Feature/Performance/QueryCountTest.php` (só adições)
      Mudança: casos com `measureQueryCount` para os 3 detalhes (3 vs 30 eventos, 1 vs 10 anexos), a Nova Solicitação (1 vs 15 obras) e o Dashboard (0 vs 5 "Outra", com e sem período De/Até). Se uma contagem crescer, corrigir o eager loading; nunca afrouxar o teste.
      Cobre: RNF-03
      Acceptance criteria: cada par de medições dá contagens iguais.
      Testes: `tests/Feature/Performance/QueryCountTest.php`.

- [ ] T30 — Navegador: responsividade e fluxo ponta a ponta (Obra e Suprimentos criam)
      Arquivos: `tests/Browser/ResponsiveIdentityTest.php` (adições), `tests/Browser/SolicitacaoFinalizacaoFlowTest.php` (novo), `tests/Browser/DemoRoteiroTest.php` (só rótulos e seletores da UI-01)
      Mudança: auditoria de responsividade (1440×900, 820×1180, 390×844) nas 2 Novas Solicitações e nos 3 detalhes. Fluxo: (1) obra cria "Outra" com PDF + PNG; (2) adiciona observação e marca entregue; (3) suprimentos vê Finalizar desabilitado e a chamada forjada mostra o erro do RF-35; (4) anexa romaneio e finaliza; (5) **(F-03)** um suprimentos associado a uma obra ativa abre `/suprimentos/nova-solicitacao` pela toolbar, cria uma solicitação para essa obra e a vê em Todos os Pedidos. Respeitar as armadilhas do pest-browser.
      Cobre: RNF-04, UI-01, UI-02, UI-05, UI-06, UI-07, RF-01, RF-35
      Acceptance criteria: sem overflow horizontal e com o controle principal dentro da viewport nas 3 viewports. O fluxo mostra código e Data prevista `dd/mm/aaaa`, a mensagem exata do RF-35 em `role="alert"`, o badge "Finalizado", as linhas "Romaneio anexado" e "Pedido finalizado", e a criação por Suprimentos com código e link para Todos os Pedidos.
      Testes: os três arquivos acima (exigem `npm run build` e Chromium).

- [ ] T31 — Varreduras de conformidade da fatia
      Arquivos: `tests/Feature/Compliance/{DataPrevistaSingleRuleTest,PedidoAttachmentsComplianceTest,LocalTimeDisplayComplianceTest}.php` (novos)
      Mudança: guardas estáticas e de runtime.
      - Regra única de dias úteis em `app/`; a única outra implementação no repositório é a cópia congelada da migration de T02. Sem `easter_*` em `app/`/`database/`. Sem "3 dias" exibido como previsão. Data prevista só via `dataPrevistaLabel()`/`presentDataPrevista()`.
      - Anexos: `Anexo` só em `CreatePedidoAction`, `Romaneio` só em `AttachRomaneioAction`. Nenhum update/delete de anexo fora de `ResetDemoData`. Nada no disco `public`. Rota única com `auth`+`active`. Sem `temporaryUrl()`.
      - RF-47: nas views das fatias 1 e 2 (resumo, timeline, detalhes, Kanban, Dashboard, Visão Geral, obras, associações e convite/cadastro), nenhum `->format(` com `H:i` fora de `LocalTime`, e nenhum timestamp (`requested_at`, `created_at`, `expires_at`, `revoked_at`, `used_at`) formatado direto. `x-pedido-table` fica excluído (fatia 3).
      - RF-48: nenhuma migration desta fatia grava `obra_profile`.
      Cobre: RF-11, RF-12, RF-14, RF-16, RF-19, RF-47, RF-48, RNF-01
      Acceptance criteria: as três varreduras passam no código final da fatia.
      Testes: os três arquivos acima.

## Phase 9: Runbook e gates finais

Antes de implementar, leia:
1. `.spec/features/solicitacao-historico-finalizacao/SPEC.md` — requisitos RIGID que esta fase cobre (RF-20, RF-44, RF-48, RNF-05, RNF-06, RNF-07)
2. `.spec/features/solicitacao-historico-finalizacao/PLAN.md` — decomposição completa, dependências e riscos (T34 lista a allow-list completa de asserções reescritas)

- [ ] T32 — Runbook de produção: Volume Railway, raiz do disco, limites de upload e associações de Suprimentos
      Arquivos: `README.md` ("Produção (Railway)" → nova subseção "Anexos de pedidos (Volume e limites de upload)" e um passo no checklist de publicação; nota local em "Instalação e execução local"), `.env.example` (linha comentada `# PEDIDO_ANEXOS_ROOT=`), `tests/Feature/Compliance/EnvExampleTest.php` (adições)
      Mudança: passos operacionais, não executados agora:
      (1) criar um Volume em `/data`;
      (2) definir `PEDIDO_ANEXOS_ROOT=/data/pedido-anexos` e `PHP_INI_SCAN_DIR=:/app/config/php` antes do build;
      (3) verificar somente leitura `php --ini`, `ini_get` e `test -w /data`, com confirmação do desenvolvedor;
      (4) não dar push antes de (1) e (2);
      (5) testar upload → redeploy → download de PDF e DOCX;
      (6) **(RF-48)** "associar os usuários Suprimentos às obras em /associacoes antes de anunciar a Nova Solicitação";
      (7) ambiente local com `PHP_INI_SCAN_DIR`.
      Registrar também que "hoje" e o período seguem o dia de São Paulo.
      Cobre: RF-20, RF-48, RNF-07, RNF-05
      Acceptance criteria: o README contém "PEDIDO_ANEXOS_ROOT", "Volume", "PHP_INI_SCAN_DIR" e o passo exato "associar os usuários Suprimentos às obras em /associacoes antes de anunciar a Nova Solicitação". O `.env.example` só cita `PEDIDO_ANEXOS_ROOT` comentada e vazia. `EnvExampleTest` e `NoCommittedSecretsTest` passam.
      Testes: `tests/Feature/Compliance/EnvExampleTest.php`.

- [ ] T34 — Gates finais: Pint, build, suítes e dependências
      Arquivos: nenhum (somente verificação)
      Mudança:
      - Rodar Pint e `npm run build`, depois Feature, Unit e Browser, um processo Pest por vez.
      - `git diff <base>..HEAD` não mostra mudança em `composer.json`, `composer.lock`, `package.json` nem `package-lock.json`, nem arquivo de teste apagado.
      - As únicas asserções ou fixtures pré-existentes reescritas são as da allow-list do PLAN. Ela cobre T05, T06, T08, T10, T11, T12, T13, T14 e T15 (inclusive os chamadores do F-02: `PedidoDetalheObraTest`, `PedidoDetalheGestaoTest`, `ObraScreensRouteTest`, `BypassUiAuthorizationTest`, `CrossObraTest` e `ZeroObraUserTest` da fatia 1), além de T16, T21 (`PedidoDetalheObraTest:34` e `PedidoDetalheGestaoTest:43` → "Pedido criado"), T23 e T24 (o teste de `PedidoDetalheObraTest:36-53`, estreitado à US-2.2 com asserções positivas; `PedidoDetalheGestaoTest:61-62` idêntico à base), T37 (só relógio de fixture), T28 e T30.
      - `migrate:fresh --seed` roda duas vezes seguidas num banco de rascunho.
      Cobre: RNF-05, RNF-06, RF-44
      Acceptance criteria: 0 falhas em Unit, Feature e Browser. Pint limpo e build com saída 0. Nenhuma dependência nova e nenhum teste apagado. `tests/Feature/{Auth,Authorization,Security,Compliance,Actions,Livewire}` verde.
      Testes: as suítes completas.

## Phase 10: Documentação (commit somente de documentação)

Antes de implementar, leia:
1. `.spec/features/solicitacao-historico-finalizacao/SPEC.md` — requisitos RIGID que esta fase cobre (RNF-08, RF-44)
2. `.spec/features/solicitacao-historico-finalizacao/PLAN.md` — decomposição completa, dependências e riscos

Esta fase só altera `docs/agents/*.md`, `CLAUDE.md` e `tests/README.md` (espelha o F-05 da fatia 1). Antes de regenerar, `git status --short docs/agents` precisa estar vazio (G-1).

- [ ] T33 — Documentação: `/ai-context` e `CLAUDE.md`
      Arquivos: `docs/agents/*.md` (regenerados), `CLAUDE.md` (manual), `tests/README.md`
      Mudança: rodar `/ai-context` para registrar `create-pedido`, as duas rotas de Nova Solicitação, o download, `pedido_attachments`, `obra_reference`/`data_prevista`, o disco `pedido_anexos`, `finalizado`, os 3 tipos de evento, as 4 Actions, os pontos de apresentação, `LocalTime` e `RequestedPeriodFilter`. Se o Ralph headless não conseguir invocar `/ai-context` ([UNVERIFIED]), a regeneração é passo do desenvolvedor num commit só de documentação. `CLAUDE.md` à mão: §3 (Finalizado, saída única Entregue → Finalizado, Data prevista e feriados, cópia congelada do backfill, "Outra", regra do calendário local), §4, §5, §6 (snapshot de `criacao_pedido`) e §7. Nunca criar `AI_CONTEXT.md`.
      Cobre: RNF-08, RF-44
      Acceptance criteria: `docs/agents/*.md` citam os nomes acima. `CLAUDE.md` documenta Finalizado, a regra do calendário local e o snapshot. O diff da fase se restringe a `docs/agents/*.md`, `CLAUDE.md` e `tests/README.md`. `DocumentationParityTest` passa.
      Testes: `tests/Feature/Compliance/DocumentationParityTest.php`.
