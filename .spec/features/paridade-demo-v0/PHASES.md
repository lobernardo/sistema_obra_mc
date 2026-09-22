# Phases: paridade-demo-v0

Gerado por /plan a partir de PLAN.md — view executável para `./ralph.sh .spec/features/paridade-demo-v0/PHASES.md`.
Branch: `feat/paridade-demo-v0` · Base: `5d36ba2` (`build/v0-demo-laravel`) · 33 tarefas em 6 fases.

Regras válidas para todas as fases: código PHP compatível com **8.4**; `vendor/bin/pint --dirty --format agent` antes de finalizar qualquer mudança PHP; `php artisan make:*` para criar arquivos; **nenhuma dependência nova**; textos de interface em PT-BR, identificadores em inglês, segmentos de URL em PT-BR; um processo Pest por vez contra o PostgreSQL de teste em `127.0.0.1:5434`, e `--filter` sem `--testsuite=Feature` arrasta `tests/Browser` para a execução.

## Phase 0: Identidade de e-mail normalizada

Antes de implementar, leia:
1. `.spec/features/paridade-demo-v0/SPEC.md` — requisitos RIGID que esta fase cobre (RF-01..RF-10, CT-04, CT-07, RNF-09)
2. `.spec/features/paridade-demo-v0/PLAN.md` — decomposição completa, dependências e riscos

Esta fase é **bloqueante** (RNF-01): nenhuma tarefa das fases seguintes pode ser implementada, commitada ou mesclada antes de todos os critérios de RF-01..RF-10 estarem verdes. Nada é removido: os 4 rate limiters, `AuthenticateSession` e as três trilhas append-only permanecem intactos.

- [ ] T01 — Regra canônica única de normalização de e-mail
      Arquivos: `app/Support/EmailNormalizer.php` (novo), `app/Services/AuthenticationRateLimiter.php`
      Mudança: criar `EmailNormalizer::normalize(string $email): string` devolvendo `mb_strtolower(trim($email))` como única implementação do projeto; reescrever o corpo de `AuthenticationRateLimiter::normalizeEmail()` (`:34`) para uma única chamada delegando a ela, mantendo o método público (chamado por `LoginForm:68`, `ForgotPassword:52`, `AuthenticationEventRecorder:89-93` e pelo teste unitário existente). Duplicar a expressão é proibido.
      Cobre: RF-01
      Acceptance criteria: uma varredura estática sobre `app/` encontra `strtolower`/`mb_strtolower` aplicado a e-mail exatamente uma vez, dentro de `EmailNormalizer`; a função devolve `'marcelo@albuquerque.com'` para `'  Marcelo@Albuquerque.COM '`; `tests/Unit/Services/AuthenticationRateLimiterTest.php` passa sem edição.
      Testes: `tests/Unit/Support/EmailNormalizerTest.php` (novo) — entrada com espaços e caixa mista, entrada já normalizada, string vazia; `tests/Feature/Compliance/EmailNormalizationGuardTest.php` (novo) — varredura estática.

- [ ] T02 — Normalização nos dois Actions de usuário e no formulário da Gestão
      Arquivos: `app/Actions/Usuarios/CreateUserAction.php`, `app/Actions/Usuarios/UpdateUserAction.php`, `app/Livewire/Gestao/Usuarios/Form.php`
      Mudança: normalizar `$data['email']` **antes** de `Validator::make(...)` nos dois Actions (`CreateUserAction:54-59`, `UpdateUserAction:44-49`), de modo que `unique:users,email` e `Rule::unique(...)->ignore($target)` avaliem o valor canônico e a coluna gravada (`:66`, `:67`) seja canônica; em `Form.php:64` trocar `trim($this->email)` pela chamada canônica.
      Cobre: RF-02
      Acceptance criteria: criar usuário com `'Marcelo@Albuquerque.com'` grava `'marcelo@albuquerque.com'`; um segundo create com `'MARCELO@albuquerque.com'` falha em `email` com a mensagem PT-BR existente "Já existe um usuário com este e-mail."; as duas mesmas asserções valem para `UpdateUserAction` contra o e-mail de outro usuário.
      Testes: `tests/Feature/Actions/Usuarios/CreateUserActionTest.php`, `UpdateUserActionTest.php`, `tests/Feature/Livewire/UsuariosFormTest.php` — acréscimos; reexecutar `UserAdminAuditTest` (o snapshot passa a registrar o e-mail canônico).

- [ ] T03 — Normalização em `php artisan users:create-gestao`
      Arquivos: `app/Console/Commands/CreateGestaoUser.php`
      Mudança: normalizar o valor de `--email=` logo após a leitura e usar o valor normalizado no `firstOrNew(['email' => $email])` (`:77`), no save e na mensagem `Usuário Gestão garantido: %s (%s)`. Nada mais do comando muda.
      Cobre: RF-03
      Acceptance criteria: rodar o comando com `'Gestor@X.com'` e depois com `'gestor@x.com'` resulta em exatamente uma linha em `users` com `email = 'gestor@x.com'`, e a segunda execução é reportada como `atualizado`, nunca `criado`.
      Testes: `tests/Feature/Console/CreateGestaoUserCommandTest.php` — acréscimo.

- [ ] T04 — Normalização no consumo de convite e de redefinição
      Arquivos: `app/Livewire/Auth/Concerns/DefinesPasswordFromToken.php`
      Mudança: normalizar o e-mail vindo da query string em `mount()` (`:39`) e normalizar de novo em `definePasswordThroughBroker()` antes de entregar `'email'` ao broker (`:74-78`), porque o campo é editável. A mensagem genérica de falha e o comportamento de não revelar se o e-mail existe (`:96-98`) permanecem idênticos.
      Cobre: RF-04
      Acceptance criteria: um link com `?email=Marcelo@Example.com` para um usuário armazenado em minúsculas conclui o fluxo e define a senha; a linha de `authentication_events` escrita por `AuthenticationEventRecorder` carrega o e-mail normalizado.
      Testes: `tests/Feature/Auth/FirstAccessInviteTest.php`, `tests/Feature/Auth/PasswordResetTest.php` — acréscimos com `Livewire::withQueryParams`.

- [ ] T05 — Comando de diagnóstico `users:email-case-report`
      Arquivos: `app/Console/Commands/EmailCaseReport.php` (novo)
      Mudança: comando somente leitura que varre **as duas** tabelas `users` e `password_reset_tokens`, listando por tabela toda linha cujo e-mail difere da forma normalizada e todo grupo que colidiria sob `lower(email)`, em PT-BR via `$this->table()`, no estilo de `ResetDemoData` (sem prompt). O veredito é a união das duas tabelas; a saída vai só para stdout, nunca para canal de log.
      Cobre: RF-07, CT-04
      Acceptance criteria: base limpa → exit `0` e a frase "Nenhuma colisão encontrada."; colisão plantada em `users` → exit não-zero, os dois endereços impressos, a tabela nomeada e a contagem de linhas afetadas reportada; colisão apenas em `password_reset_tokens` → também exit não-zero, nomeando essa tabela; nas três execuções, asserção de estado do banco mostra zero escritas.
      Testes: `tests/Feature/Console/EmailCaseReportCommandTest.php` (novo) — os três cenários acima.

- [ ] T06 — Migration: aborto por colisão, backfill das duas tabelas e índice único funcional
      Arquivos: `database/migrations/<timestamp>_normalize_user_emails_and_add_lower_unique_index.php` (novo)
      Mudança: em `up()`, numa única transação e nesta ordem — (1) detectar colisões sob `lower(email)` em `users` **e** `password_reset_tokens` e, havendo qualquer uma, lançar `RuntimeException` com mensagem PT-BR listando os endereços colidentes, sem escrever nada; (2) reescrever as duas colunas para `lower(btrim(email))`; (3) `CREATE UNIQUE INDEX users_email_lower_unique ON users (lower(email))`. `down()` executa apenas `DROP INDEX IF EXISTS users_email_lower_unique`. Sem extensão, sem `citext`, `users.email` continua `varchar(255)`, nenhuma linha de `pedido_events`, `user_admin_events` ou `authentication_events` é tocada, nenhuma conta é desativada, fundida ou reatribuída.
      Cobre: RF-05, RF-06, RF-08, RF-09, CT-07
      Acceptance criteria: após `php artisan migrate` sobre base com caixa mista, `SELECT count(*) FROM users WHERE email <> lower(email)` devolve `0` e a mesma consulta em `password_reset_tokens` devolve `0`; um token de convite emitido antes da migration ainda conclui o primeiro acesso depois dela; com `a@x.com` e `A@x.com` presentes, `migrate` falha, a mensagem nomeia os dois endereços, as duas linhas seguem inalteradas e `users_email_lower_unique` não existe.
      Testes: `tests/Feature/Migrations/EmailNormalizationMigrationTest.php` (novo) — backfill, convite pré-migration, aborto por colisão.

- [ ] T07 — Índice, reversibilidade, idempotência e trilhas intactas
      Arquivos: `tests/Feature/MigrationSchemaTest.php`, `tests/Feature/Migrations/EmailNormalizationMigrationTest.php`
      Mudança: somente verificação, sem código de produção. Afirmar o nome e a expressão do índice via `Schema::getIndexes('users')`, no mesmo estilo das asserções de índice já existentes no arquivo (`:23-34`, `:124`, `:167`, `:199`).
      Cobre: RF-08, RF-09, RNF-09
      Acceptance criteria: um insert bruto de `'A@X.com'` ao lado de `'a@x.com'` levanta violação de unicidade no nível do banco com a camada de aplicação contornada; após `migrate:rollback` o índice não existe e o tipo da coluna segue `varchar(255)`; `migrate → rollback → migrate` sobre base já normalizada roda três vezes sem erro e deixa `users` byte-idêntica ao final; uma linha semeada em cada uma das três trilhas com e-mail em caixa mista permanece byte-idêntica após a migration.
      Testes: as asserções acima, acrescentadas aos dois arquivos.

- [ ] T08 — Compliance: nenhum endereço real do cliente em arquivo desta feature
      Arquivos: `tests/Feature/Compliance/NoRealClientEmailTest.php` (novo)
      Mudança: varrer todo arquivo adicionado ou modificado por esta feature (diff contra a base do branch) procurando `@albuquerque.` e o domínio de produção configurado; afirmar que o comando de diagnóstico escreve apenas em stdout, sem canal de log recebendo endereço.
      Cobre: RF-10
      Acceptance criteria: zero ocorrências nos arquivos adicionados/modificados; nenhum endereço de e-mail em canal de log; os dados de teste da feature usam `example.com`/`example.org` ou valores de factory; `tests/Feature/Compliance/NoCommittedSecretsTest.php` passa sem edição.
      Testes: o próprio arquivo novo.

## Phase 1: Legibilidade das listagens e experiência mobile

Antes de implementar, leia:
1. `.spec/features/paridade-demo-v0/SPEC.md` — requisitos RIGID que esta fase cobre (RF-11, RF-12, RF-13, UI-01, UI-03, UI-05, CT-06, RNF-02, RNF-04, RNF-05)
2. `.spec/features/paridade-demo-v0/PLAN.md` — decomposição completa, dependências e riscos

Precondição obrigatória (RNF-01): a Phase 0 está inteiramente verde. Não inicie esta fase antes disso. O contrato de props de `x-pedido-table` (`:pedidos :show-route :empty-message`) não muda, e os três call sites (`obra/acompanhamento.blade.php:10`, `suprimentos/todos-pedidos.blade.php:50`, `gestao/todos-pedidos.blade.php:50`) continuam funcionando sem alteração de assinatura.

- [ ] T09 — Colunas "Itens" e "Solicitado em" em `x-pedido-table`
      Arquivos: `resources/views/components/pedido-table.blade.php`
      Mudança: acrescentar `<th>Itens</th>` logo após "Obra" e `<th>Solicitado em</th>`; a célula de Itens mostra `items_description` truncado e carrega o texto completo em `title`; a célula de Solicitado em renderiza `requested_at` em `d/m/Y`; substituir o literal `colspan="8"` (`:34`) por valor derivado de uma lista de colunas declarada uma vez no componente. Nenhuma query nova — os dois atributos já vêm carregados na linha.
      Cobre: RF-11, RF-12, RF-13, CT-06
      Acceptance criteria: para um pedido com `items_description` de 300 caracteres, o texto visível tem no máximo 90 caracteres e o atributo `title` vale exatamente a string completa de 300; `requested_at = 2026-03-07 14:22` renderiza `07/03/2026`; o estado vazio renderiza `colspan="10"` — as três asserções valem para as três listagens.
      Testes: `tests/Feature/Livewire/PedidoTableColumnsTest.php` (novo), exercitando `Obra\Acompanhamento`, `Suprimentos\TodosPedidos` e `Gestao\TodosPedidos`.

- [ ] T10 — Variante card abaixo do breakpoint `md:`
      Arquivos: `resources/views/components/pedido-table.blade.php`
      Mudança: no mesmo componente e a partir da mesma coleção `$pedidos`, renderizar uma lista de cards empilhados marcada `md:hidden` com pelo menos código, itens, status, data necessária e o indicador de atraso, e marcar o wrapper da tabela como `hidden md:block`. Manter `wire:key` e `data-pedido-code`, reutilizar `x-status-badge` e `x-atraso-indicator`, sem prop nova, sem segunda fonte de dados e sem padrão visual novo.
      Cobre: UI-01, UI-05, CT-06
      Acceptance criteria: para o mesmo conjunto de dados, o mesmo código de pedido está presente tanto na tabela quanto no markup de cards; a mensagem de estado vazio aparece exatamente uma vez por caminho de renderização; os três arquivos de teste das listagens e `tests/Feature/Livewire/PedidoCardRenderTest.php` continuam verdes.
      Testes: `tests/Feature/Livewire/PedidoTableColumnsTest.php` — acréscimos; reexecutar `AcompanhamentoTest`, `TodosPedidosFiltersTest`, `GestaoKanbanReadOnlyTest`, `PedidoCardRenderTest`.

- [ ] T11 — Cobertura responsiva das três listagens
      Arquivos: `tests/Browser/ResponsiveIdentityTest.php`
      Mudança: acrescentar um teste (somente acréscimo, sem tocar em asserção existente) que semeia `DemoSeeder`, autentica como os usuários demo obra / suprimentos / gestão e roda o helper existente `assertResponsiveAndAccessible()` em `/obra/pedidos`, `/suprimentos/pedidos` e `/gestao/pedidos` com o dataset `viewports` já declarado (1440×900, 820×1180, 390×844).
      Cobre: UI-03, RNF-04, RNF-05
      Acceptance criteria: as três listagens passam nas quatro regras do helper nos três viewports — sem overflow horizontal do documento, controle primário dentro da largura do viewport, todo controle de formulário rotulado, todo focável com anel ≥ 2 px.
      Testes: o próprio teste novo; exige `npm run build` e Chromium via pest-plugin-browser.

- [ ] T12 — `QueryCountTest` passa a cobrir a listagem da Gestão
      Arquivos: `tests/Feature/Performance/QueryCountTest.php`
      Mudança: acrescentar um caso para `Gestao\TodosPedidos` no mesmo padrão 5-vs-50 dos quatro casos existentes (hoje o arquivo cobre Acompanhamento, a listagem de Suprimentos, o Kanban e o dashboard, mas não a listagem da Gestão). Somente acréscimo.
      Cobre: RF-13, RNF-02
      Acceptance criteria: a contagem de queries da listagem da Gestão com 5 pedidos é igual à contagem com 50; os quatro casos pré-existentes seguem com as mesmas asserções.
      Testes: o próprio caso novo.

## Phase 2: Filtros nas três listagens

Antes de implementar, leia:
1. `.spec/features/paridade-demo-v0/SPEC.md` — requisitos RIGID que esta fase cobre (RF-14..RF-20, UI-02, CT-02, RNF-08, RF-32)
2. `.spec/features/paridade-demo-v0/PLAN.md` — decomposição completa, dependências e riscos

Precondição obrigatória (RNF-01): a Phase 0 está inteiramente verde. Nada existente é removido: busca textual, filtro de atraso, as duas faixas de data, `pendenteOnly` e a paginação sobrevivem intactos. O conjunto de opções de obra **nunca** leva `->active()` (obra inativa segue filtrável), ao contrário do caminho de criação em `NovaSolicitacao.php:79`, que permanece restrito a obras ativas.

- [ ] T13 — Quatro filtros na listagem de Suprimentos
      Arquivos: `app/Livewire/Suprimentos/TodosPedidos.php`, `resources/views/livewire/suprimentos/todos-pedidos.blade.php`
      Mudança: acrescentar `?int $obraId`, `?int $statusId`, `?int $priorityId`, `?int $responsibleId`, cada um aplicado como `where` singular somente quando não-nulo, combináveis entre si e com os seis filtros existentes; `render()` fornece `Obra::query()->orderBy('name')->get()` **sem** `->active()`, `Status::ordered()->get()`, `Priority::ordered()->get()` e `User::query()->suprimentos()->orderBy('name')->get()` — as mesmas fontes que `Gestao\Dashboard::render():85-88` já usa; quatro selects no card de filtros seguindo o padrão de `dashboard.blade.php:37-75`.
      Cobre: RF-14, RF-18, UI-02
      Acceptance criteria: cada um dos quatro filtros aplicado sozinho reduz um conjunto semeado exatamente aos pedidos esperados; os quatro combinados devolvem a interseção; os quatro filtros pré-existentes se comportam identicamente com e sem os novos; um pedido de obra desativada continua encontrável pelo filtro e a obra desativada aparece no select; cada novo controle tem `<label for>` cujo id existe exatamente uma vez no documento, `class="form-control"`, `wire:model.live` e opção vazia "Todas"/"Todos"; `QueryCountTest` continua com igualdade 5-vs-50.
      Testes: `tests/Feature/Livewire/TodosPedidosFiltersTest.php` — acréscimos; `tests/Feature/Livewire/ObraInativaPreservaHistoricoTest.php` passa sem edição.

- [ ] T14 — Quatro filtros na listagem da Gestão
      Arquivos: `app/Livewire/Gestao/TodosPedidos.php`, `resources/views/livewire/gestao/todos-pedidos.blade.php`, `tests/Feature/Livewire/GestaoKanbanReadOnlyTest.php`
      Mudança: espelhar T13 exatamente — mesmas quatro propriedades, mesma ordem de aplicação, mesmas fontes de opção, mesmo markup UI-02; `pendenteOnly` mantém o comportamento atual e segue sem controle renderizado. Estender o regex de ids em `GestaoKanbanReadOnlyTest:70` com `obraId|statusId|priorityId|responsibleId`, para que o teste de paridade entre as duas listagens continue significativo (acréscimo à classe de caracteres, sem enfraquecer asserção).
      Cobre: RF-15, RF-18, UI-02
      Acceptance criteria: as asserções de RF-14 passam contra `Gestao\TodosPedidos`; uma requisição combinando `?pendente=true` com `?statusId=<id>` devolve a interseção dos dois; o teste "the read-only listing filter set matches the Suprimentos listing filter set" segue verde com o regex estendido.
      Testes: `tests/Feature/Livewire/TodosPedidosFiltersTest.php` e `GestaoKanbanReadOnlyTest.php` — acréscimos.

- [ ] T15 — Conjunto reduzido de filtros na listagem da Obra
      Arquivos: `app/Livewire/Obra/Acompanhamento.php`, `resources/views/livewire/obra/acompanhamento.blade.php`
      Mudança: acrescentar `string $search`, `?int $obraId`, `?int $statusId`, `bool $atrasoOnly` — e somente estes; sem controles de prioridade e de responsável, e `priorityId`/`responsibleId` na query string são ignorados, não aplicados. A instrução `Pedido::` continua sendo um único statement que chama `visibleTo` antes do ponto e vírgula: `$query = Pedido::query()->visibleTo(Auth::user())->with([...]);`, com cada filtro aplicado depois, em statements separados, e o de obra com `where('obra_id', $this->obraId)` singular — `whereIn('obra_id', …)` é violação, assim como `find`/`findOrFail`/`firstOrFail` nesses statements. Reutilizar `AtrasoClassifier::scopeAtrasado($query)`; busca textual casando `code`, `items_description` e nome da obra; `updating()` resetando a página. Opções: `Auth::user()->obras()->orderBy('name')->get()` (só as obras do próprio usuário, sem `->active()`) e `Status::ordered()->get()`.
      Cobre: RF-16, RF-17, RF-18, RNF-08, UI-02
      Acceptance criteria: cada um dos quatro controles reduz corretamente o conjunto visível ao usuário Obra; nenhum controle de prioridade ou responsável é renderizado em `/obra/pedidos`; um `obraId` forjado de obra alheia devolve conjunto vazio e nenhum 500; o select de obra renderizado não contém o nome de nenhuma obra à qual o usuário não está associado; `tests/Feature/Compliance/ObraVisibleToGuardTest.php` passa sem edição, com suas quatro regras mecânicas.
      Testes: `tests/Feature/Livewire/AcompanhamentoTest.php` — acréscimos; `tests/Feature/Authorization/PedidoVisibleToScopeTest.php` — caso do `obraId` forjado e caso do select renderizado.

- [ ] T16 — Migração de todo o estado de filtro para `#[Url]`
      Arquivos: `app/Livewire/Suprimentos/TodosPedidos.php`, `app/Livewire/Gestao/TodosPedidos.php`, `app/Livewire/Obra/Acompanhamento.php`, `tests/Feature/Livewire/DashboardDrillDownTest.php`, `tests/Feature/Livewire/TodosPedidosFiltersTest.php`, `tests/Feature/Livewire/GestaoKanbanReadOnlyTest.php`
      Mudança: ligar **todas** as propriedades de filtro das três listagens com `Livewire\Attributes\Url` — os quatro `*Id` com `except: null`, `search` com `except: ''`, `atrasoOnly` como `#[Url(as: 'atrasado', except: false)]`, `pendenteOnly` como `#[Url(as: 'pendente', except: null)]`, e `neededAtFrom`/`neededAtTo`/`requestedFrom`/`requestedTo` com os nomes atuais e `except: ''`. **Remover** as leituras manuais de `Gestao\TodosPedidos::mount():46-49`, preservando apenas `authorize('is-gestao')`; misturar os dois mecanismos é proibido, porque `mount()` não re-executa em update Livewire. Esta é convenção nova no projeto (`grep -rn "#\[Url" app/` devolve zero no HEAD). Os testes que hoje fixam o mecanismo de `mount()` são **atualizados**, nunca removidos, e continuam cobrindo todos os parâmetros que cobriam antes.
      Cobre: RF-20, CT-02, RF-32
      Acceptance criteria: mudar qualquer filtro estando na página 3 leva à página 1; abrir `/suprimentos/pedidos?statusId=<id>&obraId=<id>` renderiza o conjunto filtrado já na primeira pintura, com os dois selects pré-selecionados; `/gestao/pedidos?atrasado=true` e `?pendente=true` resolvem para os mesmos conjuntos filtrados do HEAD; um valor default não aparece na URL; uma varredura estática em `app/Livewire/{Obra,Suprimentos,Gestao}/**` não encontra nenhuma leitura `request()->` de parâmetro de filtro; as asserções se repetem para as três listagens.
      Testes: atualizações em `DashboardDrillDownTest`, `TodosPedidosFiltersTest` e `GestaoKanbanReadOnlyTest`; testes novos de primeira pintura por URL direta e de varredura estática.

- [ ] T17 — Controle "Limpar filtros" nas três telas
      Arquivos: `app/Livewire/Suprimentos/TodosPedidos.php`, `app/Livewire/Gestao/TodosPedidos.php`, `app/Livewire/Obra/Acompanhamento.php` e as três views correspondentes
      Mudança: um método `limparFiltros()` por componente, resetando toda propriedade de filtro daquela tela ao default declarado — as novas **e** `search`, `atrasoOnly`, `pendenteOnly` e as faixas de data — via `$this->reset([...])`, seguido de `resetPage()`; um botão "Limpar filtros" dentro do formulário de filtros, no padrão de botão já existente no projeto.
      Cobre: RF-19
      Acceptance criteria: com todos os filtros preenchidos e a listagem na página 2, acionar o controle produz estado em que cada propriedade de filtro é igual ao seu default declarado, o paginador está na página 1 e a URL resultante não carrega nenhum parâmetro de filtro; recarregar essa URL devolve a listagem sem filtro; válido nas três telas.
      Testes: `tests/Feature/Livewire/TodosPedidosFiltersTest.php` e `AcompanhamentoTest.php` — acréscimos por tela.

## Phase 3: Indicadores e drill-down exato

Antes de implementar, leia:
1. `.spec/features/paridade-demo-v0/SPEC.md` — requisitos RIGID que esta fase cobre (RF-21..RF-24, CT-03, CT-05)
2. `.spec/features/paridade-demo-v0/PLAN.md` — decomposição completa, dependências e riscos

Precondição obrigatória (RNF-01): a Phase 0 está verde e a Phase 2 está concluída — o drill-down exato só é possível porque a listagem de destino passou a ter os quatro filtros. Atraso, pendência e prazo continuam existindo apenas em `app/Domain/Pedidos/*Classifier`: nenhum consumidor os re-deriva em Blade, componente ou SQL.

- [ ] T18 — KPIs Total / Pendentes / Atrasados no topo de Suprimentos › Pedidos
      Arquivos: `app/Livewire/Suprimentos/TodosPedidos.php`, `resources/views/livewire/suprimentos/todos-pedidos.blade.php`
      Mudança: extrair o builder filtrado para um método privado consumido tanto por `pedidos()` quanto por um novo `indicators()`, de modo que os números nunca divirjam das linhas paginadas; calcular `total` com `(clone $builder)->count()`, `pendentes` com `PendenteClassifier::scopePendente((clone $builder), true)->count()` e `atrasados` com `AtrasoClassifier::scopeAtrasado(clone $builder)->count()`, clonando **antes** do `paginate()`; renderizar três cards acima da listagem no padrão `data-testid="indicator-*"` / `data-value` do dashboard, com tokens de tema.
      Cobre: RF-21
      Acceptance criteria: sem filtro, os três números equivalem às contagens do conjunto inteiro; após aplicar o filtro de obra, equivalem às contagens daquela obra; os números nunca contradizem as linhas que a listagem pagina; nenhuma lógica de classificador é reimplementada no componente ou no Blade; `QueryCountTest` mantém a igualdade 5-vs-50 para a tela.
      Testes: `tests/Feature/Livewire/TodosPedidosFiltersTest.php` ou arquivo próprio de KPIs — acréscimos.

- [ ] T19 — Chave `entregues` em `DashboardIndicatorsService`
      Arquivos: `app/Services/DashboardIndicatorsService.php`
      Mudança: acrescentar a chave `entregues`, contando no conjunto filtrado já carregado os pedidos cujo slug de status é `entregue`, calculada em PHP sobre a coleção devolvida por `filteredPedidos()` e **sem** query adicional; atualizar a array shape do `@return` de `compute()` (`:25-32`) na mesma edição. O serviço segue role-agnostic e continua **não** aplicando `Pedido::visibleTo`.
      Cobre: RF-22, CT-05
      Acceptance criteria: `entregues` está correto sem filtro e sob cada um dos 5 filtros do dashboard; o conjunto de chaves devolvido bate com a shape documentada; nenhuma query nova é emitida por esta chave.
      Testes: `tests/Feature/Livewire/DashboardIndicatorsTest.php` — acréscimos.

- [ ] T20 — Drill-down exato e critério `entregue`
      Arquivos: `app/Livewire/Gestao/Dashboard.php`, `app/Livewire/Gestao/TodosPedidos.php`
      Mudança: `drillDownUrl(string $criterion)` (`:72-79`) passa a carregar todo filtro ativo do dashboard que a listagem de destino suporta — `requestedFrom`, `requestedTo`, `obraId`, `statusId`, `priorityId`, `responsibleId`, apenas os não vazios, mantendo o idioma do `array_filter` — mais `$criterion => 'true'`, com `$criterion ∈ {atrasado, pendente, entregue}`; substituir o parágrafo do docblock em `:21-28` que documenta a restrição deliberada, cuja razão foi removida pela Phase 2. Em `Gestao\TodosPedidos`, o critério `entregue` da URL resolve para o id do status `entregue` e é aplicado como restrição AND adicional junto a um `statusId` explícito.
      Cobre: RF-23, RF-24, CT-03
      Acceptance criteria: com obra, prioridade e período definidos no dashboard, a quantidade de linhas que a listagem de drill-down reporta é igual ao valor do KPI clicado; nenhum parâmetro de drill-down é descartado e nenhum é inventado; a URL do KPI "Entregues" carrega o critério `entregue`, `Gestao\TodosPedidos` o resolve para o id do status já na primeira carga, e a contagem resultante é igual ao valor de `entregues` sob os mesmos filtros; os três casos pré-existentes de `DashboardDrillDownTest` seguem verdes.
      Testes: `tests/Feature/Livewire/DashboardDrillDownTest.php` — acréscimos.

- [ ] T21 — Quarto card "Entregues" no dashboard
      Arquivos: `resources/views/livewire/gestao/dashboard.blade.php`, `app/Livewire/Gestao/Dashboard.php`
      Mudança: expor `entreguesDrillDownUrl` em `render()` ao lado das duas URLs de drill-down existentes; acrescentar um quarto card KPI com exatamente o padrão de markup de `indicator-pendentes`/`indicator-atrasados` (`:86-96`) — `data-testid="indicator-entregues"`, `data-value`, token de tema para o estado entregue e a contagem linkando para o drill-down; ajustar a grade de KPIs (`:79`, hoje `sm:grid-cols-3`) para acomodar quatro cards.
      Cobre: RF-22, RF-24, UI-05
      Acceptance criteria: o card renderiza com o mesmo padrão de markup dos dois existentes e não provoca overflow horizontal em 390 px; nenhuma cor literal é introduzida; `ThemeTokensTest` e `BrandIdentityComplianceTest` passam sem edição.
      Testes: `tests/Feature/Livewire/DashboardIndicatorsTest.php` — acréscimo de renderização do card.

## Phase 4: Visualização e Visão Geral de Suprimentos

Antes de implementar, leia:
1. `.spec/features/paridade-demo-v0/SPEC.md` — requisitos RIGID que esta fase cobre (RF-25..RF-30, UI-04, UI-05, CT-01, RNF-10)
2. `.spec/features/paridade-demo-v0/PLAN.md` — decomposição completa, dependências e riscos

Precondição obrigatória (RNF-01): a Phase 0 está verde e as Phases 1–3 estão concluídas. As duas seções de barras proporcionais que já existem no dashboard (`dashboard.blade.php:110,148`) **não** são reconstruídas — apenas recebem a camada de acessibilidade. Zero dependência de runtime nova: nenhuma biblioteca de gráfico.

- [ ] T22 — Donut de prazos em SVG inline
      Arquivos: `resources/views/livewire/gestao/dashboard.blade.php`
      Mudança: dentro de `[data-testid="indicator-prazos"]`, acima da lista numérica existente, renderizar um `<svg>` inline com `viewBox` e `preserveAspectRatio` contendo três fatias proporcionais a `indicators['prazos']`. Pintar por um mapa literal `$prazoFills = ['dentro_do_prazo' => 'fill-success', 'vencendo_em_breve' => 'fill-warning', 'atrasado' => 'fill-atraso']`, espelhando o `$prazoColors` de `:7-11`; a classe é sempre literal e completa. **Interpolar a classe é proibido** — o projeto não tem safelist, e a classe interpolada renderizaria preto apenas no build de produção. Como o mecanismo RIGID é `fill-*`, as fatias são `<path>` preenchidos, e não o `stroke-dasharray` sugerido no FLEXIBLE, que pinta por `stroke` e não seria coberto pela asserção de build. Tratar o caso de zero pendentes com a guarda `max(..., 1)` já existente em `:12`.
      Cobre: RF-25
      Acceptance criteria: existe exatamente um `<svg>` dentro do card de prazos e suas três fatias são proporcionais às três contagens dentro de 1 ponto percentual; a lista numérica com os itens `data-situacao` permanece presente e inalterada; o markup do donut não contém `fill-{{` nem `stroke-{{`; nenhum arquivo sob `resources/views/` ou `resources/css/` **adicionado ou modificado** por esta feature contém cor hexadecimal, literal `rgb(`/`hsl(` ou classe de paleta do Tailwind; nenhuma query nova e nenhum JavaScript novo.
      Testes: `tests/Feature/Livewire/DashboardDonutTest.php` (novo).

- [ ] T23 — Asserção sobre a saída de `npm run build`
      Arquivos: `tests/Feature/Compliance/BuiltAssetsUtilitiesTest.php` (novo)
      Mudança: resolver a entrada CSS de `resources/css/app.css` pelo `public/build/manifest.json`, ler a folha emitida em `public/build/assets/` e afirmar que os três utilitários gerados estão presentes. É a única asserção que protege o render de produção do donut, por isso é tarefa própria e não detalhe de implementação. Se o manifest não existir, falhar com mensagem acionável em PT-BR mandando rodar `npm run build` — nunca `skip`: a suíte Feature já depende do manifest porque `layouts/app.blade.php:12` chama `@vite`, e `public/build` é gitignored.
      Cobre: RF-25
      Acceptance criteria: a saída do build contém `fill-success`, `fill-warning` e `fill-atraso`; com o manifest ausente, o teste falha com a mensagem acionável em vez de ser pulado; o teste é reexecutado após o build final da Phase 5.
      Testes: o próprio arquivo novo.

- [ ] T24 — Camada de acessibilidade das três seções de indicadores
      Arquivos: `resources/views/livewire/gestao/dashboard.blade.php`
      Mudança: acrescentar `role="img"` e um `aria-label` em PT-BR descrevendo a distribuição e seus totais às seções `indicator-por-status` (`:100`), `indicator-prazos` (`:117`) e `indicator-por-obra` (`:138`), preservando os números textuais como alternativa acessível. As duas seções de barras existentes (`:110`, `:148`) ficam como estão — apenas os atributos são acrescentados.
      Cobre: RF-26
      Acceptance criteria: cada uma das três seções expõe `role="img"` e um `aria-label` não vazio nomeando a seção e seus totais; os blocos `data-testid="indicator-por-status"`, `indicator-prazos` e `indicator-por-obra` e seus atributos `data-*` por linha continuam renderizando com os mesmos valores de antes da mudança.
      Testes: `tests/Feature/Livewire/DashboardIndicatorsTest.php` — acréscimos.

- [ ] T25 — Chave `entreguesHoje` no serviço, com a única consulta autorizada
      Arquivos: `app/Services/DashboardIndicatorsService.php`
      Mudança: acrescentar a 8ª chave `entreguesHoje`, definida como pedido com status `entregue` **e** com um `pedido_events` do tipo `entrega` (`event_types.slug = 'entrega'`) com `created_at` no dia corrente — a entrega que de fato aconteceu, não a previsão (`expected_delivery_at` é nullable e só Suprimentos a preenche). Calcular com **exatamente uma** consulta adicional: um `whereExists` sobre `pedido_events` juntado a `event_types`, sobre o mesmo conjunto filtrado, constante em relação à quantidade de linhas. Atualizar a shape do `@return` para as 8 chaves e registrar no PHPDoc a dívida aceita com o limiar de ≈5 000 pedidos, esta única exceção autorizada e o aviso de que qualquer reuso futuro em contexto de Obra precisa aplicar `Pedido::visibleTo` antes.
      Cobre: RF-29, CT-05, RNF-10
      Acceptance criteria: um conjunto com um pedido entregue hoje, um entregue ontem e um entregue hoje **sem** `expected_delivery_at` devolve `entreguesHoje = 2`, afirmado explicitamente para os três pedidos; o valor é produzido pelo serviço; `compute()` emite exatamente uma query a mais do que no HEAD e a mesma contagem com 5 e com 50 pedidos; uma varredura estática não encontra referência ao slug `'entrega'` em `app/Livewire/Suprimentos/` nem no Blade da Visão Geral.
      Testes: `tests/Feature/Services/DashboardIndicatorsServiceTest.php` ou `tests/Feature/Livewire/DashboardIndicatorsTest.php` — acréscimos, mais a asserção de contagem de queries.

- [ ] T26 — Rota e componente `Suprimentos\VisaoGeral`
      Arquivos: `routes/web.php`, `app/Livewire/Suprimentos/VisaoGeral.php` (novo)
      Mudança: registrar `Route::get('/visao-geral', VisaoGeral::class)->name('visao-geral')` dentro do grupo `can:is-suprimentos` existente (`routes/web.php:74-78`), herdando `auth` + `active` + o gate de papel; componente full-page com `#[Layout('layouts.app')]` que re-verifica `$this->authorize('is-suprimentos')` em `mount()`, como `Suprimentos\TodosPedidos:37-40`. O braço `/home` não é tocado — Suprimentos continua aterrissando no Kanban.
      Cobre: RF-27, CT-01
      Acceptance criteria: um usuário suprimentos recebe 200; um usuário obra recebe 403; um usuário gestão recebe 403; um visitante é redirecionado para `login`; a verificação existente de que toda rota autenticada carrega `active` (`tests/Feature/Auth/EnsureUserIsActiveTest.php:109`) passa com a rota nova presente.
      Testes: `tests/Feature/Authorization/RoleGatesTest.php` e `tests/Feature/Livewire/SuprimentosScreensRouteTest.php` — acréscimos.

- [ ] T27 — Conteúdo da tela "Visão Geral"
      Arquivos: `app/Livewire/Suprimentos/VisaoGeral.php`, `resources/views/livewire/suprimentos/visao-geral.blade.php` (novo), `tests/Feature/Performance/QueryCountTest.php`
      Mudança: `render()` consome `DashboardIndicatorsService::compute([])` sem alterar o comportamento role-agnostic do serviço e sem segunda codificação de qualquer regra de classificador. A página apresenta (a) três cards KPI — Total de pedidos (`volumeTotal`), Atrasados (`atrasados`) e Entregues hoje (`entreguesHoje`); (b) a contagem de cada um dos 5 status de workflow não cancelados, vinda de `porStatus`; (c) um atalho para `route('suprimentos.kanban')`; (d) um `x-pedido-table` com os 5 pedidos mais recentes (`latest('requested_at')->take(5)`, com eager-load de `obra`, `status`, `priority`, `responsible`) e um link "Ver todos" para `route('suprimentos.pedidos.index')`. Somente componentes e classes já existentes: `card`, `page-title`, `section-title`, `x-pedido-table`, `x-status-badge`.
      Cobre: RF-28, RF-29, UI-05, RNF-02
      Acceptance criteria: as contagens por status equivalem às do conjunto semeado e batem com `porStatus`; a tabela mostra exatamente 5 linhas ordenadas por `requested_at` decrescente; o link do Kanban resolve para `route('suprimentos.kanban')` e o "Ver todos" para `route('suprimentos.pedidos.index')`; a contagem de queries da página com 5 pedidos é igual à com 50; `BrandIdentityComplianceTest` e `ThemeTokensTest` passam sem edição.
      Testes: `tests/Feature/Livewire/VisaoGeralTest.php` (novo); `tests/Feature/Performance/QueryCountTest.php` — acréscimo da nova tela.

- [ ] T28 — Entrada "Visão Geral" no menu de Suprimentos
      Arquivos: `resources/views/layouts/app.blade.php`
      Mudança: acrescentar `['label' => 'Visão Geral', 'route' => 'suprimentos.visao-geral', 'active' => 'suprimentos.visao-geral']` ao braço de Suprimentos de `$navItems` (`:25-28`), mantendo a convenção de estado ativo já usada ali (`request()->routeIs($item['active'])` e `aria-current="page"`, `:51-55`). Os braços de Obra e Gestão e o redirect por papel de `/home` ficam intactos.
      Cobre: RF-30
      Acceptance criteria: o menu de Suprimentos renderiza 3 entradas; a entrada nova é marcada ativa apenas em `/suprimentos/visao-geral`; `/home` continua redirecionando um usuário Suprimentos para `suprimentos.kanban`; `tests/Feature/Livewire/LayoutIdentityTest.php` passa sem edição.
      Testes: `tests/Feature/Livewire/LayoutIdentityTest.php` ou `SuprimentosScreensRouteTest.php` — acréscimos.

- [ ] T29 — Cobertura browser do donut e da Visão Geral
      Arquivos: `tests/Browser/DashboardChartsTest.php` (novo), `tests/Browser/ResponsiveIdentityTest.php`
      Mudança: um teste de browser que muda um filtro do dashboard duas vezes e, após cada mudança, verifica o gráfico; e a extensão do audit responsivo (somente acréscimo) para `/suprimentos/visao-geral` nos três viewports, confirmando também que `/gestao/dashboard` segue sem overflow horizontal em 390 px com o quarto card e o donut.
      Cobre: UI-04, RNF-04, RNF-05
      Acceptance criteria: após cada uma das duas mudanças de filtro existe exatamente um `<svg>` no card de prazos e suas proporções correspondem aos números recém-renderizados — sem gráfico sumindo, duplicando ou ficando obsoleto; `/suprimentos/visao-geral` passa nas quatro regras do audit nos três viewports; `/gestao/dashboard` não tem overflow horizontal em 390 px.
      Testes: os dois arquivos acima; exigem `npm run build` e Chromium.

## Phase 5: Documentação, suíte, build e gates

Antes de implementar, leia:
1. `.spec/features/paridade-demo-v0/SPEC.md` — requisitos RIGID que esta fase cobre (RF-31, RF-32, RNF-03, RNF-06, RNF-07, RNF-10)
2. `.spec/features/paridade-demo-v0/PLAN.md` — decomposição completa, dependências e riscos

Precondição obrigatória (RNF-01): as Phases 0–4 estão concluídas. Esta fase é estritamente sequencial, e a tarefa final deve rodar depois do build que ela valida.

- [ ] T30 — `docs/agents/*` regenerados via `/ai-context`
      Arquivos: `docs/agents/api_contracts.md`, `architecture.md`, `coding_guidelines.md`, `data_model.md`, `dependencies.md`, `domain_rules.md`, `project_overview.md`, `tech_stack.md`
      Mudança: rodar `/ai-context` e conferir que a árvore regenerada registra a rota `suprimentos.visao-geral` e seu componente, os conjuntos de filtro por tela e o novo contrato de query string, as chaves `entregues` e `entreguesHoje` com a shape de 8 chaves de `compute()`, a convenção nova `#[Url]`, a migration da Phase 0 com `users_email_lower_unique` e `App\Support\EmailNormalizer`, e a dívida da RNF-10 com o limiar de ≈5 000 pedidos e a única consulta autorizada. Esses arquivos carregam banner de geração e nunca são editados à mão.
      Cobre: RF-31, RNF-10
      Acceptance criteria: `git status` mostra alterações sob `docs/agents/`; o `api_contracts.md` regenerado lista a rota nova e os parâmetros de query string novos.
      Testes: verificação por inspeção de arquivo (`git status` e leitura do `api_contracts.md` regenerado).

- [ ] T31 — `CLAUDE.md` e onboarding (edição manual)
      Arquivos: `CLAUDE.md`, `docs/onboarding-albuquerque.md`
      Mudança: `CLAUDE.md` e `AGENTS.md` são escritos à mão, sem banner de geração, e nunca são sobrescritos por máquina. Editar `CLAUDE.md` manualmente para registrar as decisões "gráficos em SVG inline, sem biblioteca de chart" e "`visibleTo` antes de qualquer filtro", a convenção nova `#[Url]`, a regra canônica de normalização de e-mail com seu índice único funcional, o fechamento das divergências de §4 que esta feature resolve (filtro por status e dashboard de Suprimentos) e a ressalva operacional do `DemoSeeder` para `entreguesHoje`. Em `docs/onboarding-albuquerque.md`, revisar a tabela "Limites conhecidos" (`:308-318`) removendo "Sem filtro por status em Todos os Pedidos" e "Sem dashboard próprio para Suprimentos". Nenhum `AI_CONTEXT.md` é criado.
      Cobre: RF-31
      Acceptance criteria: `git status` mostra `CLAUDE.md` e `docs/onboarding-albuquerque.md` alterados; nenhum arquivo chamado `AI_CONTEXT.md` existe na árvore; a seção de limites conhecidos não menciona mais a ausência de filtros nem a de uma tela de visão geral para Suprimentos.
      Testes: asserções de arquivo sobre a árvore e a tabela de limites.

- [ ] T32 — Gates de segurança e de dependências
      Arquivos: nenhum arquivo de produção; verificação sobre `tests/Feature/Authorization/*`, `tests/Feature/Security/Adversarial/*`, `tests/Feature/Compliance/*`, `tests/Feature/Design/ThemeTokensTest.php`
      Mudança: rodar e confirmar verdes, sem nenhuma asserção enfraquecida, apagada ou pulada: `tests/Feature/Authorization/*`, `tests/Feature/Security/Adversarial/*`, `ObraVisibleToGuardTest.php`, `AuditTrailsAppendOnlyTest.php`, `NoCommittedSecretsTest.php`, `ThemeTokensTest.php`, `BrandIdentityComplianceTest.php`, `BrandAssetsTest.php`, `NoNextJsDependencyTest.php` e `NoSupabaseDependencyTest.php`.
      Cobre: RNF-03, RNF-07
      Acceptance criteria: todos os arquivos listados passam; `git diff --stat composer.json package.json composer.lock package-lock.json` não mostra alteração nos blocos de dependência de produção — zero dependência de runtime nova, nenhuma biblioteca de gráfico; uma revisão de `git diff` comprova que os arquivos de teste pré-existentes receberam apenas acréscimos.
      Testes: a própria execução dos gates mais a revisão de diff.

- [ ] T33 — Pint, build de assets e suíte completa
      Arquivos: nenhum arquivo de código; `public/build/` regenerado
      Mudança: rodar `vendor/bin/pint --dirty --format agent`; rodar `npm run build` **antes** da execução final da suíte, porque é esse build que a asserção da T23 lê; rodar `php artisan test --compact` sobre `tests/Unit` e `tests/Feature`; rodar `vendor/bin/pest tests/Browser` separadamente, um processo Pest por vez contra o banco de teste em `127.0.0.1:5434`.
      Cobre: RNF-06, RF-32
      Acceptance criteria: 0 falhas e 0 erros nas três suítes; `npm run build` sai com código 0; Pint não reporta correção pendente; `git diff` comprova que `tests/Browser/DemoRoteiroTest.php` está sem edição e que as únicas asserções pré-existentes reescritas são as de leitura em `mount()` substituídas pela T16.
      Testes: a execução completa da suíte, o build e a revisão de diff.
