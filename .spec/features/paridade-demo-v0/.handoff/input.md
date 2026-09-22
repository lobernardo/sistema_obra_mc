# Input confirmado — paridade-demo-v0

**Slug:** `paridade-demo-v0` · **Branch:** `feat/paridade-demo-v0` · **Tier:** `complete`
**HEAD base:** `5d36ba2` (`build/v0-demo-laravel`)

## Fontes de verdade (ler integralmente)
1. `claude/PLANO-PARIDADE-DEMO.md` — documento principal, define as 4 fases de paridade.
2. `claude/ADENDO-VERIFICACAO-PLANO-PARIDADE.md` — verificação do plano contra o código real; onde os dois divergirem, **o adendo vence**, porque foi conferido no HEAD.

## Summary
Paridade com o demo de apresentação: corrigir o bloqueador de normalização de e-mail; tornar as listagens legíveis e usáveis em celular; acrescentar os filtros obra/status/prioridade/responsável; completar indicadores e tornar o drill-down exato; fechar o gap de visualização e entregar a tela "Visão Geral" de Suprimentos.

## Acceptance criteria confirmados

**AC-0 (Fase 0, bloqueante).** Existe uma regra canônica única de normalização de e-mail, aplicada em **todos** os caminhos de escrita identificados no HEAD (`CreateUserAction.php:67`, `UpdateUserAction.php:68`, `CreateGestaoUser.php:77`, além de `Gestao/Usuarios/Form.php:64` que hoje só faz `trim()`), consistente com a normalização de leitura que já existe (`AuthenticationRateLimiter::normalizeEmail()` = `mb_strtolower(trim())`, consumida por `LoginForm::authenticate` e `ForgotPassword::sendResetLink`). Login, recuperação e convite funcionam para um e-mail cadastrado em caixa mista. `password_reset_tokens` (PK `email`, alimentada por `SendAccessLinkAction.php:44` a partir do valor armazenado) é tratada. Nenhum e-mail real aparece em log ou em teste.

**AC-1 (Fase 1).** `x-pedido-table` ganha as colunas "Itens" (truncada, com `title` completo) e "Solicitado em" (`d/m/Y`), sem nenhuma query adicional. Em desktop permanece tabela; abaixo do breakpoint as três listagens apresentam cards. Nenhuma das três listagens perde comportamento.

**AC-2 (Fase 2).** As três listagens ganham filtros de obra, status, prioridade e responsável mais "Limpar filtros", sem remover busca textual, atraso ou faixas de data. Na listagem da Obra, `visibleTo` é aplicado antes de qualquer filtro; um `obraId` forjado para obra alheia devolve conjunto vazio.

**AC-3 (Fase 3).** KPIs (total, pendentes, atrasados) no topo de Suprimentos › Pedidos, refletindo os filtros aplicados. `DashboardIndicatorsService` passa a devolver `entregues` e o dashboard mostra o quarto card. O drill-down carrega todos os filtros ativos que a listagem de destino passa a suportar, ficando exato.

**AC-4 (Fase 4).** O donut de prazos é renderizado em SVG inline com os tokens de tema, `role="img"` e `aria-label`, preservando os números em texto. Existe `/suprimentos/visao-geral` sob `can:is-suprimentos`, com KPIs, contagem por status, atalho para o Kanban e os 5 pedidos mais recentes.

**AC-5 (Fase final).** Documentação atualizada, suíte completa verde, build de assets, gates de segurança e dependências.

## Decisões já resolvidas (NÃO marcar como [NEEDS CLARIFICATION])

1. **Responsividade (1.3): caminho A.** Cards no mobile, tabela em desktop. O cliente já usou o sistema em celular e relatou problemas. Breakpoint seguindo a convenção `md:` já vigente no projeto.
2. **`#[Url]`: adotar.** Já verificado no HEAD — `vendor/livewire/livewire/src/Attributes/Url.php` existe no Livewire v4.4.5 instalado. Não há incompatibilidade; nenhuma alternativa é necessária. É convenção nova no projeto (hoje `grep -rn "#\[Url" app/` devolve zero) e deve ser registrada como tal.
3. **Obras inativas permanecem filtráveis.** O conjunto que alimenta o select de filtro **não** leva `->active()` — ao contrário de `NovaSolicitacao.php:79`, que é caminho de criação e continua restrito a obras ativas. Regra preservada: obra inativa não recebe pedido novo, mas seu histórico continua visível e filtrável (`ObraInativaPreservaHistoricoTest` fixa isso).
4. **SVG com tokens de tema.** Nenhuma cor literal. `ThemeTokensTest` e `BrandIdentityComplianceTest` exigem as cores do bloco `@theme` de `resources/css/app.css`. Como SVG não aceita `bg-*`, o mecanismo (`fill-*`/`stroke-*` do Tailwind 4, `currentColor` ou `var(--color-…)`) precisa ser decidido explicitamente no SPEC. Acessibilidade textual preservada.
5. **Drill-down exato.** Aproveitar os filtros novos para transportar o que hoje não pode ser transportado. `Gestao/Dashboard.php:22-28` documenta que hoje só o período é carregado porque a listagem de destino não tem os quatro selects — a Fase 2 remove essa razão. Acréscimo, não substituição.
6. **Unicidade no banco (Fase 0): índice único funcional.** `CREATE UNIQUE INDEX users_email_lower_unique ON users (lower(email))`, revertido com `DROP INDEX`. Sem extensão, sem privilégio especial, coluna permanece `varchar(255)`. **Não usar `citext`.**
7. **Colisões no backfill (Fase 0): abortar.** Um comando de diagnóstico lista as colisões antes; a migration **falha** se existir qualquer colisão. Nenhuma conta real é desativada, reatribuída ou fundida automaticamente. A resolução é manual e precede o deploy.

## Restrições rígidas herdadas da verificação do código

- `Pedido::visibleTo(Auth::user())` antes de qualquer filtro controlável pelo perfil Obra. `obraId` só restringe dentro do conjunto já visível, nunca amplia.
- `tests/Feature/Compliance/ObraVisibleToGuardTest.php` impõe mecanicamente sobre `app/Livewire/Obra/*.php`: todo statement iniciado em `Pedido::` chama `visibleTo` antes do `;` (linha 63); `find`/`findOrFail`/`firstOrFail` proibidos nesses statements (linha 56); **qualquer `whereIn('obra_id', …)` é violação** (linha 83); `Pedido.php` sem `addGlobalScope`/`ScopedBy` (linha 93). Forma segura: `$query = Pedido::query()->visibleTo(Auth::user())->…;` como um statement e `$query->where('obra_id', $this->obraId);` em statement separado.
- `tests/Feature/Design/ThemeTokensTest.php` e `Compliance/BrandIdentityComplianceTest.php` permanecem gates.
- `tests/Feature/Authorization/*` e `tests/Feature/Security/Adversarial/*` permanecem gates. O teste de `obraId` forjado vai em `tests/Feature/Authorization/PedidoVisibleToScopeTest.php`, que já existe.
- `tests/Browser/ResponsiveIdentityTest.php` cobre hoje `/login`, `/esqueci-senha`, `/suprimentos/kanban`, `/gestao/dashboard`, `/gestao/usuarios`, `/gestao/usuarios/novo` em 1440×900, 820×1180 e 390×844, afirmando: sem overflow horizontal do documento, todo controle com `<label for>`, todo focável com anel ≥ 2px. **As três listagens não são cobertas** — a Fase 1 precisa acrescentar essa cobertura, que hoje não existe.
- `tests/Browser/DemoRoteiroTest.php` **não** conta colunas de tabela: `:81` usa `assertSeeIn('tr[data-pedido-code="…"]', 'Solicitado')` (escopo de linha) e `:89` usa `[data-column="solicitado"]`, que é coluna do **Kanban**. Não planejar edição desse teste como se ele validasse colunas; apenas reexecutá-lo.
- O dashboard **já** renderiza barras proporcionais para status e obra (`dashboard.blade.php:110,131,148` — `<div class="h-full rounded bg-primary" style="width: N%">`). Não replanejar essas duas como se não existissem. O gap real de visualização é o donut de prazos, a camada de acessibilidade (`role="img"`/`aria-label`) e a tela Visão Geral.
- `DashboardIndicatorsService` faz `->get()` e filtra em PHP, e **não** aplica `visibleTo`. Correto hoje porque só telas de suprimentos/gestão o consomem. Registrar como armadilha para qualquer reuso futuro em contexto de Obra.
- Preservar integralmente o que existe. Nada é removido: busca textual, faixas de data, paginação, gestão de usuários, Kanban, `pedido_events`, `user_admin_events`, `authentication_events`, rate limiting e `AuthenticateSession` permanecem intactos.
- `AI_CONTEXT.md` **não existe**. A árvore canônica é `AGENTS.md` + `docs/agents/*.md`. Se a fase de documentação exigir esse arquivo, declarar explicitamente que é arquivo novo.
- `CLAUDE.md` e `AGENTS.md` são hand-written e **not-owned** pelo `/ai-context` (sem o banner de geração). A fase de documentação atualiza `docs/agents/*` via `/ai-context` e trata `CLAUDE.md` como edição manual — nunca sobrescrita automática.

## Fora de escopo (não incluir sob nenhuma hipótese)
cadastro de obras · observações/comentários · seletor ou troca de perfil · auto-cadastro · aprovação · valores · fornecedores · itens estruturados · anexos · qualquer item não previsto no documento principal ou no adendo.

## Estrutura de fases esperada
- Fase 0 — Correção bloqueante da identidade/normalização de e-mail (bloqueia mecanicamente todas as demais)
- Fase 1 — Legibilidade das listagens + experiência mobile
- Fase 2 — Filtros
- Fase 3 — Indicadores e drill-down
- Fase 4 — Visualização + Visão Geral de Suprimentos
- Fase final — documentação, suíte completa, build e gates de segurança/dependências
