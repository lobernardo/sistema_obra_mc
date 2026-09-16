# Sistema de Solicitações e Compras — Database Schema

<!-- inputs: project-description.md@sha256:0506deb15094 user-stories.md@sha256:6fd622675450 -->

## Overview

O modelo gira em torno de **pedidos** (a solicitação de compra que vira pedido rastreável), cada um
associado a uma **obra** e a um **profile** solicitante. Um **profile** representa qualquer usuário
autenticado (Obra, Suprimentos ou Gestão) e espelha `auth.users` do Supabase Auth; seu **role**
determina o perfil de acesso. Como um usuário Obra pode acessar múltiplas obras, `obras` e `profiles`
se relacionam em muitos-para-muitos através do pivot `obra_profile`. Todo campo categórico (perfil,
status do workflow, prioridade, tipo de evento de histórico) é uma tabela de lookup com chave
estrangeira — nunca um enum de banco — seguindo a convenção universal do harness. Cada alteração
relevante de um pedido gera uma linha em `pedido_events`, a fonte única de verdade para o histórico e
auditoria. Não há soft delete: pedidos não são apagados, apenas movidos para o status `cancelado`;
`obras` e `profiles` usam `is_active` para desativação sem perda de histórico. Um flag `is_demo` em
`obras`, `profiles` e `pedidos` isola os dados de demonstração para permitir reset seguro (US-9.1).

Convenções em vigor: Supabase/PostgreSQL — chaves primárias `uuid` (`gen_random_uuid()`), tabelas e
colunas em `snake_case`, `created_at`/`updated_at` em tabelas de domínio, tabelas de lookup para todo
campo categórico, autorização reforçada por Row Level Security sobre estas tabelas (não modelada em
DBML).

## Schema (DBML)

```dbml
// Lookup tables first, then domain tables, then pivots.

Table roles {
  id uuid [pk, default: `gen_random_uuid()`]
  name varchar [not null]
  slug varchar [unique, not null]
  description text [null]
  is_active boolean [not null, default: true]
  created_at timestamp [not null, default: `now()`]
  updated_at timestamp [not null, default: `now()`]

  Note: 'Valores V0: obra, suprimentos, gestao'
}

Table statuses {
  id uuid [pk, default: `gen_random_uuid()`]
  name varchar [not null]
  slug varchar [unique, not null]
  description text [null]
  sort_order integer [not null, unique]
  is_active boolean [not null, default: true]
  created_at timestamp [not null, default: `now()`]
  updated_at timestamp [not null, default: `now()`]

  Note: 'Valores V0: solicitado, em_analise, em_compra_preparacao, aguardando_entrega, entregue, cancelado. sort_order define a ordem das colunas do Kanban (cancelado fica fora do fluxo principal).'
}

Table priorities {
  id uuid [pk, default: `gen_random_uuid()`]
  name varchar [not null]
  slug varchar [unique, not null]
  sort_order integer [not null, unique]
  is_active boolean [not null, default: true]
  created_at timestamp [not null, default: `now()`]
  updated_at timestamp [not null, default: `now()`]

  Note: 'Valores V0: baixa, normal, alta, urgente'
}

Table event_types {
  id uuid [pk, default: `gen_random_uuid()`]
  name varchar [not null]
  slug varchar [unique, not null]
  description text [null]
  is_active boolean [not null, default: true]
  created_at timestamp [not null, default: `now()`]
  updated_at timestamp [not null, default: `now()`]

  Note: 'Valores V0: criacao_pedido, mudanca_status, alteracao_responsavel, alteracao_prioridade, alteracao_previsao, cancelamento, entrega'
}

Table profiles {
  id uuid [pk]
  full_name varchar [not null]
  role_id uuid [ref: > roles.id, not null]
  is_active boolean [not null, default: true]
  is_demo boolean [not null, default: false]
  created_at timestamp [not null, default: `now()`]
  updated_at timestamp [not null, default: `now()`]

  Note: 'id espelha auth.users.id (Supabase Auth); linha criada via trigger no signup/seed. Sem FK explícita para auth.users pois o schema auth não é modelado neste DBML.'
}

Table obras {
  id uuid [pk, default: `gen_random_uuid()`]
  name varchar [not null]
  is_active boolean [not null, default: true]
  is_demo boolean [not null, default: false]
  created_at timestamp [not null, default: `now()`]
  updated_at timestamp [not null, default: `now()`]
}

Table pedidos {
  id uuid [pk, default: `gen_random_uuid()`]
  code varchar [unique, not null]
  obra_id uuid [ref: > obras.id, not null]
  requester_id uuid [ref: > profiles.id, not null]
  requested_at timestamp [not null, default: `now()`]
  needed_at date [not null]
  items_description text [not null]
  status_id uuid [ref: > statuses.id, not null]
  priority_id uuid [ref: > priorities.id, null]
  responsible_id uuid [ref: > profiles.id, null]
  expected_delivery_at date [null]
  is_demo boolean [not null, default: false]
  created_at timestamp [not null, default: `now()`]
  updated_at timestamp [not null, default: `now()`]

  Note: 'code é o identificador estável exibido ao usuário (ex.: PED-000123), gerado pela aplicação. status_id inicia sempre no status de sort_order mais baixo (solicitado). priority_id e responsible_id ficam null até serem definidos por Suprimentos.'
}

Table pedido_events {
  id uuid [pk, default: `gen_random_uuid()`]
  pedido_id uuid [ref: > pedidos.id, not null]
  event_type_id uuid [ref: > event_types.id, not null]
  previous_value text [null]
  new_value text [null]
  actor_id uuid [ref: > profiles.id, not null]
  created_at timestamp [not null, default: `now()`]

  Note: 'Fonte única de verdade do histórico/auditoria (US-6.1). Uma linha por evento relevante; nunca editada, apenas inserida.'
}

Table obra_profile {
  obra_id uuid [ref: > obras.id, not null]
  profile_id uuid [ref: > profiles.id, not null]
  created_at timestamp [not null, default: `now()`]

  indexes {
    (obra_id, profile_id) [pk]
  }

  Note: 'Pivot muitos-para-muitos: um profile de perfil Obra pode ter acesso a mais de uma obra. Não populado para profiles de Suprimentos/Gestão, que enxergam todas as obras por regra de RLS/aplicação.'
}
```

## Relationships

- Um **role** tem muitos **profiles** (`profiles.role_id`).
- Um **profile** (perfil Obra) acessa muitas **obras**, e uma **obra** é acessada por muitos
  **profiles**, via `obra_profile`.
- Uma **obra** tem muitos **pedidos** (`pedidos.obra_id`).
- Um **profile** (solicitante) cria muitos **pedidos** (`pedidos.requester_id`).
- Um **profile** (Suprimentos) pode ser responsável por muitos **pedidos** (`pedidos.responsible_id`).
- Um **status** tem muitos **pedidos** (`pedidos.status_id`).
- Uma **priority** tem muitos **pedidos** (`pedidos.priority_id`).
- Um **pedido** tem muitos **pedido_events** (histórico) — `pedido_events.pedido_id`.
- Um **event_type** classifica muitos **pedido_events** (`pedido_events.event_type_id`).
- Um **profile** é autor de muitos **pedido_events** (`pedido_events.actor_id`).

## Lookup Table Seeds

- **roles**: `obra` (Obra/Solicitante), `suprimentos` (Suprimentos), `gestao` (Gestão).
- **statuses** (sort_order crescente = ordem do workflow/Kanban): `solicitado` (1), `em_analise` (2),
  `em_compra_preparacao` (3), `aguardando_entrega` (4), `entregue` (5), `cancelado` (6, fora das
  colunas principais do Kanban).
- **priorities** (sort_order crescente): `baixa` (1), `normal` (2), `alta` (3), `urgente` (4).
- **event_types**: `criacao_pedido`, `mudanca_status`, `alteracao_responsavel`,
  `alteracao_prioridade`, `alteracao_previsao`, `cancelamento`, `entrega`.

## Notes & Conventions

- Nenhum campo categórico usa enum de banco — `roles`, `statuses`, `priorities` e `event_types` são
  tabelas de lookup com FK, conforme convenção do harness.
- Sem soft delete: pedidos nunca são apagados, apenas movidos para o status `cancelado`
  (`statuses.slug = 'cancelado'`), preservando histórico completo. `obras` e `profiles` usam
  `is_active` para desativação lógica.
- `is_demo` em `obras`, `profiles` e `pedidos` isola os dados de demonstração para permitir
  limpar/recriar o ambiente com segurança sem afetar dados reais (US-9.1).
- `pedidos.code` é o identificador único, estável e legível exigido pelo PRD (seção 11); formato e
  geração ficam a cargo da aplicação (ex.: sequência `PED-000001`), não fixados neste schema.
- A regra de atraso (US-8.1) **não é persistida**: é calculada em tempo de consulta comparando
  `pedidos.needed_at` com a data atual e `pedidos.status_id` (atrasado apenas quando o status não é
  `entregue`). Mantém Kanban, listagens, filtros e dashboard sempre consistentes por construção.
- Kanban e Dashboard Gerencial **não são tabelas**: são visões/agregações sobre `pedidos`, `statuses`,
  `priorities` e `obras`. `statuses.sort_order` e `priorities.sort_order` existem justamente para
  ordenar essas visões de forma determinística.
- Autorização por perfil e por obra (US-1.2) é aplicada via Row Level Security sobre `pedidos`,
  `pedido_events` e `obra_profile`, usando `auth.uid()` correlacionado a `profiles.id`; não modelada
  em DBML, mas obrigatória na implementação.
- Índices recomendados além das PKs/uniques declaradas: `pedidos(obra_id, status_id)` e
  `pedidos(needed_at)` para suportar Kanban, listagens filtradas e o cálculo de atraso; `pedido_events(pedido_id, created_at)`
  para renderizar a timeline em ordem.

## Coverage: Key Concepts → Tables

| Key Concept | Table(s) |
|---|---|
| Obra | obras, obra_profile |
| Usuário e Perfil | profiles, roles |
| Pedido/Solicitação | pedidos |
| Itens e Quantidades | pedidos (items_description) |
| Status / Workflow | statuses, pedidos (status_id) |
| Cancelamento | statuses (cancelado), pedido_events (cancelamento) |
| Prioridade | priorities, pedidos (priority_id) |
| Responsável | pedidos (responsible_id → profiles) |
| Previsão de Entrega | pedidos (expected_delivery_at) |
| Regra de Atraso | — not persisted: calculada em runtime a partir de needed_at e status_id |
| Histórico/Auditoria | pedido_events, event_types |
| Kanban | — not persisted: visão sobre pedidos + statuses (ordenada por statuses.sort_order) |
| Dashboard Gerencial | — not persisted: agregação sobre pedidos, statuses, priorities, obras |
| Dados de Demonstração | obras, profiles, pedidos (is_demo) |
