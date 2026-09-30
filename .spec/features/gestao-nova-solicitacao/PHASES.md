# Phases: gestao-nova-solicitacao

Gerado por /plan a partir de PLAN.md — view executável para `./ralph.sh .spec/features/gestao-nova-solicitacao/PHASES.md`.
Branch: `build/v0-demo-laravel` · 10 tarefas em 7 fases · SPEC v1.1, sem marcadores abertos · sem migration, sem dependência nova, sem componente Livewire novo.

**Gate de execução (não é tarefa):**
- **G-1:** `git status --short docs/agents` precisa estar vazio antes da Fase 1 (commit documental separado ou descarte, decisão do desenvolvedor), para que o diff da Fase 7 seja só desta feature.
- **G-2:** o `git push` (deploy automático no Railway, sem CI) só acontece depois de T09 verde e da validação manual.

Regras válidas para todas as fases:
- Código PHP compatível com **8.4**. Rodar `vendor/bin/pint --dirty --format agent` depois de cada mudança PHP. Arquivos de teste novos via `php artisan make:test --pest <Pasta/Nome> --no-interaction`.
- Autorização em camadas (CLAUDE.md §5): rota `can:` → `mount()` → `PedidoPolicy::create` (delega ao gate) → guarda na Action. Só a habilidade `create-pedido` muda; nenhuma outra policy/guard de escrita muda.
- A sidebar é só apresentação: o item novo lista exatamente as `can:` da rota de destino e entra no catálogo `App\Support\SidebarNavigation`, nunca no Blade.
- Toda recusa da criação acontece antes da inspeção de anexos, da transação e do `nextval('pedido_code_sequence')`.
- "Obra ativa" só via escopo `->active()` (nunca `ObraStatus`/`where('status', …)` em `NovaSolicitacao.php` e `CreatePedidoAction.php` — `ObraActivityDefinitionTest` exige isso).
- Testes são atualizados, nunca apagados; nenhum arquivo tocado perde casos. Um processo Pest por vez contra `127.0.0.1:5434`; `--filter` sempre com `--testsuite=Feature`; saída da suíte Browser redirecionada para arquivo.
- Regra de verde: cada fase fecha com os arquivos de teste que tocou verdes e `php artisan test --compact --testsuite=Unit` verde. A Feature inteira fica verde ao fim da Fase 4; a Browser ao fim da Fase 5.

## Phase 1: Gate create-pedido estendido à Gestão

Antes de implementar, leia:
1. `.spec/features/gestao-nova-solicitacao/SPEC.md` — requisitos RIGID que esta fase cobre (RF-01, RF-02, CT-02)
2. `.spec/features/gestao-nova-solicitacao/PLAN.md` — decomposição completa, dependências e riscos

Vermelhos esperados na Feature depois desta fase (fechados nas Fases 2–4): testes que ainda fixam "Gestão negada" em `NovaSolicitacaoTest`, `CreatePedidoActionTest`, `BypassUiAuthorizationTest`, `PedidoOperationsAuthorizationTest`.

- [ ] T01 — Gate `create-pedido` estendido à Gestão e docblock da policy
      Arquivos: `app/Providers/AppServiceProvider.php`, `app/Policies/PedidoPolicy.php` (só docblock), `tests/Feature/Authorization/RoleGatesTest.php`, `tests/Feature/Authorization/PedidoPolicyTest.php`
      Mudança: acrescentar `RoleSlug::Gestao->value` ao `in_array` estrito de `create-pedido` (AppServiceProvider linha 50) e atualizar o docblock do gate; `PedidoPolicy` só docblock (`create` = Obra, Suprimentos e Gestão; Gestão nunca escreve sobre pedido existente). `RoleGatesTest.php:68-78`: dataset `gestao` → true, nova linha "papel desconhecido" (`User::factory()->create()`) → false, teste renomeado; testes de rota `:105-129` intactos. `PedidoPolicyTest.php:65-81`: dataset positivo com `gestao`; teste da linha 76 passa a cobrir só o usuário sem papel reconhecido.
      Cobre: RF-01, RF-02 (Policy), CT-02
      Acceptance criteria: `Gate::forUser($u)->allows('create-pedido')` é true para `obra`, `suprimentos` e `gestao` e false para papel desconhecido; `PedidoPolicy::create` idem, sem regra própria (continua `Gate::forUser($user)->allows('create-pedido')`); nenhum outro método de `PedidoPolicy` mudou de código.
      Testes: `tests/Feature/Authorization/RoleGatesTest.php` — gate por papel; `tests/Feature/Authorization/PedidoPolicyTest.php` — `create` por papel.

## Phase 2: Regra de obra da Gestão em CreatePedidoAction

Antes de implementar, leia:
1. `.spec/features/gestao-nova-solicitacao/SPEC.md` — requisitos RIGID que esta fase cobre (RF-02, RF-06, RF-07, RF-08, RF-09, RF-10, CT-02, CT-03, RNF-01, RNF-02)
2. `.spec/features/gestao-nova-solicitacao/PLAN.md` — decomposição completa, dependências e riscos

- [ ] T02 — Ramo da Gestão em `CreatePedidoAction` + nova mensagem de recusa
      Arquivos: `app/Actions/Pedidos/CreatePedidoAction.php`, `tests/Feature/Actions/CreatePedidoActionTest.php`, `tests/Feature/Actions/CreatePedidoActionGestaoTest.php` (novo)
      Mudança: mensagem de recusa exata "Apenas os perfis Obra, Suprimentos e Gestão podem criar solicitações."; `noActiveObraMessage()` ganha ramo `RoleSlug::Gestao` → "Nenhuma obra ativa cadastrada. Cadastre ou reative uma obra em Obras." (textos de Obra/Suprimentos idênticos). Depois de `validate()`, ramo decidido só por `$requester->role?->slug === RoleSlug::Gestao->value`: Gestão → `Obra::query()->active()->doesntExist()` → 422 `obra_id` (também para "Outra"); com id → novo `ensureObraAcceptsSolicitacaoForGestao(int $obraId)`: inexistente → 422 `obra_id` "A obra informada não foi encontrada."; não ativa → 422 `obra_id` "A obra informada está inativa e não recebe novas solicitações.". Obra/Suprimentos: código atual (linhas 97-108) e `ensureObraAcceptsSolicitacao()` intactos. Tudo antes de anexos/transação/`nextval`; nenhuma linha de `obra_profile` criada. Docblock da classe atualizado. `CreatePedidoActionTest.php:289-298` passa a usar papel desconhecido e a mensagem exata nova; `:210-215` ganha a mensagem da Gestão.
      Cobre: RF-02, RF-06, RF-07, RF-08, RF-09, RF-10, CT-02, CT-03, RNF-01, RNF-02
      Acceptance criteria: Gestão sem associação cria em obra `em_andamento` com `obra_id`, `requester_id` = gestão, `obra_reference = null`, status `solicitado`, 1 evento `criacao_pedido` com o nome da obra, `obra_profile` inalterada; id inexistente e obra `concluido` → `ValidationException` em `obra_id` com os textos exatos; "Outra" com "  Galpão X  " → `obra_reference = 'Galpão X'`, `new_value = 'Outra — Galpão X'`; sem nenhuma obra ativa no sistema, "Outra" e id `concluido` → 422 `obra_id` (texto exato para "Outra"); toda recusa deixa 0 linhas em `pedidos`/`pedido_events`/`pedido_attachments`, 0 arquivos em `pedido_anexos` e a sequência intacta; `suprimentos`/`obra` com obra ativa não associada continuam recebendo "A obra informada não está associada ao solicitante."; consultas de `execute()` como Gestão ≤ Suprimentos com 1 obra associada (entrada idêntica); o literal antigo não aparece em `app/`.
      Testes: `tests/Feature/Actions/CreatePedidoActionGestaoTest.php` — RF-06, RF-07, RF-08, RF-09, RF-10, RNF-01 (com `DB::enableQueryLog()`), RNF-02; `tests/Feature/Actions/CreatePedidoActionTest.php` — recusa por papel desconhecido com mensagem nova e mensagem da Gestão.

## Phase 3: Rota da Gestão e componente NovaSolicitacao

Antes de implementar, leia:
1. `.spec/features/gestao-nova-solicitacao/SPEC.md` — requisitos RIGID que esta fase cobre (RF-03, RF-04, RF-05, RF-10, UI-02, CT-01, RNF-01)
2. `.spec/features/gestao-nova-solicitacao/PLAN.md` — decomposição completa, dependências e riscos

T03 e T04 têm arquivos disjuntos.

- [ ] T03 — Rota `GET /gestao/nova-solicitacao` e fixação do middleware
      Arquivos: `routes/web.php`, `tests/Feature/Compliance/RouteMiddlewareBaselineTest.php`, `tests/Feature/Security/Adversarial/CrossRoleTest.php`, `tests/Feature/Livewire/GestaoScreensRouteTest.php` (novo)
      Mudança: no grupo `can:is-gestao`, `Route::get('/nova-solicitacao', NovaSolicitacao::class)->middleware('can:create-pedido')->name('nova-solicitacao');`. Baseline ganha `'gestao.nova-solicitacao' => ['web', 'auth', 'active', 'can:is-gestao', 'can:create-pedido']` sem alterar entradas existentes. `CrossRoleTest` G-06: lista esperada de `gestao.*` ganha `gestao.nova-solicitacao` (ordenada, depois de `gestao.kanban`). Arquivo novo espelha `ObraScreensRouteTest`/`SuprimentosScreensRouteTest`.
      Cobre: RF-03, CT-01
      Acceptance criteria: `gestao` → 200 com o título "Nova Solicitação"; `obra` → 403; `suprimentos` → 403; visitante → redirect `login`; `gestao` inativo → redirect `login`; `gestao` continua 403 em `/obra/nova-solicitacao` e `/suprimentos/nova-solicitacao`; `gatherMiddleware()` da rota é exatamente `['web', 'auth', 'active', 'can:is-gestao', 'can:create-pedido']`; `obra` recebe 403 em todas as rotas `gestao.*`, inclusive a nova.
      Testes: `tests/Feature/Livewire/GestaoScreensRouteTest.php` — matriz de status; `tests/Feature/Compliance/RouteMiddlewareBaselineTest.php` — entrada nova; `tests/Feature/Security/Adversarial/CrossRoleTest.php` — G-06.
- [ ] T04 — `NovaSolicitacao`: select da Gestão e `listingRoute()`
      Arquivos: `app/Livewire/Pedidos/NovaSolicitacao.php`, `tests/Feature/Livewire/NovaSolicitacaoTest.php`, `tests/Feature/Performance/QueryCountTest.php`
      Mudança: `obras()` → para `gestao`, `Obra::query()->active()->orderBy('name')->get()`; demais papéis inalterados (`->active()` literal nos dois ramos). `listingRoute()` → `match` por papel: `suprimentos` → `suprimentos.pedidos.index`, `gestao` → `gestao.pedidos.index`, outro → `obra.pedidos.index`. View sem mudança. Docblock atualizado. `NovaSolicitacaoTest.php:280-303`: os dois testes de Gestão negada passam a usar papel desconhecido (mount 403; submit forjado 403 sem escrita e com sequência intacta); dataset `:305-313` ganha `gestao` (`gestao.nova-solicitacao`, com obra ativa sem associação); `:315-322` intacto. Novos casos RF-04, RF-05, UI-02 e RF-10 (UI). `QueryCountTest.php`: teste de exatamente 1 consulta em `obras` no render da Gestão e `gestao` no dataset "1 vs 15".
      Cobre: RF-04, RF-05, RF-10, UI-02, RNF-01
      Acceptance criteria: com A (`em_andamento`), B (`a_iniciar`), C (`concluido`) sem associação, o select da Gestão contém exatamente A, B (ordem de nome) e "Outra"; `suprimentos` associado só a A vê A e "Outra"; `listingRoute()` devolve `route('gestao.pedidos.index')`, `route('suprimentos.pedidos.index')` e `route('obra.pedidos.index')` por papel; Gestão envia numa obra ativa e vê `PED-XXXXXX`, "Data prevista" e link com `href` = `route('gestao.pedidos.index')`; só com obras `concluido`, a página da Gestão mostra exatamente "Nenhuma obra ativa cadastrada. Cadastre ou reative uma obra em Obras." sem select nem botão de envio, e com uma obra ativa o formulário volta; o render da Gestão faz exatamente 1 consulta em `obras` e a contagem não cresce de 1 para 15 obras.
      Testes: `tests/Feature/Livewire/NovaSolicitacaoTest.php` — RF-04, RF-05, UI-02, RF-10 e testes reescopados; `tests/Feature/Performance/QueryCountTest.php` — RNF-01 (render).

## Phase 4: Sidebar da Gestão e testes adversariais

Antes de implementar, leia:
1. `.spec/features/gestao-nova-solicitacao/SPEC.md` — requisitos RIGID que esta fase cobre (UI-01, RF-02, RF-03, RF-08, RF-09, RF-11)
2. `.spec/features/gestao-nova-solicitacao/PLAN.md` — decomposição completa, dependências e riscos

T05 e T06 têm arquivos disjuntos. Ao fim desta fase, `php artisan test --compact --testsuite=Feature` termina com 0 falhas.

- [ ] T05 — Item "+ Nova Solicitação" da Gestão no catálogo da sidebar
      Arquivos: `app/Support/SidebarNavigation.php`, `tests/Feature/Authorization/SidebarNavigationCatalogueTest.php`, `tests/Feature/Livewire/SidebarNavigationTest.php`, `tests/Feature/Livewire/LayoutIdentityTest.php`
      Mudança: primeiro item do bloco Gestão = `self::item('+ Nova Solicitação', 'gestao.nova-solicitacao', 'gestao.nova-solicitacao', ['is-gestao', 'create-pedido'], null, true)`; Blade sem mudança (o layout já emite `sidebar-nova-solicitacao` e `topbar-nova-solicitacao` para o item destacado). Catálogo: rótulos da Gestão com "+ Nova Solicitação" em primeiro; teste de destaque itera os três papéis. `SidebarNavigationTest.php:97-106` reescrito para Gestão com o item (sem Visão Geral); dataset de `aria-current` ganha `gestao nova solicitação`; `:130-131` intactos. `LayoutIdentityTest.php:50-56` (7 links) e `:177-184` (entrada presente apontando para a rota da Gestão).
      Cobre: UI-01, RF-03
      Acceptance criteria: `SidebarNavigation::for($gestao)` devolve, em ordem, "+ Nova Solicitação" (`highlight = true`, rota `gestao.nova-solicitacao`), Pedidos, Dashboard, Kanban, Obras, Associações, Usuários; exatamente um item destacado por papel para `obra`, `suprimentos` e `gestao`; as abilities do item novo são iguais às `can:` da rota; página da Gestão contém `data-testid="sidebar-nova-solicitacao"` e `data-testid="topbar-nova-solicitacao"`, ambos com `href` para `/gestao/nova-solicitacao`; em `GET /gestao/nova-solicitacao` só "+ Nova Solicitação" tem `aria-current="page"`; "Visão Geral" ausente para `gestao`.
      Testes: `tests/Feature/Authorization/SidebarNavigationCatalogueTest.php`, `tests/Feature/Livewire/SidebarNavigationTest.php`, `tests/Feature/Livewire/LayoutIdentityTest.php`.
- [ ] T06 — Testes adversariais: nenhuma outra escrita da Gestão e visibilidade do pedido criado por ela
      Arquivos: `tests/Feature/Security/Adversarial/PedidoOperationsAuthorizationTest.php`, `tests/Feature/Authorization/BypassUiAuthorizationTest.php`, `tests/Feature/Security/Adversarial/GestaoCreatedPedidoTest.php` (novo)
      Mudança: `PedidoOperationsAuthorizationTest.php:153` → linha `creation by papel desconhecido`; `:156-178` reescrito para "gestao creates only through its own route and never through another papel's route" (403 nas rotas de Obra/Suprimentos, 200 na da Gestão, gate/`create` verdadeiros, Action cria 1 pedido e avança a sequência em exatamente 1; papel desconhecido negado em todas as camadas sem avançar a sequência); linha `:105` (`submit by gestao` sobre snapshot de Obra) mantida — deve seguir 403 pelo `can:is-obra` persistente; se falhar, parar e registrar como regressão. `BypassUiAuthorizationTest.php:101-111` → papel desconhecido com a mensagem exata nova. Arquivo novo: pedido criado pela Gestão (com obra e "Outra") — matriz RF-11 e visibilidade RF-08.
      Cobre: RF-02, RF-08, RF-09, RF-11
      Acceptance criteria: para pedido criado pela Gestão, `PedidoPolicy::{addObservacao, marcarEntregue, anexarRomaneio, finalizar, setResponsavel, setPrioridade, setPrevisao, updateStatus, cancelar}` são false para o criador e as 9 Actions correspondentes lançam `AuthorizationException` sem gravar `pedido_events`; `Gestao\PedidoDetalhe` e `KanbanReadOnly` não mostram controle de mutação; o "Outra" da Gestão aparece em `gestao.pedidos.index`/`show` para o criador, é visível a `suprimentos` e, para qualquer `obra`, some do Acompanhamento e o detalhe responde 403; nenhum arquivo tocado perde casos.
      Testes: `tests/Feature/Security/Adversarial/GestaoCreatedPedidoTest.php`, `tests/Feature/Security/Adversarial/PedidoOperationsAuthorizationTest.php`, `tests/Feature/Authorization/BypassUiAuthorizationTest.php`.

## Phase 5: Suíte Browser e documentação

Antes de implementar, leia:
1. `.spec/features/gestao-nova-solicitacao/SPEC.md` — requisitos RIGID que esta fase cobre (UI-01, UI-02, RNF-05)
2. `.spec/features/gestao-nova-solicitacao/PLAN.md` — decomposição completa, dependências e riscos

T07 e T08 têm arquivos disjuntos.

- [ ] T07 — Suíte Browser: Gestão com "+ Nova Solicitação"
      Arquivos: `tests/Browser/SidebarNavigationTest.php`, `tests/Browser/ResponsiveIdentityTest.php`
      Mudança: dataset `sidebar papéis` (`SidebarNavigationTest.php:28`) e `responsive papéis` (`ResponsiveIdentityTest.php:494`) → Gestão `true`. Nada além de datasets. Rodar com saída redirecionada para arquivo, um processo Pest por vez.
      Cobre: UI-01, UI-02
      Acceptance criteria: em 1440×900 a Gestão vê `#sidebar [data-testid="sidebar-nova-solicitacao"]`; em 390×844 o botão `topbar-nova-solicitacao` leva a `/gestao/nova-solicitacao` e o formulário carrega; os dois arquivos terminam verdes.
      Testes: `tests/Browser/SidebarNavigationTest.php`, `tests/Browser/ResponsiveIdentityTest.php`.
- [ ] T08 — Documentação manual: onboarding e CLAUDE.md
      Arquivos: `docs/onboarding-albuquerque.md`, `CLAUDE.md`
      Mudança: onboarding — tabela de perfis (linha 73, Gestão com "+ Nova Solicitação" e "cria solicitações; não altera pedidos"), regra da linha 79, nota na linha 128 (Gestão vê todas as obras ativas), nova subseção "Nova Solicitação pela Gestão" na seção da Gestão (~248) com a mensagem de zero obras ativas, e remoção de "Criar solicitações" na linha 269. `CLAUDE.md` — §3 "Criação do pedido" (quem cria, nova mensagem, regra da Gestão e suas mensagens), §4 (sidebar da Gestão, parágrafo da Nova Solicitação com três rotas e `listingRoute()`, tabela `gestao`), §5 camadas 3, 4 e 6. Citar símbolos, não números de linha inventados. Não tocar em `docs/agents/*.md`.
      Cobre: RNF-05
      Acceptance criteria: `grep -n "a Gestão também não cria solicitações" docs/onboarding-albuquerque.md` vazio; o onboarding descreve a Nova Solicitação da Gestão; `CLAUDE.md` diz que `create-pedido` = `obra`, `suprimentos` ou `gestao` e cita a rota `gestao.nova-solicitacao` e a mensagem "Apenas os perfis Obra, Suprimentos e Gestão podem criar solicitações."; `php artisan test --compact tests/Feature/Compliance` verde.
      Testes: `tests/Feature/Compliance` — verde.

## Phase 6: Regressão completa

Antes de implementar, leia:
1. `.spec/features/gestao-nova-solicitacao/SPEC.md` — requisitos RIGID que esta fase cobre (RNF-03, RNF-04, RF-12)
2. `.spec/features/gestao-nova-solicitacao/PLAN.md` — decomposição completa, dependências e riscos

- [ ] T09 — Regressão completa e checagem de RNF-03
      Arquivos: nenhum arquivo de produto; só correções pontuais em testes que falharem por causa desta feature (atualizar, nunca apagar).
      Mudança: `vendor/bin/pint --dirty --format agent`; `php artisan test --compact --testsuite=Unit`; `php artisan test --compact --testsuite=Feature`; `vendor/bin/pest tests/Browser` com saída em arquivo; conferir que `composer.json`, `composer.lock`, `package.json`, `package-lock.json` e `database/migrations/` não mudaram e que nenhum arquivo novo apareceu em `app/Livewire`; `grep -rn "Apenas os perfis Obra e Suprimentos podem criar" app/` vazio.
      Cobre: RNF-03, RNF-04, RF-12
      Acceptance criteria: Unit, Feature e Browser com 0 falhas; testes de RF-12 (`CreateUserAction`/`UpdateUserAction` com `gestao` + obras → 422, mudança para `gestao` faz `detach()`, `AttachUserObrasAction` recusa alvo `gestao`) verdes e inalterados; nenhuma dependência, migration ou componente Livewire novo; Pint sem pendências.
      Testes: suítes Unit, Feature e Browser completas.

## Phase 7: Regeneração de docs/agents

Antes de implementar, leia:
1. `.spec/features/gestao-nova-solicitacao/SPEC.md` — requisitos RIGID que esta fase cobre (RNF-05)
2. `.spec/features/gestao-nova-solicitacao/PLAN.md` — decomposição completa, dependências e riscos

- [ ] T10 — Regenerar `docs/agents/*.md` via `/ai-context`
      Arquivos: `docs/agents/*.md` (gerados)
      Mudança: executar `/ai-context`; nunca editar esses arquivos à mão (carregam o banner "Generated by /ai-context"). Se o motor não puder invocar o comando, registrar e deixar para o desenvolvedor logo após o Ralph.
      Cobre: RNF-05
      Acceptance criteria: `docs/agents/domain_rules.md` descreve `create-pedido` incluindo `gestao` e a mensagem nova; `docs/agents/api_contracts.md` lista `GET /gestao/nova-solicitacao` (`gestao.nova-solicitacao`, `can:is-gestao`, `can:create-pedido`); nenhum arquivo de `docs/agents` foi editado fora do `/ai-context`; `php artisan test --compact tests/Feature/Compliance` verde.
      Testes: `grep -n "gestao/nova-solicitacao" docs/agents/api_contracts.md`; `tests/Feature/Compliance` — verde.
