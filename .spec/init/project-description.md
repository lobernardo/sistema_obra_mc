# Sistema de Solicitações e Compras — Project Description

## Overview

O **Sistema de Solicitações e Compras** é um sistema interno para centralizar, padronizar e rastrear
solicitações de compras originadas pelas obras de uma empresa de construção. Ele substitui pedidos
informais e dispersos (mensagens, planilhas soltas) por um **fluxo único, rastreável e auditável**, que
vai desde o registro da necessidade pela Obra até a entrega, com histórico completo de tudo que
aconteceu no caminho.

O sistema atende três perfis com visões e permissões distintas: **Obra/Solicitante** (registra
necessidades e acompanha seus próprios pedidos), **Suprimentos** (conduz operacionalmente todos os
pedidos através de um Kanban) e **Gestão** (visão consolidada e indicadores gerenciais, somente
leitura sobre o fluxo). O **fluxo macro** do produto é: `Obra → Nova Solicitação → Suprimentos →
Acompanhamento → Entrega → Histórico → Gestão`.

Este é o **primeiro milestone (V0 Demo)**: uma aplicação funcional e visualmente apresentável a
cliente, com **persistência real de dados** (não é protótipo estático). A V0 prioriza um fluxo
completo ponta a ponta — autenticação, criação de solicitação, operação em Kanban, histórico e
dashboard — em vez de muitas funcionalidades parciais. Ficam **fora do escopo da V0**: integração com
ERP ou sistemas externos, catálogo obrigatório de materiais/SKU, fornecedores, cotação formal,
financeiro, pagamentos, ordens de compra, hierarquias complexas de aprovação, SLAs complexos, entrega
parcial e notificações externas. `docs/product/PRD-V1.md` é a fonte de verdade de produto e regras de
negócio; em caso de conflito, o PRD prevalece.

Milestones seguintes (fora do escopo desta descrição): **V1 Pré-produção** (hardening, CI/CD,
observabilidade, backup) e **Produção**.

### Key Concepts

- **Obra:** unidade/canteiro de obra ao qual solicitações estão associadas. Um usuário do perfil Obra
  pode estar associado a **múltiplas obras** (relação muitos-para-muitos usuário↔obra); ao criar uma
  solicitação, o solicitante informa/seleciona a qual obra ela pertence, restrito às obras às quais
  tem acesso.
- **Usuário e Perfil:** cada usuário autenticado possui exatamente um perfil de acesso: **Obra**,
  **Suprimentos** ou **Gestão**. O perfil determina o que pode ver e fazer (matriz de permissões do
  PRD, seção 7). A autorização é aplicada no backend e nos dados (RLS), não apenas escondida na UI.
- **Pedido/Solicitação:** entidade rastreável criada quando a Obra envia uma solicitação. Contém, no
  mínimo: identificador, obra, solicitante, data da solicitação, data necessária, descrição livre de
  itens/quantidades, status, responsável, prioridade, previsão de entrega, data de criação, data da
  última atualização e histórico. Possui um **identificador único, estável e legível** (formato
  decidido pela engenharia, ex.: código sequencial como `PED-000123`), usado para rastreamento em
  todas as telas.
- **Itens e Quantidades:** campo de texto livre no formulário de solicitação (ex.: "20 sacos de
  cimento"); **não há catálogo obrigatório nem SKU na V0**.
- **Status / Workflow:** todo pedido percorre `Solicitado → Em análise → Em compra/preparação →
  Aguardando entrega → Entregue`. Esse é o workflow oficial da V0 e é a base das colunas do Kanban.
- **Cancelamento:** ação exclusiva de Suprimentos (Obra não cancela diretamente). Representado como um
  estado terminal alcançável a partir de qualquer status ativo, **fora das colunas principais do
  Kanban** (não vira uma nova coluna do fluxo); fica registrado no histórico com autor e data/hora.
- **Prioridade:** um de quatro níveis — **Baixa, Normal, Alta, Urgente** — definido por Suprimentos e
  visível nas interfaces operacionais (Kanban, listagens, detalhe).
- **Responsável:** pessoa de Suprimentos atribuída a um pedido; persistido, exibido no detalhe e no
  contexto operacional, e registrado no histórico quando alterado.
- **Previsão de Entrega:** data estimada, definida/alterada por Suprimentos, consultável pela Obra,
  visível no detalhe do pedido e no Kanban; toda alteração gera evento de histórico.
- **Regra de Atraso:** um pedido é considerado **atrasado** quando a data necessária já passou e o
  status ainda não é `Entregue`. Essa regra é aplicada de forma consistente no Kanban, listagens,
  filtros, dashboard e indicadores.
- **Histórico/Auditoria:** linha do tempo persistente de eventos relevantes do pedido (não depende de
  mensagens editáveis na UI). Cada evento registra pedido, tipo de evento, valor anterior, valor novo,
  usuário responsável e data/hora. Eventos mínimos da V0: criação do pedido, mudança de status,
  alteração de responsável, alteração de prioridade, alteração de previsão de entrega, cancelamento
  (quando ocorrer) e marcação como entregue.
- **Kanban:** interface operacional principal de Suprimentos, com 5 colunas correspondentes ao
  workflow oficial. Cada card mostra pedido, obra, resumo da necessidade, data necessária, prioridade,
  responsável, previsão de entrega e condição de atraso, com tratamento visual claro para prioridade,
  atraso e status. Movimentação entre colunas persiste o novo status e gera histórico; deve existir uma
  alternativa acessível à movimentação por drag-and-drop. Gestão visualiza o Kanban em modo somente
  leitura.
- **Dashboard Gerencial:** indicadores obrigatórios da V0 — volume total, pendentes, atrasados,
  distribuição por status, prazos e visão por obra — com filtros por período, obra, status, prioridade
  e responsável, e drill-down de indicadores para a lista de pedidos correspondente quando
  tecnicamente simples.
- **Dados de Demonstração:** conjunto de dados de exemplo (múltiplas obras, pedidos em diferentes
  status/prioridades/responsáveis, pedidos atrasados e entregues) provisionado via **seed
  controlado** (script/rotina administrativa), sem tela pública de cadastro. Não são dados reais e
  devem poder ser limpos/recriados com segurança.

## Tech Stack

| Layer | Technology |
|---|---|
| Aplicação / Frontend | Next.js 16 (App Router), TypeScript |
| UI | Tailwind CSS, shadcn/ui |
| Backend | Next.js server-side (route handlers / server actions) — arquitetura monolítica, sem microserviços |
| Banco de Dados | PostgreSQL via Supabase |
| Autenticação | Supabase Auth |
| Autorização | Regras na aplicação + PostgreSQL Row Level Security (RLS) |
| Testes | Vitest (unitário/integração), Playwright (E2E) |
| Deploy previsto | Vercel (aplicação), Supabase (dados) |

Projeto ainda pré-código (sem `package.json`/scaffold no momento desta descrição) — stack acima
confirmada a partir do PRD e das instruções do desenvolvedor, a ser inicializada nas próximas fases.

## Core Workflows

### 1. Autenticação e Controle de Acesso

1. Usuário acessa o sistema e se autentica via Supabase Auth.
2. O sistema identifica o perfil do usuário (Obra, Suprimentos ou Gestão) e, quando Obra, as obras às
   quais ele tem acesso.
3. Toda leitura/escrita subsequente é filtrada por perfil e, quando aplicável, por obra — aplicado no
   backend e via RLS, nunca apenas escondido na UI.
4. Não há autocadastro (self-signup) na V0: usuários, perfis e vínculos com obras são provisionados via
   seed controlado.

### 2. Criação de Solicitação (Obra)

1. Usuário Obra clica em **+ Nova Solicitação**.
2. Preenche o formulário: obra relacionada (dentre as obras às quais tem acesso), data necessária e
   itens/quantidades em texto livre. A data da solicitação é registrada automaticamente.
3. Ao enviar uma solicitação válida, o sistema: persiste os dados; cria um pedido rastreável com
   identificador único; inicia o status em `Solicitado`; associa obra e solicitante; registra
   data/hora de criação; disponibiliza o pedido para Suprimentos; gera o evento inicial no histórico.
4. O pedido criado fica imediatamente visível nas listagens/telas da própria Obra e de Suprimentos.
5. Após o envio, a Obra **não pode editar** a solicitação original.

### 3. Triagem e Condução Operacional do Pedido (Suprimentos)

1. Suprimentos visualiza todos os pedidos de todas as obras, em listagem e no Kanban.
2. Para um pedido, Suprimentos pode: definir/alterar responsável; definir/alterar prioridade
   (`Baixa/Normal/Alta/Urgente`); registrar/alterar previsão de entrega; mover o pedido entre os
   status do workflow (`Solicitado → Em análise → Em compra/preparação → Aguardando entrega →
   Entregue`) via Kanban (drag-and-drop) ou via ação alternativa acessível.
3. Cada uma dessas alterações é persistida, refletida em todas as visualizações (Kanban, listagem,
   detalhe, dashboard) e gera um evento de histórico com valor anterior, valor novo, autor e data/hora.
4. Suprimentos marca o pedido como `Entregue` ao concluir a entrega — estado final do fluxo normal.

### 4. Acompanhamento do Pedido pela Obra

1. Usuário Obra visualiza a lista de pedidos das obras às quais tem acesso, com status, prioridade,
   responsável, previsão de entrega e condição de atraso.
2. Pode abrir o detalhe de um pedido próprio para consultar dados da solicitação, dados operacionais
   atuais e a linha do tempo de histórico.
3. Não pode alterar status, responsável, prioridade ou previsão — apenas consulta.

### 5. Cancelamento de Pedido

1. Suprimentos identifica um pedido que não deve prosseguir e aciona o cancelamento (Obra não tem essa
   ação).
2. O sistema marca o pedido como cancelado (estado terminal fora das colunas principais do Kanban),
   preserva quem realizou a ação e quando, e gera evento de histórico.
3. O pedido cancelado deixa de aparecer no fluxo operacional ativo do Kanban, mas permanece consultável
   (listagem/filtro/detalhe/histórico).

### 6. Histórico e Auditoria

1. Toda alteração relevante de um pedido (criação, mudança de status, alteração de responsável,
   alteração de prioridade, alteração de previsão de entrega, cancelamento, marcação como entregue)
   gera automaticamente um evento de histórico persistente — nunca dependente de texto livre editável.
2. Cada evento registra: pedido, tipo de evento, valor anterior, valor novo, usuário responsável e
   data/hora.
3. O histórico é exibido como linha do tempo no detalhe do pedido e é consultável pelos três perfis
   (cada um dentro do escopo de pedidos que já pode ver).

### 7. Dashboard Gerencial (Gestão)

1. Usuário Gestão acessa o dashboard e visualiza, para o escopo/período selecionado: volume total de
   pedidos, pendentes, atrasados, distribuição por status, visão de prazos e distribuição por obra.
2. Pode filtrar por período, obra, status, prioridade e responsável.
3. Pode fazer drill-down a partir de um indicador (ex.: "7 atrasados") para a lista dos pedidos que o
   compõem, quando tecnicamente simples.
4. Pode visualizar o Kanban em modo somente leitura, sem operar o fluxo.
5. Gestão não altera status, responsável, prioridade ou previsão de nenhum pedido na V0.
