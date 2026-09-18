# Phases: reimplementacao-v0-laravel-livewire

Gerado por /plan a partir de PLAN.md — view executável para `./ralph.sh .spec/features/reimplementacao-v0-laravel-livewire/PHASES.md`.

## Phase 1: Bootstrap do Laravel, Livewire, Boost e Tooling Frontend

Antes de implementar, leia:
1. `.spec/features/reimplementacao-v0-laravel-livewire/SPEC.md` — requisitos RIGID que esta fase cobre
2. `.spec/features/reimplementacao-v0-laravel-livewire/PLAN.md` — decomposição completa, dependências e riscos

- [ ] T01 — Scaffold Laravel application skeleton
      Arquivos: `laravel/composer.json`, `laravel/artisan`, `laravel/.env.example`, `laravel/config/app.php`
      Mudança: `composer create-project laravel/laravel laravel` em subdiretório dedicado (evita colisão com `app/`, `lib/`, `components/`, `package.json`, `tsconfig.json` do Next.js na raiz); configurar `APP_NAME`/config base.
      Cobre: RF-01, RNF-02, RNF-06
      Acceptance criteria: rota raiz responde 200 após instalação limpa; `php artisan serve` inicia sem erro fatal.
      Testes: `laravel/tests/Feature/BootstrapTest.php` — rota raiz responde 200.

- [ ] T02 — Install and configure Livewire
      Arquivos: `laravel/composer.json`, `laravel/resources/views/layouts/app.blade.php`, `laravel/app/Providers/AppServiceProvider.php`
      Mudança: `composer require livewire/livewire`; layout base inclui `@livewireStyles`/`@livewireScripts`.
      Cobre: RF-03
      Acceptance criteria: um componente Livewire trivial monta e renderiza via `Livewire::test`.
      Testes: `laravel/tests/Feature/LivewireSmokeTest.php` — componente monta/renderiza.

- [ ] T03 — Install and configure Laravel Boost + Claude Code integration
      Arquivos: `laravel/composer.json` (`require-dev laravel/boost`), arquivos de config/guideline gerados pelo Boost
      Mudança: `composer require laravel/boost --dev`; executar instalação/configuração recomendada; confirmar integração MCP com Claude Code.
      Cobre: RF-02
      Acceptance criteria: `composer.json` lista `laravel/boost` em `require-dev` e a configuração recomendada pelo pacote está aplicada.
      Testes: verificação manual/documentação — sem teste automatizado dedicado nesta fase (auditado em T56).

- [ ] T04 — Configure Vite + Tailwind CSS pipeline
      Arquivos: `laravel/vite.config.js`, `laravel/package.json`, `laravel/resources/css/app.css`, `laravel/tailwind.config.js`
      Mudança: instalar/configurar Tailwind para os caminhos de conteúdo Blade; script de build Vite para assets de produção.
      Cobre: RNF-01
      Acceptance criteria: `npm run build` (em `laravel/`) termina com exit code 0.
      Testes: gate de build verificado em T51.

- [ ] T05 — Configure Pest/PHPUnit test runner
      Arquivos: `laravel/tests/Pest.php`, `laravel/phpunit.xml`, `laravel/tests/TestCase.php`
      Mudança: configurar conexão de teste `pgsql` (banco de teste dedicado, `RefreshDatabase`/transações).
      Cobre: RF-23
      Acceptance criteria: teste de exemplo passa via `php artisan test`/`vendor/bin/pest`.
      Testes: `laravel/tests/Feature/ExampleTest.php` — passa.

## Phase 2: Fundação PostgreSQL — Conexão e Migrations

Antes de implementar, leia:
1. `.spec/features/reimplementacao-v0-laravel-livewire/SPEC.md` — requisitos RIGID que esta fase cobre
2. `.spec/features/reimplementacao-v0-laravel-livewire/PLAN.md` — decomposição completa, dependências e riscos

- [ ] T06 — Configure PostgreSQL connection via environment variables
      Arquivos: `laravel/config/database.php`, `laravel/.env.example`
      Mudança: conexão padrão `pgsql`; todas as credenciais (`DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`) vêm de env vars, sem SDK Supabase.
      Cobre: RF-04
      Acceptance criteria: aplicação conecta ao Postgres e executa uma query trivial usando apenas env vars.
      Testes: `laravel/tests/Feature/DatabaseConnectionTest.php`.

- [ ] T07 — Migration: lookup tables (roles, statuses, priorities, event_types)
      Arquivos: `laravel/database/migrations/*_create_roles_table.php` (+ statuses, priorities, event_types)
      Mudança: 4 tabelas de lookup, `slug` único, `sort_order` em statuses/priorities, `is_active`, timestamps.
      Cobre: RF-05, RF-26
      Acceptance criteria: `migrate:fresh` cria as 4 tabelas com colunas/constraints esperadas.
      Testes: `laravel/tests/Feature/MigrationSchemaTest.php`.

- [ ] T08 — Migration: users identity com role_id
      Arquivos: `laravel/database/migrations/*_add_role_and_profile_fields_to_users_table.php`
      Mudança: adaptar migration padrão de `users` adicionando `role_id` FK, `is_active`, `is_demo`.
      Cobre: RF-05, RF-26, RF-07
      Acceptance criteria: `users.role_id` FK e `is_demo` presentes no schema migrado.
      Testes: `laravel/tests/Feature/MigrationSchemaTest.php`.

- [ ] T09 — Migration: obras, obra_profile pivot
      Arquivos: `laravel/database/migrations/*_create_obras_table.php`, `*_create_obra_profile_table.php`
      Mudança: `obras` (name, is_active, is_demo, timestamps); `obra_profile` com PK composta `(obra_id, user_id)`.
      Cobre: RF-05, RF-10, RF-26
      Acceptance criteria: PK composta e FKs de `obra_profile` confirmadas no schema migrado.
      Testes: `laravel/tests/Feature/MigrationSchemaTest.php`.

- [ ] T10 — Migration: pedidos, pedido_events, índices
      Arquivos: `laravel/database/migrations/*_create_pedidos_table.php`, `*_create_pedido_events_table.php`, `*_add_pedidos_query_indexes.php`
      Mudança: colunas completas de `pedidos` conforme `docs/agents/data_model.md`; `pedido_events` somente-inserção (sem `updated_at`); índices `(obra_id,status_id)`, `(needed_at)`, `(pedido_id,created_at)`.
      Cobre: RF-05, RF-26, RNF-07
      Acceptance criteria: colunas/FKs/índices presentes; `pedido_events` sem coluna que habilite update.
      Testes: `laravel/tests/Feature/MigrationSchemaTest.php`.

- [ ] T11 — Migrate-from-zero verification
      Arquivos: `laravel/tests/Feature/FreshMigrationTest.php`
      Mudança: nenhuma (apenas teste) — roda `migrate:fresh` contra banco de teste vazio.
      Cobre: RF-05
      Acceptance criteria: `migrate:fresh` roda sem erro e todas as tabelas esperadas existem.
      Testes: `laravel/tests/Feature/FreshMigrationTest.php`.

## Phase 3: Camada de Domínio — Models, Enums, Classifiers e Actions

Antes de implementar, leia:
1. `.spec/features/reimplementacao-v0-laravel-livewire/SPEC.md` — requisitos RIGID que esta fase cobre
2. `.spec/features/reimplementacao-v0-laravel-livewire/PLAN.md` — decomposição completa, dependências e riscos

- [ ] T12 — Eloquent Models para tabelas de lookup
      Arquivos: `laravel/app/Models/Role.php`, `Status.php`, `Priority.php`, `EventType.php`
      Mudança: `$fillable`, relações, scopes por `sort_order`.
      Cobre: RF-26
      Acceptance criteria: cada Model expõe as relações e colunas esperadas verificadas por teste unitário.
      Testes: `laravel/tests/Unit/Models/LookupModelsTest.php`.

- [ ] T13 — Eloquent Models: User, Obra, Pedido, PedidoEvent
      Arquivos: `laravel/app/Models/User.php`, `Obra.php`, `Pedido.php`, `PedidoEvent.php`
      Mudança: relações completas (User belongsTo Role, belongsToMany Obra via obra_profile; Pedido belongsTo Obra/Status/Priority/requester/responsible; PedidoEvent belongsTo Pedido/EventType/actor); `$fillable`/`$guarded` explícitos.
      Cobre: RF-26, RNF-08
      Acceptance criteria: cardinalidade many-to-many obra↔usuário confirmada por teste de factory; `$fillable` declarado em todos os Models de escrita.
      Testes: `laravel/tests/Unit/Models/PedidoModelTest.php`, `laravel/tests/Feature/ObraProfileCardinalityTest.php`.

- [ ] T14 — PHP Enums para slugs congelados
      Arquivos: `laravel/app/Enums/RoleSlug.php`, `StatusSlug.php`, `PrioritySlug.php`, `EventTypeSlug.php`
      Mudança: enums backed com os valores RIGID (3 roles, 6 statuses, 4 priorities, 7 event_types).
      Cobre: RF-26
      Acceptance criteria: valores dos enums batem exatamente com os literais RIGID de SPEC.md.
      Testes: `laravel/tests/Unit/Enums/SlugEnumsTest.php`.

- [ ] T15 — Gerador de código do pedido (PED-######)
      Arquivos: `laravel/app/Services/PedidoCodeGenerator.php`, `laravel/database/migrations/*_create_pedido_code_sequence.php`
      Mudança: gerador baseado em sequência de banco, formato `PED-000001`, seguro sob criação concorrente.
      Cobre: RF-26
      Acceptance criteria: chamadas concorrentes nunca colidem; formato sempre casa com `^PED-\d{6}$`.
      Testes: `laravel/tests/Unit/Services/PedidoCodeGeneratorTest.php`.

- [ ] T16 — Classifiers de Atraso/Pendente/Prazo (fonte única de verdade)
      Arquivos: `laravel/app/Domain/Pedidos/AtrasoClassifier.php`, `PendenteClassifier.php`, `PrazoClassifier.php`
      Mudança: funções puras e reutilizadas; `PrazoClassifier` usa `VENCENDO_EM_BREVE_DIAS = 3` como constante nomeada, nunca configurável em runtime.
      Cobre: RF-19, RF-19b, RF-19c
      Acceptance criteria: matriz de 4 combinações (atraso), 7 status (pendente) e janela de 3 dias (prazo) cobertas por teste parametrizado; nenhum outro consumidor reimplementa a lógica.
      Testes: `laravel/tests/Unit/Domain/AtrasoClassifierTest.php`, `PendenteClassifierTest.php`, `PrazoClassifierTest.php`.

- [ ] T17 — CreatePedidoAction (RF-11/RF-11b/RF-11c)
      Arquivos: `laravel/app/Actions/Pedidos/CreatePedidoAction.php`
      Mudança: valida `obra_id`/`needed_at`/`items_description` obrigatórios; valida `obra_id` ∈ `obra_profile` do solicitante no backend independentemente do payload da UI; atribui status de menor `sort_order`; gera código (T15); insere pedido + evento `criacao_pedido` em uma transação (rollback se o evento falhar).
      Cobre: RF-11, RF-11b, RF-11c, RF-18, CT-01
      Acceptance criteria: criação válida persiste com exatamente 1 evento `criacao_pedido`; qualquer campo obrigatório ausente rejeita sem criar registro; `obra_id` fora da associação rejeita mesmo com payload manipulado diretamente; falha no insert do evento reverte o pedido.
      Testes: `laravel/tests/Feature/Actions/CreatePedidoActionTest.php`.

- [ ] T18 — UpdatePedidoResponsavelAction (RF-14/RF-14b)
      Arquivos: `laravel/app/Actions/Pedidos/UpdatePedidoResponsavelAction.php`, `laravel/app/Rules/ResponsibleMustBeSuprimentos.php`
      Mudança: apenas `suprimentos`; `responsible_id` deve pertencer a usuário com papel `suprimentos` mesmo via payload direto; no-op quando inalterado (sem evento); rejeita pedido terminal; evento `alteracao_responsavel`.
      Cobre: RF-14, RF-14b, RF-13b, RF-18
      Acceptance criteria: alteração válida gera exatamente 1 evento; reatribuição do mesmo valor não gera evento; ator não-suprimentos rejeitado; `responsible_id` sem papel suprimentos rejeitado; pedido terminal rejeitado.
      Testes: `laravel/tests/Feature/Actions/UpdatePedidoResponsavelActionTest.php`.

- [ ] T19 — UpdatePedidoPrioridadeAction (RF-15)
      Arquivos: `laravel/app/Actions/Pedidos/UpdatePedidoPrioridadeAction.php`
      Mudança: apenas `suprimentos`; `priority_id` restrito aos 4 valores seedados; no-op quando inalterado; rejeita pedido terminal; evento `alteracao_prioridade`.
      Cobre: RF-15, RF-13b, RF-18
      Acceptance criteria: valor fora do conjunto {baixa,normal,alta,urgente} rejeitado; alteração válida gera 1 evento; mesmo valor não gera evento.
      Testes: `laravel/tests/Feature/Actions/UpdatePedidoPrioridadeActionTest.php`.

- [ ] T20 — UpdatePedidoPrevisaoAction (RF-16)
      Arquivos: `laravel/app/Actions/Pedidos/UpdatePedidoPrevisaoAction.php`
      Mudança: apenas `suprimentos`; no-op quando data inalterada; rejeita pedido terminal; evento `alteracao_previsao`.
      Cobre: RF-16, RF-13b, RF-18
      Acceptance criteria: alteração válida gera evento com valor anterior/novo; nova data visível para os 3 perfis autorizados.
      Testes: `laravel/tests/Feature/Actions/UpdatePedidoPrevisaoActionTest.php`.

- [ ] T21 — UpdatePedidoStatusAction (matriz de transição, RF-13/RF-13b)
      Arquivos: `laravel/app/Actions/Pedidos/UpdatePedidoStatusAction.php`
      Mudança: apenas `suprimentos`; alvo ∈ `ACTIVE_NON_FINAL_STATUSES` ∪ {entregue}; rejeita `cancelado` como alvo desta action; rejeita qualquer transição quando o status atual é terminal; evento `mudanca_status` (ou `entrega` quando o alvo é `entregue`).
      Cobre: RF-13, RF-13b, RF-18, UI-07
      Acceptance criteria: matriz parametrizada de 4 transições × 4 origens não-terminais permitida; alvo `cancelado` rejeitado; origem terminal rejeitada; payload forjado de drag-and-drop por ator não-suprimentos rejeitado.
      Testes: `laravel/tests/Feature/Actions/UpdatePedidoStatusActionTest.php`.

- [ ] T22 — CancelPedidoAction (RF-17/RF-17b)
      Arquivos: `laravel/app/Actions/Pedidos/CancelPedidoAction.php`
      Mudança: apenas `suprimentos`; apenas a partir de status não-terminal; define `cancelado` (irreversível); evento `cancelamento`.
      Cobre: RF-17, RF-17b, RF-13b, RF-18
      Acceptance criteria: cancelamento de pedido ativo gera evento `cancelamento`; cancelar `entregue`/`cancelado` rejeitado; atores `obra`/`gestao` rejeitados.
      Testes: `laravel/tests/Feature/Actions/CancelPedidoActionTest.php`.

- [ ] T23 — Guarda de imutabilidade do PedidoEvent
      Arquivos: `laravel/app/Models/PedidoEvent.php`, `laravel/app/Policies/PedidoEventPolicy.php`
      Mudança: guarda em nível de Model que impede qualquer tentativa de update/delete pós-criação (defesa em profundidade além da ausência de rota).
      Cobre: RF-18
      Acceptance criteria: nenhuma rota/action de UPDATE ou DELETE existe para `pedido_events`; tentativa direta no Model lança exceção.
      Testes: `laravel/tests/Unit/Models/PedidoEventImmutabilityTest.php`.

## Phase 4: Autenticação

Antes de implementar, leia:
1. `.spec/features/reimplementacao-v0-laravel-livewire/SPEC.md` — requisitos RIGID que esta fase cobre
2. `.spec/features/reimplementacao-v0-laravel-livewire/PLAN.md` — decomposição completa, dependências e riscos

- [ ] T24 — Autenticação baseada em sessão (login/logout)
      Arquivos: `laravel/app/Livewire/Auth/LoginForm.php`, `laravel/routes/web.php`
      Mudança: e-mail/senha via guard `auth:web`, hashing `bcrypt` padrão do Laravel; credenciais inválidas nunca autenticam.
      Cobre: RF-07, RNF-08
      Acceptance criteria: login válido cria sessão autenticada; credenciais inválidas não autenticam; senha armazenada é hash, nunca texto plano.
      Testes: `laravel/tests/Feature/Auth/LoginTest.php`.

- [ ] T25 — Middleware auth:web + redirecionamento de não autenticado
      Arquivos: `laravel/routes/web.php`, `laravel/app/Http/Middleware/Authenticate.php`
      Mudança: toda rota fora do login exige `auth:web`; requisição não autenticada redireciona para `/login`.
      Cobre: RF-09
      Acceptance criteria: requisição não autenticada a rota protegida é redirecionada para `/login`.
      Testes: `laravel/tests/Feature/Auth/UnauthenticatedAccessTest.php`.

## Phase 5: Autorização (Policies e Gates)

Antes de implementar, leia:
1. `.spec/features/reimplementacao-v0-laravel-livewire/SPEC.md` — requisitos RIGID que esta fase cobre
2. `.spec/features/reimplementacao-v0-laravel-livewire/PLAN.md` — decomposição completa, dependências e riscos

- [ ] T26 — Gates de papel (is-obra, is-suprimentos, is-gestao)
      Arquivos: `laravel/app/Providers/AppServiceProvider.php`
      Mudança: gates reutilizáveis para checagens grosseiras de papel, base para Policies e componentes Livewire.
      Cobre: RF-08, RF-08b, RF-08c
      Acceptance criteria: cada gate retorna true/false corretamente para os 3 papéis em teste dedicado.
      Testes: `laravel/tests/Feature/Authorization/RoleGatesTest.php`.

- [ ] T27 — PedidoPolicy (view/create/5 mutações)
      Arquivos: `laravel/app/Policies/PedidoPolicy.php`
      Mudança: `view` → `obra` restrito a `obra_profile`, `suprimentos`/`gestao` irrestritos; `create` → `obra` + associação; as 5 mutações operacionais → apenas `suprimentos`; `gestao` nunca autorizado a escrever.
      Cobre: RF-08, RF-08b, RF-08c, RF-09, RF-10, RF-17b, RF-20
      Acceptance criteria: `obra` negado nas 5 mutações; `suprimentos` permitido nas 5; `gestao` negado nas 5; `obra` negado leitura/escrita em pedido de obra não associada (403/404 sem vazamento); `obra` associada a múltiplas obras vê pedidos de todas.
      Testes: `laravel/tests/Feature/Authorization/PedidoPolicyTest.php`.

- [ ] T28 — Autorização aplicada no backend independente da UI
      Arquivos: `laravel/tests/Feature/Authorization/BypassUiAuthorizationTest.php`
      Mudança: nenhuma (apenas teste) — chama o método público do componente Livewire/Action diretamente, sem passar pela UI, e confirma que a Policy ainda rejeita.
      Cobre: RF-09
      Acceptance criteria: chamada direta ao backend, bypassando a UI, é rejeitada da mesma forma que via UI.
      Testes: `laravel/tests/Feature/Authorization/BypassUiAuthorizationTest.php`.

- [ ] T29 — Restrição de papel no seletor de responsável
      Arquivos: `laravel/app/Rules/ResponsibleMustBeSuprimentos.php`, query do seletor usada em T39
      Mudança: query do seletor restrita a usuários com papel `suprimentos`; regra de backend rejeita qualquer outro `responsible_id`.
      Cobre: RF-14b
      Acceptance criteria: seletor de responsável na UI lista apenas usuários `suprimentos`; submissão de `responsible_id` inválido rejeitada no backend.
      Testes: `laravel/tests/Feature/Rules/ResponsibleMustBeSuprimentosTest.php`.

## Phase 6: Fluxo da Obra (Blade + Livewire)

Antes de implementar, leia:
1. `.spec/features/reimplementacao-v0-laravel-livewire/SPEC.md` — requisitos RIGID que esta fase cobre
2. `.spec/features/reimplementacao-v0-laravel-livewire/PLAN.md` — decomposição completa, dependências e riscos

- [ ] T30 — Página/componente Livewire de Login
      Arquivos: `laravel/app/Livewire/Auth/LoginForm.php`, `laravel/resources/views/livewire/auth/login-form.blade.php`, `laravel/resources/views/auth/login.blade.php`
      Mudança: formulário Livewire ligado a T24; CSRF nativo; erros de validação exibidos.
      Cobre: RF-03, RF-07, UI-01
      Acceptance criteria: tela de login renderiza sem erro e autentica um usuário de demonstração válido.
      Testes: `laravel/tests/Feature/Livewire/LoginFormTest.php`.

- [ ] T31 — Componente Livewire "Nova Solicitação"
      Arquivos: `laravel/app/Livewire/Obra/NovaSolicitacao.php`, `laravel/resources/views/livewire/obra/nova-solicitacao.blade.php`
      Mudança: select de obra restrito à `obra_profile` do usuário; date picker `needed_at`; textarea `items_description`; chama `CreatePedidoAction` (T17); exibe o `code` gerado no sucesso.
      Cobre: RF-03, RF-11, RF-11b, RF-11c, UI-01
      Acceptance criteria: formulário lista apenas obras associadas ao usuário; envio com campo ausente rejeitado server-side; `obra_id` fora da associação rejeitado mesmo definido diretamente via `Livewire::test`.
      Testes: `laravel/tests/Feature/Livewire/NovaSolicitacaoTest.php`.

- [ ] T32 — Componente Livewire "Acompanhamento" (listagem da Obra)
      Arquivos: `laravel/app/Livewire/Obra/Acompanhamento.php`, `laravel/resources/views/livewire/obra/acompanhamento.blade.php`
      Mudança: listagem paginada restrita às obras associadas ao usuário; exibe status/prioridade/responsável/previsão/atraso via T16.
      Cobre: RF-03, RF-11, UI-01, RNF-07
      Acceptance criteria: apenas pedidos das obras associadas aparecem; contagem de queries constante entre datasets de 5 e 50 pedidos.
      Testes: `laravel/tests/Feature/Livewire/AcompanhamentoTest.php`.

- [ ] T33 — Detalhe do pedido (Obra, somente leitura) com timeline de histórico
      Arquivos: `laravel/app/Livewire/Obra/PedidoDetalhe.php`, `laravel/resources/views/livewire/obra/pedido-detalhe.blade.php`, `laravel/resources/views/components/pedido-history-timeline.blade.php`
      Mudança: detalhe somente leitura, sem controles de edição; timeline de eventos ordenada; acesso a pedido de obra não associada negado.
      Cobre: RF-03, RF-11, UI-01, UI-04
      Acceptance criteria: evento `criacao_pedido` visível após fluxo completo de criação; nenhum formulário/botão de edição no HTML renderizado; acesso a pedido não associado negado.
      Testes: `laravel/tests/Feature/Livewire/PedidoDetalheObraTest.php`.

## Phase 7: Fluxo de Suprimentos — Listagem, Kanban e Ações Operacionais

Antes de implementar, leia:
1. `.spec/features/reimplementacao-v0-laravel-livewire/SPEC.md` — requisitos RIGID que esta fase cobre
2. `.spec/features/reimplementacao-v0-laravel-livewire/PLAN.md` — decomposição completa, dependências e riscos

- [ ] T34 — Componente Livewire "Todos os Pedidos" com conjunto de filtros RF-12
      Arquivos: `laravel/app/Livewire/Suprimentos/TodosPedidos.php`, `laravel/resources/views/livewire/suprimentos/todos-pedidos.blade.php`
      Mudança: busca livre (código/obra/itens); filtro booleano "Atraso" (reusa T16); intervalos independentes `neededAtFrom`/`neededAtTo` e `requestedFrom`/`requestedTo`; paginação; eager loading.
      Cobre: RF-12, UI-02, RNF-07
      Acceptance criteria: cada filtro isolado e combinado retorna o conjunto correto; filtro "Atraso" bate com `AtrasoClassifier`; contagem de queries constante entre datasets.
      Testes: `laravel/tests/Feature/Livewire/TodosPedidosFiltersTest.php`.

- [ ] T35 — Componente Kanban (Suprimentos, interativo)
      Arquivos: `laravel/app/Livewire/Kanban/KanbanBoard.php`, `laravel/resources/views/livewire/kanban/kanban-board.blade.php`
      Mudança: 5 colunas ativas ordenadas por `statuses.sort_order`; `cancelado` excluído; drag-and-drop despacha para `UpdatePedidoStatusAction` (T21) via método com policy check.
      Cobre: RF-03, RF-12, UI-02, UI-03
      Acceptance criteria: as 5 colunas aparecem na ordem de `sort_order`; pedidos `cancelado` nunca aparecem em coluna.
      Testes: `laravel/tests/Feature/Livewire/KanbanBoardTest.php`.

- [ ] T36 — Card do Kanban (7 campos obrigatórios + estilo de atraso)
      Arquivos: `laravel/resources/views/livewire/kanban/pedido-card.blade.php`
      Mudança: renderiza código/obra/data necessária/prioridade/responsável/previsão/atraso; pedido atrasado recebe classe CSS distinta.
      Cobre: UI-03
      Acceptance criteria: os 7 campos aparecem no card; pedido atrasado tem classe visual distinta.
      Testes: `laravel/tests/Feature/Livewire/PedidoCardRenderTest.php`.

- [ ] T37 — Rejeição backend de drag-and-drop forjado (UI-07)
      Arquivos: `laravel/app/Livewire/Kanban/KanbanBoard.php`
      Mudança: nenhuma além do método existente — verificado por teste: movimento forjado (transição inválida ou ator não-suprimentos) chamado diretamente no componente é rejeitado e a posição do card não muda após reload.
      Cobre: UI-07, RF-13b
      Acceptance criteria: payload forjado de drag-and-drop é rejeitado no backend e a UI não reflete a mudança.
      Testes: `laravel/tests/Feature/Livewire/KanbanForgedMoveTest.php`.

- [ ] T38 — Controle acessível de mudança de status sem drag-and-drop (UI-08)
      Arquivos: `laravel/resources/views/livewire/kanban/pedido-card.blade.php`, `laravel/app/Livewire/Kanban/KanbanBoard.php`
      Mudança: seletor/botão acessível por teclado movendo o pedido pelo workflow, mesmo resultado/evento que o drag-and-drop.
      Cobre: UI-08
      Acceptance criteria: é possível mover um pedido por todo o workflow usando apenas o controle acessível.
      Testes: `laravel/tests/Feature/Livewire/AccessibleStatusControlTest.php`.

- [ ] T39 — Detalhe do pedido (Suprimentos) com as 5 ações operacionais
      Arquivos: `laravel/app/Livewire/Suprimentos/PedidoDetalhe.php`, `laravel/resources/views/livewire/suprimentos/pedido-detalhe.blade.php`
      Mudança: controles de responsável/prioridade/previsão/status/cancelamento ligados a T18–T22 + T27; controles ocultos/desabilitados quando o pedido atinge status terminal.
      Cobre: RF-12, UI-02, RF-14, RF-15, RF-16, RF-13, RF-13b
      Acceptance criteria: cada um dos 5 controles é funcional; controles desabilitados/ausentes para pedido terminal.
      Testes: `laravel/tests/Feature/Livewire/PedidoDetalheSuprimentosTest.php`.

- [ ] T40 — Controle de confirmação de cancelamento
      Arquivos: `laravel/resources/views/livewire/suprimentos/pedido-detalhe.blade.php`, `laravel/app/Livewire/Suprimentos/PedidoDetalhe.php`
      Mudança: diálogo de confirmação antes do cancelamento irreversível; ligado a `CancelPedidoAction` (T22).
      Cobre: RF-17, RF-17b, UI-02
      Acceptance criteria: cancelamento exige confirmação e, ao confirmar, persiste `cancelado` e gera evento `cancelamento`.
      Testes: `laravel/tests/Feature/Livewire/CancelPedidoControlTest.php`.

## Phase 8: Fluxo de Gestão — Kanban Read-Only e Dashboard

Antes de implementar, leia:
1. `.spec/features/reimplementacao-v0-laravel-livewire/SPEC.md` — requisitos RIGID que esta fase cobre
2. `.spec/features/reimplementacao-v0-laravel-livewire/PLAN.md` — decomposição completa, dependências e riscos

- [ ] T41 — "Todos os Pedidos" + Kanban somente leitura para Gestão (UI-05)
      Arquivos: `laravel/app/Livewire/Gestao/TodosPedidos.php`, `laravel/app/Livewire/Gestao/KanbanReadOnly.php`
      Mudança: reusa o conjunto de filtros de T34 (idêntico ao de Suprimentos, conforme AC de RF-20) em modo leitura; reusa o Kanban de T35 sem controles de mutação.
      Cobre: RF-20, UI-05
      Acceptance criteria: nenhum controle de mutação (`wire:click`) renderizado; conjunto de filtros idêntico ao de Suprimentos.
      Testes: `laravel/tests/Feature/Livewire/GestaoKanbanReadOnlyTest.php`.

- [ ] T42 — Detalhe do pedido para Gestão (reuso somente leitura)
      Arquivos: `laravel/app/Livewire/Gestao/PedidoDetalhe.php`
      Mudança: reusa o padrão de detalhe somente leitura de T33 para o papel `gestao`.
      Cobre: RF-20, UI-05
      Acceptance criteria: detalhe renderiza com histórico completo e sem nenhum controle de edição.
      Testes: `laravel/tests/Feature/Livewire/PedidoDetalheGestaoTest.php`.

- [ ] T43 — Indicadores do dashboard (volume/pendentes/atrasados/distribuição/prazos/por obra)
      Arquivos: `laravel/app/Livewire/Gestao/Dashboard.php`, `laravel/app/Services/DashboardIndicatorsService.php`, `laravel/resources/views/livewire/gestao/dashboard.blade.php`
      Mudança: serviço de agregação único reusando exclusivamente os classifiers de T16; os 6 indicadores computados a partir do mesmo dataset/consulta compartilhada.
      Cobre: RF-21, UI-06
      Acceptance criteria: contagem de "atrasados" idêntica à contagem via `AtrasoClassifier` no mesmo dataset; distribuição por status soma ao volume total; visão por obra soma ao volume total.
      Testes: `laravel/tests/Feature/Livewire/DashboardIndicatorsTest.php`.

- [ ] T44 — Filtros do dashboard (período/obra/status/prioridade/responsável)
      Arquivos: `laravel/app/Livewire/Gestao/Dashboard.php`
      Mudança: 5 filtros combináveis, cada um atualizando todos os indicadores de forma consistente.
      Cobre: RF-21, UI-06
      Acceptance criteria: aplicar cada filtro altera os indicadores de forma consistente com os dados filtrados.
      Testes: `laravel/tests/Feature/Livewire/DashboardFiltersTest.php`.

- [ ] T45 — Drill-down de indicador para listagem filtrada (RF-22, opcional)
      Arquivos: `laravel/app/Livewire/Gestao/Dashboard.php`, `laravel/routes/web.php`
      Mudança: clicar em "atrasados"/"pendentes" navega para a listagem de T34 pré-filtrada de acordo.
      Cobre: RF-22
      Acceptance criteria: resultado do drill-down bate exatamente com a contagem exibida no indicador.
      Testes: `laravel/tests/Feature/Livewire/DashboardDrillDownTest.php`.

## Phase 9: Dados de Demonstração

Antes de implementar, leia:
1. `.spec/features/reimplementacao-v0-laravel-livewire/SPEC.md` — requisitos RIGID que esta fase cobre
2. `.spec/features/reimplementacao-v0-laravel-livewire/PLAN.md` — decomposição completa, dependências e riscos

- [ ] T46 — Seeder de demonstração idempotente
      Arquivos: `laravel/database/seeders/DemoSeeder.php`, `laravel/database/seeders/DatabaseSeeder.php`
      Mudança: criação idempotente (upsert por chave natural) de 3 usuários de demonstração (obra/suprimentos/gestão) + um usuário obra multi-obra, obras e pedidos cobrindo status/prioridades/responsáveis, ≥1 atrasado, ≥1 entregue; `is_demo=true`; nomes prefixados `[DEMO]`.
      Cobre: RF-06
      Acceptance criteria: rodar o seeder duas vezes produz as mesmas contagens sem erro de unicidade; todas as linhas criadas têm `is_demo=true`.
      Testes: `laravel/tests/Feature/Seeders/DemoSeederIdempotencyTest.php`.

- [ ] T47 — Comando de reset dos dados de demonstração
      Arquivos: `laravel/app/Console/Commands/ResetDemoData.php`
      Mudança: comando artisan que remove apenas linhas `is_demo=true` (cascata para `pedido_events`/`obra_profile`), preservando `is_demo=false`.
      Cobre: RF-06
      Acceptance criteria: em base com dados reais e demo misturados, reset remove só as linhas demo e preserva as reais integralmente.
      Testes: `laravel/tests/Feature/Console/ResetDemoDataTest.php`.

## Phase 10: Suíte de Testes Automatizados — Cobertura, Performance e Segurança

Antes de implementar, leia:
1. `.spec/features/reimplementacao-v0-laravel-livewire/SPEC.md` — requisitos RIGID que esta fase cobre
2. `.spec/features/reimplementacao-v0-laravel-livewire/PLAN.md` — decomposição completa, dependências e riscos

- [ ] T48 — Mapa de cobertura de testes (temas do brief §30)
      Arquivos: `laravel/tests/README.md`
      Mudança: tabela mapeando os 18 temas do brief §30 aos arquivos de teste concretos de T17–T47.
      Cobre: RF-23
      Acceptance criteria: nenhum tema do brief §30 aparece sem um arquivo de teste correspondente na tabela.
      Testes: verificação documental — cobertura cruzada com a saída de `php artisan test` em T51.

- [ ] T49 — Testes de performance N+1/paginação (RNF-07 consolidado)
      Arquivos: `laravel/tests/Feature/Performance/QueryCountTest.php`
      Mudança: nenhuma além do teste — assevera contagem de queries constante entre datasets de 5 e 50 pedidos para as listagens (T32/T34), Kanban (T35) e dashboard (T43).
      Cobre: RNF-07
      Acceptance criteria: contagem de queries não escala linearmente com o tamanho do dataset nas 4 telas testadas.
      Testes: `laravel/tests/Feature/Performance/QueryCountTest.php`.

- [ ] T50 — Testes de reforço de segurança (CSRF, mass assignment, escaping)
      Arquivos: `laravel/tests/Feature/Security/CsrfProtectionTest.php`, `MassAssignmentTest.php`, `laravel/tests/Feature/Security/BladeEscapingTest.php`
      Mudança: nenhuma além dos testes — assevera token CSRF válido exigido nos formulários mutantes; `$fillable` explícito nos Models; ausência de `{!! !!}` para conteúdo de usuário não sanitizado.
      Cobre: RNF-08
      Acceptance criteria: formulário sem token CSRF é rejeitado; todo Model de escrita declara `$fillable`; nenhuma view usa `{!! !!}` para conteúdo de usuário sem justificativa.
      Testes: arquivos listados acima.

- [ ] T51 — Execução verde da suíte completa + verificação de exit code
      Arquivos: `laravel/composer.json`
      Mudança: nenhuma além da verificação — `php artisan test`/`vendor/bin/pest` passa com exit code 0 em toda a suíte (T17–T50).
      Cobre: RF-23
      Acceptance criteria: comando de teste completo retorna exit code 0.
      Testes: gate de verificação — execução completa da suíte.

## Phase 11: Validação Ponta a Ponta (E2E)

Antes de implementar, leia:
1. `.spec/features/reimplementacao-v0-laravel-livewire/SPEC.md` — requisitos RIGID que esta fase cobre
2. `.spec/features/reimplementacao-v0-laravel-livewire/PLAN.md` — decomposição completa, dependências e riscos

- [ ] T52 — Script E2E do roteiro de 19 passos
      Arquivos: `laravel/tests/Browser/DemoRoteiroTest.php`
      Mudança: automatiza os 19 passos do brief §31 contra dados de demonstração (T46), confirmando estado persistido e UI visível a cada passo.
      Cobre: RF-24
      Acceptance criteria: os 19 passos executam sem erro com dados persistidos e visíveis nas telas de cada perfil.
      Testes: `laravel/tests/Browser/DemoRoteiroTest.php`.

## Phase 12: Remoção de Dependências Next.js/Supabase

Antes de implementar, leia:
1. `.spec/features/reimplementacao-v0-laravel-livewire/SPEC.md` — requisitos RIGID que esta fase cobre
2. `.spec/features/reimplementacao-v0-laravel-livewire/PLAN.md` — decomposição completa, dependências e riscos

- [ ] T53 — Remoção de dependências Supabase em runtime
      Arquivos: `laravel/composer.json`, `laravel/.env.example`
      Mudança: nenhuma além da verificação — confirma que nenhuma chamada a SDK/API Supabase existe em `laravel/`.
      Cobre: RNF-03
      Acceptance criteria: busca por "supabase" no código Laravel não retorna dependência de runtime.
      Testes: `laravel/tests/Feature/Compliance/NoSupabaseDependencyTest.php`.

- [ ] T54 — Promoção do app Laravel para a raiz do repositório; desativação do runtime Next.js
      Arquivos: raiz do repositório — mover `laravel/*` para a raiz; remover/realocar `app/`, `components/`, `lib/`, `next.config.ts`, `proxy.ts`, `package.json`/`tsconfig.json` (Next.js), `supabase/`
      Mudança: processo de start/build de produção não invoca mais `next build`/`next start`; Laravel passa a ser a única aplicação servida na raiz; executar somente após T51 e T52 estarem verdes.
      Cobre: RNF-04, RF-01
      Acceptance criteria: nenhum script de deploy invoca `next build`/`next start`; `package.json` da raiz (se mantido para Vite) não tem `next`/`react` como dependência de runtime.
      Testes: `laravel/tests/Feature/Compliance/NoNextJsDependencyTest.php`.

## Phase 13: Documentação e Rastreabilidade

Antes de implementar, leia:
1. `.spec/features/reimplementacao-v0-laravel-livewire/SPEC.md` — requisitos RIGID que esta fase cobre
2. `.spec/features/reimplementacao-v0-laravel-livewire/PLAN.md` — decomposição completa, dependências e riscos

- [ ] T55 — Reescrita do README (instalação/config/migrate/seed/run/test/credenciais)
      Arquivos: `README.md`
      Mudança: cobre os 10 passos do brief §43 mais instruções de execução de testes e credenciais de demonstração (sem segredos reais).
      Cobre: RNF-02, RNF-05, RF-02
      Acceptance criteria: README contém todas as seções exigidas (instalação, `.env`, PostgreSQL, migrations, seed, execução local, testes, credenciais demo).
      Testes: verificação manual (walkthrough) conforme AC de RNF-02.

- [ ] T56 — Matriz de rastreabilidade de requisitos (RF-25, AC-33)
      Arquivos: `.spec/features/reimplementacao-v0-laravel-livewire/TRACEABILITY.md`
      Mudança: tabela mapeando cada regra de `docs/agents/domain_rules.md` + cada RF desta SPEC + cada ação de `docs/agents/api_contracts.md` ao equivalente Laravel (arquivo + teste); qualquer lacuna marcada com justificativa do brief §40 ou `[NEEDS CLARIFICATION]` explícito.
      Cobre: RF-25
      Acceptance criteria: documento existe e nenhuma linha está marcada "não implementado" sem justificativa referenciando brief §40 ou um `[NEEDS CLARIFICATION]`.
      Testes: auditoria manual — artefato requerido por AC-33.

## Phase 14: Preparação para Railway

Antes de implementar, leia:
1. `.spec/features/reimplementacao-v0-laravel-livewire/SPEC.md` — requisitos RIGID que esta fase cobre
2. `.spec/features/reimplementacao-v0-laravel-livewire/PLAN.md` — decomposição completa, dependências e riscos

- [ ] T57 — Documentação de environment variables e configuração de produção
      Arquivos: `README.md`, `laravel/.env.example`
      Mudança: documenta `APP_NAME`/`APP_ENV`/`APP_KEY`/`APP_DEBUG`/`APP_URL` + variáveis PostgreSQL; `APP_DEBUG=false` + `migrate --force` documentados para produção; caches de produção documentados.
      Cobre: RNF-06
      Acceptance criteria: documentação lista todas as env vars necessárias e o procedimento de deploy (Composer, build de assets, migrations `--force`, caches, start).
      Testes: verificação manual da documentação.

- [ ] T58 — Verificação de ausência de segredos commitados
      Arquivos: `.gitignore`, `laravel/tests/Feature/Compliance/NoCommittedSecretsTest.php`
      Mudança: confirma que `.env` não está versionado e `.gitignore` o cobre; grep por padrões de segredo no repositório retorna apenas placeholders/exemplos.
      Cobre: RNF-06, RNF-05
      Acceptance criteria: nenhum arquivo `.env` real está versionado; nenhum valor de segredo real encontrado fora de placeholders/exemplos.
      Testes: `laravel/tests/Feature/Compliance/NoCommittedSecretsTest.php`.
