# Phases: notificacoes-internas

Gerado por /plan a partir de PLAN.md — view executável para `./ralph.sh .spec/features/notificacoes-internas/PHASES.md`.
Branch: `build/v0-demo-laravel` · 22 tarefas em 9 fases · SPEC v1.2 sem marcadores (Q-07: abrir notificação marca lida e navega) · nenhum arquivo de contrato emitido (sem superfície REST/gRPC/assíncrona; esquemas inline no PLAN).

**Gates de execução (não são tarefas). Detalhes no topo do PLAN.md:**
- **G-1:** `git status --short docs/agents` vazio antes do Ralph.
- **G-2 (antes do push):** o desenvolvedor confirma `MAIL_MAILER`, `MAIL_FROM_ADDRESS` e `MAIL_FROM_NAME` no Railway. Nenhuma variável nova.
- **G-3 (pós-deploy):** o desenvolvedor executa o roteiro de medição RNF-03 de `.spec/features/notificacoes-internas/defer-verificacao.md` e registra passa/falha.

Regras válidas para todas as fases:
- Toda fase fecha verde: `php artisan test --compact --testsuite=Feature` e `--testsuite=Unit`. Um processo Pest por vez (PostgreSQL de teste em `127.0.0.1:5434`); `--filter` sempre com `--testsuite=Feature`.
- PHP compatível com **8.4**; `vendor/bin/pint --dirty --format agent` depois de qualquer mudança PHP; arquivos criados com `php artisan make:* --no-interaction`.
- Nenhuma dependência nova (Composer/npm), nenhum serviço Railway, nenhuma variável de ambiente nova, nenhum `ShouldQueue`, nenhuma pasta-base nova.
- Componente Livewire → `authorize()` → Action (guards, validação PT-BR, `DB::transaction`). `Pedido::visibleTo` abre toda consulta de visibilidade, inclusive dentro de `InternalNotification::forRecipient`. Datas/horas exibidas só por `App\Support\LocalTime`. Filtros só por `#[Url]` com `except:`. Toggles Alpine por `data-open` com classes Tailwind literais. Item de navegação só por `SidebarNavigation::catalogue()`.
- Testes são atualizados, nunca apagados; só mudam as asserções listadas no PLAN (T12, T15, T16).

## Phase 1: Fundação — verificação do defer(), tabela, classificação e destinatários

Antes de implementar, leia:
1. `.spec/features/notificacoes-internas/SPEC.md` — requisitos RIGID que esta fase cobre (RF-02..RF-09, RF-22, RNF-01, RNF-03, RNF-04, RNF-05, RNF-09, CT-02)
2. `.spec/features/notificacoes-internas/PLAN.md` — decomposição completa, dependências e riscos

A migration desta fase roda no start do container em produção (Railpack). Ela é só aditiva.

- [ ] T01 — Verificação leve de `defer()` sob FrankenPHP (RNF-03, parte estática)
      Arquivos: `.spec/features/notificacoes-internas/defer-verificacao.md` (novo), `tests/Feature/Notifications/DeferredCallbacksAfterResponseTest.php` (novo)
      Mudança: sem código de produção. Confirmar no vendor a ordem `Response::send()` (→ `fastcgi_finish_request()` se existir) → `InvokeDeferredCallbacks::terminate()`, a regra status < 400 / `always`, e a execução em console no `CommandFinished`. Verificar pela versão do FrankenPHP do log de deploy (Railway read-only; sem acesso → `[UNVERIFIED]`) se o SAPI HTTP expõe `fastcgi_finish_request()`/`frankenphp_finish_request()`. Registrar no `.md` a conclusão e a decisão sobre a chamada guardada `frankenphp_finish_request()` em T06. Teste com rota registrada só dentro do teste.
      Cobre: RNF-03 (parte estática), RNF-01, RNF-05
      Acceptance criteria: `defer-verificacao.md` existe com versão do FrankenPHP (ou `[UNVERIFIED]`), conclusão sobre `fastcgi_finish_request`/`frankenphp_finish_request` e decisão explícita para T06; o teste prova que o callback diferido roda depois da resposta no pipeline HTTP, não roda em 500 sem `always` e roda com `always: true`; nenhum arquivo em `app/`, `config/` ou `routes/` foi alterado.
      Testes: `tests/Feature/Notifications/DeferredCallbacksAfterResponseTest.php` — ordem e condições de execução dos callbacks diferidos
- [ ] T02 — Tabela `internal_notifications`, modelo `InternalNotification`, enum de estado do e-mail e factory
      Arquivos: `database/migrations/<timestamp>_create_internal_notifications_table.php`, `app/Models/InternalNotification.php`, `app/Enums/InternalNotificationEmailStatus.php`, `database/factories/InternalNotificationFactory.php` (novos); `app/Models/User.php` (relação `internalNotifications()`)
      Mudança: colunas `id`, `recipient_id` (FK users RESTRICT), `pedido_id` (FK pedidos CASCADE), `pedido_event_id` (FK pedido_events CASCADE), `event_type_slug varchar(40)`, `actor_id` (FK users RESTRICT), `created_at` useCurrent sem `updated_at`, `read_at` nullable, `email_status varchar(10) default 'pendente'` com check `internal_notifications_email_status_check` (`pendente|enviado|falhou`), `email_status_at` nullable. Unique `(pedido_event_id, recipient_id)`; índice parcial `internal_notifications_unread_index` em `(recipient_id, created_at desc) where read_at is null`; índice `(recipient_id, created_at)`. Enum `Pendente|Enviado|Falhou`. Modelo com `#[Fillable]`, `UPDATED_AT = null`, casts, relações `recipient`, `actor`, `pedido`, `event`; `updating` lança `LogicException` se coluna suja fora de `read_at`/`email_status`/`email_status_at`; `deleting` sempre lança (padrão `PedidoEvent.php:28-37`).
      Cobre: CT-02, RF-22, RNF-04
      Acceptance criteria: `migrate:fresh` cria a tabela com as FKs e ações, o check, a unique e o índice parcial descritos e sem `updated_at`; atualizar `read_at` ou `email_status` via Eloquent funciona; atualizar qualquer outra coluna ou chamar `delete()` lança `LogicException`; `down()` remove a tabela.
      Testes: `tests/Feature/Migrations/InternalNotificationsTableTest.php` — schema; `tests/Unit/Models/InternalNotificationImmutabilityTest.php` — colunas permitidas e proibidas, exclusão
- [ ] T03 — Classificação única dos tipos notificáveis (`NotifiableEventTypes`)
      Arquivos: `app/Domain/Pedidos/NotifiableEventTypes.php` (novo), `tests/Unit/Domain/NotifiableEventTypesTest.php` (novo), `tests/Feature/Compliance/NotifiableEventTypesDefinitionTest.php` (novo)
      Mudança: classe de instância resolvida pelo container; `classification(): array<string, bool>` com os 10 valores de `EventTypeSlug` = `true`; `isNotifiable(string $slug): bool` (`false` fora do mapa). Único lugar de `app/` que decide se um tipo notifica.
      Cobre: RF-02, RF-09
      Acceptance criteria: os 10 slugs são notificáveis e um slug desconhecido não é; o teste de compliance itera `EventTypeSlug::cases()` e falha se algum caso faltar no mapa; uma subclasse ligada no container altera a classificação sem tocar em outro arquivo.
      Testes: `tests/Unit/Domain/NotifiableEventTypesTest.php` — mapa e slug desconhecido; `tests/Feature/Compliance/NotifiableEventTypesDefinitionTest.php` — cobertura de `EventTypeSlug::cases()` e definição única
- [ ] T04 — Regra única de destinatários (`NotificationRecipientResolver`) em uma consulta deduplicada
      Arquivos: `app/Domain/Pedidos/NotificationRecipientResolver.php` (novo), `tests/Feature/Notifications/NotificationRecipientResolverTest.php` (novo), `tests/Feature/Notifications/NotificationRecipientPolicyParityTest.php` (novo)
      Mudança: `recipientIdsFor(PedidoEvent $event): list<int>` executa 1 `SELECT DISTINCT users.id` (users ⋈ roles, lendo a linha atual de `pedidos` do evento): ativos, ≠ `actor_id`; `gestao` sempre; com `obra_id`: `obra` associado em `obra_profile`, `suprimentos` associado **ou** `= responsible_id`; "Outra" (`obra_id` nulo): `obra` só se `= requester_id`, todos os `suprimentos`. Papel desconhecido nunca entra. O docblock registra o espelho de `PedidoPolicy::view`/`Pedido::visibleTo` e o escopo mais estreito de `suprimentos`. Só leitura.
      Cobre: RF-03, RF-04, RF-04a, RF-04b, RF-05, RF-06, RF-07, RF-08, RNF-09
      Acceptance criteria: na matriz do AC de RF-04, S1, S3 e O1 entram e S2 e O2 não; S1 associado e responsável aparece 1 vez; após troca de responsável para S3 não associado, S3 entra e o anterior não; autor e inativos ficam fora; no "Outra" do AC de RF-08, O1 e S2 entram e S1, S3 e outros `obra` não; o resolver emite exatamente 1 consulta; para todo id devolvido, `Gate::forUser($u)->allows('view', $pedido)` é `true`.
      Testes: `tests/Feature/Notifications/NotificationRecipientResolverTest.php` — matriz RF-03..RF-08 e contagem de consultas; `tests/Feature/Notifications/NotificationRecipientPolicyParityTest.php` — paridade com `PedidoPolicy::view`

## Phase 2: Apresentação, e-mail diferido e ponto único de registro

Antes de implementar, leia:
1. `.spec/features/notificacoes-internas/SPEC.md` — requisitos RIGID que esta fase cobre (RF-01, RF-09, RF-14..RF-17, RNF-01, RNF-02, RNF-06, RNF-08, CT-03)
2. `.spec/features/notificacoes-internas/PLAN.md` — decomposição completa, dependências e riscos

Leia também `.spec/features/notificacoes-internas/defer-verificacao.md` (resultado de T01) antes de T06. Ordem obrigatória: T05 → T06 → T07.

- [ ] T05 — Apresentação do evento fora do detalhe e rota de detalhe por papel
      Arquivos: `app/Services/PedidoEventValuePresenter.php` (alterado), `app/Support/PedidoDetailRoute.php` (novo), `tests/Unit/Services/PedidoEventValuePresenterEachTest.php` (novo), `tests/Unit/Support/PedidoDetailRouteTest.php` (novo)
      Mudança: `describeEach(Collection $events)` para eventos de vários pedidos (fallback de `criacao_pedido` pelo `pedido` de cada evento, lookups via `labelsFor()` uma vez por coleção); `describeAll()` intocado. `PedidoDetailRoute::nameFor(User)` → `obra.pedidos.show` | `suprimentos.pedidos.show` | `gestao.pedidos.show` | `null`; `absoluteUrlFor(User, Pedido)` ancorado em `APP_URL` como `BuildsAppUrl`.
      Cobre: RF-14, RF-15, UI-01, UI-02
      Acceptance criteria: `describeEach` em eventos de 3 pedidos devolve o mesmo que `describeAll` por pedido, com número de consultas constante; com `APP_URL=https://exemplo.test`, `obra` → `https://exemplo.test/obra/pedidos/{id}` e `gestao` → `https://exemplo.test/gestao/pedidos/{id}`; o histórico do detalhe continua idêntico (testes existentes do presenter verdes).
      Testes: `tests/Unit/Services/PedidoEventValuePresenterEachTest.php` — paridade e consultas; `tests/Unit/Support/PedidoDetailRouteTest.php` — rota por papel e URL absoluta
- [ ] T06 — E-mail de notificação enviado depois da resposta (`InternalNotificationMailer` + `PedidoEventNotification`)
      Arquivos: `app/Services/InternalNotificationMailer.php`, `app/Notifications/PedidoEventNotification.php`, `resources/views/mail/pedidos/notificacao.blade.php` (novos); `app/Providers/AppServiceProvider.php` (binding `scoped`); `tests/Feature/Notifications/InternalNotificationMailerTest.php`, `tests/Feature/Notifications/PedidoEventNotificationMailTest.php` (novos)
      Mudança: notificação só `mail`, sem `ShouldQueue`, assunto `[<código>] <rótulo do tipo> — <obraLabel>`, markdown sobre `<x-mail.transactional>` com código, obra, tipo, conteúdo, ator, `LocalTime::formatDateTime` e botão "Ver pedido" (`PedidoDetailRoute::absoluteUrlFor`); nunca senha/token/anexo. Mailer `scoped`: `queueEvent(int)` acumula ids e registra um único `defer(fn () => $this->flush(), always: true)`; `flush()` chama `frankenphp_finish_request()` guardado por `function_exists` se T01 decidir, carrega as linhas `pendente` com eager load, envia uma a uma em `try/catch`, grava `email_status`/`email_status_at` por linha, loga falha só com ids e classe da exceção (nunca mensagem, e-mail ou texto) e espaça envios por `SEND_INTERVAL_MS` quando `mail.default = resend`.
      Cobre: RF-14, RF-15, RF-16, RNF-01, RNF-06, RNF-08, CT-03
      Acceptance criteria: o e-mail renderizado contém `PED-…`, rótulo do tipo, conteúdo, ator, data/hora local e o link do papel, com o assunto no formato de CT-03; duas chamadas de `queueEvent` na mesma requisição registram 1 callback diferido; 0 mensagens antes de `app(DeferredCallbackCollection::class)->invoke()` e N depois; com transporte que lança para A, A fica `falhou` com `email_status_at`, B fica `enviado` e recebe; o log capturado não contém o e-mail de A, o texto nem token; nenhuma classe em `app/Notifications` implementa `ShouldQueue`.
      Testes: `tests/Feature/Notifications/PedidoEventNotificationMailTest.php` — conteúdo e assunto; `tests/Feature/Notifications/InternalNotificationMailerTest.php` — diferimento, estado por linha, falha isolada, log sem PII
- [ ] T07 — Ponto único de registro (`PedidoNotificationRecorder`)
      Arquivos: `app/Services/PedidoNotificationRecorder.php` (novo), `tests/Feature/Notifications/PedidoNotificationRecorderTest.php` (novo)
      Mudança: `record(PedidoEvent $event): void`: `LogicException` se `DB::transactionLevel() === 0`; lê o slug do tipo e sai se `NotifiableEventTypes::isNotifiable()` for falso; `NotificationRecipientResolver::recipientIdsFor()`; sem destinatário sai; senão 1 INSERT em lote (`email_status = pendente`); `DB::afterCommit(fn () => $mailer->queueEvent($event->id))`. Delta ≤ 3 consultas, constante.
      Cobre: RF-01, RF-09, RF-17, RNF-02
      Acceptance criteria: fora de transação lança `LogicException`; dentro de `DB::transaction` com 3 destinatários grava 3 linhas e enfileira 1 evento no mailer após o commit; com exceção depois do `record`, 0 notificações e 0 e-mails após `invoke()`; tipo não notificável grava 0; um slug fictício ligado como notificável (subclasse no container + linha em `event_types`) notifica sem nenhuma outra mudança.
      Testes: `tests/Feature/Notifications/PedidoNotificationRecorderTest.php` — transação, rollback, classificação e extensão fictícia

## Phase 3: Acoplamento às 10 Actions e controle "Observação / ocorrência"

Antes de implementar, leia:
1. `.spec/features/notificacoes-internas/SPEC.md` — requisitos RIGID que esta fase cobre (RF-01, RF-02, RF-04b, RF-09..RF-13, UI-09, CT-04, CT-05)
2. `.spec/features/notificacoes-internas/PLAN.md` — decomposição completa, dependências e riscos

Guards, validação, mensagens e assinaturas das Actions **não mudam**: só entra a chamada ao recorder, dentro da transação existente, logo depois de cada evento.

- [ ] T08 — Acoplar o registro às Actions de status (status, entrega, cancelamento, finalização, romaneio, entrega pela obra)
      Arquivos: `app/Actions/Pedidos/UpdatePedidoStatusAction.php`, `app/Actions/Pedidos/CancelPedidoAction.php`, `app/Actions/Pedidos/FinalizePedidoAction.php`, `app/Actions/Pedidos/AttachRomaneioAction.php`, `app/Actions/Pedidos/MarkPedidoEntregueByObraAction.php`
      Mudança: injetar `PedidoNotificationRecorder` por construtor (property promotion; manter `PedidoAttachmentStorage` onde já existe) e chamar `$this->notificationRecorder->record($event)` logo depois de cada `events()->create(...)`, dentro do mesmo `DB::transaction` e depois do `lockForUpdate`. Docblocks atualizados.
      Cobre: RF-01, RF-02, RF-09
      Acceptance criteria: cada um dos 5 arquivos chama `notificationRecorder->record(` dentro do `DB::transaction`, depois do `events()->create(`; os testes existentes `tests/Feature/Actions/{UpdatePedidoStatus,CancelPedido,FinalizePedido,AttachRomaneio,MarkPedidoEntregueByObra}ActionTest.php` passam sem alteração de asserção.
      Testes: suíte existente de `tests/Feature/Actions/` (regressão); a cobertura nova está em T10
- [ ] T09 — Acoplar o registro às Actions de criação, atributos e observação
      Arquivos: `app/Actions/Pedidos/CreatePedidoAction.php`, `app/Actions/Pedidos/UpdatePedidoResponsavelAction.php`, `app/Actions/Pedidos/UpdatePedidoPrioridadeAction.php`, `app/Actions/Pedidos/UpdatePedidoPrevisaoAction.php`, `app/Actions/Pedidos/AddPedidoObservacaoAction.php`
      Mudança: igual a T08. Em `CreatePedidoAction`, dentro do `try` da transação (a limpeza de anexos continua). Em `UpdatePedidoResponsavelAction`, depois do `update` do pedido (RF-04b). Em `AddPedidoObservacaoAction`, a closure cria o evento, chama `record()` e devolve o evento; assinatura, `MAX_LENGTH`, mensagens e `ensureActorMayObserve` intactos; docblock "Observação / ocorrência" listando `suprimentos`, `gestao` e `obra` com `view`.
      Cobre: RF-01, RF-02, RF-04b, RF-09, RF-10, RF-11, RF-12, CT-04
      Acceptance criteria: cada um dos 5 arquivos chama `notificationRecorder->record(` dentro do `DB::transaction`, depois do evento; em `UpdatePedidoResponsavelAction` a chamada vem depois do `update`; `AddPedidoObservacaoAction::execute(User, Pedido, string): PedidoEvent` mantém a assinatura e as duas mensagens; os testes existentes de `tests/Feature/Actions/` passam sem alteração de asserção.
      Testes: suíte existente de `tests/Feature/Actions/` (regressão); a cobertura nova está em T10
- [ ] T12 — Controle "Observação / ocorrência" nos detalhes (UI-09)
      Arquivos: `resources/views/components/pedido-observacao-form.blade.php`, `app/Livewire/Suprimentos/PedidoDetalhe.php` (só docblock), `tests/Feature/Livewire/PedidoObservacaoControlTest.php`, `tests/Feature/Security/Adversarial/GestaoOperacaoPedidosTest.php`, `tests/Browser/SolicitacaoFinalizacaoFlowTest.php`, `tests/Browser/ResponsiveIdentityTest.php`
      Mudança: `aria-label` da seção e `<label>` "Observação / ocorrência"; dica citando falta de produto, troca, atraso, problema de entrega ou qualquer informação relevante e o limite de 2000; botão "Registrar observação / ocorrência"; campo único obrigatório, sem select de categoria, `maxlength` de `MAX_LENGTH`, erro inline. `adicionarObservacao` mantém a assinatura. Allow-list: `PedidoObservacaoControlTest.php:51,57,66`, `GestaoOperacaoPedidosTest.php:89`, `SolicitacaoFinalizacaoFlowTest.php:144`, `ResponsiveIdentityTest.php:434,456` trocam "Adicionar observação" por "Observação / ocorrência".
      Cobre: UI-09, RF-10, CT-04, CT-05
      Acceptance criteria: os detalhes de Obra, Suprimentos e Gestão (`gestao.pedidos.show`) mostram "Observação / ocorrência" sem nenhum `<select>` na seção; enviar vazio mostra "Escreva a observação." no campo; a entrada registrada aparece no histórico com autor e data/hora local; só as asserções da allow-list mudaram.
      Testes: `tests/Feature/Livewire/PedidoObservacaoControlTest.php` — rótulo, ausência de categoria, erro inline, histórico

## Phase 4: Testes integrados de geração e orçamento de consultas

Antes de implementar, leia:
1. `.spec/features/notificacoes-internas/SPEC.md` — requisitos RIGID que esta fase cobre (RF-01..RF-05, RF-09..RF-14, RF-17, RNF-01, RNF-02, RNF-09)
2. `.spec/features/notificacoes-internas/PLAN.md` — decomposição completa, dependências e riscos

Os testes de e-mail sem pipeline HTTP chamam `app(DeferredCallbackCollection::class)->invoke()` (ou `withoutDefer()`).

- [ ] T10 — Testes integrados de geração, rollback, envio diferido e conformidade do ponto único
      Arquivos: `tests/Feature/Notifications/NotificationGenerationTest.php`, `tests/Feature/Notifications/ObservacaoOcorrenciaNotificationTest.php`, `tests/Feature/Compliance/PedidoNotificationSinglePointTest.php` (novos)
      Mudança: por tipo (10), Action real com destinatário elegível → ≥ 1 notificação, `count = destinatários resolvidos`, campos corretos; 2 `gestao` recebem; autor não recebe; Kanban `moveViaControl` notifica; rollback forçado depois do INSERT do evento → 0 notificações e 0 e-mails; RNF-01 com 5 destinatários (0 antes de `invoke()`, 5 depois); observação: "   " e 2001 caracteres → 422 com as mensagens existentes e 0 eventos/notificações; `obra` sem `view` → 403; 3 papéis permitidos; 1 teste por status terminal; `pedidos.updated_at`/`status_id` inalterados. Compliance: todo arquivo de `app/Actions/Pedidos/` com `events()->create(` ou `PedidoEvent::query()->create(` chama `notificationRecorder->record(`; só `PedidoNotificationRecorder` insere em `internal_notifications`.
      Cobre: RF-01, RF-02, RF-03, RF-05, RF-09, RF-10, RF-11, RF-12, RF-13, RF-14, RF-17, RNF-01
      Acceptance criteria: os 3 arquivos existem e passam; cada um dos 10 `EventTypeSlug` tem um caso com ≥ 1 notificação; o caso de rollback mostra 0 notificações e 0 e-mails; o caso RNF-01 mostra 0 → 5 mensagens; os 3 status terminais têm caso próprio; a varredura de compliance falha se o `record(` for removido de qualquer Action.
      Testes: os 3 arquivos acima
- [ ] T11 — Orçamento de consultas da geração (RNF-02, RNF-09)
      Arquivos: `tests/Feature/Performance/QueryCountTest.php` (só acréscimo)
      Mudança: medir só as consultas de `PedidoNotificationRecorder::record()` durante `UpdatePedidoStatusAction` com 1 e 20 destinatários; segundo teste com `suprimentos` associado e responsável vs. só associado. Nenhum teste existente alterado.
      Cobre: RNF-02, RNF-09, RF-04a
      Acceptance criteria: o delta com 1 e com 20 destinatários é igual e ≤ 4; o delta com associado+responsável é igual ao sem responsável e gera 1 linha para esse usuário; os testes pré-existentes de `QueryCountTest.php` continuam verdes e inalterados.
      Testes: `tests/Feature/Performance/QueryCountTest.php` — 2 testes novos

## Phase 5: Leitura, página Notificações Internas e sidebar

Antes de implementar, leia:
1. `.spec/features/notificacoes-internas/SPEC.md` — requisitos RIGID que esta fase cobre (RF-18..RF-21, UI-01..UI-04, UI-08, CT-01, CT-05, RNF-06, RNF-07)
2. `.spec/features/notificacoes-internas/PLAN.md` — decomposição completa, dependências e riscos

Ordem obrigatória: T13 → T14 → T15. `InternalNotification::forRecipient` é a única definição de visibilidade de notificação e abre toda consulta.

- [ ] T13 — Habilidade `view-notifications`, escopo do destinatário, Policy e Actions de leitura
      Arquivos: `app/Providers/AppServiceProvider.php`, `app/Models/InternalNotification.php` (alterados); `app/Policies/InternalNotificationPolicy.php`, `app/Actions/Notificacoes/MarkInternalNotificationReadAction.php`, `app/Actions/Notificacoes/MarkAllInternalNotificationsReadAction.php` (novos); `tests/Feature/Authorization/RoleGatesTest.php` (acréscimo); `tests/Feature/Actions/Notificacoes/MarkInternalNotificationReadActionTest.php`, `tests/Feature/Actions/Notificacoes/MarkAllInternalNotificationsReadActionTest.php` (novos)
      Mudança: gate `view-notifications` = `obra|suprimentos|gestao`. `scopeForRecipient(Builder, User)` = `recipient_id = user` + `whereIn('pedido_id', Pedido::query()->visibleTo($user)->select('pedidos.id'))`. Policy `update` = dono, `delete` = false. `MarkInternalNotificationReadAction::execute(User, int)`: `forRecipient()->findOrFail()`, `authorize('update')`, UPDATE `whereNull('read_at')`. `MarkAllInternalNotificationsReadAction::execute(User): int`: 1 UPDATE `recipient_id = actor and read_at is null`. Só tocam `read_at`. `MarkInternalNotificationReadAction` devolve a notificação (com `pedido`) para `abrir()` de T14/T16.
      Cobre: CT-01, CT-05, RF-18, RF-19, RF-20, RF-21, RNF-07
      Acceptance criteria: o gate permite os 3 papéis e nega papel desconhecido; marcar lida preenche `read_at` e uma segunda marcação mantém o primeiro valor; id de outro usuário → 404 e `read_at` intacto; A com 5 e B com 3 não lidas → depois da ação de A, A tem 0 e B tem 3; `obra` desassociado da obra X não vê nem conta as notificações de X por `forRecipient`.
      Testes: `tests/Feature/Authorization/RoleGatesTest.php` — gate novo; os 2 testes das Actions — idempotência, isolamento, `visibleTo`
- [ ] T14 — Página "Notificações Internas" (`Notificacoes\Index`) e rota `notificacoes.index`
      Arquivos: `app/Livewire/Notificacoes/Index.php`, `resources/views/livewire/notificacoes/index.blade.php` (novos), `routes/web.php` (alterado), `tests/Feature/Livewire/NotificacoesIndexTest.php` (novo)
      Mudança: `GET /notificacoes` → `Notificacoes\Index`, nome `notificacoes.index`, no grupo `['auth','active']` com `can:view-notifications`, sem parâmetro. `mount()` re-checa `authorize('view-notifications')`; 20 por página, `created_at`/`id` DESC; consulta aberta por `InternalNotification::query()->forRecipient(Auth::user())` com eager load; conteúdo por `describeEach`. Filtros `#[Url]` com `except:`: `lidas` (`''|nao|sim`), `tipo` (slugs de `NotifiableEventTypes`), `codigo`; "Limpar filtros". `markAsRead(int)` e `markAllAsRead()` → T13 + `dispatch('notificacoes-atualizadas')`. `abrir(int $id)`: `MarkInternalNotificationReadAction` (reautoriza a posse; id forjado → 403/404 sem navegar; já lida mantém o `read_at`) e depois `redirectRoute(PedidoDetailRoute::nameFor($user), $notification->pedido)`; cada item é um botão `wire:click="abrir({id})"` com o código, sem `<a href>` direto ao detalhe. Cada item: `obraLabel()`, tipo, conteúdo, ator, data/hora local, "Não lida" textual + visual, botão "Marcar como lida". `x-filter-panel`, classes literais, PT-BR.
      Cobre: CT-01, CT-05, UI-01, UI-02, UI-03, UI-04, RF-18, RF-19, RF-20, RF-21, RNF-06
      Acceptance criteria: com 25 notificações a página 1 mostra 20, a mais recente primeiro, com os 7 campos, e as não lidas exibem "Não lida"; `abrir(id)` de um `suprimentos` numa não lida preenche `read_at` e redireciona a `/suprimentos/pedidos/{id}`; numa já lida mantém o `read_at` original e redireciona; com id de outro usuário responde 403/404, sem redirect e com `read_at` intacto; `?lidas=nao` mostra só não lidas; os filtros por tipo e código funcionam; "Limpar filtros" deixa a URL sem parâmetros; "Marcar todas como lidas" remove todo "Não lida" e dispara `notificacoes-atualizadas`; papel desconhecido recebe 403; contagem de consultas igual para 1 e 20 itens.
      Testes: `tests/Feature/Livewire/NotificacoesIndexTest.php` — listagem, paginação, filtros, ações, `abrir` (marca e navega, idempotente, id forjado), 403, consultas
- [ ] T15 — Item "Notificações Internas" na sidebar e baseline de middleware
      Arquivos: `app/Support/SidebarNavigation.php` (alterado), `tests/Feature/Authorization/SidebarNavigationCatalogueTest.php`, `tests/Feature/Livewire/SidebarNavigationTest.php`, `tests/Feature/Compliance/RouteMiddlewareBaselineTest.php` (acréscimo)
      Mudança: `self::item('Notificações Internas', 'notificacoes.index', 'notificacoes.*', ['view-notifications'], 'Notificações')` nos 3 papéis, depois dos itens de operação (Obra: depois de Acompanhamento; Suprimentos/Gestão: depois de Kanban, antes de Cadastros/Administração). Baseline: `notificacoes.index` → `['web','auth','active','can:view-notifications']`. As listas esperadas por papel nos testes de sidebar ganham o item.
      Cobre: UI-08, CT-01
      Acceptance criteria: `SidebarNavigationCatalogueTest` passa com o item nos 3 papéis e com habilidades iguais às `can:` da rota; em `/notificacoes` só esse item tem `aria-current="page"`; `RouteMiddlewareBaselineTest` inclui a rota nova e passa.
      Testes: os 3 arquivos acima

## Phase 6: Sino global e testes de navegador

Antes de implementar, leia:
1. `.spec/features/notificacoes-internas/SPEC.md` — requisitos RIGID que esta fase cobre (UI-02, UI-03, UI-05..UI-07, UI-10, RF-21, RNF-04, RNF-07)
2. `.spec/features/notificacoes-internas/PLAN.md` — decomposição completa, dependências e riscos

O layout continua sem `wire:click` e sem saída crua (`NavigationListingComplianceTest`). Haverá uma única instância do sino. Antes de T17, rode `npm run build`.

- [ ] T16 — Sino global com contador (`Notificacoes\Bell`) no layout
      Arquivos: `app/Livewire/Notificacoes/Bell.php`, `resources/views/livewire/notificacoes/bell.blade.php` (novos), `resources/views/layouts/app.blade.php` (alterado), `tests/Feature/Livewire/NotificacoesBellTest.php` (novo), `tests/Feature/Performance/QueryCountTest.php` (acréscimo)
      Mudança: uma única `<livewire:notificacoes.bell />` em `@auth`, num controle fixo visível no desktop e na barra superior do celular. Render = 1 consulta `forRecipient($user)->whereNull('read_at')->count()`; badge com N, `9+` acima de 9, oculto em 0; `aria-label="Notificações, N não lidas"`. Painel com `data-open="false"` + `x-bind:data-open` e variantes literais (sem `x-show`/`<details>`), Escape fecha e devolve o foco ao sino; `loadPanel()` carrega as 10 não lidas mais recentes (código, tipo, data/hora local), cada uma com `wire:click="abrir({id})"` de mesma semântica de T14 (marca lida → redirect ao detalhe do papel; id forjado → 403/404 sem navegar), "Marcar todas como lidas", "Ver todas" → `notificacoes.index`, vazio "Nenhuma notificação nova.". `#[On('notificacoes-atualizadas')]`; timer Alpine de 60000 ms em `refreshIfVisible()` que só chama `$wire.$refresh()` com `document.visibilityState === 'visible'`.
      Cobre: UI-02, UI-05, UI-06, UI-07, UI-10, RF-18, RF-20, RF-21, RNF-04, RNF-07
      Acceptance criteria: 3 não lidas → badge "3" e `aria-label="Notificações, 3 não lidas"`; 12 → "9+"; 0 → sem badge; `loadPanel` lista 10 de 12, a mais recente primeiro; `abrir(id)` no painel marca lida, redireciona ao detalhe do papel e o contador cai 1; `abrir` com id alheio → 403/404 sem redirect; estado vazio exibido; "Ver todas" aponta para `/notificacoes`; marcar todas zera o contador; notificações de obra desassociada não contam; a view do sino contém `data-open="false"`, `x-bind:data-open`, `60000` e `visibilityState`, e não contém `x-show` nem `<details`; renderizar o layout com 500 notificações acrescenta exatamente 1 consulta em relação a 0; o layout tem uma única tag do sino e nenhum `wire:click`.
      Testes: `tests/Feature/Livewire/NotificacoesBellTest.php` — badge, painel, `abrir`, eventos, markup; `tests/Feature/Performance/QueryCountTest.php` — 1 consulta do contador
- [ ] T17 — Testes de navegador: sino, página e fluxo ponta a ponta
      Arquivos: `tests/Browser/NotificacoesInternasFlowTest.php` (novo)
      Mudança: Pest Browser (URL absoluta no `goto`, esperar `wire:model`, saída redirecionada para arquivo): Suprimentos muda status → Gestão vê o badge e o item na página, clica no item e chega a `/gestao/pedidos/{id}` com a notificação já lida; clicar num item do painel do sino também marca lida e navega; "Marcar todas como lidas" some com o badge; painel abre, Escape fecha e o foco volta ao sino; viewport de celular mostra o badge na barra superior; `refreshIfVisible()` com `visibilityState` simulado `hidden` não emite `/livewire/update` e com `visible` emite 1; notificação criada no servidor aparece no badge depois de `refreshIfVisible()`.
      Cobre: UI-02, UI-03, UI-05, UI-06, UI-07, UI-10
      Acceptance criteria: `vendor/bin/pest tests/Browser/NotificacoesInternasFlowTest.php` passa com todos os cenários listados, incluindo o clique (página e sino) que chega ao detalhe com o badge 1 a menos ao voltar, incluindo o retorno de foco ao sino depois do Escape e 0 requisições com a aba simulada como oculta.
      Testes: `tests/Browser/NotificacoesInternasFlowTest.php` — fluxo, foco, celular, visibilidade

## Phase 7: demo:reset, testes adversariais e conformidade

Antes de implementar, leia:
1. `.spec/features/notificacoes-internas/SPEC.md` — requisitos RIGID que esta fase cobre (RF-04, RF-16, RF-20..RF-23, RNF-05..RNF-08, UI-04, UI-10)
2. `.spec/features/notificacoes-internas/PLAN.md` — decomposição completa, dependências e riscos

`ResetDemoData` toca `internal_notifications` só por `DB::table`, nunca pelo modelo.

- [ ] T18 — `demo:reset` limpa as notificações demo (RF-23)
      Arquivos: `app/Console/Commands/ResetDemoData.php` (alterado), `tests/Feature/Console/ResetDemoDataTest.php`, `tests/Feature/Compliance/AuditTrailsAppendOnlyTest.php` (acréscimo)
      Mudança: dentro da transação existente, antes de excluir os pedidos demo, `DB::table('internal_notifications')->where(...)->delete()` para `pedido_id` de pedido demo **ou** `recipient_id`/`actor_id` de usuário demo (inclui pedido real com destinatário demo). Docblock atualizado.
      Cobre: RF-23, RF-22
      Acceptance criteria: `demo:reset --force` com notificações demo (pedido demo e pedido real com destinatário demo) termina com código 0 e 0 notificações ligadas a pedidos ou usuários demo; notificações reais permanecem; `AuditTrailsAppendOnlyTest` confirma que `ResetDemoData` usa só `DB::table('internal_notifications')`.
      Testes: `tests/Feature/Console/ResetDemoDataTest.php` — limpeza e preservação; `tests/Feature/Compliance/AuditTrailsAppendOnlyTest.php` — acesso só por `DB::table`
- [ ] T19 — Testes adversariais: isolamento, ids forjados e falha de e-mail
      Arquivos: `tests/Feature/Security/Adversarial/InternalNotificationsIsolationTest.php`, `tests/Feature/Security/Adversarial/InternalNotificationMailFailureTest.php` (novos)
      Mudança: 2 usuários: página, contador, painel, `markAsRead` e `abrir` (página e sino) com id do outro (403/404, sem redirect, `read_at` intacto) e `markAllAsRead` (o outro intacto); `obra` desassociado deixa de ver/contar. Mutação pelo `/livewire/update` real com transporte que lança para A: mutação gravada, A `falhou` com `email_status_at`, B `enviado`; log sem e-mail, texto ou token. `suprimentos` S2 não associado e não responsável abre o pedido e não é notificado.
      Cobre: RNF-07, RNF-08, RF-16, RF-20, RF-21, RF-04, UI-02
      Acceptance criteria: os 2 arquivos passam; nenhuma das superfícies (página, contador, painel, marcações, `abrir`) retorna ou altera linha de outro destinatário; o id forjado em `markAsRead` e em `abrir` responde 403 ou 404 sem redirect e sem mudar `read_at`; no caso de falha de transporte a mutação está gravada, A = `falhou`, B = `enviado` e o log não contém os 3 valores sensíveis; S2 recebe 200 no detalhe e 0 notificações.
      Testes: os 2 arquivos acima
- [ ] T20 — Varreduras de conformidade da feature
      Arquivos: `tests/Feature/Compliance/LocalTimeDisplayComplianceTest.php`, `tests/Feature/Compliance/FilterUrlStateComplianceTest.php` (acréscimo), `tests/Feature/Compliance/InternalNotificationsComplianceTest.php` (novo)
      Mudança: varredura de `LocalTime` sobre `livewire/notificacoes/index.blade.php`, `livewire/notificacoes/bell.blade.php` e `mail/pedidos/notificacao.blade.php`; `FilterUrlState` cobre `Notificacoes\Index` (`lidas`, `tipo`, `codigo`). Compliance novo: nenhum `ShouldQueue` em `app/Notifications/` e nenhum schedule em `routes/console.php`; `composer.json`/`package.json` sem dependência nova (listas fixadas); toda consulta estática de `InternalNotification::query()` em `app/Livewire/Notificacoes/` e `app/Actions/Notificacoes/` abre com `->forRecipient(` (exceto `MarkAll`, por `recipient_id`); atualizações de `internal_notifications` em `app/` só tocam `read_at`/`email_status`/`email_status_at`; nenhuma classe interpolada, `x-show` ou `<details` nas views novas.
      Cobre: RNF-05, RNF-06, RF-21, RF-22, UI-04, UI-10
      Acceptance criteria: os 3 arquivos passam e cada varredura falha no próprio self-check quando alimentada com um exemplo violador (`ShouldQueue`, consulta sem `forRecipient`, update de coluna proibida, classe interpolada).
      Testes: os 3 arquivos acima

## Phase 8: Gates finais

Antes de implementar, leia:
1. `.spec/features/notificacoes-internas/SPEC.md` — requisitos RIGID que esta fase cobre (todos, por regressão; RNF-05)
2. `.spec/features/notificacoes-internas/PLAN.md` — decomposição completa, dependências e riscos

- [ ] T21 — Gates finais: Pint, build, suítes e dependências
      Arquivos: nenhum de produção; correções pontuais nos arquivos da feature, se algum gate falhar
      Mudança: `vendor/bin/pint --dirty --format agent`; `npm run build`; `php artisan test --compact --testsuite=Unit`; `--testsuite=Feature`; `vendor/bin/pest tests/Browser` com saída em arquivo; `git diff --stat build/v0-demo-laravel -- composer.json composer.lock package.json package-lock.json` vazio; `php artisan route:list --path=notificacoes` com `can:view-notifications`.
      Cobre: RNF-05, todos os ACs (regressão)
      Acceptance criteria: Pint não deixa diff; o build termina sem erro; as suítes Unit, Feature e Browser passam sem falhas; o diff de `composer.json`, `composer.lock`, `package.json` e `package-lock.json` está vazio; a rota `notificacoes.index` aparece com `auth`, `active` e `can:view-notifications`.
      Testes: suítes completas Unit, Feature e Browser

## Phase 9: Documentação e roteiro de medição RNF-03

Antes de implementar, leia:
1. `.spec/features/notificacoes-internas/SPEC.md` — requisitos RIGID que esta fase cobre (RNF-03 roteiro, RF-09 documentação)
2. `.spec/features/notificacoes-internas/PLAN.md` — decomposição completa, dependências e riscos

Fase só de documentação: `docs/agents/*.md` são regenerados por `/ai-context`, nunca editados à mão; `CLAUDE.md` é manual.

- [ ] T22 — Documentação e roteiro de medição RNF-03
      Arquivos: `CLAUDE.md` (manual), `docs/agents/*.md` (via `/ai-context`), `.spec/features/notificacoes-internas/defer-verificacao.md`
      Mudança: `CLAUDE.md` §2 (e-mail via `defer()`, sem fila/worker; opção C só com aprovação), §3 (10 tipos notificáveis, regra de destinatários, autor/inativos fora, "Outra"), §4 (`/notificacoes`, item da sidebar, sino, "Observação / ocorrência"), §5 (`view-notifications`, `InternalNotification::forRecipient` com `visibleTo` dentro), §6 (`internal_notifications` append-only exceto `read_at`/`email_status*`, `demo:reset`), §7 (logs sem PII). Rodar `/ai-context`. Em `defer-verificacao.md`: roteiro de G-3 (5 vs. 0 destinatários, 20 execuções cada, p95, critério ≤ +150 ms) e campo para o resultado passa/falha.
      Cobre: RNF-03, RF-09
      Acceptance criteria: `CLAUDE.md` cita `internal_notifications`, `view-notifications`, `forRecipient`, `defer()` e "Observação / ocorrência"; `docs/agents/*.md` mantêm o banner de geração; `defer-verificacao.md` contém o roteiro de medição com critério ≤ +150 ms p95 e um campo de resultado; `tests/Feature/Compliance/DocumentationParityTest.php` passa.
      Testes: `tests/Feature/Compliance/DocumentationParityTest.php` — paridade de documentação
