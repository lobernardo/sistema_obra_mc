# Phases: navegacao-sidebar-listagens

Gerado por /plan a partir de PLAN.md — view executável para `./ralph.sh .spec/features/navegacao-sidebar-listagens/PHASES.md`.
Branch: `build/v0-demo-laravel` · Base: commit que fecha a fatia 2 (registrado como `<base>` em T01) · 24 tarefas em 9 fases · fatia 3 de 3, fecha o incremento (as fatias 1 e 2 são pré-requisito). SPEC v1.2 (correções F-01, F-08, F-11, F-15, F-16 aplicadas; texto do RF-22 ajustado por N-06), sem marcadores abertos. Revisão cruzada v2 aplicada: N-01 (T20 e o guia de onboarding), N-03 (regra de verde por fase), N-05 (Compliance inteira em T20 e a regressão completa T24 na Fase 9), N-06 (legenda "Preciso para" em T14 e varredura sem distinção de caixa em T17). Defaults D-5, D-6 e D-10 confirmados pelo desenvolvedor.

**Gates de execução (não são tarefas). Detalhes no topo do PLAN.md:**
- **G-1 (commit documental, por referência ao gate da fatia 1):** `git status --short docs/agents` precisa estar vazio antes do Ralph. Qualquer pendência vai para um commit só de `docs/agents/*.md`.
- **G-2 (fatias 1 e 2 mergeadas e validadas):**
  - fatia 1: os 8 commits `feat(phase-N)` e o commit documental da Fase 9; fatia 2: os 9 commits `feat(phase-N)` e o commit documental da Fase 10 (ou o commit documental equivalente feito pelo desenvolvedor);
  - fatia 1: existem `obras.index`, `obras.create`, `obras.edit`, `associacoes.index`, `register`, `manage-obras`, `ObraStatus`, `Obra::scopeActive` por status, `App\Livewire\Auth\Register`, `App\Livewire\Associacoes\Index` e `AttachUserObrasAction`;
  - fatia 2: existem `obra.nova-solicitacao` e `suprimentos.nova-solicitacao` em `App\Livewire\Pedidos\NovaSolicitacao`, `create-pedido`, `Pedido::obraLabel()`, `dataPrevistaLabel()`, `presentDataPrevista()`, `OUTRA_LABEL`, `CreatePedidoAction::noActiveObraMessage()`, `LocalTime` (`TIMEZONE`, `toLocal`, `formatDateTime`, `formatDate`, `today`, `localDayStartUtc`, `todayWindowUtc`), **`App\Domain\Pedidos\RequestedPeriodFilter`** (`COLUMN`, `utcBoundsForLocalRange`, `applyLocalRange`) com `RequestedPeriodFilterTest`, `RequestedPeriodSingleDefinitionTest` e `LocalTimeDisplayComplianceTest`, `StatusSlug::Finalizado`, `terminal()`, `terminalValues()`, `isTerminal()`, `finalizableFrom()`, `Pedido::factory()->outra()`, `seedWorkflowStatuses()` e `seedHistoryEventTypes()`; `grep -rn "whereDate('requested_at'" app/` → vazio;
  - as suítes estão verdes, o desenvolvedor validou a fatia 2 e a árvore está limpa.
- **G-3 (publicação):** o `git push` só acontece depois de T22 verde, do commit documental de T20, da validação manual do incremento e de **T24 verde no HEAD final**. Se a fatia 2 ainda não foi publicada, vale também o G-4 da fatia 2 (Volume Railway, limites de upload, associações de Suprimentos). Esta fatia não tem migration.

Regras válidas para todas as fases:
- Código PHP compatível com **8.4**. Rodar `vendor/bin/pint --dirty --format agent` antes de finalizar qualquer mudança PHP. Criar arquivos com `php artisan make:* --no-interaction`. **Nenhuma dependência nova** (Composer/npm), nenhuma pasta-base nova, nenhuma migration.
- A sidebar é só apresentação. Nenhuma rota, middleware, gate, policy ou Action muda, exceto o corpo da closure `home` (T07). Um item aparece se e somente se o usuário passa exatamente nas habilidades `can:` da rota de destino.
- Estado de filtro só via `#[Url(except: <default>)]`. O `mount()` nunca lê o request, só propriedades já hidratadas pelo `#[Url]`. Os nomes legados `atrasado`, `pendente`, `requestedFrom` e `requestedTo` continuam valendo.
- `Pedido::visibleTo(Auth::user())` abre toda consulta de listagem na mesma instrução, e todo filtro só estreita. "Obra ativa" vem de `Obra::active()` (fatia 1), obra/referência de `Pedido::obraLabel()` e Data prevista de `Pedido::dataPrevistaLabel()` (fatia 2). Dia local só via `App\Support\LocalTime`.
- **Uma só classe de período (F-01):** todo limite de `requested_at` (presets, Personalizado, Dashboard, drill-down) vem de `App\Domain\Pedidos\RequestedPeriodFilter`, criada pela fatia 2. Esta fatia **nunca recria** esse arquivo, só soma métodos. Nenhum `whereDate('requested_at', …)`, nenhum `where('requested_at', …)` fora dessa classe e nenhum literal `'America/Sao_Paulo'` fora de `LocalTime`.
- Classes Tailwind literais, sem `{!! !!}`, ícones em SVG inline, sem gradiente, sem botão pílula, marca só via `config('app.name')`. A listagem da Gestão não tem nenhum `wire:click` (nem no layout).
- Textos em PT-BR com as strings exatas do RNF-05.
- Um processo Pest por vez contra o PostgreSQL de teste em `127.0.0.1:5434`. `--filter` sem `--testsuite=Feature` arrasta `tests/Browser`.
- **Regra de verde por fase (N-03):** "a fase fecha verde" = **Unit + Feature** verdes (`php artisan test --compact --testsuite=Unit`, depois `--testsuite=Feature`). `tests/Browser` fica sabidamente vermelho entre a Fase 2 e a Fase 6 (T19) e só é garantido nos gates de regressão: T22 (Fase 7) e T24 (Fase 9, HEAD final). A fase só de documentação (Fase 8) fecha com toda `tests/Feature/Compliance` verde.
- Testes são **atualizados, nunca apagados**, e o número de casos por arquivo tocado não diminui. Asserções que quebram só por causa do texto da sidebar são reescopadas para `<main>`, nunca afrouxadas.

## Phase 1: Fundamentos — linha de base de rotas, catálogo da sidebar e CSS

Antes de implementar, leia:
1. `.spec/features/navegacao-sidebar-listagens/SPEC.md` — requisitos RIGID que esta fase cobre (RF-02, RF-03, RF-07, RF-08, RF-09, RF-24, CT-02, UI-03, UI-07, RNF-01, RNF-04, RNF-05)
2. `.spec/features/navegacao-sidebar-listagens/PLAN.md` — decomposição completa, dependências e riscos

T01 captura a linha de base **antes** de qualquer outra mudança de código. As três tarefas têm arquivos disjuntos.

- [ ] T01 — Linha de base do middleware das rotas, capturada antes de qualquer mudança
      Arquivos: `tests/Feature/Compliance/RouteMiddlewareBaselineTest.php` (novo)
      Mudança: no `<base>`, capturar via tinker o `gatherMiddleware()` de toda rota nomeada e transcrever para um array literal `routeMiddlewareBaseline()`. O docblock registra o SHA do `<base>`. Teste 1: cada rota da base existe e tem exatamente o mesmo middleware, na mesma ordem. Teste 2: `home` mantém `web`, `auth` e `active`.
      Cobre: RF-02, RF-09, RF-24
      Acceptance criteria: o teste passa no `<base>` e continua verde em todas as fases seguintes. O mapa só pode ganhar rotas, nunca mudar as existentes.
      Testes: `tests/Feature/Compliance/RouteMiddlewareBaselineTest.php`.

- [ ] T02 — Catálogo da sidebar `App\Support\SidebarNavigation`
      Arquivos: `app/Support/SidebarNavigation.php` (novo), `tests/Feature/Authorization/SidebarNavigationCatalogueTest.php` (novo)
      Mudança:
      - `for(?User $user)` e `catalogue()` devolvem itens `{label, route, active, abilities, group, highlight}` conforme o CT-02, na ordem do RF-03.
      - Obra: + Nova Solicitação (`obra.nova-solicitacao`, `is-obra` + `create-pedido`) e Acompanhamento.
      - Suprimentos: + Nova Solicitação (`suprimentos.nova-solicitacao`); grupo "Operação" com Pedidos, Visão Geral e Kanban; grupo "Cadastros" com Obras e Associações (`manage-obras`).
      - Gestão: grupo "Operação" com Pedidos, Dashboard e Kanban; grupo "Administração" com Obras, Associações e Usuários (`is-gestao` + `manage-users`).
      - Um item só entra se `Gate::forUser($user)->allows()` passa em todas as habilidades listadas. Papel desconhecido ou `null` → `[]`. Nenhuma consulta SQL.
      Cobre: RF-02, RF-03, RF-07, RF-08, CT-02, RNF-01, RNF-05
      Acceptance criteria:
      - as listas de rótulos por papel são exatamente as do RF-03, e um usuário sem papel recebe `[]`;
      - `route()` resolve para todos os itens;
      - as habilidades `can:` do middleware de cada rota de destino são iguais às `abilities` do item, como conjuntos;
      - só os dois "+ Nova Solicitação" têm destaque, cada um em primeiro;
      - 0 consultas com `role` carregado.
      Testes: `tests/Feature/Authorization/SidebarNavigationCatalogueTest.php`.

- [ ] T03 — Componentes CSS da sidebar e do painel de filtros
      Arquivos: `resources/css/app.css`, `tests/Feature/Design/ThemeTokensTest.php` (só adições)
      Mudança: adicionar `.sidebar-link` (com `min-h-11` e `focus-visible:ring-2 focus-visible:ring-focus/40`), `.sidebar-link-active` (`bg-primary/10 text-primary`), `.sidebar-group-label` e `.filter-panel .form-control` → `min-h-11 lg:min-h-0`. Assim o markup `<select … class="form-control">` fica byte-idêntico e o toque chega a 44 px. Tudo literal, sem gradiente e sem `rounded-full`. `.nav-link*` só sai em T04.
      Cobre: UI-03, UI-07, RNF-04
      Acceptance criteria: `.sidebar-link` não está vazio e contém `focus-visible:ring-2`. `.sidebar-link-active` contém `primary` e não contém `rounded-full`. `.filter-panel .form-control` contém `min-h-11`.
      Testes: `tests/Feature/Design/ThemeTokensTest.php`.

## Phase 2: Sidebar no layout e páginas iniciais

Antes de implementar, leia:
1. `.spec/features/navegacao-sidebar-listagens/SPEC.md` — requisitos RIGID que esta fase cobre (RF-01..RF-09, RF-24, UI-01..UI-04, CT-01, CT-02, RNF-03, RNF-04)
2. `.spec/features/navegacao-sidebar-listagens/PLAN.md` — decomposição completa, dependências e riscos

T04 e T07 podem rodar em paralelo. T05 vem depois das duas, porque edita `LayoutIdentityTest`, inclusive o caso da home. T04 e T05 entram no mesmo commit. T06 é a última.

- [ ] T04 — Layout autenticado com sidebar (fixa no desktop, gaveta no mobile)
      Arquivos: `resources/views/layouts/app.blade.php`, `resources/css/app.css` (remover `.nav-link` e `.nav-link-active`), `tests/Feature/Design/ThemeTokensTest.php` (`:188-190` migrado para `.sidebar-link*`)
      Mudança:
      - O `match` de papel (`:17-37`) e a toolbar (`:47-60`) saem, inclusive as entradas provisórias da fatia 1 (Obras, Associações) e da fatia 2 (+ Nova Solicitação de Suprimentos); entra `SidebarNavigation::for($currentUser)`. O `<body>` ganha `x-data="{ sidebarOpen: false }"` com Escape fechando e devolvendo o foco a `$refs.menuButton`.
      - Barra superior mobile `<header … lg:hidden>`: marca truncada, `data-testid="topbar-nova-solicitacao"` (`btn-primary`) quando houver destaque, e o botão `data-testid="menu-toggle"` com `aria-controls="sidebar"`, `aria-expanded` ligado ao estado, SVG e `sr-only` "Menu".
      - `<aside id="sidebar" data-open="false" x-bind:data-open="sidebarOpen" class="… hidden data-[open=true]:flex lg:sticky lg:top-0 lg:flex lg:h-screen …">`, com fundo `bg-surface` e borda direita. Nunca usar `hidden` estático junto com `:class` do Alpine. Um backdrop com a mesma técnica fecha a gaveta.
      - Dentro da aside: a marca (link para `home`), o botão "Fechar menu" (`lg:hidden`) e **um único** `<nav aria-label="Navegação principal">`. O item de destaque vem primeiro (`btn-primary`, `data-testid="sidebar-nova-solicitacao"`), depois os rótulos de grupo e os links `.sidebar-link` com `sidebar-link-active` e `aria-current="page"` via `request()->routeIs()`. Cada link fecha a gaveta ao ser clicado.
      - No rodapé da aside: nome, badge do papel e o form `POST logout` com `@csrf` e o botão "Sair".
      - Nada de `wire:click`, `{!! !!}` ou gradiente. O comentário do cabeçalho é atualizado. Desktop a partir de `lg`.
      Cobre: RF-01, RF-04, RF-05, RF-06, RF-07, RF-08, UI-01, UI-02, UI-03, RNF-03, RNF-04
      Acceptance criteria:
      - toda tela autenticada renderiza exatamente um `<aside id="sidebar">` contendo o único `aria-label="Navegação principal"`;
      - o `<header>` não tem `nav-link` nem `sidebar-link`;
      - "Sair" continua sendo `POST /logout` com CSRF;
      - `app.css` não tem mais `.nav-link` e o `ThemeTokensTest` migrado passa;
      - nenhuma rota ou middleware muda (T01 verde).
      Testes: T05 e T06 (e navegador em T18).

- [ ] T07 — Página inicial por papel: Suprimentos e Gestão aterrissam em Pedidos
      Arquivos: `routes/web.php` (só a closure `home`, `:46-53`), `tests/Feature/Auth/UnauthenticatedAccessTest.php` (dataset `:20-30`), `tests/Feature/Livewire/HomeLandingTest.php` (novo)
      Mudança: suprimentos → `suprimentos.pedidos.index` e gestao → `gestao.pedidos.index`. Obra e o 403 "Perfil de acesso não reconhecido." ficam iguais. O comentário é atualizado. Nenhum middleware muda.
      Cobre: RF-09, CT-01
      Acceptance criteria:
      - `/home` → 302 para `/obra/pedidos`, `/suprimentos/pedidos` e `/gestao/pedidos`; um usuário sem papel recebe 403 com a mensagem;
      - o login pelo `LoginForm` termina nessas telas com 200;
      - `suprimentos.visao-geral`, `suprimentos.kanban`, `gestao.dashboard` e `gestao.kanban` seguem com 200 para o papel;
      - o dataset atualizado tem o mesmo número de casos.
      Testes: `tests/Feature/Livewire/HomeLandingTest.php`, `tests/Feature/Auth/UnauthenticatedAccessTest.php`.

- [ ] T05 — Atualizar os testes que fixam a toolbar, "sem sidebar", a contagem de itens e a página inicial
      Arquivos: `tests/Feature/Livewire/LayoutIdentityTest.php`, `tests/Feature/Livewire/UsuariosIndexTest.php` (`:40-57`), `tests/Feature/Compliance/BrandIdentityComplianceTest.php` (`:128-140`), mais os testes Feature que falharem só pelo texto da sidebar (listados no log da fase)
      Mudança: atualizar, nunca apagar.
      - `LayoutIdentityTest`: a Gestão passa a ter os 6 links do CT-02; os casos de item ativo usam `sidebar-link-active`; `<aside` passa de "ausente" para exatamente 1, com `bg-surface` e sem `bg-primary`; badge, nome e "Sair" são procurados na aside; a lista de Suprimentos tem "+ Nova Solicitação" primeiro; o caso da home (`:164-168`) espera `suprimentos.pedidos.index`.
      - `UsuariosIndexTest:40` → 6 links, com "Usuários" depois de "Associações".
      - `BrandIdentityComplianceTest` (f) é reescrito no lugar: a asserção de pílula fica igual; o layout app tem exatamente 1 `<aside`, e o de auth e os demais arquivos têm 0.
      - Colisões de texto com a sidebar: reescopar para `<main>`.
      Cobre: RF-24, UI-04, RF-01, RF-03, RF-09
      Acceptance criteria: os 4 arquivos citados e a suíte Feature inteira estão verdes. O número de casos por arquivo não diminui e nenhuma asserção é removida ou afrouxada.
      Testes: `php artisan test --compact tests/Feature/Livewire/LayoutIdentityTest.php tests/Feature/Livewire/UsuariosIndexTest.php tests/Feature/Compliance/BrandIdentityComplianceTest.php tests/Feature/Design/ThemeTokensTest.php`, depois `--testsuite=Feature`.

- [ ] T06 — Testes da sidebar: itens por papel, estado ativo, landmark único e logout
      Arquivos: `tests/Feature/Livewire/SidebarNavigationTest.php` (novo)
      Mudança: só verificação, com os helpers `sidebarRegion()` e `sidebarLinks()`.
      Cobre: RF-01..RF-08, UI-01, UI-02, UI-03
      Acceptance criteria:
      - landmark único dentro de `#sidebar`, e nenhum `nav-link` na página;
      - os conjuntos por papel batem com o CT-02: Obra não vê Obras, Associações, Usuários, Dashboard nem Kanban; Gestão não tem "+ Nova Solicitação" em nenhum lugar do HTML nem "Visão Geral";
      - todo `href` da sidebar responde 200, e as URLs escondidas mantêm o 403 da rota (obra → `/gestao/usuarios`, `/obras`, `/suprimentos/pedidos`; suprimentos → `/gestao/usuarios`, `/gestao/dashboard`; gestao → `/suprimentos/nova-solicitacao`, `/obra/nova-solicitacao`);
      - em cada linha "Active when" do CT-02 e nas telas de detalhe e formulário há exatamente 1 `aria-current="page"` no item esperado (`gestao.pedidos.show` → Pedidos, não Dashboard);
      - a aside tem o form POST de logout com `_token`, `config('app.name')`, o nome e o papel;
      - um usuário sem papel tem 0 links, mas vê a marca e "Sair";
      - `menu-toggle` tem `aria-controls="sidebar"` e `aria-expanded="false"`;
      - a aside tem `bg-surface` e não tem `bg-primary`.
      Testes: `tests/Feature/Livewire/SidebarNavigationTest.php`.

## Phase 3: Período "Solicitado" e tabela compartilhada

Antes de implementar, leia:
1. `.spec/features/navegacao-sidebar-listagens/SPEC.md` — requisitos RIGID que esta fase cobre (RF-10..RF-13, RF-16..RF-19, UI-05, CT-03, CT-04, RNF-01)
2. `.spec/features/navegacao-sidebar-listagens/PLAN.md` — decomposição completa, dependências e riscos
3. `.spec/features/solicitacao-historico-finalizacao/PLAN.md` — tabela "Outbound contract surface" (CT-10 `RequestedPeriodFilter` e a API de `LocalTime`), que T08 estende sem recriar

T08 e T09 podem rodar em paralelo: T08 cria o enum e o trait e soma métodos a `RequestedPeriodFilter.php`; T09 edita a tabela e as linhas `with()`.

- [ ] T08 — Período "Solicitado": enum de presets, presets na classe única de período da fatia 2 e trait de componente
      Arquivos: `app/Enums/RequestedPeriodPreset.php` (novo), `app/Domain/Pedidos/RequestedPeriodFilter.php` (**alterado**, criado pela fatia 2 T38; nunca recriar nem rodar `make:class` para ele), `app/Livewire/Concerns/FiltersByRequestedPeriod.php` (novo), `tests/Unit/Enums/RequestedPeriodPresetTest.php` (novo), `tests/Unit/Domain/RequestedPeriodFilterTest.php` (da fatia 2; só adições)
      Mudança:
      - Enum `Hoje='hoje'`, `Ultimos3Dias='3d'`, `Ultimos7Dias='7d'`, `UltimoMes='mes'`, `Personalizado='personalizado'`, com `label()`, `NEUTRAL_LABEL = 'Qualquer data'`, `daysBack()` (0, 2, 6, 29, null) e `isRelative()`. O enum não calcula limite e não conhece fuso.
      - `RequestedPeriodFilter`, só adições (`COLUMN`, `utcBoundsForLocalRange()` e `applyLocalRange()` ficam idênticos):
        - `effectivePreset()`: valor desconhecido sem datas → neutro; valor vazio ou desconhecido com datas → Personalizado;
        - `localRangeFor(preset)`: De = `LocalTime::today()->subDays(daysBack)`, Até = `LocalTime::today()` (datas locais `Y-m-d`); Personalizado → `LogicException`;
        - `utcWindow(preset)` = `utcBoundsForLocalRange()` sobre esse intervalo local;
        - `apply(Builder, preset, from, to)`: neutro → nada; relativo → `applyLocalRange()` com o intervalo do preset; Personalizado → `applyLocalRange($query, $from ?: null, $to ?: null)`, ou seja, De/Até como dias locais de America/Sao_Paulo convertidos para UTC (F-01). Todo caminho termina em `applyLocalRange()`.
      - Nenhum `whereDate('requested_at', …)`, nenhum literal `'America/Sao_Paulo'`; `app.timezone` continua UTC.
      - Trait: `normalizeRequestedPeriod()` para o `mount()` (lê só propriedades; `#[Url]` já está hidratado, `LivewireServiceProvider.php:188,210`), `updatedRequestedPreset()`, `showsCustomRequestedPeriod()`, `requestedPeriodIsActive()` e `applyRequestedPeriod()`, que só delega para `RequestedPeriodFilter::apply()`.
      Cobre: RF-16, RF-17, RF-18, RF-19, CT-03, CT-04
      Acceptance criteria:
      - com o relógio em 22/09/2026 12:00 de São Paulo, `utcWindow`: Hoje = [`2026-09-22 03:00Z`, `2026-09-23 03:00Z`); 3d começa em `09-20 03:00Z`; 7d em `09-16 03:00Z`; mês em `08-24 03:00Z`; o fim é `09-23 03:00Z` nos quatro;
      - `2026-09-22T02:30Z` fica fora de Hoje nesse relógio e dentro de Hoje com o relógio em 21/09 20:00 local;
      - `effectivePreset`: `('xyz','','')` → null; `('hoje','2020-01-01','')` → Hoje; `('','2026-06-01','2026-06-30')` → Personalizado; `('xyz','2026-06-01','')` → Personalizado;
      - `apply(…, 'personalizado', '2026-09-22', '2026-09-22')->toRawSql()` é igual ao de `applyLocalRange(…, '2026-09-22', '2026-09-22')` e contém `2026-09-22 03:00:00` e `2026-09-23 03:00:00`;
      - `localRangeFor(Personalizado)` lança `LogicException`; `app.timezone` continua `UTC`;
      - os casos da fatia 2 em `RequestedPeriodFilterTest` e o `RequestedPeriodSingleDefinitionTest` passam sem nenhuma alteração.
      Testes: `tests/Unit/Enums/RequestedPeriodPresetTest.php`, `tests/Unit/Domain/RequestedPeriodFilterTest.php`, `tests/Feature/Compliance/RequestedPeriodSingleDefinitionTest.php`.

- [ ] T09 — Tabela compartilhada: Solicitante / Obra, Descrição, Preciso para e Previsão = Data prevista
      Arquivos: `resources/views/components/pedido-table.blade.php`, a lista `with([...])` de `app/Livewire/Obra/Acompanhamento.php`, `app/Livewire/Suprimentos/TodosPedidos.php` e `app/Livewire/Gestao/TodosPedidos.php` (+ `requester`), `tests/Feature/Livewire/PedidoTableColumnsTest.php`, `tests/Feature/Livewire/PedidoTableIdentificationTest.php` (novo)
      Mudança:
      - Colunas: `['Código', 'Solicitante / Obra', 'Descrição', 'Solicitado em', 'Preciso para', 'Status', 'Prioridade', 'Responsável', 'Previsão', 'Atraso']`.
      - Identificação na célula e no card: `{{ requester->name }} / {{ obraLabel() }}` (`data-field="identificacao"`).
      - Descrição mantém `data-field="items"`, o truncamento e o `title`; o card ganha o rótulo "Descrição".
      - "Solicitado em" usa `LocalTime::formatDate($pedido->requested_at)`, o mesmo dia local dos presets e do Personalizado.
      - Previsão = `dataPrevistaLabel()`, sem `expected_delivery_at`.
      Cobre: RF-10, RF-11, RF-12, RF-13, RF-18, UI-05, RNF-01
      Acceptance criteria:
      - nas 3 listagens, na tabela e no card, aparece "João Silva / Residencial Aurora" uma vez por linha e uma vez por card; "Outra" aparece com e sem referência; o nome de um solicitante inativo é exibido;
      - "Descrição" e "Preciso para" estão presentes, sem `<th>Itens</th>` nem "Data necessária";
      - um pedido de 21/09/2026 12:00 mostra "24/09/2026" com `expected_delivery_at` nulo ou 30/09, e 30/09 nunca aparece;
      - um pedido "Outra" com referência, um sem e um Finalizado → 200, com todas as linhas e "Finalizado" no select de status;
      - cabeçalhos = células = `colspan`;
      - `2026-03-08 01:30Z` é exibido como 07/03/2026 e `2026-09-22T02:30Z` como 21/09/2026;
      - o arquivo da tabela não contém `expected_delivery_at`.
      Testes: `tests/Feature/Livewire/PedidoTableColumnsTest.php`, `tests/Feature/Livewire/PedidoTableIdentificationTest.php`.

## Phase 4: Consultas das três listagens

Antes de implementar, leia:
1. `.spec/features/navegacao-sidebar-listagens/SPEC.md` — requisitos RIGID que esta fase cobre (RF-14..RF-23, CT-03)
2. `.spec/features/navegacao-sidebar-listagens/PLAN.md` — decomposição completa, dependências e riscos

As três tarefas podem rodar em paralelo: cada uma é dona de um componente e de um arquivo de teste novo. Nenhum componente calcula limite de período: tudo passa por `applyRequestedPeriod()` → `RequestedPeriodFilter`.

- [ ] T10 — Listagem de Suprimentos: `visibleTo`, ordem ascendente, período e obras ativas
      Arquivos: `app/Livewire/Suprimentos/TodosPedidos.php`, `tests/Feature/Livewire/SuprimentosListingBehaviourTest.php` (novo)
      Mudança:
      - Usar o trait. Adicionar `#[Url(as: 'solicitado', except: '')] $requestedPreset` e `#[Url(as: 'obrasAtivas', except: false)] $activeObrasOnly`.
      - O `mount()` normaliza o período.
      - `filteredQuery()` abre com `Pedido::query()->visibleTo(Auth::user())->with([...])`. A chamada `RequestedPeriodFilter::applyLocalRange(...)` que a fatia 2 T38 deixou no lugar do antigo `:178-184` vira `$this->applyRequestedPeriod($query)`. O par `whereDate` de `needed_at` (coluna `date`) fica igual. Obras ativas = `where(fn … whereNull('obra_id')->orWhereHas('obra', fn … ->active()))`, agrupado, mantendo "Outra".
      - `pedidos()` → `orderBy('requested_at')->orderBy('id')`.
      - `limparFiltros()` zera as duas novas. Adicionar `activeFilterCount()` e `moreFiltersActiveCount()`.
      Cobre: RF-14, RF-16, RF-17, RF-18, RF-19, RF-20, RF-21, RF-22, RF-23, CT-03
      Acceptance criteria:
      - datas 09-01, 09-03, 09-02 → 09-01, 09-02, 09-03, também com 09-01 Entregue; 12 pedidos com o mesmo `requested_at` → página 1 com os 10 menores ids, página 2 com os outros 2, sem repetição e em ordem estável;
      - os presets devolvem exatamente os conjuntos do RF-16 e o total dos indicadores acompanha; um preset volta para a página 1; `2026-09-22T02:30Z` fica fora de Hoje em 22/09 local e dentro em 21/09 local;
      - Personalizado (F-01): esse mesmo pedido fica fora com De = Até = 2026-09-22 e dentro com 2026-09-21, e a linha mostra "21/09/2026" em Solicitado em;
      - escolher 7d apaga `requestedFrom`; `?solicitado=xyz` → 200 sem filtro; `?solicitado=hoje&requestedFrom=2020-01-01` → só os de hoje;
      - obras ativas ligado lista A, B e Outra (sem C); desligado lista os 4; os indicadores acompanham; `obraId=C` ligado → vazio;
      - ligar e desligar não altera contagens nem `max(updated_at)` de `pedidos`, `pedido_events`, `obras` e `obra_profile`;
      - para suprimentos o conjunto é idêntico ao da consulta de referência sem `visibleTo`;
      - `?statusId&atrasado=true` → contador 2.
      Testes: `tests/Feature/Livewire/SuprimentosListingBehaviourTest.php`.

- [ ] T11 — Listagem da Gestão: `visibleTo`, período (drill-down = Personalizado) e obras ativas
      Arquivos: `app/Livewire/Gestao/TodosPedidos.php`, `tests/Feature/Livewire/GestaoListingBehaviourTest.php` (novo)
      Mudança: as mesmas mudanças de T10 (a chamada `applyLocalRange` da fatia 2 no antigo `:171-177` vira `applyRequestedPeriod`), exceto a ordem, que continua `latest('requested_at')`. `pendente` e `entregue` ficam iguais e contam como filtros ativos. Nenhum `wire:click`.
      Cobre: RF-15, RF-16, RF-17, RF-18, RF-19, RF-20, RF-21, RF-22, RF-23, CT-03
      Acceptance criteria:
      - `/gestao/pedidos?requestedFrom=2026-06-01&requestedTo=2026-06-30&atrasado=true` → `requestedPreset === 'personalizado'`, as datas são mantidas e as linhas batem com a consulta de referência `RequestedPeriodFilter::applyLocalRange(Pedido::query(), '2026-06-01', '2026-06-30')` + atraso (dias locais, nunca `whereDate`);
      - `DashboardDrillDownTest` (com o caso de fronteira local da fatia 2 T38) passa sem nenhuma alteração;
      - `2026-09-22T02:30Z` fica fora com De = Até = 2026-09-22 e dentro com 2026-09-21, tanto escolhido no controle quanto vindo da URL;
      - o RF-19 e o RF-20 se comportam como em T10; um preset volta para a página 1; o mais recente continua primeiro;
      - `GestaoKanbanReadOnlyTest` continua verde.
      Testes: `tests/Feature/Livewire/GestaoListingBehaviourTest.php`, `tests/Feature/Livewire/DashboardDrillDownTest.php`.

- [ ] T12 — Acompanhamento: novo eixo "Solicitado", sempre depois de `visibleTo`
      Arquivos: `app/Livewire/Obra/Acompanhamento.php`, `tests/Feature/Livewire/AcompanhamentoSolicitadoTest.php` (novo)
      Mudança:
      - Usar o trait. Adicionar `requestedPreset` (`as: 'solicitado'`), `requestedFrom` e `requestedTo` (`except: ''`), com os mesmos nomes das outras listagens.
      - O `mount()` normaliza; `applyRequestedPeriod()` roda depois da instrução `visibleTo` de `:78`, que não muda. Nenhum limite de período é montado no componente.
      - `limparFiltros()` zera os 3. `activeFilterCount()` conta busca, obra, status, atraso e Solicitado.
      - Sem obras ativas e sem mudança de ordem.
      Cobre: RF-15, RF-16, RF-17, RF-18, RF-19, RF-22, RF-23, CT-03
      Acceptance criteria: os presets só listam os pedidos visíveis ao usuário, na janela certa. Um `obraId` forjado de outra obra com qualquer preset → 0 linhas. `?obrasAtivas=true` não muda nada. Limpar remove os parâmetros de Solicitado. Um preset volta para a página 1, e `?statusId&atrasado=true` → contador 2. O pedido do usuário em `2026-09-22T02:30Z` fica fora com `?requestedFrom=2026-09-22&requestedTo=2026-09-22` e dentro com 2026-09-21.
      Testes: `tests/Feature/Livewire/AcompanhamentoSolicitadoTest.php`.

## Phase 5: Filtros compactos nas três listagens

Antes de implementar, leia:
1. `.spec/features/navegacao-sidebar-listagens/SPEC.md` — requisitos RIGID que esta fase cobre (RF-11, RF-15, RF-20, RF-22, UI-06, UI-07, UI-08, RNF-04, RNF-05)
2. `.spec/features/navegacao-sidebar-listagens/PLAN.md` — decomposição completa, dependências e riscos

T13 vem primeiro. Depois, T14 e T15 podem rodar em paralelo (views e arquivos de teste disjuntos). As linhas `<select id="…" wire:model.live="…" class="form-control">` ficam byte-idênticas ao contrato atual.

- [ ] T13 — Componentes Blade do painel de filtros, do controle "Solicitado" e de "Somente obras ativas"
      Arquivos: `resources/views/components/filter-panel.blade.php`, `resources/views/components/solicitado-filter.blade.php`, `resources/views/components/active-obras-filter.blade.php` (novos), `tests/Feature/Livewire/FilterPanelComponentsTest.php` (novo)
      Mudança:
      - `x-filter-panel` (props `activeCount` e `moreActiveCount`; slots `primary`, `secondary` e `more`) renderiza `<form wire:submit.prevent aria-label="Filtros" class="filter-panel card …" x-data="{ filtersOpen: false, moreOpen: false }">`.
      - Botão mobile `data-testid="filtros-toggle"` (`lg:hidden`, `aria-controls="filtros-painel"`, `aria-expanded` ligado ao estado) com o texto exato `Filtros` ou `Filtros (N)`.
      - Painel `#filtros-painel` com `data-open` e `hidden data-[open=true]:flex lg:flex`. Linha 1 = `primary`; linha 2 = `secondary` + "Mais filtros (N)" (só desktop). O bloco `more` fica sempre visível dentro do painel mobile aberto e, no desktop, só quando aberto.
      - O estado fica no Alpine, nunca em `<details>` (o morph do Livewire removeria o `open`). Nenhum `wire:click`.
      - `x-solicitado-filter`: `<select id="requestedPreset" wire:model.live="requestedPreset" class="form-control">` com "Qualquer data" + os 5 presets em ordem; De e Até (`requestedFrom`, `requestedTo`, com `label[for]`) só com `showCustom`.
      - `x-active-obras-filter`: checkbox `activeObrasOnly` com o rótulo exato "Somente obras ativas" e a ajuda `Oculta pedidos de obras concluídas; pedidos "Outra" (sem obra) continuam listados.` ligada por `aria-describedby`.
      Cobre: RF-15, RF-22, UI-06, UI-07, UI-08, RNF-04, RNF-05
      Acceptance criteria:
      - o botão diz `Filtros` com 0 e `Filtros (2)` com 2, com `aria-expanded="false"` e `aria-controls="filtros-painel"`, e não há `wire:click`;
      - o botão "Mais filtros" só existe quando `moreActiveCount` não é null;
      - o select tem 6 opções na ordem `'', hoje, 3d, 7d, mes, personalizado` com os rótulos exatos;
      - De e Até só aparecem com `showCustom`;
      - o rótulo de obras ativas é exato e a ajuda cita "concluídas" e "Outra".
      Testes: `tests/Feature/Livewire/FilterPanelComponentsTest.php`.

- [ ] T14 — Filtros compactos nas listagens de Suprimentos e Gestão
      Arquivos: `resources/views/livewire/suprimentos/todos-pedidos.blade.php`, `resources/views/livewire/gestao/todos-pedidos.blade.php`, `tests/Feature/Livewire/TodosPedidosFiltersTest.php` (só adições)
      Mudança: o form em grade (`:33-123`) vira `<x-filter-panel>`.
      - `primary`, nesta ordem: Obra, Status, Prioridade, `x-solicitado-filter`, Responsável e "Limpar filtros" (via `$wire`, `data-testid="limpar-filtros"`), cada select em `lg:w-36` com `form-label` visível.
      - `secondary`: Busca, com o placeholder "Código, obra ou descrição".
      - `more`: "Somente com atraso", `x-active-obras-filter` e o fieldset De/Até da data de necessidade, com a legenda **"Preciso para"** (antes "Data necessária", F-08). Ids `neededAtFrom`/`neededAtTo` e nomes `#[Url]` não mudam.
      - Sai o fieldset antigo "Solicitado A partir de/Até". Os indicadores de Suprimentos mantêm números, links e markup; só a legenda do card de atrasados (`suprimentos/todos-pedidos.blade.php:29`, "data necessária vencida e não entregues") vira "Preciso para vencido e não concluídos" (N-06, só texto). A Gestão continua sem `wire:click`.
      Cobre: RF-11, RF-15, RF-20, RF-22, UI-06, UI-07, UI-08, RNF-05
      Acceptance criteria:
      - no carregamento não existe `id="requestedFrom"`; com Personalizado aparecem os dois campos, e com Hoje somem;
      - a legenda do fieldset de necessidade é exatamente "Preciso para", e a listagem renderizada não contém "data necessária" em nenhuma caixa (`mb_stripos`);
      - em Suprimentos, a legenda do card de atrasados contém "Preciso para vencido e não concluídos";
      - o rótulo e a ajuda de obras ativas são idênticos nas duas listagens;
      - todo controle tem `label[for]` ou `aria-label`;
      - `?statusId&atrasado=true` → `Filtros (2)`, e a Gestão não tem `wire:click`;
      - todos os casos já existentes passam sem mudança, inclusive o contrato de markup `:507-530`, o intervalo requested (`:91-105`), os casos de fronteira local da fatia 2 T38 e os nomes legados.
      Testes: `tests/Feature/Livewire/TodosPedidosFiltersTest.php`.

- [ ] T15 — Filtros compactos no Acompanhamento
      Arquivos: `resources/views/livewire/obra/acompanhamento.blade.php`, `tests/Feature/Livewire/AcompanhamentoTest.php` (só adições)
      Mudança: remover o botão "+ Nova Solicitação" da página (`:7`), que agora fica na sidebar e na barra superior. Usar `<x-filter-panel :more-active-count="null">`: `primary` com Obra, Status, `x-solicitado-filter` e Limpar; `secondary` com Busca ("Código, obra ou descrição") e "Somente com atraso". O contrato de markup UI-02 fica byte-idêntico.
      Cobre: RF-11, RF-15, RF-22, UI-06, UI-07
      Acceptance criteria: o placeholder não contém "itens" e De/Até só aparecem com Personalizado. `Filtros (2)` aparece com status e atraso. Não há controle `activeObrasOnly`, `priorityId` nem `responsibleId`, e `<main>` não tem link para `obra.nova-solicitacao`. Os casos existentes passam sem mudança.
      Testes: `tests/Feature/Livewire/AcompanhamentoTest.php`.

## Phase 6: Desempenho, conformidade, fluxo §45 e navegador da fatia

Antes de implementar, leia:
1. `.spec/features/navegacao-sidebar-listagens/SPEC.md` — requisitos RIGID que esta fase cobre (RF-05, RF-07, RF-09, RF-11, RF-12, RF-17, RF-18, RF-20, RF-23, RF-24, RF-25, UI-01..UI-07, RNF-01, RNF-02, RNF-04)
2. `.spec/features/navegacao-sidebar-listagens/PLAN.md` — decomposição completa, dependências e riscos

T16, T17, T18 e T23 podem rodar em paralelo (arquivos disjuntos). T19 vem depois de T18, porque usa os helpers movidos para `tests/Pest.php` e edita `ResponsiveIdentityTest`. Rodar `npm run build` antes do navegador, com um processo Pest por vez.

- [ ] T16 — Orçamento de consultas: listagens com sidebar, 1 vs 10 pedidos por papel
      Arquivos: `tests/Feature/Performance/QueryCountTest.php` (só adições)
      Mudança: três casos, um por papel, com GET da página inteira (layout + sidebar + componente) para 1 e para 10 pedidos, com solicitantes distintos, obras misturadas e um "Outra". Repetir com `?solicitado=7d&obrasAtivas=true` (Obra: `?solicitado=7d`). Se a contagem crescer, corrigir o eager load em T09–T12, nunca afrouxar o teste.
      Cobre: RNF-01
      Acceptance criteria: a contagem de consultas é idêntica entre 1 e 10 pedidos para cada papel, com e sem filtros.
      Testes: `tests/Feature/Performance/QueryCountTest.php`.

- [ ] T17 — Varreduras de conformidade da fatia
      Arquivos: `tests/Feature/Compliance/FilterUrlStateComplianceTest.php`, `tests/Feature/Compliance/ObraVisibleToGuardTest.php` (um caso novo), `tests/Feature/Compliance/LocalTimeDisplayComplianceTest.php` (da fatia 2; só a lista de caminhos), `tests/Feature/Compliance/NavigationListingComplianceTest.php` (novo)
      Mudança:
      - O mapa de nomes ganha `requestedPreset` → `solicitado` e `activeObrasOnly` → `obrasAtivas` (Suprimentos/Gestão), e `requestedPreset`, `requestedFrom` e `requestedTo` (Acompanhamento). O glob inclui `app/Livewire/Concerns/*.php`.
      - Novo caso de `visibleTo` nas duas `TodosPedidos`, com o mesmo token walker.
      - `LocalTimeDisplayComplianceTest`: sai a exclusão de `components/pedido-table.blade.php` que a fatia 2 deixou para esta fatia.
      - Novo teste verifica:
        - a tabela sem `expected_delivery_at`, `>Itens<` e "Data necessária";
        - o layout sem `match (`, `nav-link`, `wire:click` e `{!!`;
        - as `TodosPedidos` sem `ObraStatus::`, sem `'concluido'` e sem `whereHas('obra'` sem `whereNull('obra_id')` na mesma instrução;
        - classe única de período (F-01): Acompanhamento, as duas `TodosPedidos` e o trait sem `whereDate('requested_at'`, `where('requested_at'`, `localDayStartUtc`, `utcBoundsForLocalRange` e `America/Sao_Paulo`; os componentes só usam `applyRequestedPeriod` e o trait só `RequestedPeriodFilter::apply`; o enum não referencia `LocalTime` nem `Carbon`; o único `*PeriodFilter.php` em `app/` é `app/Domain/Pedidos/RequestedPeriodFilter.php`;
        - nenhuma view de listagem (`livewire/{obra,suprimentos,gestao}/*todos-pedidos*` e `acompanhamento`) com "A partir de" ou "data necessária", comparando **sem distinção de caixa** (`mb_stripos` ou regex `/…/iu`), para que a legenda minúscula do KPI de Suprimentos não escape (N-06).
      - `RequestedPeriodSingleDefinitionTest` (fatia 2) não é editado.
      Cobre: RF-11, RF-12, RF-17, RF-18, RF-20, RF-23, RNF-04
      Acceptance criteria: os quatro arquivos e o `RequestedPeriodSingleDefinitionTest` inalterado estão verdes. Os casos existentes continuam e o número de casos não diminui.
      Testes: os quatro arquivos acima e `tests/Feature/Compliance/RequestedPeriodSingleDefinitionTest.php`.

- [ ] T18 — Navegador: sidebar e filtros compactos (testes novos)
      Arquivos: `tests/Browser/SidebarNavigationTest.php`, `tests/Browser/ListingFiltersLayoutTest.php` (novos), `tests/Pest.php` (helpers de navegador), `tests/Browser/ResponsiveIdentityTest.php` (só mover `RESPONSIVE_AUDIT_SCRIPT` e `assertResponsiveAndAccessible()` para `tests/Pest.php`, sem mudar a lógica)
      Mudança: criar os helpers `openSidebarIfCollapsed()` e `logoutThroughSidebar()`. Seguir os gotchas do pest-browser (URLs absolutas, esperar o `wire:model` antes de digitar).
      Cobre: UI-01, UI-02, UI-03, UI-06, UI-07, RF-05, RF-07, RNF-02
      Acceptance criteria:
      - 1440×900:
        - o nav fica visível sem interação e o `menu-toggle` fica oculto;
        - o fundo de `#sidebar` é `rgb(255, 255, 255)`;
        - em `/suprimentos/pedidos` rolado até o fim, o `sidebar-nova-solicitacao` intersecta o viewport e abre o formulário;
        - "Sair" leva a `/login`.
      - 390×844:
        - o nav fica oculto e o `topbar-nova-solicitacao` fica visível e funciona;
        - Enter no Menu → `aria-expanded="true"`; Escape → `"false"` com o foco no botão;
        - "Fechar menu" devolve o foco; seguir um link fecha a gaveta;
        - "Sair" é alcançável.
      - `scrollWidth ≤ clientWidth` nos 3 viewports, com o menu aberto e fechado, e o anel de foco dos links é visível.
      - Filtros em 1280×800 e 1440×900:
        - os controles principais ficam na mesma linha (±4 px);
        - no máximo 2 linhas com a disclosure fechada;
        - "Mais filtros" continua aberto depois do re-render e mostra `(1)`;
        - Personalizado mostra De/Até, e Hoje os remove.
      - Filtros em 390×844:
        - nenhum select visível no carregamento;
        - `Filtros (2)` com `?statusId&atrasado=true`;
        - com o painel aberto, controles ≥ 44 px;
        - sem overflow, com o painel aberto ou fechado;
        - o topo do primeiro `pedido-card` fica abaixo de 844 px com o painel fechado.
      Testes: `vendor/bin/pest tests/Browser/SidebarNavigationTest.php tests/Browser/ListingFiltersLayoutTest.php`.

- [ ] T23 — Fluxo ponta a ponta do §45 (Feature): Novo Cadastro → associação → pedido na obra associada
      Arquivos: `tests/Feature/Livewire/MasterPlanFlowTest.php` (novo)
      Mudança: só verificação (RF-25, F-16). Um teste Feature (não Browser) com dataset do papel que associa (`suprimentos`, `gestao`). Factories só para os atores pré-existentes, obra A (Em andamento) e lookups (`seedWorkflowStatuses()`, `seedHistoryEventTypes()`). Cada passo do usuário novo passa pelas rotas, componentes e Actions reais, nunca por escrita direta em modelo:
      1. Novo Cadastro: GET `route('register')` → 200; `Livewire::test(Auth\Register::class)` com nome "Ana Obra", e-mail e senha → redirect `home`; 1 usuário novo, papel `obra`, 0 linhas em `obra_profile`.
      2. Estado vazio: GET `route('obra.nova-solicitacao')` mostra `CreatePedidoAction::noActiveObraMessage($user)` e nenhum `<form` em `<main>`. Criação forjada para a obra A por `Livewire::test(Pedidos\NovaSolicitacao::class)` → erro em `obra_id`, 0 pedidos e sequência `pedido_code_sequence` inalterada.
      3. Associação: como o papel do dataset, `Livewire::test(Associacoes\Index::class)->set("selectedObraIds.{id}", [A])->call('attach', id)` → sem erros e exatamente a linha (A, usuário novo).
      4. Logout; login pelo `LoginForm`; `/home` → 302 `/obra/pedidos`; o `href` de `[data-testid="sidebar-nova-solicitacao"]` é `route('obra.nova-solicitacao')` e responde 200 com o formulário; `submit` com `obra_selection` = A, descrição e "Preciso para" → sem erros e `code` preenchido.
      5. GET `route('obra.pedidos.index')` → 200 com o código e "Ana Obra / <nome da obra A>".
      Cobre: RF-25, RF-07, RF-09, RF-10
      Acceptance criteria: o teste existe em `tests/Feature/`, passa nas duas linhas do dataset e afirma exatamente 1 pedido com `requester_id` = usuário novo e `obra_id` = A, exatamente 1 evento `criacao_pedido` com ator = usuário novo, e a linha no Acompanhamento. Antes do passo 3, a criação forjada falha em `obra_id` sem gravar nada.
      Testes: `php artisan test --compact tests/Feature/Livewire/MasterPlanFlowTest.php`.

- [ ] T19 — Testes de navegador existentes: novas páginas iniciais e logout pela sidebar
      Arquivos: `tests/Browser/DemoRoteiroTest.php` (`:83-90`, `:135-140`, passo 16), `tests/Browser/ResponsiveIdentityTest.php` (caso das listagens `:251-285`), `tests/Browser/AuthRecoveryAndUsersTest.php` (`:56-59`, `:107`), `tests/Browser/ObraInvitationFlowTest.php` (fatia 1) e `tests/Browser/SolicitacaoFinalizacaoFlowTest.php` (fatia 2, inclusive o passo (5) que abria `/suprimentos/nova-solicitacao` pela toolbar), só as linhas de navegação
      Mudança:
      - Suprimentos passa a aterrissar em `/suprimentos/pedidos` e depois vai para `/suprimentos/kanban`; Gestão aterrissa em `/gestao/pedidos` e depois vai para `/gestao/dashboard`.
      - O logout usa `logoutThroughSidebar()`, e os cliques de navegação ficam escopados em `#sidebar`. O passo (5) da fatia 2 abre a Nova Solicitação por `[data-testid="sidebar-nova-solicitacao"]`.
      - Seletores primários: `topbar-`/`sidebar-nova-solicitacao` e `filtros-toggle`/`#obraId`, conforme a largura.
      - Nenhuma asserção de negócio muda e nenhum caso sai.
      Cobre: RF-09, RF-05, RF-24
      Acceptance criteria: `vendor/bin/pest tests/Browser` está verde, os casos por arquivo não diminuem e os comentários de `:83` e `:135` estão atualizados.
      Testes: `vendor/bin/pest tests/Browser`.

## Phase 7: Passe responsivo transversal e regressão final do incremento

Antes de implementar, leia:
1. `.spec/features/navegacao-sidebar-listagens/SPEC.md` — requisitos RIGID que esta fase cobre (RNF-01..RNF-06, RF-07, RF-18, RF-24, RF-25, UI-01, UI-07)
2. `.spec/features/navegacao-sidebar-listagens/PLAN.md` — decomposição completa, dependências e riscos

Esta fase fecha o código do incremento das três fatias. T22 é a última tarefa. Um processo Pest por vez, e `npm run build` antes das suítes.

- [ ] T21 — Passe responsivo transversal (§41): telas das três fatias dentro do novo layout
      Arquivos: `tests/Browser/ResponsiveIdentityTest.php` (só adições). Correções, se necessárias, só na view que falhar, com classes literais.
      Mudança: auditorias com `assertResponsiveAndAccessible` em 1440×900, 820×1180 e 390×844, com um seletor primário documentado por tela:
      - a sidebar por papel, fechada e aberta no mobile;
      - as três listagens com `?solicitado=personalizado` e o painel aberto no mobile;
      - fatia 1: `/obras`, `/obras/{obra}/editar` com Convites e `/associacoes`, como gestao e como suprimentos;
      - fatia 2: Nova Solicitação de obra e de suprimentos com "Outra" e 2 arquivos, e os três detalhes com histórico, anexos, observação, Marcar como entregue, romaneio e Finalizar;
      - telas anteriores: Kanbans, Dashboard, Visão Geral, lista e formulário de Usuários;
      - auth (login, esqueci-senha, `/cadastro`, `/convite`): re-executar os casos existentes, sem duplicar.
      Cobre: RNF-02, UI-01, UI-07, RF-07
      Acceptance criteria: sem overflow horizontal, com o controle primário dentro do viewport, todo controle rotulado e anel de foco ≥ 2 px em todas as telas acima, nos 3 viewports. Nenhuma auditoria é afrouxada, e cada correção fica listada no log da fase.
      Testes: `vendor/bin/pest tests/Browser/ResponsiveIdentityTest.php`.

- [ ] T22 — Regressão final do incremento (fatias 1 + 2 + 3): Pint, build, suítes completas e gates
      Arquivos: nenhum (só verificação; correções só em arquivos de tarefas anteriores desta fatia)
      Mudança: rodar nesta ordem, um processo Pest por vez em `127.0.0.1:5434`:
      - `vendor/bin/pint --dirty --format agent` e depois `npm run build`;
      - `php artisan test --compact --testsuite=Unit`, depois `--testsuite=Feature` (inclui o fluxo §45 de T23) e depois `vendor/bin/pest tests/Browser` (suíte inteira, inclusive fatias 1 e 2);
      - `git diff --stat <base>..HEAD` em `composer.json`, `composer.lock`, `package.json` e `package-lock.json` → vazio;
      - `git diff --name-only --diff-filter=A <base>..HEAD -- database/migrations` → vazio, e `migrate` depois de `migrate:fresh` → "Nothing to migrate";
      - classe única de período: `git diff --name-status <base>..HEAD -- app/Domain/Pedidos/RequestedPeriodFilter.php` → `M` (nunca `A`); `grep -rn "whereDate('requested_at'" app/` → vazio; `find app -name '*PeriodFilter.php'` → só `app/Domain/Pedidos/RequestedPeriodFilter.php`;
      - `git diff --name-only --diff-filter=D <base>..HEAD -- tests` → vazio, e em cada teste tocado a contagem de `^(test|it)\(` no `<base>` é ≤ à do HEAD;
      - `migrate:fresh --seed` duas vezes, `demo:reset --force` e `db:seed` com exit 0;
      - entregar ao desenvolvedor o checklist manual do incremento, a ser usado **depois** do commit documental de T20 e **antes** de T24: 3 usuários demo em desktop e celular, sidebar, homes, "+ Nova Solicitação", presets, Personalizado perto da meia-noite local, obras ativas, drill-down → Personalizado com a mesma contagem do KPI e o fluxo §45 à mão; lembrar a decisão F-01 (confirmada como D-5, reversível): "hoje", atraso, `entreguesHoje` e períodos viram à meia-noite local, não mais às 21:00 de Brasília.
      - T22 é a regressão incremental do código, antes do commit documental; não substitui T24, que repete o gate no HEAD final.
      Cobre: RF-18, RF-24, RF-25, RNF-01, RNF-02, RNF-03, RNF-04, RNF-05, RNF-06
      Acceptance criteria: 0 falhas em Unit, Feature e Browser. Pint limpo e build com exit 0. Nenhuma dependência nova, nenhuma migration nova, `RequestedPeriodFilter.php` só modificado, nenhum `whereDate('requested_at'` em `app/`, nenhum teste apagado e nenhuma contagem de casos menor. Seeds e reset com exit 0.
      Testes: as três suítes completas.

## Phase 8: Documentação (commit somente de documentação)

Antes de implementar, leia:
1. `.spec/features/navegacao-sidebar-listagens/SPEC.md` — requisitos RIGID que esta fase cobre (RNF-05, RF-24, CT-03)
2. `.spec/features/navegacao-sidebar-listagens/PLAN.md` — decomposição completa, dependências e riscos
3. `.spec/features/obras-associacoes-cadastro-convites/SPEC.md` e `.spec/features/solicitacao-historico-finalizacao/SPEC.md` — fonte de cada frase nova do guia de onboarding (N-01)

Pré-condições: T22 verde e G-1 (`git status --short docs/agents` vazio antes de rodar `/ai-context`). O diff desta fase fica restrito a `README.md`, `CLAUDE.md`, `docs/onboarding-albuquerque.md`, `tests/Feature/Compliance/DocumentationParityTest.php` (só as asserções de onboarding), `tests/README.md` e `docs/agents/*.md`. A fase fecha com toda `tests/Feature/Compliance` verde. Se o Ralph headless não conseguir invocar `/ai-context`, a regeneração é passo do desenvolvedor, num commit documental separado.

- [ ] T20 — Documentação: README, CLAUDE.md, guia de onboarding e `/ai-context` (fase própria, somente documentação, depois de T22)
      Arquivos: `README.md` (`:14-15`, `:167-168`, `:194-196`), `CLAUDE.md` (manual), `docs/onboarding-albuquerque.md` (manual), `tests/Feature/Compliance/DocumentationParityTest.php` (só asserções de onboarding), `tests/README.md`, `docs/agents/*.md` (regenerados)
      Mudança:
      - README: novas páginas iniciais e sidebar no roteiro.
      - CLAUDE.md §4: homes, sidebar por papel, ordem de Suprimentos, presets, obras ativas e o teste de fluxo §45 (`MasterPlanFlowTest`).
      - CLAUDE.md §5: a sidebar não é camada de autorização, e `visibleTo` abre as três listagens.
      - CLAUDE.md §8: a regra da sidebar (`SidebarNavigation`, habilidades = `can:` da rota), `x-filter-panel` com Alpine em vez de `<details>`, o padrão `data-open` com variantes literais, e a regra "Solicitado": presets e Personalizado em dias locais de America/Sao_Paulo, todos os limites vindos da única `RequestedPeriodFilter` compartilhada com o Dashboard (nunca `whereDate('requested_at', …)`), com os parâmetros `solicitado` e `obrasAtivas`.
      - `docs/onboarding-albuquerque.md` → produto pós-incremento (N-01), cada frase confirmada nas três SPECs e no código do HEAD, nada inventado:
        - "O que o sistema faz" (`:17`): anexos e observações saem da lista de fora do escopo;
        - "Os seis status" (`:38`): entra Finalizado (terminal, depois de Entregue, exige romaneio);
        - "Os três perfis" (`:59-75`): "menu do topo" vira sidebar (rótulos CT-02); "ninguém se cadastra sozinho" vira Novo Cadastro (`/cadastro`, papel obra, zero obras) e convites de obra; Suprimentos cria solicitações e cuida de Obras/Associações; Obra marca Entregue e registra observação; página inicial: Obra → Acompanhamento, Suprimentos e Gestão → Pedidos;
        - "Acesso" (`:77-103`): obra e suprimentos com 0..N obras; convites de obra;
        - "Guia do perfil Obra" (`:105-127`): "+ Nova Solicitação" na sidebar/barra superior (`:111`), campos da fatia 2 (Obra com "Outra" + Referência, Preciso para, Descrição, anexos), colunas do Acompanhamento (Solicitante / Obra, Descrição, Preciso para, Previsão = Data prevista) e filtro "Solicitado";
        - "Guia do perfil Suprimentos" (`:129-183`): Kanban com a coluna Finalizado (reescrever "Cinco colunas", `:143`) e "Preciso para" no card; "Todos os Pedidos" vira "Pedidos" (mais antigo → mais novo, filtros compactos, presets, "Somente obras ativas"); Nova Solicitação por Suprimentos; romaneio e Finalizar; Obras e Associações;
        - "Guia do perfil Gestão" (`:185-227`): legendas "Preciso para" no Dashboard; Obras e Associações; a regra de Usuários de `:220` vira a regra da fatia 1; "O que a Gestão não faz" (`:224-226`) perde "Cadastrar obras";
        - "Limites conhecidos" (`:319-327`): sai "Cadastro de obras só por via técnica" e "Sem anexos e sem comentários"; "Sem edição do pedido original", "Sem entrega parcial" e "Sem aprovação hierárquica" ficam se ainda forem verdade no HEAD.
      - `DocumentationParityTest`, reescrita explícita (número de casos não diminui; nenhum outro caso muda):
        1. o caso "the onboarding known-limits table drops the two limits this feature removed (RF-31)" é renomeado para citar também o incremento (N-01), mantém os dois `not->toContain` atuais, **troca** `->toContain('Cadastro de obras só por via técnica')` por `->not->toContain('Cadastro de obras só por via técnica')` e soma `->not->toContain('Sem anexos e sem comentários')`, `->toContain('Sem edição do pedido original')` e `->toContain('Sem entrega parcial')`;
        2. o caso "the onboarding guide describes the filters and the Visão Geral it now has (RF-31)" fica inalterado;
        3. caso novo "the onboarding guide describes the post-increment navigation and cadastros (N-01)": `not->toContain` de 'Cinco colunas', 'no menu do topo', 'Suprimentos no Kanban e a Gestão no Dashboard' e 'ninguém se cadastra sozinho'; `toContain` de 'Finalizado', 'Preciso para', 'Somente obras ativas' e 'Associações'.
      - `tests/README.md` espelha a mudança: a linha de paridade documental cita `DocumentationParityTest` e as asserções de onboarding, e o mapa ganha os testes novos desta fatia.
      - Depois rodar `/ai-context`, sem editar `docs/agents/*.md` à mão e sem criar `AI_CONTEXT.md`.
      Cobre: RNF-05, RF-24, CT-03
      Acceptance criteria: `api_contracts.md` lista `solicitado`, `obrasAtivas`, `requestedFrom`/`requestedTo` no Acompanhamento e a ordem de Suprimentos. O guia de onboarding não contém "Cadastro de obras só por via técnica", "Cinco colunas", "no menu do topo" nem "ninguém se cadastra sozinho", e contém "Finalizado", "Preciso para", "Somente obras ativas" e "Associações". `DocumentationParityTest` tem as três asserções de onboarding acima e não perdeu casos. **Toda `tests/Feature/Compliance` está verde** (inclui `DocumentationParityTest`, `EnvExampleTest` e `NoCommittedSecretsTest`, que leem README, CLAUDE.md, `docs/agents` e o guia). O README não cita mais `/suprimentos/kanban` nem `/gestao/dashboard` como tela inicial, e o diff da fase só toca os seis caminhos permitidos.
      Testes: `php artisan test --compact tests/Feature/Compliance`.

## Phase 9: Regressão completa no HEAD final

Antes de implementar, leia:
1. `.spec/features/navegacao-sidebar-listagens/SPEC.md` — requisitos RIGID que esta fase cobre (RF-24, RF-25, RNF-01..RNF-06)
2. `.spec/features/navegacao-sidebar-listagens/PLAN.md` — decomposição completa, dependências e riscos

Pré-condições: o commit documental de T20 existe e o desenvolvedor registrou a validação manual do incremento com o checklist de T22 (ordem do plano mestre: Ralph 3 → validar → regressão completa). Esta fase não muda código: uma falha é corrigida no arquivo da tarefa dona e T24 recomeça do passo 1; uma correção de documentação também re-roda toda `tests/Feature/Compliance`.

- [ ] T24 — Regressão completa no HEAD final (plano mestre: "Ralph 3 → validar → regressão completa")
      Arquivos: nenhum (só verificação)
      Mudança: no HEAD final, nesta ordem, um processo Pest por vez em `127.0.0.1:5434`:
      1. `git status --short` vazio; registrar `git rev-parse HEAD` no log da fase (é o SHA publicado);
      2. `vendor/bin/pint --dirty --format agent` não altera nenhum arquivo (`git status --short` continua vazio);
      3. `npm run build` com exit 0 (antes das suítes: `tests/Browser` e `BuiltAssetsUtilitiesTest` leem o build);
      4. `php artisan test --compact --testsuite=Unit`, depois `--testsuite=Feature` (inclui `tests/Feature/Compliance` e o fluxo §45 de T23), depois `vendor/bin/pest tests/Browser` (suíte inteira, fatias 1–3);
      5. `migrate:fresh --seed` num banco descartável (nunca o de desenvolvimento) e depois `php artisan migrate` → "Nothing to migrate";
      6. `git diff --stat <base>..HEAD -- composer.json composer.lock package.json package-lock.json` → vazio.
      Cobre: RF-24, RF-25, RNF-01, RNF-02, RNF-03, RNF-04, RNF-05, RNF-06
      Acceptance criteria: no SHA registrado, 0 falhas em Unit, Feature e Browser; Pint não altera arquivo; build com exit 0; `migrate` responde "Nothing to migrate" depois de `migrate:fresh --seed`; nenhuma dependência nova no diff desde `<base>`.
      Testes: `php artisan test --compact --testsuite=Unit`, `php artisan test --compact --testsuite=Feature`, `vendor/bin/pest tests/Browser`.
