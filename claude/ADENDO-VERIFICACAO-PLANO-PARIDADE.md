# ADENDO — Verificação do PLANO-PARIDADE-DEMO contra o código

**Data:** 22/09/2026 · **HEAD verificado:** `5d36ba2` (`build/v0-demo-laravel`) — bate com a base declarada no plano.
**Natureza:** somente leitura. Nenhum arquivo de aplicação foi alterado. Nada foi implementado.
**Destino:** entrada para `/plan` (SPEC.md · PLAN.md · PHASES.md).

---

## 1. PRÉ-REQUISITO BLOQUEANTE — confirmado, e é **unilateral**

O plano trata "normalização de e-mail" como um problema único. No código são **dois lados**, e só um está feito:

| Lado | Estado no HEAD | Evidência |
|---|---|---|
| **Leitura** (o que o usuário digita) | ✅ normalizado | `LoginForm::authenticate` e `ForgotPassword::sendResetLink` chamam `AuthenticationRateLimiter::normalizeEmail()` = `mb_strtolower(trim())` **antes** da validação (comentário "RF-12") |
| **Escrita** (o que é gravado em `users.email`) | ❌ **não normalizado** | `CreateUserAction.php:67` e `UpdateUserAction.php:68` gravam `$validated['email']` cru; `Gestao/Usuarios/Form.php:64` só faz `trim()`, sem `mb_strtolower`; `CreateGestaoUser.php:77` faz `firstOrNew(['email' => $email])` com o valor cru de `--email=` |

**Consequência exata.** Gestão cadastra `Marcelo@Albuquerque.com`. O convite funciona (o link carrega `?email=` com o valor armazenado, e `DefinesPasswordFromToken:39` o lê da query string) — a senha é definida com sucesso. No login, `LoginForm` rebaixa o digitado para minúsculas e `Auth::attempt` executa `where email = 'marcelo@albuquerque.com'`. **No PostgreSQL essa comparação é case-sensitive** e não há `citext` nem índice `lower(email)` em nenhuma migration (`grep -rn "citext\|lower(" database/migrations/` → vazio). Resultado: "E-mail ou senha inválidos." para sempre, mesmo com a senha certa.

**Efeito colateral adicional:** `unique:users,email` (`CreateUserAction.php:56`, `UpdateUserAction.php:46`) também é case-sensitive → `a@x.com` e `A@x.com` podem coexistir como duas contas.

**Implicação para o SPEC:** a Fase 0 não é "aplicar `strtolower` no login" (já está lá). É **normalizar os três caminhos de escrita + decidir o que fazer com as linhas já gravadas**. Decisão de migração de dados pendente: `UPDATE users SET email = lower(email)` precisa de um plano para colisões pré-existentes, e o mesmo vale para `password_reset_tokens.email` (é PK e é alimentada por `SendAccessLinkAction.php:44` a partir do valor armazenado).

---

## 2. Divergências entre o plano e o código

| # | O que o plano afirma | O que o HEAD mostra | Impacto |
|---|---|---|---|
| D-1 | Documentos de origem `claude/COMPARATIVO-DEMO-VS-PRODUCAO.md` e `claude/DIAGNOSTICO-LOGIN-E-FEEDBACK-CLIENTE.md` | **Não existem no repositório.** `claude/` contém apenas `PLANO-PARIDADE-DEMO.md` | O SPEC não pode citá-los como fonte. O diagnóstico de login está reconstruído na seção 1 acima |
| D-2 | "Documentação obrigatória ao fim: **AI_CONTEXT.md**" | **Não existe.** A árvore canônica é `AGENTS.md` + `docs/agents/*.md` (8 arquivos, gerados por `/ai-context`) | Corrigir o alvo na fase de documentação |
| D-3 | Fase 2: `#[Url] public ?int $obraId` | **`#[Url]` não é usado em lugar nenhum do projeto** (`grep -rn "#\[Url" app/` → zero). A convenção vigente é ler a query string manualmente no `mount()` (`Gestao/TodosPedidos.php:46-49`) | Adotar `#[Url]` é introduzir convenção nova — decisão consciente, não detalhe de implementação. Também muda comportamento: `#[Url]` reescreve a URL a cada filtro |
| D-4 | Fase 4.1: "o sistema tem as mesmas três seções **em números**" | **Já existem barras proporcionais** em `dashboard.blade.php:110,131,148` — `<div class="h-full rounded bg-primary" style="width: N%">` para `porStatus`, `prazos` e `porObra` | Duas das três formas propostas (barras horizontais de status e de obra) **já estão lá**. O que falta de verdade: o **donut** de prazos, `role="img"`/`aria-label`, e a migração para SVG |
| D-5 | Risco #6: "`DemoRoteiroTest` quebra a cada fase (conta colunas)" | `DemoRoteiroTest.php:81` usa `assertSeeIn('tr[data-pedido-code="…"]', 'Solicitado')` — **escopo de linha**, imune a colunas novas. `:89` usa `[data-column="solicitado"]`, que é **coluna do Kanban**, não da tabela | Risco superestimado. O teste sobrevive à Fase 1 como especificada; ainda assim deve ser reexecutado |
| D-6 | Fase 4.2: "`PedidoPolicy::view` devolve `true` para suprimentos" | Correto, mas o mecanismo central de escopo é **`Pedido::visibleTo()`**, um `#[Scope]` em `Pedido.php:41` — que o plano cita e que o `DashboardIndicatorsService` **não aplica** | Confirmado. Ver seção 3 |

---

## 3. Restrições rígidas que o SPEC precisa herdar (segurança e testes já existentes)

### 3.1 `ObraVisibleToGuardTest` governa a Fase 2.3

`tests/Feature/Compliance/ObraVisibleToGuardTest.php` tokeniza `app/Livewire/Obra/*.php` e impõe:

1. Todo statement iniciado em `Pedido::` deve chamar `visibleTo` **antes do `;`** (linha 63);
2. `find`, `findOrFail` e `firstOrFail` são **proibidos** nesses statements (linha 56);
3. Qualquer `whereIn('obra_id', …)` dentro de `app/Livewire/Obra/` é **violação** (linha 83) — a intenção é que o escopo de obra passe só por `visibleTo`;
4. `Pedido.php` não pode conter `addGlobalScope` nem `ScopedBy` (linha 93).

**Forma segura para o filtro `obraId` da Obra:** manter `$query = Pedido::query()->visibleTo(Auth::user())->…;` como um statement, e aplicar `$query->where('obra_id', $this->obraId);` em statement separado (não começa com `Pedido::`, e é `where` singular, não `whereIn`). Isso satisfaz os quatro pontos e garante o requisito do plano: o filtro **restringe dentro** do conjunto visível, nunca amplia.

### 3.2 O select de obras da Fase 2.3 não deve copiar `->active()`

`NovaSolicitacao.php:79` usa `Auth::user()->obras()->active()` — correto para *criar*. Mas `Pedido::visibleTo` **deliberadamente não filtra obra inativa** (docblock: obras inativas mantêm seus pedidos históricos no escopo, D-06), e `tests/Feature/Livewire/ObraInativaPreservaHistoricoTest.php` fixa esse comportamento. Se o select de filtro usar `->active()`, os pedidos históricos de uma obra desativada ficam **invisíveis ao filtro** — regressão silenciosa. **Decisão a registrar no SPEC.**

### 3.3 `ResponsiveIdentityTest` é um gate de acessibilidade para toda a Fase 2

Roda em 3 viewports (1440×900, 820×1180, **390×844**) e afirma, por tela: sem overflow horizontal do documento; todo controle de formulário com `<label for>` associado; todo elemento focável com anel de foco ≥ 2px.

**Telas cobertas hoje:** `/login`, `/esqueci-senha`, `/suprimentos/kanban`, `/gestao/dashboard`, `/gestao/usuarios`, `/gestao/usuarios/novo`.
**Telas NÃO cobertas:** `/obra/pedidos`, `/suprimentos/pedidos`, `/gestao/pedidos` — exatamente as três listagens que as Fases 1 e 2 alteram.

Consequências: (a) cada `<select>` novo da Fase 2 precisa de `label`+`for` e anel de foco, ou o teste quebra nas telas já cobertas; (b) `/gestao/dashboard` é testado a 390px — o 4º KPI da Fase 3.2 e os SVGs da Fase 4.1 não podem causar overflow; (c) **não existe guarda automática para o comportamento mobile das listagens** — o que torna a Decisão 1.3 uma escolha sem rede de segurança atual.

### 3.4 Tokens de tema são obrigatórios (Fase 4.1)

`tests/Feature/Design/ThemeTokensTest.php` e `BrandIdentityComplianceTest.php` exigem que as cores venham do bloco `@theme` de `resources/css/app.css` (`bg-primary`, `bg-success`, `bg-warning`, `bg-atraso`, `text-text-muted`, `border-border`…), nunca literais. SVG não aceita `bg-*`: o SPEC precisa definir o mecanismo (`fill-*`/`stroke-*` do Tailwind 4, ou `currentColor`, ou `var(--color-…)`) **antes** de a Fase 4 começar.

---

## 4. O que o plano cita e **existe exatamente como descrito** ✅

`resources/views/components/pedido-table.blade.php` com 8 colunas e `colspan="8"`, compartilhado pelas três listagens (confirmado: `obra/acompanhamento.blade.php:10`, `suprimentos/todos-pedidos.blade.php:50`, `gestao/todos-pedidos.blade.php:50` — Risco #1 do plano é real) · `DashboardIndicatorsService::compute()` devolvendo as 6 chaves, com `->get()` + filtragem em PHP (Risco #3 real) · `AtrasoClassifier::isAtrasado`/`scopeAtrasado`, `PendenteClassifier`, `PrazoClassifier` · `Pedido::visibleTo(User)` · `Dashboard::drillDownUrl()` + leitura de `atrasado`/`pendente` no `mount()` de `Gestao\TodosPedidos` · `Status::ordered()`, `Priority::ordered()`, `User::suprimentos()` — as mesmas fontes que o `Dashboard` já usa no `render()` · padrão de filtro em Blade (`form-label`, `form-control`, `wire:model.live`, `fieldset`+`legend`) · `tests/Feature/Authorization/PedidoVisibleToScopeTest.php` (destino indicado para o teste de `obraId` forjado) · `docs/onboarding-albuquerque.md` · `requested_at` já carregado no modelo (Fase 1.2 sem query nova).

---

## 5. Oportunidade que o plano não previu (decorrência da Fase 2)

`Gestao/Dashboard.php:22-28` documenta por que o drill-down carrega **apenas** o período: obra/status/prioridade/responsável "não têm contrapartida na listagem de destino (o AC do RF-20 a mantém idêntica à de Suprimentos), então um drill-down com esses filtros ativos sub-filtraria silenciosamente a listagem".

**A Fase 2 remove essa limitação.** Com os quatro selects na listagem de Gestão, o drill-down pode passar a carregar todos os filtros ativos e ficar exato. Não está no plano; é ganho barato e coerente com o princípio de só somar. **Registrar como item opcional da Fase 3.3 para o Clarifier.**

---

## 6. DECISÃO EM ABERTO — para o Planner/Clarifier

### Decisão 1.3 — responsividade da tabela (10 colunas)

**Permanece explicitamente em aberto. Não assumida aqui.** O plano recomenda **A** condicionado a "se o discovery com o Marcelo confirmar uso intenso em celular"; esse discovery **não consta no repositório**.

| Opção | Custo | Observação verificada |
|---|---|---|
| **A** — variante card no mobile (`md:` para cima = tabela) | alto | Resolve de fato. Exige novo caso em `ResponsiveIdentityTest` **e** cobertura inédita das 3 listagens (hoje ausente — §3.3) |
| **B** — prop `:columns` em `x-pedido-table`, cada tela declara as suas | médio | Alteração num componente usado por 3 telas; o `colspan` do estado vazio passa a ser derivado da contagem de colunas, não literal |
| **C** — manter `overflow-x-auto` | zero | O `div.overflow-x-auto` contém a rolagem, então provavelmente **não** dispara a asserção de overflow do documento — mas as listagens não são testadas, então isso não está comprovado |

**Bloqueia:** Fase 1 inteira, e por dependência as Fases 2–4.
**Pergunta a levar ao usuário:** o perfil Obra usa o sistema em celular no canteiro com que frequência?

### Decisões menores, também em aberto
1. `#[Url]` vs. leitura manual no `mount()` (D-3) — convenção nova vs. padrão vigente.
2. Select de obras da Fase 2.3 inclui obras inativas? (§3.2) — recomendação técnica: **sim**, para não esconder histórico.
3. Fase 0: estratégia de migração das linhas de `users.email` já gravadas em caixa mista (§1).
4. Mecanismo de cor para SVG sob os tokens de tema (§3.4).

---

## 7. Escopo — preservação integral

Nenhum item do plano remove funcionalidade. A revisão confirma que tudo que hoje existe e não aparece no plano (busca textual, faixas de data, paginação, gestão de usuários, Kanban, trilhas de auditoria `pedido_events` / `user_admin_events` / `authentication_events`, rate limiting, `AuthenticateSession`) é ortogonal às quatro fases e deve ser preservado como está. Os itens da seção "O QUE ESTE PLANO NÃO INCLUI" permanecem fora de escopo.
