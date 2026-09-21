# Revisão visual — Etapa 7 (§46 quality gate)

- Feature: `ajustes-finais-albuquerque` — SPEC v1.1, PLAN T23/T24
- Cobre: UI-03, UI-07, UI-13, UI-14, UI-21, UI-22, RNF-16, AC-51.1, AC-51.2, AC-51.3, AC-51.6, AC-51.11, AC-Q08
- Data: 2026-09-20
- Regra (RNF-16): trocar classes Tailwind não conta como concluído. Cada linha abaixo foi verificada com a tela **renderizada** em Chromium headless (pest-plugin-browser 4.3.1) nos 3 viewports, complementada por inspeção do markup e por asserções automatizadas.

## 1. Método

| Verificação | Como foi feita | Evidência |
|---|---|---|
| Renderização nos 3 viewports | Screenshots full-page de todas as 17 telas em 1440×900 (desktop ≥1280 px), 820×1180 (tablet 768–1024 px) e 390×844 (mobile ≤414 px) — 51 capturas, inspecionadas uma a uma | `tests/Browser/Screenshots/` (diretório ignorado pelo Git; regenerável) |
| Overflow horizontal, controle primário alcançável, labels, foco visível, erros JS | `tests/Browser/ResponsiveIdentityTest.php` (T23): 6 telas × 3 viewports, 9 casos / 237 asserções | verde em 2026-09-20 |
| Anti-padrões §26 no código | `grep` em `resources/views` e `resources/css`: `bg-gradient-*` = 0, `shadow-lg/xl/2xl` = 0, `animate-*` = 0, `rounded-xl+` = 0, paletas `sky/slate/gray/blue/violet` = 0, `<aside>` = 0, `MC Inteligência` só em `auth/login.blade.php` | `tests/Feature/Design/ThemeTokensTest.php`, `tests/Feature/Livewire/LayoutIdentityTest.php` |
| Contraste | Cálculo WCAG 2.1 (luminância relativa) para todos os pares de tokens usados como texto | §6 deste documento |

Legenda das matrizes: **OK** = verificado e conforme; **OK\*** = conforme após correção pontual desta etapa (ver §7); **N/A** = não se aplica à tela; **PEND** = pendente de etapa posterior (nenhum restante após a Etapa 9 — §10).

## 2. Linha obrigatória — sidebar

**§19 Sidebar: N/A — navegação topbar-only**

Não existe `<aside>` nem região lateral em `resources/views/layouts/app.blade.php`; a navegação é o `<nav aria-label="Navegação principal">` dentro do topbar branco (UI-07/UI-08, Q-08). Todos os itens "sidebar" de §27, §34 e §51 abaixo estão marcados N/A com esta mesma nota. §19 só passa a valer se uma sidebar for introduzida em entrega futura.

## 3. Matriz A — 17 telas × 12 itens de §46

Itens: (1) consistência de cores, (2) spacing, (3) tipografia, (4) alinhamento, (5) bordas, (6) radius, (7) sombras, (8) estados, (9) responsividade, (10) logos, (11) hierarquia visual, (12) legibilidade.

| # | Tela | Rota / view | 1 Cores | 2 Spacing | 3 Tipografia | 4 Alinhamento | 5 Bordas | 6 Radius | 7 Sombras | 8 Estados | 9 Responsividade | 10 Logos | 11 Hierarquia | 12 Legibilidade |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| 1 | Login | `/login` — `auth/login` + `livewire/auth/login-form` | OK | OK | OK | OK | OK | OK | OK | OK\* | OK | OK (Etapa 9 — §10) | OK | OK |
| 2 | Esqueci minha senha | `/esqueci-senha` — `livewire/auth/forgot-password` | OK | OK | OK | OK | OK | OK | OK | OK\* | OK | OK (Etapa 9 — §10) | OK | OK |
| 3 | Redefinir senha | `/redefinir-senha/{token}` — `livewire/auth/reset-password` | OK | OK | OK | OK | OK | OK | OK | OK\* | OK | OK (Etapa 9 — §10) | OK | OK |
| 4 | Primeiro acesso | `/primeiro-acesso/{token}` — `livewire/auth/accept-invite` | OK | OK | OK | OK | OK | OK | OK | OK\* | OK | OK (Etapa 9 — §10) | OK | OK |
| 5 | Topbar | `layouts/app` (`<header>`) | OK | OK | OK | OK | OK | OK | OK (nenhuma) | OK | OK | N/A (sem logo no topbar por decisão UI-25; nome via `config('app.name')`) | OK | OK |
| 6 | Obra — Acompanhamento | `/obra/pedidos` — `livewire/obra/acompanhamento` + `components/pedido-table` | OK | OK | OK | OK | OK | OK | OK | OK\* | OK | N/A | OK | OK |
| 7 | Obra — Nova solicitação | `/obra/nova-solicitacao` — `livewire/obra/nova-solicitacao` | OK | OK | OK | OK | OK | OK | OK | OK | OK | N/A | OK | OK |
| 8 | Obra — Detalhe | `/obra/pedidos/{pedido}` — `livewire/obra/pedido-detalhe` + `pedido-summary` + `pedido-history-timeline` | OK | OK | OK | OK | OK | OK | OK | OK\* | OK | N/A | OK | OK |
| 9 | Suprimentos — Todos os pedidos | `/suprimentos/pedidos` — `livewire/suprimentos/todos-pedidos` | OK | OK | OK | OK | OK | OK | OK | OK\* | OK | N/A | OK | OK |
| 10 | Suprimentos — Detalhe | `/suprimentos/pedidos/{pedido}` — `livewire/suprimentos/pedido-detalhe` | OK | OK | OK | OK | OK | OK | OK | OK\* | OK | N/A | OK | OK |
| 11 | Kanban (Suprimentos) | `/suprimentos/kanban` — `livewire/kanban/kanban-board` + `pedido-card` | OK | OK | OK | OK | OK | OK | OK | OK | OK | N/A | OK | OK |
| 12 | Gestão — Dashboard | `/gestao/dashboard` — `livewire/gestao/dashboard` | OK | OK | OK | OK | OK | OK | OK | OK\* | OK | N/A | OK | OK |
| 13 | Gestão — Kanban (leitura) | `/gestao/kanban` — `livewire/gestao/kanban-read-only` + `pedido-card-read-only` | OK | OK | OK | OK | OK | OK | OK | OK\* | OK | N/A | OK | OK |
| 14 | Gestão — Todos os pedidos | `/gestao/pedidos` — `livewire/gestao/todos-pedidos` | OK | OK | OK | OK | OK | OK | OK | OK\* | OK | N/A | OK | OK |
| 15 | Gestão — Detalhe | `/gestao/pedidos/{pedido}` — `livewire/gestao/pedido-detalhe` | OK | OK | OK | OK | OK | OK | OK | OK\* | OK | N/A | OK | OK |
| 16 | Usuários — Index | `/gestao/usuarios` — `livewire/gestao/usuarios/index` | OK | OK\* | OK | OK\* | OK | OK | OK | OK\* | OK\* | N/A | OK | OK\* |
| 17 | Usuários — Form (novo/editar) | `/gestao/usuarios/novo`, `/gestao/usuarios/{user}/editar` — `livewire/gestao/usuarios/form` | OK | OK | OK | OK | OK | OK | OK | OK | OK | N/A | OK | OK |

Notas por item:
- **Cores**: todas as telas consomem exclusivamente os tokens do `@theme` (`primary`, vinhos, `background`, `surface`, `border`, `text`, `text-muted`, `focus`, estados semânticos). Nenhuma classe de paleta Tailwind (`sky/slate/gray/blue/violet`) em `resources/views`/`resources/css`.
- **Spacing / alinhamento**: grid `gap-4/5`, cards `p-5`, `max-w-7xl`, formulários `max-w-2xl`/`max-w-md`. Usuários index: ver §7 (ações da tabela).
- **Tipografia**: `Instrument Sans`; hierarquia `page-title` (2xl/semibold) → `section-title` (base/semibold) → corpo `text-sm` → auxiliar `text-xs text-text-muted`; cabeçalhos de tabela `text-xs uppercase tracking-wide`.
- **Bordas / radius / sombras**: `border-border` 1 px em cards, tabelas, inputs e topbar; radius máximo `rounded-lg` (8 px) em cards/colunas e `rounded-md` (6 px) em botões/inputs/badges — sem `rounded-full` em botões (apenas o marcador de 12 px da timeline e o ponto de 10 px da legenda de prazos); sombra máxima `shadow-sm`.
- **Estados**: hover (`primary-hover`/`background`), active (`primary-active`/`border`), focus (anel 2 px `focus/30–40` ou outline 2 px `focus`), disabled (`opacity-60`, `bg-background`), loading (`wire:loading` → `opacity-60`/`disabled`), erro (`role="alert"` + `text-error`), sucesso/info (`alert-*`). OK\* = foco de links passou a ter anel institucional nesta etapa (§7.1).
- **Responsividade**: tabelas com `overflow-x-auto` (rolagem interna, sem overflow do documento); Kanban 1 → 2 → 5 colunas (`md`/`xl`); dashboard 1 → 2 → 6 colunas de filtro e 1 → 3 cards; topbar com `flex-wrap` e nav `overflow-x-auto`; formulários em coluna única no mobile.
- **Logos**: aplicadas na Etapa 9 (T27/T28) nas 4 telas de autenticação — `logo_Albuquerque.png` acima do título e `logo_MC.png` junto à assinatura; medidas renderizadas em §10; nas telas autenticadas o item é N/A por decisão UI-25 (sem logo/favicon MC; marca textual Albuquerque via `config('app.name')`).
- **Hierarquia / legibilidade**: título da página > subtítulo muted > cards; código do pedido em `text-primary font-semibold` como âncora de leitura; badges semânticos distintos por estado; contraste em §6.

## 4. Matriz B — 11 anti-padrões de §26 × 17 telas (pass/fail)

Anti-padrões: (a) grandes áreas totalmente vermelhas, (b) excesso de gradientes, (c) sombras pesadas, (d) interface escura, (e) excesso de cores, (f) aparência de template SaaS genérico, (g) componentes arredondados demais, (h) aparência infantil, (i) efeitos visuais gratuitos, (j) excesso de animação, (k) excesso de decoração.

| # | Tela | a | b | c | d | e | f | g | h | i | j | k |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| 1 | Login | pass | pass | pass | pass | pass | pass | pass | pass | pass | pass | pass |
| 2 | Esqueci minha senha | pass | pass | pass | pass | pass | pass | pass | pass | pass | pass | pass |
| 3 | Redefinir senha | pass | pass | pass | pass | pass | pass | pass | pass | pass | pass | pass |
| 4 | Primeiro acesso | pass | pass | pass | pass | pass | pass | pass | pass | pass | pass | pass |
| 5 | Topbar | pass | pass | pass | pass | pass | pass | pass | pass | pass | pass | pass |
| 6 | Obra — Acompanhamento | pass | pass | pass | pass | pass | pass | pass | pass | pass | pass | pass |
| 7 | Obra — Nova solicitação | pass | pass | pass | pass | pass | pass | pass | pass | pass | pass | pass |
| 8 | Obra — Detalhe | pass | pass | pass | pass | pass | pass | pass | pass | pass | pass | pass |
| 9 | Suprimentos — Todos os pedidos | pass | pass | pass | pass | pass | pass | pass | pass | pass | pass | pass |
| 10 | Suprimentos — Detalhe | pass | pass | pass | pass | pass | pass | pass | pass | pass | pass | pass |
| 11 | Kanban (Suprimentos) | pass | pass | pass | pass | pass | pass | pass | pass | pass | pass | pass |
| 12 | Gestão — Dashboard | pass | pass | pass | pass | pass | pass | pass | pass | pass | pass | pass |
| 13 | Gestão — Kanban (leitura) | pass | pass | pass | pass | pass | pass | pass | pass | pass | pass | pass |
| 14 | Gestão — Todos os pedidos | pass | pass | pass | pass | pass | pass | pass | pass | pass | pass | pass |
| 15 | Gestão — Detalhe | pass | pass | pass | pass | pass | pass | pass | pass | pass | pass | pass |
| 16 | Usuários — Index | pass | pass | pass | pass | pass | pass | pass | pass | pass | pass\* | pass |
| 17 | Usuários — Form | pass | pass | pass | pass | pass | pass | pass | pass | pass | pass | pass |

Critérios aplicados: (a) o único preenchimento vermelho sólido é em botões primários, badge "Urgente" e botão "Cancelar pedido" (`bg-error`) — nenhum container de largura total; (b) `bg-gradient-*` = 0; (c) `shadow-sm` no máximo; (d) fundo `#F7F7F8`/`#FFFFFF`, sem `dark:` (variantes `dark:` da view de paginação publicada foram removidas); (e) 1 institucional + 6 semânticos + neutros, cada um com função; (f) sem hero, sem ilustrações, sem cards flutuantes coloridos; (g) radius ≤ 8 px; (h) sem ícones lúdicos/emoji, tipografia sóbria; (i) sem blur/glass/glow; (j) únicas transições: `transition-opacity` no `wire:loading` de 3 listagens — pass\* em Usuários index porque a transição de 150 ms da paginação vendor (que atrasava o anel de foco) foi removida nesta etapa; (k) sem ornamentos.

## 5. Matriz C — itens de §27 (UI-14)

Os marcadores de §27 do documento-fonte (`docs/specs/AJUSTES-FINAIS-ALBUQUERQUE.md`, 27 marcadores — o PLAN/T24 os referencia como "26 itens", contando os estados hover/focus/active/disabled de forma agregada) são listados um a um, na ordem do documento.

| # | Item §27 | Status | Onde / como verificado |
|---|---|---|---|
| 1 | Autenticação | OK | `auth/login` + `login-form`: título `config('app.name')`, card branco, `Entrar no sistema`, `btn-primary`, assinatura MC discreta no rodapé (`text-xs text-text-muted`) |
| 2 | Recuperação de senha | OK | `forgot-password`, `reset-password`: mesmo shell (UI-24), `alert-info` para resposta genérica, link `Voltar ao login` em `text-primary` |
| 3 | Definição inicial de senha | OK | `accept-invite`: mesmo shell, `Defina sua senha`, botão `Definir senha` |
| 4 | Sidebar | **N/A — §19 Sidebar: N/A — navegação topbar-only** | sem `<aside>` em `layouts/app` (Q-08, UI-07) |
| 5 | Topbar | OK | `bg-surface border-b border-border`; marca em `text-text`; `.nav-link` muted com `nav-link-active` em `border-primary text-primary` + `aria-current`; badge de perfil `badge-neutral`; `Sair` em `btn-secondary`; 4 links para Gestão (`LayoutIdentityTest`) |
| 6 | Dashboards | OK | `dashboard`: 3 cards de indicadores (Pendentes `border-t-warning`, Atrasados `border-t-atraso`), barras `bg-primary` para status/obra, 3 cores semânticas em prazos (`DashboardIndicatorsTest`) |
| 7 | Cards | OK | `.card` = `rounded-lg border-border bg-surface p-5 shadow-sm` em todas as telas autenticadas e no card de login |
| 8 | Kanban | OK | colunas `bg-background border-border rounded-lg`; cards `bg-surface shadow-sm`; contador `badge-neutral`; card atrasado `pedido-atrasado` (borda esquerda `atraso` + tinta `error/5`); leitura (Gestão) sem seletor "Mover para" |
| 9 | Tabelas | OK | `.data-table` (thead `bg-background text-text-muted uppercase`, linhas `border-border`), wrapper `overflow-x-auto rounded-lg border shadow-sm` em Acompanhamento, Todos os pedidos (×2) e Usuários |
| 10 | Filtros | OK | `form.card` com `fieldset rounded-lg border-border` + `legend uppercase muted`, `form-control` em inputs/selects, checkbox `accent-primary` |
| 11 | Formulários | OK | Nova solicitação, Usuários form, 4 formulários de Operação no detalhe Suprimentos, formulários de autenticação: `form-label` + `form-control`, erro `role="alert" .form-error` |
| 12 | Modais | N/A | nenhum modal/`<dialog>`/`wire:confirm` existe na aplicação; ações destrutivas usam botão dedicado com texto explicativo (`Cancelar pedido`) |
| 13 | Menus | OK | menu = navegação do topbar (item 5); não há dropdowns |
| 14 | Botões | OK | `.btn-primary` (`bg-primary` → hover `primary-hover` → active `primary-active`, anel `focus/40`), `.btn-secondary` (branco, `border-border`, texto `secondary`), `.btn-danger` (`bg-error`, hover `atraso`); radius `rounded-md`; sem `rounded-full` |
| 15 | Paginação | OK\* | view `livewire::tailwind` publicada em `resources/views/vendor/livewire/tailwind.blade.php` e reescrita com tokens nesta etapa (§7.4): superfícies neutras, página atual `text-primary bg-secondary-soft`, foco `ring-focus/30` + `border-primary` (antes: `ring-blue-300`/`border-blue-300`) |
| 16 | Badges | OK | `status-badge` (6 variantes distintas), `priority-badge` (4, só Urgente sólida), `atraso-indicator` (`badge-atraso`/`badge-success`), badge de perfil no topbar, `Ativo`/`Inativo` em Usuários (`SemanticBadgeTest`, `PedidoCardRenderTest`) |
| 17 | Notificações | OK | `alert-success` (`role="status"`) em Usuários index/form e detalhe Suprimentos; `alert-error` (`role="alert"`) para `target`/`status_id`; `alert-info` em Esqueci minha senha; e-mails em `components/mail/transactional` (fora do escopo visual desta etapa, sem assinatura MC) |
| 18 | Gráficos | OK | barras horizontais do dashboard: `bg-primary` (status/obra), `bg-success`/`bg-warning`/`bg-atraso` (prazos), trilho `bg-background` |
| 19 | Histórico | OK | `pedido-history-timeline`: linha `border-l-2 border-border`, marcador `bg-primary` só no evento mais recente, anteriores `bg-border`; valores `anterior → novo` |
| 20 | Detalhes do pedido | OK | `pedido-summary` em `dl` 1→3 colunas, rótulos `uppercase text-xs muted`, itens em bloco `bg-background rounded-md`; status como `x-status-badge` ao lado do título |
| 21 | Área de usuários | OK\* | index (`.card` de busca, `.data-table`, badges, `btn-*`) e form (`max-w-2xl`, `fieldset` de obras com checkboxes `accent-primary`); ações da tabela reorganizadas nesta etapa (§7.3) |
| 22 | Estados hover | OK | `btn-primary` → `primary-hover`; `btn-secondary` → `bg-background`; `nav-link` → `border-border text-text`; links → `primary-hover`/`underline`; paginação → `text-text`/`text-text-muted` |
| 23 | Estados focus | OK\* | anel 2 px `ring-focus/30–40` em `form-control`, `btn-*`, checkboxes, paginação; `focus-visible:ring-2` no topbar; links de texto com `outline` 2 px `focus` em `:focus-visible` (§7.1); auditado em todos os controles focáveis das 6 telas pelo `ResponsiveIdentityTest` |
| 24 | Estados active | OK | `btn-primary` → `primary-active`; `btn-secondary` → `bg-border`; paginação → `bg-background`; item de navegação ativo `nav-link-active` + `aria-current="page"` |
| 25 | Estados disabled | OK | `btn-*` → `opacity-60` (+ `wire:loading.attr="disabled"`); `form-control:disabled` → `bg-background text-text-muted cursor-not-allowed`; paginação desabilitada → `text-text-muted cursor-default` |
| 26 | Telas vazias | OK | `.empty-state` (borda tracejada, `bg-surface`, texto muted) em `pedido-table`, Usuários index, histórico e "Visão por obra" |
| 27 | Mensagens de erro/sucesso | OK | `.form-error` (`text-error`, `role="alert"`), `.alert-error`, `.alert-success`, mensagens PT-BR das Actions/validação |

"Não reformular apenas a home": `/home` é apenas o redirecionamento por perfil (RF-01); a identidade foi aplicada a todas as 17 telas das Matrizes A/B, e todas as views Livewire em `resources/views/livewire/{auth,obra,suprimentos,kanban,gestao}` e os 6 componentes de `resources/views/components` foram verificados como consumidores dos tokens (grep de paletas = 0).

### 5.1 §34 — responsividade (atenção especial)

| Item §34 | Status | Evidência |
|---|---|---|
| desktop / notebook (≥1280 px) | OK | viewport 1440×900 — Matriz A item 9 |
| tablet (768–1024 px) | OK | viewport 820×1180 |
| mobile (≤414 px) | OK | viewport 390×844 |
| login | OK | card `max-w-md`, coluna única, sem overflow |
| sidebar | **N/A — §19 Sidebar: N/A — navegação topbar-only** | — |
| tabelas | OK\* | rolagem interna `overflow-x-auto`; overflow do documento em Usuários corrigido (§7.2) |
| Kanban | OK | 1 → 2 → 5 colunas; seletor "Mover para" dentro do viewport |
| dashboard | OK | filtros 1 → 2 → 6 colunas; indicadores 1 → 3 colunas; drill-down alcançável |
| administração de usuários | OK\* | listagem paginada e formulário auditados nos 3 viewports |
| formulários | OK | Nova solicitação, Usuários form e autenticação em coluna única no mobile |
| modais | N/A | não existem modais na aplicação |

### 5.2 §51 — critérios de aceite visuais

| Critério §51 | Status | Evidência |
|---|---|---|
| interface é predominantemente light | OK | §9 |
| identidade Albuquerque é reconhecível | OK | `#9E0128` como assinatura em botões/ativos/links; nome via `config('app.name')` em título e topbar; logo oficial Albuquerque nas telas de autenticação (Etapa 9, §10) |
| vermelho não domina grandes superfícies | OK | §9 |
| azul genérico anterior não é mais a identidade principal | OK | paletas `sky/blue` = 0 em `resources/`; único azul remanescente é o semântico `info` (`#1D4ED8`) em badges "Em análise"/"Normal"; foco azul da paginação removido (§7.4) |
| componentes usam tokens globais | OK | `ThemeTokensTest`; grep de paletas = 0 |
| sidebar/topbar estão coerentes | OK (sidebar **N/A — §19 Sidebar: N/A — navegação topbar-only**) | Matriz C item 5 |
| dashboards estão coerentes | OK | Matriz C item 6 |
| Kanban está coerente | OK | Matriz C item 8 |
| formulários estão coerentes | OK | Matriz C item 11 |
| autenticação está coerente | OK | Matriz C itens 1–3 |
| responsividade está preservada | OK | §5.1, `ResponsiveIdentityTest`, `DemoRoteiroTest` |
| estados semânticos continuam distinguíveis | OK | 6 tokens semânticos distintos (`ThemeTokensTest`), badges com texto do estado; contraste em §6 |

## 6. Contraste (T23 → registrado aqui)

Cálculo WCAG 2.1 (luminância relativa, sRGB). Limiar AA: 4,5:1 para texto normal, 3:1 para texto grande (≥ 24 px, ou ≥ 18,66 px em negrito) e componentes de interface.

| Par (texto sobre fundo) | Uso | Razão | AA |
|---|---|---|---|
| `#FFFFFF` sobre `#9E0128` | texto de `btn-primary`, item ativo | **8,44:1** | ✔ |
| `#202124` sobre `#FFFFFF` | texto principal em cards/tabelas/inputs | **16,10:1** | ✔ |
| `#6B7280` sobre `#FFFFFF` | texto secundário em cards | **4,83:1** | ✔ |
| `#202124` sobre `#F7F7F8` | texto principal sobre fundo da página / thead | 15,04:1 | ✔ |
| `#6B7280` sobre `#F7F7F8` | texto secundário sobre fundo da página / thead | 4,52:1 | ✔ (margem estreita) |
| `#9E0128` sobre `#FFFFFF` | links, códigos de pedido, nav ativa | 8,44:1 | ✔ |
| `#520C1F` sobre `#FFFFFF` | texto de `btn-secondary` | 14,67:1 | ✔ |
| `#FFFFFF` sobre `#802036` / `#661F35` | hover / active do botão primário | 9,64:1 / 11,59:1 | ✔ |
| `#FFFFFF` sobre `#DC2626` | `btn-danger`, badge Urgente | 4,83:1 | ✔ |
| `#1D4ED8` sobre `#DBEAFE` | `badge-info` (Em análise, Normal) | 5,49:1 | ✔ |
| `#B91C1C` sobre `#FFE4E6` | `badge-atraso` | 5,39:1 | ✔ |
| `#9E0128` sobre `#F5E6EA` | página atual da paginação | 6,99:1 | ✔ |
| `#520C1F` sobre `#F5E6EA` | `badge-secondary` (Em compra/preparação) | 12,15:1 | ✔ |
| `#D97706` sobre `#FFFFFF` | número "Pendentes" (30 px negrito = texto grande) | 3,19:1 | ✔ (grande) |
| `#B91C1C` sobre `#FFFFFF` | número "Atrasados" | 6,47:1 | ✔ |
| `#047857` sobre `#A7F3D0` | `badge-concluido` (Entregue), 12 px semibold | 4,28:1 | ✘ (3:1 para UI ✔) |
| `#DC2626` sobre `#FEE2E2` | `badge-error` (Cancelado), `alert-error` | 3,95:1 | ✘ (3:1 ✔) |
| `#059669` sobre `#D1FAE5` | `badge-success` (No prazo), `alert-success` | 3,32:1 | ✘ (3:1 ✔) |
| `#D97706` sobre `#FEF3C7` | `badge-warning` (Aguardando entrega, Alta), | 2,86:1 | ✘ |

Conclusão: os pares exigidos por UI-22 (texto sobre primário e texto de corpo) atendem AA com folga. Os quatro badges/alertas "soft" (success, warning, error, concluído) ficam abaixo de 4,5:1 para texto de 12 px; cada badge é sempre acompanhado do texto do estado (não depende só de cor) e os tons fortes atendem ≥ 3:1 como componente de interface, mas **não** atingem AA para texto pequeno. Isto é registrado como pendência (§8.1) e não foi alterado nesta etapa: os tons fortes também colorem barras/pontos do dashboard e o número "Pendentes", e a paleta semântica é entrega do T20 (Etapa 6); a correção proposta está em §8.1.

## 7. Correções pontuais feitas nesta etapa (T23)

1. **Foco visível em links** — `resources/css/app.css`, `@layer base`: `a:focus-visible { outline: 2px solid var(--color-focus); outline-offset: 2px }`. Antes, links de texto (`Esqueci minha senha`, `Voltar ao login`, códigos de pedido, `← Todos os Pedidos`, drill-downs do dashboard) só tinham o `outline: auto` do navegador (1 px computado). Componentes com `focus-visible:ring-*` próprios (marca e nav do topbar) prevalecem pela camada de utilitários. Verificado negativamente: sem a regra, `ResponsiveIdentityTest` falha no link do login.
2. **Overflow horizontal no mobile (Usuários index)** — `resources/views/livewire/gestao/usuarios/index.blade.php`: o `<th>` da coluna de ações recebeu `relative`, porque o `<span class="sr-only">Ações</span>` (posicionado em absoluto) escapava do wrapper `overflow-x-auto` e alargava o documento para 692 px em um viewport de 390 px. Único overflow de documento encontrado nas 6 telas × 3 viewports.
3. **Alinhamento/spacing das ações da tabela de usuários** — mesmo arquivo: os três botões (rótulo longo "Reenviar convite / Enviar link de redefinição" mantido, exigido pelo SPEC) estavam empilhados em 3 linhas com quebra interna de texto (linhas de ~160 px no desktop). Agora: `Editar` + `Desativar/Ativar` lado a lado e o botão longo abaixo, todos `whitespace-nowrap` (linhas de ~95 px). `wire:click`, textos e testes inalterados.
4. **Paginação com foco azul** — `resources/views/vendor/livewire/tailwind.blade.php` (publicada via `livewire:publish --pagination`; as variantes bootstrap/simple foram removidas por não serem usadas): `ring-blue-300`/`focus:border-blue-300` → `focus:ring-2 focus:ring-focus/30 focus:border-primary`; cinzas → tokens `text`, `text-muted`, `surface`, `border`, `background`; página atual `text-primary bg-secondary-soft`; variantes `dark:` e a transição de 150 ms removidas (a transição fazia o anel de foco aparecer com atraso — capturado pelo teste automatizado); echos brutos `{!! __() !!}` substituídos por literais PT-BR escapados (RNF-08). A listagem de usuários passa a ser paginada no `ResponsiveIdentityTest` (15 por página + 15 usuários extra) para que os controles de paginação entrem na auditoria de foco/labels.

Sem alteração de comportamento, rotas, componentes Livewire, Actions, Policies ou testes existentes. Testes verdes após as correções: `ResponsiveIdentityTest` (9/237), `AccessibleStatusControlTest`, `DemoRoteiroTest`, `ThemeTokensTest`, `LayoutIdentityTest`, `UsuariosIndexTest`, `LoginScreenIdentityTest`, suíte `Compliance`.

## 8. Pendências e observações (fora do escopo desta etapa)

1. **Contraste AA dos badges "soft"** (§6): recomendação para decisão do desenvolvedor — escurecer apenas os tons fortes `success` `#059669 → #065F46` (6,78:1 sobre `#D1FAE5`; 7,68:1 sobre branco), `warning` `#D97706 → #B45309` (4,51:1 sobre `#FEF3C7`; 5,02:1 sobre branco), `error` `#DC2626 → #B91C1C` colide com `atraso` (teste de unicidade) — alternativa `#991B1B`; `concluido`: trocar a tinta `#A7F3D0 → #D1FAE5` (4,84:1). Impacto: barras/pontos do dashboard e número "Pendentes" ficam ligeiramente mais escuros; `ThemeTokensTest` não fixa os hex, apenas exige 6 valores distintos.
2. **Textos da paginação**: a view vendor renderizava em inglês ("Showing 1 to 15 of 19 results", "« Previous") via `__()` sem `lang/pt_BR`, e usava echo bruto (`{!! !!}`), vetado por RNF-08 (`BladeEscapingTest`). A view publicada passou a usar literais PT-BR escapados ("Exibindo … a … de … resultados", "« Anterior", "Próxima »", `aria-label="Paginação"`). Resolvido nesta etapa como consequência de §7.4; registrado aqui por ser cópia, não identidade visual.
3. **Topbar no mobile**: com `flex-wrap`, a ordem visual é marca → (nome oculto) badge de perfil + `Sair` → nav rolável; funcional e sem overflow, mas o bloco de perfil fica alinhado à esquerda sob a marca. Comportamento responsivo pré-existente, preservado (UI-21).
4. **Tabelas no mobile** rolam horizontalmente dentro do wrapper (comportamento pré-existente e aceito: "controle primário alcançável" foi verificado — `Novo usuário`, `Enviar solicitação`, seletor "Mover para", drill-down "Pendentes" e botões de submissão ficam dentro do viewport).
5. **Logos** (coluna 10 da Matriz A): resolvido na Etapa 9 — T27 copiou os ativos originais e T28 aplicou as logos; proporção/alinhamento/responsividade registrados em §10.

## 9. §15 — proporção light por tela (UI-03 / AC-51.1–51.3)

| Tela | Fundo da página | Cards / tabelas / formulários | Container de largura total em vermelho/vinho? | Presença de vermelho/vinho |
|---|---|---|---|---|
| 1–4 Autenticação | `#F7F7F8` | card `#FFFFFF` | não | botão primário, link `Esqueci minha senha`/`Voltar ao login` (~5 % da tela) |
| 5 Topbar | `#FFFFFF` + `border-border` | — | não | sublinhado 2 px + texto do item ativo |
| 6 Obra — Acompanhamento | `#F7F7F8` | `#FFFFFF` | não | códigos de pedido, botão `+ Nova Solicitação`, borda esquerda de linhas atrasadas |
| 7 Obra — Nova solicitação | `#F7F7F8` | `#FFFFFF` | não | botão `Enviar solicitação` |
| 8 Obra — Detalhe | `#F7F7F8` | `#FFFFFF` (itens em `#F7F7F8`) | não | link de retorno, marcador do último evento |
| 9 Suprimentos — Todos os pedidos | `#F7F7F8` | `#FFFFFF` | não | códigos, linha atrasada, badge Urgente (vermelho semântico) |
| 10 Suprimentos — Detalhe | `#F7F7F8` | `#FFFFFF` | não | `Mover status` (primário), `Cancelar pedido` (`error`), link de retorno |
| 11 Kanban | `#F7F7F8` | colunas `#F7F7F8` sobre cards `#FFFFFF` | não | códigos, card atrasado (tinta 5 % + borda esquerda) |
| 12 Gestão — Dashboard | `#F7F7F8` | `#FFFFFF` | não | barras de status/obra (`primary`), borda superior do card Atrasados |
| 13 Gestão — Kanban | `#F7F7F8` | `#F7F7F8` / `#FFFFFF` | não | idem 11 |
| 14 Gestão — Todos os pedidos | `#F7F7F8` | `#FFFFFF` | não | idem 9 |
| 15 Gestão — Detalhe | `#F7F7F8` | `#FFFFFF` | não | link de retorno, marcador da timeline |
| 16 Usuários — Index | `#F7F7F8` | `#FFFFFF` | não | botão `Novo usuário`, página atual da paginação |
| 17 Usuários — Form | `#F7F7F8` | `#FFFFFF` | não | botão `Criar usuário`/`Salvar alterações`, checkbox `accent-primary` |

Resultado: em todas as telas o vermelho institucional ocupa apenas botões primários, item ativo, links importantes, indicadores e pequenos elementos — a superfície é branca/`#F7F7F8` com cinzas em bordas e texto secundário (estimativa visual pelas capturas: ≥ 80 % branco/neutro, ~15 % cinzas, ≤ 5 % vermelho/vinho). Nenhum header, hero, sidebar (N/A) ou fundo de página usa preenchimento vermelho/vinho (AC-51.3).

## 10. Rastreabilidade

| AC | Evidência nesta revisão |
|---|---|
| AC-51.1 interface predominantemente light | §9 |
| AC-51.2 vermelho como assinatura | §9 (coluna "presença") + Matriz C itens 5, 14, 19 |
| AC-51.3 vermelho não domina grandes superfícies | §9 (coluna "container de largura total") |
| AC-51.6 sidebar/topbar coerentes | §2 (N/A) + Matriz C item 5 |
| AC-51.11 responsividade preservada | Matriz A item 9 + `ResponsiveIdentityTest` |
| AC-Q08 topbar-only | §2 |
| UI-13 (§26) | Matriz B |
| UI-14 (§27) | Matriz C (27 marcadores do documento-fonte) |
| UI-22 contraste/labels/foco | §6, §7.1, `ResponsiveIdentityTest` (labels e foco ≥ 2 px em todos os controles focáveis das 6 telas) |
| RNF-16 | §1 (método baseado em telas renderizadas) |

Etapa 8 (T25) registra aqui, em seção própria, as contagens finais da suíte e o diff de preservação; Etapa 9 (T28) atualiza a coluna de logos (§10).

## 10. Etapa 9 — Logos (T27/T28 — UI-16, UI-17, UI-18, UI-19, UI-20, UI-25, CT-06, AC-52.2–52.8)

- Data: 2026-09-20. Ativos: `public/images/logo-albuquerque.png` (1063×345 RGBA, 209281 B, sha256 `64e21002…f77b4d`) e `public/images/logo-mc.png` (1305×200 RGBA, 23177 B, sha256 `798e27b0…309b36`) — cópias byte a byte dos originais (`cp`, sem `mv`, sem reencodar); originais inalterados (mesmo tamanho/mtime); rastreados por `git ls-files public/images`; nenhuma referência a `/mnt/c/` ou `C:\Users` em `app/`, `resources/`, `public/`, `config/` (`BrandAssetsTest`).
- Aplicação: somente em `resources/views/auth/login.blade.php` (layout compartilhado das 4 telas auth) via `asset()`; `layouts/app.blade.php` sem `<img>`, sem logo, sem favicon/título MC (`LoginScreenIdentityTest`, `BrandIdentityComplianceTest` (d)).
- Método: as 4 telas renderizadas em Chromium headless nos 3 viewports (12 capturas full-page, inspecionadas) + geometria medida por `getBoundingClientRect()`/`naturalWidth` no caso "the Albuquerque and MC logos render proportionally…" de `ResponsiveIdentityTest` (3 viewports, 60 asserções — suíte passou de 9 casos / 237 para 12 casos / 297).

### 10.1 Medidas renderizadas (iguais nas 4 telas — layout compartilhado)

| Viewport | Card | Logo Albuquerque (h × w) | % do card | Proporção | Respiro logo → h1 | Logo MC (h × w) | Assinatura |
|---|---|---|---|---|---|---|---|
| 1440×900 (desktop) | 448 px | 72 × 221,8 px | 49,5 % | 3,081 (= 1063/345) | 16 px (`mb-4`) | 16 × 104,4 px | centro vertical da logo = centro do texto (Δ < 1,5 px) |
| 820×1180 (tablet) | 448 px | 72 × 221,8 px | 49,5 % | 3,081 | idem | 16 × 104,4 px | idem |
| 390×844 (mobile) | 358 px | 56 × 172,5 px | 48,2 % | 3,081 | idem | 16 × 104,4 px | idem |

### 10.2 Matriz — logos × 4 telas de autenticação

| # | Tela | Proporção (UI-17) | Radius / respiro | Tamanho responsivo (≤ 60 % do card) | Alinhamento | MC menor que Albuquerque e < 2× line-height (UI-16) | MC discreta, junto à assinatura | Sem overflow / erros JS |
|---|---|---|---|---|---|---|---|---|
| 1 | Login | OK — `w-auto` + `width="1063" height="345"`, sem distorção | OK — `rounded-md`, `mb-4` | OK — 56 px mobile / 72 px `sm:` | OK — centrada no card (Δx < 1 px) | OK — 16 px vs 56/72 px; line-height `text-xs` = 16 px → 16 < 32 | OK — `h-4`, `text-text-muted` (#6B7280), abaixo do card | OK |
| 2 | Esqueci minha senha | OK | OK | OK | OK | OK | OK | OK |
| 3 | Redefinir senha | OK | OK | OK | OK | OK | OK | OK |
| 4 | Primeiro acesso | OK | OK | OK | OK | OK | OK | OK |

- Hierarquia: logo Albuquerque → nome (`h1`) → card → assinatura MC; a logo MC ocupa 104×16 px contra 222×72 px da Albuquerque (≈ 10 % da área) — sem disputa 50/50 (§14, AC-52.6).
- `npm run build`: exit 0 (utilities `h-14`, `sm:h-[72px]`, `h-4`, `w-auto`, `rounded-md` presentes no CSS gerado).
- Histórico: `git log --oneline -- public/images resources/views/auth/login.blade.php` — os commits de T27 (assets) e T28 (`<img>`) são os últimos da branch, posteriores às Etapas 2–8 (UI-20).
