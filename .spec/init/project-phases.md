# Sistema de Solicitações e Compras — Project Phases

<!-- inputs: project-description.md@sha256:0506deb15094 user-stories.md@sha256:6fd622675450 database-schema.md@sha256:7334b80fc639 -->

## Overview

O build segue **fundação primeiro, depois fluxos de produto**: banco de dados e seeds de lookup (Fase
2) → tipos e regras de negócio do pedido (Fase 3, modelos e relações completos) → autenticação e RLS
(Fase 4) → design system compartilhado (Fase 5) → os três fluxos de perfil — Obra (Fase 6), Suprimentos
(Fase 7), Gestão (Fase 8) — → dados de demonstração (Fase 9) → validação ponta a ponta do roteiro
oficial (Fase 10). São **10 fases** (24 sub-fases), totalizando 78 tarefas. Como o PRD define a V0
Demo como um único milestone funcional e apresentável (seção 35), **todo este documento é a V0 Demo**
— não há corte de MVP menor dentro dele; V1 Pré-produção e Produção ficam fora deste plano.
`.spec/init/design/` não existe nesta rodada: por decisão do desenvolvedor, toda tarefa de
tela/componente usa como referência a barreira de qualidade visual do PRD §36 (hierarquia clara,
navegação simples, estados de loading/vazio, feedback de sucesso/erro, Kanban legível, responsividade)
em vez de um mockup específico. Cada fase e sub-fase é referenciável por número (`Phase 7`,
`Phase 7.2`) ao ser entregue a um agente de IA.

**Conventions:**
- `[ ]` pending · `[x]` done in the codebase.
- Phases and sub-phases are numbered (`Phase 1`, `Phase 5.3`) for reference by AI agents.
- Business-logic tasks list the **feature tests** to generate; frontend-only tasks list validatable **acceptance criteria** and a **Design ref**.

---

## Phase 1: Configuração do Projeto e Ferramentas

**Goal:** Scaffold da aplicação Next.js e do pipeline de qualidade sobre o qual todo o resto é construído. · **Depends on:** none · **Covers:** Tech Stack (project-description.md)

### Phase 1.1: Scaffold da Aplicação

- [ ] **Task:** Inicializar o projeto Next.js 16 (App Router) com TypeScript
  - **Acceptance criteria:**
    - `package.json` presente com Next.js 16 e TypeScript configurados; `next dev` sobe a aplicação sem erros.
    - Estrutura de pastas segue App Router (`app/`), com rotas segmentadas por perfil previstas (`app/obra`, `app/suprimentos`, `app/gestao`, `app/(auth)`).
    - `tsconfig.json` em modo `strict`.
  - **Traces:** Tech Stack (Next.js 16, TypeScript)

- [ ] **Task:** Configurar Tailwind CSS e shadcn/ui
  - **Acceptance criteria:**
    - Tailwind instalado e funcional (classe utilitária aplicada renderiza estilo).
    - shadcn/ui inicializado, com diretório de componentes (`components/ui`) e tema base configurado (tokens claros/escuros compatíveis com a paleta a ser usada nos badges de status/prioridade).
  - **Traces:** Tech Stack (Tailwind CSS, shadcn/ui)

- [ ] **Task:** Configurar ESLint, Prettier e checagem estrita de tipos
  - **Acceptance criteria:**
    - `npm run lint` e `npm run typecheck` executam sem erros sobre o scaffold inicial.
    - Regras cobrem TypeScript + React/Next.
  - **Traces:** Tech Stack (TypeScript), Requisito Não Funcional de Manutenibilidade (PRD §29)

### Phase 1.2: Testes e Integração com Supabase

- [ ] **Task:** Configurar Vitest para testes unitários e de integração
  - **Acceptance criteria:**
    - `npm run test` executa Vitest e passa com um teste de exemplo.
    - Configuração suporta testes de funções de serviço/domínio (Node) que serão adicionados nas fases seguintes.
  - **Traces:** Tech Stack (Vitest)

- [ ] **Task:** Configurar Playwright para testes E2E
  - **Acceptance criteria:**
    - `npx playwright test` executa contra a aplicação local (`baseURL` configurado) com um teste smoke de exemplo.
    - Projeto configurado para os fluxos de autenticação usados nas fases seguintes (armazenamento de estado de sessão por perfil).
  - **Traces:** Tech Stack (Playwright)

- [ ] **Task:** Configurar variáveis de ambiente e clients Supabase
  - **Acceptance criteria:**
    - `.env.example` lista `NEXT_PUBLIC_SUPABASE_URL`, `NEXT_PUBLIC_SUPABASE_ANON_KEY` e `SUPABASE_SERVICE_ROLE_KEY` (uso restrito a servidor).
    - Client factories implementadas para: client de browser (anon key), client de servidor (SSR, cookies de sessão) e client de service-role (apenas em rotinas administrativas/seed, nunca exposto ao browser).
    - Segredos de service-role nunca chegam ao bundle do cliente (verificável por inspeção do build).
  - **Traces:** Tech Stack (PostgreSQL via Supabase, Supabase Auth), Requisito de Segurança (PRD §30 — proteção de credenciais e secrets)

---

## Phase 2: Fundação de Banco de Dados — Migrations e Seeds de Lookup

**Goal:** Persistir o schema completo definido em database-schema.md e popular as tabelas de lookup. · **Depends on:** Phase 1 · **Covers:** todas as 9 tabelas do schema

### Phase 2.1: Migrations

- [ ] **Task:** Migration das tabelas de lookup `roles`, `statuses`, `priorities`, `event_types`
  - **Acceptance criteria:**
    - Cada tabela criada com as colunas do schema: `id uuid pk default gen_random_uuid()`, `name`, `slug unique not null`, `description` (quando aplicável), `is_active default true`, `created_at`/`updated_at`.
    - `statuses` e `priorities` incluem `sort_order integer not null unique`.
    - Migrations são reversíveis (down funcional).
  - **Traces:** table roles, table statuses, table priorities, table event_types

- [ ] **Task:** Migration da tabela `profiles`
  - **Acceptance criteria:**
    - Coluna `id uuid pk` (sem default — recebe o mesmo valor de `auth.users.id`), `full_name not null`, `role_id uuid ref roles.id not null`, `is_active default true`, `is_demo default false`, `created_at`/`updated_at`.
  - **Traces:** table profiles

- [ ] **Task:** Migration da tabela `obras`
  - **Acceptance criteria:**
    - Colunas `id uuid pk default gen_random_uuid()`, `name not null`, `is_active default true`, `is_demo default false`, `created_at`/`updated_at`.
  - **Traces:** table obras

- [ ] **Task:** Migration da tabela `pedidos`
  - **Acceptance criteria:**
    - Todas as colunas do schema presentes: `id`, `code unique not null`, `obra_id ref obras.id not null`, `requester_id ref profiles.id not null`, `requested_at not null default now()`, `needed_at date not null`, `items_description text not null`, `status_id ref statuses.id not null`, `priority_id ref priorities.id null`, `responsible_id ref profiles.id null`, `expected_delivery_at date null`, `is_demo default false`, `created_at`/`updated_at`.
  - **Traces:** table pedidos

- [ ] **Task:** Migration da tabela `pedido_events`
  - **Acceptance criteria:**
    - Colunas `id`, `pedido_id ref pedidos.id not null`, `event_type_id ref event_types.id not null`, `previous_value text null`, `new_value text null`, `actor_id ref profiles.id not null`, `created_at not null default now()`.
    - Sem colunas de atualização (a tabela é somente-inserção — histórico não é editado).
  - **Traces:** table pedido_events

- [ ] **Task:** Migration da tabela pivot `obra_profile`
  - **Acceptance criteria:**
    - Colunas `obra_id ref obras.id not null`, `profile_id ref profiles.id not null`, `created_at not null default now()`, chave primária composta `(obra_id, profile_id)`.
  - **Traces:** table obra_profile

- [ ] **Task:** Índices de suporte a consultas frequentes
  - **Acceptance criteria:**
    - Índices criados em `pedidos(obra_id, status_id)`, `pedidos(needed_at)` e `pedido_events(pedido_id, created_at)`, conforme recomendado em database-schema.md.
  - **Traces:** table pedidos, table pedido_events

- [ ] **Task:** Habilitar Row Level Security em todas as tabelas públicas
  - **Acceptance criteria:**
    - RLS habilitado (`ENABLE ROW LEVEL SECURITY`) em `roles`, `statuses`, `priorities`, `event_types`, `profiles`, `obras`, `pedidos`, `pedido_events`, `obra_profile`.
    - Sem nenhuma policy definida ainda nesta fase, o acesso via chave anônima/autenticada fica bloqueado por padrão (fail-closed) até a Fase 4.2 adicionar as policies.
  - **Traces:** table roles, table statuses, table priorities, table event_types, table profiles, table obras, table pedidos, table pedido_events, table obra_profile

### Phase 2.2: Seeds de Lookup

- [ ] **Task:** Seed da tabela `roles`
  - **Acceptance criteria:**
    - Linhas criadas para `obra`, `suprimentos`, `gestao` com slugs exatamente esses valores; seed idempotente (reexecutar não duplica linhas).
  - **Traces:** table roles

- [ ] **Task:** Seed da tabela `statuses`
  - **Acceptance criteria:**
    - Linhas criadas: `solicitado` (sort_order 1), `em_analise` (2), `em_compra_preparacao` (3), `aguardando_entrega` (4), `entregue` (5), `cancelado` (6); seed idempotente.
  - **Traces:** table statuses, workflow 3. Triagem e Condução Operacional do Pedido (Suprimentos)

- [ ] **Task:** Seed da tabela `priorities`
  - **Acceptance criteria:**
    - Linhas criadas: `baixa` (sort_order 1), `normal` (2), `alta` (3), `urgente` (4); seed idempotente.
  - **Traces:** table priorities

- [ ] **Task:** Seed da tabela `event_types`
  - **Acceptance criteria:**
    - Linhas criadas: `criacao_pedido`, `mudanca_status`, `alteracao_responsavel`, `alteracao_prioridade`, `alteracao_previsao`, `cancelamento`, `entrega`; seed idempotente.
  - **Traces:** table event_types

---

## Phase 3: Camada de Domínio — Tipos, Serviços e Regras de Negócio do Pedido

**Goal:** Sair da fundação com modelos e relações completos e toda a lógica de negócio do pedido implementada e testada, pronta para as fases de UI consumirem. · **Depends on:** Phase 2 · **Covers:** todas as 9 tabelas (relações), US-2.1, US-3.2 a US-3.7, US-5.1, US-6.1, US-8.1

### Phase 3.1: Tipos e Acesso a Dados

- [ ] **Task:** Definir tipos TypeScript para todas as tabelas e suas relações
  - **Acceptance criteria:**
    - Tipos gerados/definidos para `roles`, `statuses`, `priorities`, `event_types`, `profiles`, `obras`, `pedidos`, `pedido_events`, `obra_profile`, com as relações expressas no tipo (ex.: `Pedido` inclui `obra`, `requester`, `responsible?`, `status`, `priority?` quando expandido via join).
    - Tipos usados por toda a camada de serviço e componentes das fases seguintes (nenhuma leitura de tabela sem tipo associado).
  - **Traces:** table roles, table statuses, table priorities, table event_types, table profiles, table obras, table pedidos, table pedido_events, table obra_profile

- [ ] **Task:** Implementar funções de leitura (queries) com relações resolvidas
  - **Acceptance criteria:**
    - Função para listar pedidos com `obra`, `status`, `priority`, `requester`, `responsible` já resolvidos (join), aceitando filtros (obra, responsável, prioridade, status, período, data necessária, atraso, texto).
    - Função para listar as obras acessíveis a um `profile` (via `obra_profile` quando role = obra; todas as obras quando role = suprimentos/gestao).
    - Função para buscar um pedido por `id`/`code` com relações resolvidas e eventos de histórico ordenados cronologicamente.
  - **Traces:** table pedidos, table obras, table obra_profile, table pedido_events

### Phase 3.2: Serviço de Domínio do Pedido

- [ ] **Task:** Implementar gerador de identificador único do pedido (`code`)
  - **Acceptance criteria:**
    - Gera códigos no formato `PED-######` (sequencial, ex.: `PED-000001`), estável e exibível ao usuário.
    - Seguro sob criação concorrente (sequência de banco ou retry-on-conflict em `unique`).
  - **Feature tests:**
    - Criações concorrentes (chamadas simultâneas) nunca produzem `code` duplicado (constraint `unique` nunca é violada de forma observável ao chamador — falha tratada com retry).
    - Formato do código gerado sempre casa com `^PED-\d{6}$`.
  - **Traces:** table pedidos, US-2.1

- [ ] **Task:** Implementar `createPedido`
  - **Acceptance criteria:**
    - Recebe `obra_id`, `needed_at`, `items_description` e o `profile` autenticado (Obra) como solicitante; rejeita se algum campo obrigatório estiver ausente.
    - Rejeita se `obra_id` não estiver entre as obras associadas ao `profile` solicitante (via `obra_profile`).
    - Persiste o pedido com `status_id` = status de `sort_order` mínimo (`solicitado`), `requester_id` = profile atual, `requested_at` = `now()`, `code` gerado pela tarefa anterior.
    - Insere, na mesma transação, um evento em `pedido_events` do tipo `criacao_pedido` (actor = solicitante, `new_value` refletindo o pedido criado).
    - Se a inserção do evento falhar, a criação do pedido é revertida (atomicidade — nunca existe pedido sem seu evento de criação).
  - **Feature tests:**
    - Criação válida resulta em pedido com status `solicitado` e `code` atribuído.
    - Criação sem `obra_id`, `needed_at` ou `items_description` é rejeitada.
    - Criação para uma obra fora do escopo do solicitante é rejeitada.
    - Toda criação bem-sucedida produz exatamente um `pedido_events` do tipo `criacao_pedido`.
  - **Traces:** table pedidos, table pedido_events, US-2.1, US-6.1

- [ ] **Task:** Implementar `updatePedidoResponsavel`
  - **Acceptance criteria:**
    - Apenas `profile` com role `suprimentos` pode executar; qualquer outro ator é rejeitado.
    - Atualiza `responsible_id` e insere evento `alteracao_responsavel` com `previous_value`/`new_value` (identificadores do responsável anterior/novo).
    - Definir o mesmo responsável já atribuído (no-op) não gera evento adicional.
  - **Feature tests:**
    - Suprimentos altera o responsável: `pedidos.responsible_id` atualizado e um evento `alteracao_responsavel` criado com valores corretos.
    - Ator com role `obra` ou `gestao` é rejeitado.
    - Reatribuir o mesmo responsável não cria evento.
  - **Traces:** table pedidos, table pedido_events, US-3.2, US-6.1

- [ ] **Task:** Implementar `updatePedidoPrioridade`
  - **Acceptance criteria:**
    - Apenas `suprimentos` pode executar; `priority_id` deve ser um dos 4 valores válidos (`baixa`, `normal`, `alta`, `urgente`).
    - Atualiza `priority_id` e insere evento `alteracao_prioridade` com `previous_value`/`new_value`.
    - Mesma prioridade (no-op) não gera evento.
  - **Feature tests:**
    - Alteração válida de prioridade persiste e gera evento com valores corretos.
    - Ator não-suprimentos é rejeitado.
    - Valor de prioridade inválido/inexistente é rejeitado.
  - **Traces:** table pedidos, table pedido_events, US-3.3, US-6.1

- [ ] **Task:** Implementar `updatePedidoPrevisao`
  - **Acceptance criteria:**
    - Apenas `suprimentos` pode executar.
    - Atualiza `expected_delivery_at` e insere evento `alteracao_previsao` com `previous_value`/`new_value` (datas).
    - Mesma data (no-op) não gera evento.
  - **Feature tests:**
    - Definir/alterar previsão persiste e gera evento com as datas corretas.
    - Ator não-suprimentos é rejeitado.
  - **Traces:** table pedidos, table pedido_events, US-3.4, US-6.1

- [ ] **Task:** Implementar `updatePedidoStatus` (transição de workflow)
  - **Acceptance criteria:**
    - Apenas `suprimentos` pode executar.
    - Permite transição entre quaisquer dois dos 4 status ativos não finais (`solicitado`, `em_analise`, `em_compra_preparacao`, `aguardando_entrega`) e para `entregue`.
    - Transição para `entregue` insere evento do tipo `entrega` (não `mudanca_status`); qualquer outra transição válida insere evento `mudanca_status`, ambos com `previous_value`/`new_value` = slugs do status anterior/novo.
    - Rejeita definir `status_id` = `cancelado` por esta função (cancelamento só ocorre via `cancelPedido`, tarefa seguinte).
    - Rejeita qualquer alteração de status quando o pedido já está em `entregue` ou `cancelado` (estados terminais e irreversíveis na V0).
  - **Feature tests:**
    - Transição entre status ativos não finais persiste e gera evento `mudanca_status` com valores corretos.
    - Transição para `entregue` persiste e gera evento do tipo `entrega`.
    - Tentativa de definir `cancelado` via esta função é rejeitada.
    - Tentativa de alterar status de pedido já `entregue` ou `cancelado` é rejeitada.
    - Ator não-suprimentos é rejeitado.
  - **Traces:** table pedidos, table pedido_events, US-3.5, US-3.6, US-3.7, US-6.1

- [ ] **Task:** Implementar `cancelPedido`
  - **Acceptance criteria:**
    - Apenas `suprimentos` pode executar; Obra e Gestão são sempre rejeitados.
    - Permite cancelar a partir de qualquer status ativo não final; rejeita cancelar um pedido já `entregue` ou já `cancelado`.
    - Atualiza `status_id` para `cancelado` e insere evento `cancelamento` (actor, data/hora).
    - Ação é irreversível: não existe função que retorne um pedido `cancelado` a um status ativo.
  - **Feature tests:**
    - Cancelamento de pedido ativo persiste `status_id = cancelado` e gera evento `cancelamento`.
    - Cancelar pedido já `entregue` é rejeitado.
    - Cancelar pedido já `cancelado` é rejeitado.
    - Ator não-suprimentos é rejeitado.
  - **Traces:** table pedidos, table pedido_events, US-5.1, US-6.1

- [ ] **Task:** Implementar cálculo de atraso (`isPedidoAtrasado` e filtro de atraso em queries)
  - **Acceptance criteria:**
    - Pedido é atrasado quando `needed_at < data atual` **e** `status.slug` não é `entregue` **e** não é `cancelado`.
    - Cálculo é feito em tempo de consulta (não persistido), garantindo que Kanban, listagens, filtros e dashboard usem sempre a mesma lógica (reutilizando a mesma função/expressão SQL).
  - **Feature tests:**
    - Pedido com `needed_at` no passado e status ativo (não entregue/cancelado) → atrasado = true.
    - Pedido com `needed_at` no passado e status `entregue` → atrasado = false.
    - Pedido com `needed_at` no passado e status `cancelado` → atrasado = false.
    - Pedido com `needed_at` hoje ou no futuro → atrasado = false independentemente do status.
  - **Traces:** table pedidos, US-8.1

---

## Phase 4: Autenticação e Autorização

**Goal:** Login funcional por perfil e aplicação da matriz de permissões (PRD §7) no backend e nos dados, não apenas na interface. · **Depends on:** Phase 2, Phase 3 · **Covers:** US-1.1, US-1.2, US-2.2, US-6.1

### Phase 4.1: Login, Sessão e Provisionamento de Perfil

- [ ] **Task:** Implementar login por e-mail/senha via Supabase Auth
  - **Acceptance criteria:**
    - Formulário de login com e-mail e senha; sucesso redireciona para a área inicial do perfil correspondente (Obra/Suprimentos/Gestão).
    - Credenciais inválidas exibem mensagem de erro e não concedem sessão.
    - Nenhuma tela de autocadastro (self-signup) é exposta — provisionamento só ocorre via seed controlado.
  - **Feature tests:**
    - Login com credenciais válidas estabelece sessão e redireciona conforme o perfil do usuário.
    - Login com credenciais inválidas não cria sessão e exibe erro.
  - **Design ref:** PRD §36 (baseline visual) — sem `.spec/init/design/` fornecido
  - **Traces:** US-1.1

- [ ] **Task:** Provisionar `profiles` automaticamente a partir de `auth.users`
  - **Acceptance criteria:**
    - Ao criar um usuário em `auth.users` (via seed/admin), uma linha correspondente em `profiles` é criada automaticamente (trigger ou rotina de provisionamento), com `id` igual ao `auth.users.id`.
    - `role_id` e `full_name` são atribuídos no momento do provisionamento (fornecidos pelo seed/admin), nunca escolhidos pelo próprio usuário.
  - **Feature tests:**
    - Criar um usuário de autenticação produz exatamente uma linha em `profiles` com o `role_id` esperado.
  - **Traces:** table profiles, US-1.1

- [ ] **Task:** Middleware de sessão e proteção de rotas
  - **Acceptance criteria:**
    - Qualquer rota fora do grupo de autenticação exige sessão válida; sem sessão, o usuário é redirecionado para o login.
    - Sessão expirada/inválida é tratada de forma segura (sem vazamento de dados antes do redirecionamento).
  - **Feature tests:**
    - Requisição não autenticada a uma rota protegida é redirecionada para o login.
  - **Traces:** US-1.1

### Phase 4.2: Row Level Security e Guarda de Rotas por Perfil

- [ ] **Task:** Policies de RLS para `obras`
  - **Acceptance criteria:**
    - `SELECT`: profile com role `obra` só enxerga obras associadas via `obra_profile`; roles `suprimentos` e `gestao` enxergam todas.
    - Nenhuma policy de escrita concede `INSERT`/`UPDATE`/`DELETE` a usuários finais (gestão de obras é administrativa/seed).
  - **Feature tests:**
    - Profile Obra consulta apenas suas obras associadas; consulta a uma obra não associada retorna vazio.
    - Profile Suprimentos e Gestão consultam todas as obras.
  - **Traces:** table obras, table obra_profile, US-1.2

- [ ] **Task:** Policies de RLS para `pedidos`
  - **Acceptance criteria:**
    - `SELECT`: Obra só vê pedidos das obras associadas a ela; Suprimentos e Gestão veem todos.
    - `INSERT`: apenas Obra, e apenas para obras às quais está associada (reforça `createPedido`).
    - `UPDATE`: apenas Suprimentos pode atualizar `status_id`, `priority_id`, `responsible_id`, `expected_delivery_at`; nenhuma policy permite a Obra alterar `obra_id`, `needed_at`, `items_description`, `requester_id` após a criação; Gestão nunca tem `UPDATE`.
    - `DELETE`: nenhuma policy concede exclusão de pedidos a nenhum perfil.
  - **Feature tests:**
    - Obra consulta apenas pedidos de suas obras.
    - Obra tenta alterar `needed_at`/`items_description`/`obra_id` de um pedido próprio após a criação → rejeitado pelo banco.
    - Suprimentos atualiza `status_id`/`priority_id`/`responsible_id`/`expected_delivery_at` de qualquer pedido com sucesso.
    - Gestão tenta qualquer `UPDATE` em `pedidos` → rejeitado pelo banco.
  - **Traces:** table pedidos, US-1.2, US-2.2

- [ ] **Task:** Policies de RLS para `pedido_events`
  - **Acceptance criteria:**
    - `SELECT`: permitido a qualquer profile que possa ler o `pedido` pai (herda o escopo de `pedidos`).
    - `INSERT`: não permitido diretamente por clientes finais — eventos só são inseridos pelas funções de serviço da Fase 3.2, executadas em contexto de servidor.
  - **Feature tests:**
    - Obra lê o histórico de um pedido próprio; não lê histórico de pedido de obra não associada.
    - Tentativa de `INSERT` direto em `pedido_events` a partir do client autenticado (fora das funções de serviço) é rejeitada.
  - **Traces:** table pedido_events, US-1.2, US-6.1

- [ ] **Task:** Policies de RLS para `obra_profile`
  - **Acceptance criteria:**
    - `SELECT`: um profile lê suas próprias associações; Suprimentos e Gestão leem todas.
    - `INSERT`/`UPDATE`/`DELETE`: não concedidos a usuários finais — associações são geridas via seed/admin (service role).
  - **Feature tests:**
    - Profile Obra lê apenas suas próprias linhas em `obra_profile`.
    - Tentativa de `INSERT` direto por um usuário final é rejeitada.
  - **Traces:** table obra_profile, US-1.2

- [ ] **Task:** Guarda de rotas por perfil no backend (autorização de aplicação)
  - **Acceptance criteria:**
    - Cada grupo de rotas (`/obra`, `/suprimentos`, `/gestao`) verifica o `role` do profile autenticado no servidor antes de renderizar/atender a requisição — nunca depende apenas de esconder links na UI.
    - Acesso de um perfil a rotas de outro perfil resulta em bloqueio (redirecionamento/403), verificado no servidor.
  - **Feature tests:**
    - Requisição de um profile Obra a uma rota `/suprimentos/**` é bloqueada no servidor.
    - Requisição de um profile Gestão a uma ação de escrita exposta em `/suprimentos/**` é bloqueada no servidor.
  - **Traces:** US-1.2

---

## Phase 5: Fundação de Frontend — Design System e Componentes Compartilhados

**Goal:** Construir a casca visual e os componentes reutilizáveis pelos três fluxos de perfil, com qualidade suficiente para demonstração (PRD §36). · **Depends on:** Phase 1, Phase 4 · **Covers:** US-4.2, US-6.2

### Phase 5.1: Shell, Navegação e Primitivos de UI

- [ ] **Task:** Layout raiz e tema visual
  - **Acceptance criteria:**
    - Layout raiz aplica fontes, espaçamento e tokens de tema consistentes em todas as rotas.
    - Responsivo em largura de desktop e mobile (uso em campo de obra, PRD §29 Responsividade).
  - **Design ref:** PRD §36 (baseline visual) — sem `.spec/init/design/` fornecido
  - **Traces:** Requisito Não Funcional de Responsividade (PRD §29)

- [ ] **Task:** Navegação por perfil com logout
  - **Acceptance criteria:**
    - Menu de navegação mostra apenas os itens relevantes ao perfil autenticado (Obra: Nova Solicitação, Meus Pedidos; Suprimentos: Kanban, Todos os Pedidos; Gestão: Dashboard, Kanban, Todos os Pedidos).
    - Ação de logout visível e funcional em todas as telas autenticadas.
    - Nome/perfil do usuário atual exibido na navegação.
  - **Design ref:** PRD §36 (baseline visual) — sem `.spec/init/design/` fornecido
  - **Traces:** US-1.1

- [ ] **Task:** Instalar e configurar primitivos shadcn/ui necessários aos fluxos
  - **Acceptance criteria:**
    - Componentes disponíveis e estilizados: Button, Input, Select, Textarea, date picker, Badge, Card, Dialog/Sheet (confirmação de ações destrutivas), Table, Tabs, Toast.
    - Todos consistentes com o tema definido na tarefa de layout raiz.
  - **Traces:** Tech Stack (shadcn/ui)

### Phase 5.2: Componentes Compartilhados de Domínio

- [ ] **Task:** Componente `StatusBadge`
  - **Acceptance criteria:**
    - Recebe um `status` e renderiza rótulo e cor consistentes; mesma aparência usada no Kanban, em listagens e no detalhe do pedido.
    - Cobre os 6 valores de `statuses` (incluindo `cancelado`, com tratamento visual distinto dos status ativos).
  - **Design ref:** PRD §36 (baseline visual, tratamento visual claro por status) — sem `.spec/init/design/` fornecido
  - **Traces:** table statuses, workflow 3. Triagem e Condução Operacional do Pedido (Suprimentos)

- [ ] **Task:** Componente `PriorityBadge`
  - **Acceptance criteria:**
    - Recebe uma `priority` e renderiza rótulo e cor consistentes para os 4 níveis (`baixa`, `normal`, `alta`, `urgente`), mesma aparência em Kanban, listagens e detalhe.
  - **Design ref:** PRD §36 (baseline visual, tratamento visual claro por prioridade) — sem `.spec/init/design/` fornecido
  - **Traces:** table priorities, US-3.3

- [ ] **Task:** Componente `AtrasoIndicator`
  - **Acceptance criteria:**
    - Recebe um pedido (ou o resultado de `isPedidoAtrasado`) e exibe um indicador visual distinto quando atrasado, consistente em Kanban, listagens e dashboard.
  - **Design ref:** PRD §36 (baseline visual, tratamento visual claro para pedidos atrasados) — sem `.spec/init/design/` fornecido
  - **Traces:** US-8.1

- [ ] **Task:** Estados de carregamento (skeletons) e estado vazio reutilizáveis
  - **Acceptance criteria:**
    - Componentes de skeleton disponíveis para listagens, cards de Kanban e indicadores de dashboard.
    - Componente de estado vazio reutilizável (ex.: "nenhum pedido encontrado") com mensagem contextual configurável.
  - **Design ref:** PRD §36 (estados de loading e vazios) — sem `.spec/init/design/` fornecido
  - **Traces:** Requisito de Usabilidade (PRD §36)

- [ ] **Task:** Padrão de feedback de sucesso/erro (toast)
  - **Acceptance criteria:**
    - Toast de sucesso e de erro reutilizável, disparado após qualquer mutação (criação de pedido, alteração de responsável/prioridade/previsão/status, cancelamento).
    - Mensagens de erro nunca expõem detalhes técnicos sensíveis ao usuário final.
  - **Design ref:** PRD §36 (feedback de sucesso/erro) — sem `.spec/init/design/` fornecido
  - **Traces:** Requisito de Usabilidade (PRD §36)

- [ ] **Task:** Componente `PedidoHistoryTimeline`
  - **Acceptance criteria:**
    - Recebe a lista de `pedido_events` de um pedido e renderiza em ordem cronológica: tipo de evento (rótulo legível), valor anterior, valor novo, autor e data/hora.
    - Reutilizado, sem alteração de comportamento, nas telas de detalhe de Obra, Suprimentos e Gestão.
  - **Design ref:** PRD §19 (linha do tempo de histórico), PRD §36 (baseline visual) — sem `.spec/init/design/` fornecido
  - **Traces:** table pedido_events, table event_types, US-6.2

- [ ] **Task:** Componente `PedidoDetailLayout`
  - **Acceptance criteria:**
    - Estrutura o detalhe do pedido nas três seções do PRD §19: Solicitação (identificador, obra, solicitante, data da solicitação, data necessária, itens/quantidades — sempre somente leitura), Operação (status, prioridade, responsável, previsão, condição de atraso — editável apenas quando o perfil for Suprimentos) e Histórico (usa `PedidoHistoryTimeline`).
    - Aceita um modo `readOnly` que oculta todos os controles de edição, usado pelos perfis Obra e Gestão.
  - **Design ref:** PRD §19 (Detalhe do Pedido), PRD §36 (baseline visual) — sem `.spec/init/design/` fornecido
  - **Traces:** table pedidos, US-4.2, US-6.2

---

## Phase 6: Fluxo da Obra — Solicitação e Acompanhamento

**Goal:** Obra consegue criar uma solicitação e acompanhar seus pedidos ponta a ponta. · **Depends on:** Phase 3, Phase 4, Phase 5 · **Covers:** US-2.1, US-2.2, US-4.1, US-4.2

### Phase 6.1: Nova Solicitação

- [ ] **Task:** Ponto de entrada "+ Nova Solicitação"
  - **Acceptance criteria:**
    - Ação "+ Nova Solicitação" visível e acessível na área da Obra, levando ao formulário de criação.
  - **Design ref:** PRD §10 (ação evidente "+ Nova Solicitação"), PRD §36 — sem `.spec/init/design/` fornecido
  - **Traces:** US-2.1

- [ ] **Task:** Formulário de Nova Solicitação
  - **Acceptance criteria:**
    - Campos: obra (select restrito às obras associadas ao usuário via `obra_profile`), data necessária (date picker), itens/quantidades (texto livre multilinha).
    - Data da solicitação não é exibida como campo editável (é definida automaticamente pelo sistema no envio).
    - Envio bloqueado no cliente se algum campo obrigatório estiver vazio; ao confirmar, chama `createPedido` (Fase 3.2).
    - Erro de criação (ex.: obra fora de escopo, falha de rede) exibe feedback de erro sem perder os dados preenchidos.
  - **Feature tests:**
    - Formulário lista apenas as obras associadas ao usuário autenticado no select de obra.
  - **Design ref:** PRD §10 (campos da Nova Solicitação), PRD §36 — sem `.spec/init/design/` fornecido
  - **Traces:** table obras, US-2.1

- [ ] **Task:** Confirmação de criação com identificador do pedido
  - **Acceptance criteria:**
    - Após envio bem-sucedido, a tela exibe o `code` do pedido criado e um caminho claro para acompanhá-lo (link para o detalhe/listagem).
  - **Design ref:** PRD §11 (identificador do pedido), PRD §36 — sem `.spec/init/design/` fornecido
  - **Traces:** table pedidos, US-2.1

### Phase 6.2: Acompanhamento da Obra

- [ ] **Task:** Listagem "Meus Pedidos"
  - **Acceptance criteria:**
    - Lista pedidos das obras associadas ao usuário Obra autenticado, mostrando identificador, obra, status, prioridade, responsável, previsão de entrega e condição de atraso (via `AtrasoIndicator`).
    - Usa os componentes de loading/estado vazio da Fase 5.2.
  - **Design ref:** PRD §36 (listagens legíveis) — sem `.spec/init/design/` fornecido
  - **Traces:** table pedidos, US-4.1

- [ ] **Task:** Detalhe do pedido para a Obra
  - **Acceptance criteria:**
    - Usa `PedidoDetailLayout` em modo `readOnly`; inclui a timeline de histórico completa.
    - Acesso restrito a pedidos das obras associadas ao usuário (reforçado pela RLS da Fase 4.2; a UI nunca tenta buscar fora do escopo).
  - **Design ref:** PRD §19 (Detalhe do Pedido) — sem `.spec/init/design/` fornecido
  - **Traces:** table pedidos, US-4.2, US-6.2

- [ ] **Task:** Exibição somente-leitura da solicitação original após envio
  - **Acceptance criteria:**
    - Campos da solicitação original (obra, data necessária, itens/quantidades) aparecem sempre em modo leitura para o perfil Obra, sem nenhum controle de edição disponível na UI.
  - **Design ref:** PRD §19 (seção Solicitação) — sem `.spec/init/design/` fornecido
  - **Traces:** table pedidos, US-2.2

---

## Phase 7: Fluxo de Suprimentos — Kanban e Operação

**Goal:** Suprimentos consegue conduzir operacionalmente todos os pedidos através do Kanban, da atribuição de responsável/prioridade/previsão, da movimentação de status e do cancelamento. · **Depends on:** Phase 3, Phase 4, Phase 5 · **Covers:** US-3.1 a US-3.7, US-5.1, US-5.2

### Phase 7.1: Listagem e Kanban

- [ ] **Task:** Listagem "Todos os Pedidos" com filtros e busca
  - **Acceptance criteria:**
    - Lista pedidos de todas as obras com colunas: identificador, obra, status, prioridade, responsável, data necessária, previsão, atraso.
    - Filtros combináveis por obra, responsável, prioridade, status, período e data necessária; inclui alternância para incluir/excluir atrasados.
    - Busca textual localiza pedidos por identificador, obra ou conteúdo de itens/quantidades.
  - **Feature tests:**
    - Combinação de dois ou mais filtros (ex.: obra + status) retorna apenas os pedidos que satisfazem todos os critérios simultaneamente.
    - Filtro de atraso retorna exatamente os pedidos para os quais `isPedidoAtrasado` é verdadeiro.
  - **Design ref:** PRD §17, §25 (listagem e filtros de Suprimentos), PRD §36 — sem `.spec/init/design/` fornecido
  - **Traces:** table pedidos, US-8.1

- [ ] **Task:** Kanban board de Suprimentos
  - **Acceptance criteria:**
    - Exibe as 5 colunas do workflow ativo (`solicitado`, `em_analise`, `em_compra_preparacao`, `aguardando_entrega`, `entregue`), ordenadas por `statuses.sort_order`; pedidos `cancelado` não aparecem nas colunas.
    - Cada coluna lista os pedidos correspondentes ao seu status, com paginação/scroll adequado a muitos cards.
  - **Design ref:** PRD §18 (Kanban) — sem `.spec/init/design/` fornecido
  - **Traces:** table pedidos, table statuses, US-3.1

- [ ] **Task:** Card do Kanban
  - **Acceptance criteria:**
    - Exibe identificador, obra, resumo da necessidade, data necessária, `PriorityBadge`, responsável, previsão de entrega e `AtrasoIndicator`.
    - Tratamento visual claro para prioridade, atraso e status conforme componentes da Fase 5.2.
  - **Design ref:** PRD §18 (conteúdo do card) — sem `.spec/init/design/` fornecido
  - **Traces:** table pedidos, US-3.1

### Phase 7.2: Ações Operacionais no Pedido

- [ ] **Task:** Controle de definição de responsável
  - **Acceptance criteria:**
    - Disponível no detalhe do pedido (e/ou no card); seleciona entre profiles com role `suprimentos`; chama `updatePedidoResponsavel`.
    - Após sucesso, o novo responsável aparece imediatamente no card, na listagem e no detalhe.
  - **Feature tests:**
    - Alterar o responsável pela UI persiste a mudança e um novo evento aparece na timeline do pedido.
  - **Design ref:** PRD §14 (Responsável) — sem `.spec/init/design/` fornecido
  - **Traces:** table pedidos, US-3.2

- [ ] **Task:** Controle de definição de prioridade
  - **Acceptance criteria:**
    - Disponível no detalhe do pedido (e/ou no card); seleciona entre os 4 níveis de prioridade; chama `updatePedidoPrioridade`.
    - Após sucesso, a nova prioridade aparece imediatamente no card, na listagem e no detalhe.
  - **Feature tests:**
    - Alterar a prioridade pela UI persiste a mudança e um novo evento aparece na timeline do pedido.
  - **Design ref:** PRD §13 (Prioridade) — sem `.spec/init/design/` fornecido
  - **Traces:** table pedidos, US-3.3

- [ ] **Task:** Controle de registro/alteração da previsão de entrega
  - **Acceptance criteria:**
    - Date picker disponível no detalhe do pedido (e/ou no card); chama `updatePedidoPrevisao`.
    - Após sucesso, a nova previsão aparece imediatamente no card, na listagem, no detalhe e é consultável pela Obra.
  - **Feature tests:**
    - Alterar a previsão pela UI persiste a mudança e um novo evento aparece na timeline do pedido.
  - **Design ref:** PRD §15 (Previsão de Entrega) — sem `.spec/init/design/` fornecido
  - **Traces:** table pedidos, US-3.4

- [ ] **Task:** Movimentação de status via drag-and-drop no Kanban
  - **Acceptance criteria:**
    - Arrastar um card entre colunas ativas chama `updatePedidoStatus`; falha na chamada reverte a posição visual do card e exibe erro.
    - Nova posição refletida em listagem, detalhe e dashboard sem exigir recarregamento manual da página.
  - **Feature tests:**
    - Mover um card via drag-and-drop persiste o novo status e um novo evento `mudanca_status` (ou `entrega`, se aplicável) aparece na timeline do pedido.
  - **Design ref:** PRD §18 (Movimentação) — sem `.spec/init/design/` fornecido
  - **Traces:** table pedidos, table pedido_events, US-3.5

- [ ] **Task:** Alternativa acessível de mudança de status (sem drag-and-drop)
  - **Acceptance criteria:**
    - Controle explícito (ex.: seletor) disponível no card e/ou no detalhe do pedido, oferecendo a mesma sequência de status do workflow oficial.
    - Resultado idêntico ao drag-and-drop: persiste via `updatePedidoStatus`, reflete nas demais visualizações e gera o mesmo tipo de evento.
  - **Feature tests:**
    - Alterar o status pelo controle acessível produz o mesmo estado final e o mesmo evento de histórico que a movimentação por drag-and-drop equivalente.
  - **Design ref:** PRD §18 ("alternativa acessível para alteração de status") — sem `.spec/init/design/` fornecido
  - **Traces:** table pedidos, US-3.6

- [ ] **Task:** Ação "Marcar como Entregue"
  - **Acceptance criteria:**
    - Disponível a partir de `aguardando_entrega` (ou do status ativo em que o pedido esteja); chama `updatePedidoStatus` para `entregue`.
    - Após sucesso, o pedido deixa de contar como pendente/atrasado nas visualizações que dependem dessas regras.
  - **Feature tests:**
    - Marcar como Entregue persiste `status = entregue`, gera evento do tipo `entrega` e o pedido some da contagem de "Atrasados"/"Pendentes" no dashboard.
  - **Design ref:** PRD §9.5 (estado final Entregue) — sem `.spec/init/design/` fornecido
  - **Traces:** table pedidos, table pedido_events, US-3.7

### Phase 7.3: Cancelamento

- [ ] **Task:** Ação de cancelamento de pedido
  - **Acceptance criteria:**
    - Disponível apenas para Suprimentos, com diálogo de confirmação (ação irreversível); não exposta a Obra nem Gestão.
    - Chama `cancelPedido`; após sucesso, o pedido sai imediatamente das colunas ativas do Kanban.
  - **Feature tests:**
    - Cancelar um pedido pela UI persiste `status = cancelado`, gera evento `cancelamento` e remove o pedido das colunas ativas do Kanban.
  - **Design ref:** PRD §21 (Cancelamento) — sem `.spec/init/design/` fornecido
  - **Traces:** table pedidos, table pedido_events, US-5.1

- [ ] **Task:** Consulta de pedidos cancelados
  - **Acceptance criteria:**
    - Listagem/filtro por status `cancelado` disponível para Suprimentos e Gestão; detalhe do pedido cancelado permanece acessível com histórico completo.
    - Pedidos cancelados não contam como pendentes nem como atrasados em nenhuma visualização.
  - **Design ref:** PRD §21 — sem `.spec/init/design/` fornecido
  - **Traces:** table pedidos, US-5.2

---

## Phase 8: Dashboard Gerencial — Gestão

**Goal:** Gestão consegue entender a operação através de indicadores consolidados, com filtros, drill-down e visão de Kanban em leitura. · **Depends on:** Phase 3, Phase 4, Phase 5, Phase 7 · **Covers:** US-7.1 a US-7.4, US-6.2

### Phase 8.1: Indicadores

- [ ] **Task:** Layout do dashboard de Gestão
  - **Acceptance criteria:**
    - Página organiza os indicadores priorizando leitura rápida, situação atual e exceções (PRD §27), com os componentes de loading da Fase 5.2 enquanto os dados carregam.
  - **Design ref:** PRD §22, §27 (Dashboard Gerencial), PRD §36 — sem `.spec/init/design/` fornecido
  - **Traces:** US-7.1

- [ ] **Task:** Indicador "Volume total"
  - **Acceptance criteria:**
    - Exibe a contagem total de pedidos no escopo/período selecionado.
  - **Feature tests:**
    - Valor exibido é igual à contagem real de pedidos que satisfazem o escopo/filtros aplicados.
  - **Design ref:** PRD §22 — sem `.spec/init/design/` fornecido
  - **Traces:** table pedidos, US-7.1

- [ ] **Task:** Indicador "Pendentes"
  - **Acceptance criteria:**
    - Pendente = pedido cujo status não é `entregue` nem `cancelado` (ambos são estados de conclusão do fluxo).
  - **Feature tests:**
    - Valor exibido exclui exatamente os pedidos `entregue` e `cancelado` do total.
  - **Design ref:** PRD §22 — sem `.spec/init/design/` fornecido
  - **Traces:** table pedidos, table statuses, US-7.1

- [ ] **Task:** Indicador "Atrasados"
  - **Acceptance criteria:**
    - Usa a mesma função/expressão de `isPedidoAtrasado` (Fase 3.2) — nunca uma lógica de atraso duplicada.
  - **Feature tests:**
    - Valor exibido é igual à contagem de pedidos para os quais `isPedidoAtrasado` é verdadeiro no escopo/filtros aplicados.
  - **Design ref:** PRD §22 — sem `.spec/init/design/` fornecido
  - **Traces:** table pedidos, US-7.1, US-8.1

- [ ] **Task:** Indicador "Distribuição por status"
  - **Acceptance criteria:**
    - Mostra a contagem de pedidos em cada um dos 6 status (incluindo `cancelado`), ordenados por `statuses.sort_order`.
  - **Feature tests:**
    - Soma das contagens por status é igual ao "Volume total" no mesmo escopo/filtros.
  - **Design ref:** PRD §22 — sem `.spec/init/design/` fornecido
  - **Traces:** table pedidos, table statuses, US-7.1

- [ ] **Task:** Indicador "Prazos"
  - **Acceptance criteria:**
    - Apresenta a situação dos pedidos ativos em relação à data necessária (ex.: dentro do prazo / vencendo em breve / atrasado), reutilizando a mesma regra de atraso.
  - **Feature tests:**
    - Classificação de cada pedido no indicador é consistente com `isPedidoAtrasado` para o caso "atrasado".
  - **Design ref:** PRD §22 — sem `.spec/init/design/` fornecido
  - **Traces:** table pedidos, US-7.1, US-8.1

- [ ] **Task:** Indicador "Visão por obra"
  - **Acceptance criteria:**
    - Mostra a distribuição de pedidos entre as obras no escopo/filtros aplicados.
  - **Feature tests:**
    - Soma das contagens por obra é igual ao "Volume total" no mesmo escopo/filtros.
  - **Design ref:** PRD §22 — sem `.spec/init/design/` fornecido
  - **Traces:** table pedidos, table obras, US-7.1

### Phase 8.2: Filtros e Drill-down

- [ ] **Task:** Filtros do dashboard
  - **Acceptance criteria:**
    - Filtros por período, obra, status, prioridade e responsável, combináveis; aplicar um filtro atualiza todos os indicadores da Fase 8.1 de forma consistente.
  - **Feature tests:**
    - Aplicar um filtro (ex.: obra específica) atualiza simultaneamente todos os indicadores para refletir apenas os pedidos daquela obra.
  - **Design ref:** PRD §23 (Filtros do Dashboard) — sem `.spec/init/design/` fornecido
  - **Traces:** table pedidos, US-7.2

- [ ] **Task:** Drill-down de indicadores para a listagem de pedidos
  - **Acceptance criteria:**
    - Os indicadores "Pendentes" e "Atrasados" são clicáveis e levam à listagem de pedidos filtrada exatamente pelo critério do indicador (ex.: "7 atrasados" → lista com os mesmos 7 pedidos).
  - **Feature tests:**
    - Navegar pelo drill-down de "Atrasados" resulta em uma listagem cujo conjunto de pedidos é idêntico ao usado para calcular o indicador.
  - **Design ref:** PRD §24 (Drill-down) — sem `.spec/init/design/` fornecido
  - **Traces:** table pedidos, US-7.3

### Phase 8.3: Kanban e Consulta em Modo Leitura

- [ ] **Task:** Kanban em modo leitura para Gestão
  - **Acceptance criteria:**
    - Reutiliza o Kanban board da Fase 7.1 sem nenhum controle de movimentação, atribuição, prioridade, previsão ou cancelamento visível.
  - **Feature tests:**
    - Nenhum controle de mutação é renderizado na visão de Gestão, e uma tentativa de chamar as funções de mutação a partir dessa tela é bloqueada pela RLS da Fase 4.2.
  - **Design ref:** PRD §18 (Kanban — Gestão em leitura) — sem `.spec/init/design/` fornecido
  - **Traces:** table pedidos, US-7.4

- [ ] **Task:** Acesso de Gestão à listagem e ao detalhe do pedido
  - **Acceptance criteria:**
    - Gestão acessa a listagem "Todos os Pedidos" (Fase 7.1) e o detalhe do pedido (`PedidoDetailLayout` em modo `readOnly`, com timeline), sem nenhum controle de edição.
  - **Design ref:** PRD §19 (Detalhe do Pedido) — sem `.spec/init/design/` fornecido
  - **Traces:** table pedidos, table pedido_events, US-6.2

---

## Phase 9: Dados de Demonstração

**Goal:** Popular e resetar um ambiente de demonstração realista, seguro e claramente distinguível de dados reais. · **Depends on:** Phase 2, Phase 3, Phase 4 · **Covers:** US-9.1

### Phase 9.1: Seed e Reset Controlado

- [ ] **Task:** Script de seed de demonstração
  - **Acceptance criteria:**
    - Cria múltiplas `obras` (`is_demo = true`), usuários de demonstração para os três perfis (incluindo ao menos um profile Obra associado a mais de uma obra via `obra_profile`), e `pedidos` (`is_demo = true`) cobrindo diferentes status, diferentes prioridades, diferentes responsáveis, ao menos um pedido atrasado (`needed_at` no passado, status não `entregue`/`cancelado`) e ao menos um pedido `entregue`.
    - Script é idempotente: reexecutar não duplica registros.
  - **Feature tests:**
    - Executar o seed produz as contagens esperadas por status/prioridade e ao menos um pedido atrasado e um entregue.
    - Todas as linhas criadas pelo seed têm `is_demo = true`.
    - Executar o seed duas vezes não duplica `obras`, `profiles` nem `pedidos`.
  - **Traces:** table obras, table profiles, table pedidos, table obra_profile, US-9.1

- [ ] **Task:** Comando de reset/limpeza dos dados de demonstração
  - **Acceptance criteria:**
    - Remove exclusivamente as linhas com `is_demo = true` em `obras`, `profiles` e `pedidos` (com cascata para `pedido_events` e `obra_profile` relacionados), sem afetar nenhum dado real (`is_demo = false`).
  - **Feature tests:**
    - Em uma base com dados reais e de demonstração misturados, o reset remove todas as linhas `is_demo = true` e preserva integralmente as linhas `is_demo = false`.
  - **Traces:** table obras, table profiles, table pedidos, table pedido_events, table obra_profile, US-9.1

- [ ] **Task:** Convenção de nomenclatura para dados de demonstração
  - **Acceptance criteria:**
    - Obras e/ou pedidos de demonstração seguem um prefixo/sufixo identificável (ex.: `[DEMO]`) além do flag `is_demo`, para que fiquem claramente distinguíveis de dados reais durante uma apresentação.
  - **Traces:** table obras, table pedidos, US-9.1

---

## Phase 10: Validação Ponta a Ponta (E2E)

**Goal:** Confirmar que o roteiro oficial de demonstração (PRD §46) funciona integralmente com dados persistidos e sem intervenção manual no banco. · **Depends on:** Phase 1 a Phase 9 · **Covers:** todos os workflows do project-description.md, Critérios de Aceite Macro (PRD §34)

### Phase 10.1: Roteiro de Demonstração

- [ ] **Task:** Teste E2E do roteiro oficial de demonstração
  - **Acceptance criteria:**
    - Executado contra dados de demonstração (Fase 9), sem manipulação manual do banco.
    - Cobre integralmente: login como Obra → nova solicitação → confirmação do pedido criado → login como Suprimentos → pedido visível no Kanban em `solicitado` → definir responsável, prioridade e previsão → mover o pedido por `em_analise` → `em_compra_preparacao` → `aguardando_entrega` → consultar histórico → login como Obra e confirmar o acompanhamento atualizado → login como Gestão e visualizar o dashboard e seus indicadores → marcar o pedido como Entregue → confirmar atualização do histórico e do dashboard.
  - **Feature tests:**
    - Suíte Playwright automatiza o roteiro acima ponta a ponta e falha se qualquer etapa não refletir o estado persistido esperado.
  - **Traces:** workflow 1. Autenticação e Controle de Acesso, workflow 2. Criação de Solicitação (Obra), workflow 3. Triagem e Condução Operacional do Pedido (Suprimentos), workflow 4. Acompanhamento do Pedido pela Obra, workflow 6. Histórico e Auditoria, workflow 7. Dashboard Gerencial (Gestão)
