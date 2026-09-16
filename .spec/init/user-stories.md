# Sistema de Solicitações e Compras — User Stories

<!-- inputs: project-description.md@sha256:0506deb15094 -->

## Overview

O Sistema de Solicitações e Compras centraliza, padroniza e rastreia as solicitações de compras
originadas pelas obras, conduzindo cada uma como um pedido rastreável até a entrega, com histórico
completo e visões adequadas a cada perfil.

**User Types:**
- **Obra/Solicitante** - registra necessidades de compra e acompanha os pedidos das obras às quais tem acesso; não opera o fluxo.
- **Suprimentos** - conduz operacionalmente todos os pedidos de todas as obras através do Kanban (responsável, prioridade, previsão, status, cancelamento).
- **Gestão** - consulta a visão consolidada da operação (dashboard, Kanban em leitura, listagens de todas as obras); não opera o fluxo.

---

## 1. Autenticação e Controle de Acesso

### US-1.1: Login por perfil
**As a** usuário do sistema (Obra, Suprimentos ou Gestão)
**I want to** me autenticar com minhas credenciais
**So that** eu acesse apenas as informações e ações compatíveis com meu perfil

**Acceptance Criteria:**
- [ ] Autenticação realizada via Supabase Auth (e-mail/senha).
- [ ] Login inválido exibe mensagem de erro e não concede acesso.
- [ ] Login válido identifica o perfil do usuário (Obra, Suprimentos ou Gestão) e, quando Obra, as obras associadas a ele.
- [ ] Usuário não autenticado é redirecionado para a tela de login ao tentar acessar qualquer rota protegida.
- [ ] Não há tela de autocadastro (self-signup) — usuários são provisionados via seed controlado.

**Expected Result:** Cada usuário autenticado enxerga apenas a navegação e os dados compatíveis com seu perfil.

---

### US-1.2: Isolamento de dados por perfil e por obra
**As a** operador do sistema
**I want to** que a autorização seja aplicada no backend e nos dados, não apenas na interface
**So that** nenhum usuário acesse ou altere dados fora do seu escopo de permissão

**Acceptance Criteria:**
- [ ] Usuário Obra só lê pedidos das obras às quais está associado (via RLS no PostgreSQL, não apenas filtro de UI).
- [ ] Usuário Obra não consegue criar, alterar status, responsável, prioridade ou previsão de nenhum pedido via API/backend, mesmo manipulando requisições diretamente.
- [ ] Usuário Suprimentos lê e opera pedidos de todas as obras.
- [ ] Usuário Gestão lê pedidos e indicadores de todas as obras, mas nenhuma operação de escrita sobre pedidos é permitida para esse perfil no backend.
- [ ] Tentativa de acesso fora do escopo retorna erro de autorização (não retorna dados parciais nem falha silenciosa).

**Expected Result:** A matriz de permissões do PRD (seção 7) é garantida mesmo contra chamadas diretas à API, não apenas pela ausência de botões na UI.

---

## 2. Solicitação (Obra)

### US-2.1: Criar nova solicitação
**As a** usuário Obra
**I want to** registrar uma nova solicitação de compra através de um formulário simples
**So that** minha necessidade vire um pedido rastreável para Suprimentos

**Acceptance Criteria:**
- [ ] Ação "+ Nova Solicitação" visível e acessível para o perfil Obra.
- [ ] Formulário exige: obra relacionada, data necessária e itens/quantidades (texto livre); envio é bloqueado se algum desses campos estiver vazio.
- [ ] O campo "Obra" lista apenas as obras às quais o usuário tem acesso.
- [ ] Data da solicitação é registrada automaticamente pelo sistema (não editável pelo usuário).
- [ ] Não existe catálogo obrigatório nem campo de SKU — itens/quantidades é texto livre.
- [ ] Ao confirmar o envio, o sistema persiste os dados e cria um pedido com identificador único, estável e exibido ao usuário.
- [ ] Pedido criado inicia no status `Solicitado`, associado à obra e ao solicitante, com data/hora de criação registrada.
- [ ] Criação do pedido gera o primeiro evento no histórico.
- [ ] Pedido criado fica imediatamente visível na listagem da Obra e disponível para Suprimentos.

**Expected Result:** Uma solicitação enviada existe como pedido persistido, rastreável e visível a Suprimentos, sem intervenção manual no banco.

---

### US-2.2: Solicitação não editável após envio
**As a** usuário Obra
**I want to** que minha solicitação não possa ser alterada depois de enviada
**So that** o pedido original permaneça confiável como registro da necessidade original

**Acceptance Criteria:**
- [ ] Após o envio, os campos da solicitação original (obra, data necessária, itens/quantidades) são exibidos em modo somente leitura para o usuário Obra.
- [ ] Nenhuma ação de edição da solicitação original é exposta ao perfil Obra na UI.
- [ ] O backend rejeita qualquer tentativa do perfil Obra de alterar os campos da solicitação original após a criação.

**Expected Result:** A solicitação original é imutável do ponto de vista da Obra a partir do momento do envio.

---

## 3. Operação de Suprimentos e Kanban

### US-3.1: Visualizar todos os pedidos no Kanban
**As a** usuário Suprimentos
**I want to** visualizar todos os pedidos de todas as obras organizados por status em um Kanban
**So that** eu tenha uma visão operacional do fluxo completo

**Acceptance Criteria:**
- [ ] Kanban exibe 5 colunas correspondentes ao workflow oficial: `Solicitado`, `Em análise`, `Em compra/preparação`, `Aguardando entrega`, `Entregue`.
- [ ] Cada card exibe: identificador do pedido, obra, resumo da necessidade, data necessária, prioridade, responsável, previsão de entrega e condição de atraso.
- [ ] Cards de pedidos atrasados têm tratamento visual distinto dos demais.
- [ ] Cards refletem visualmente o nível de prioridade.
- [ ] Pedidos cancelados não aparecem nas colunas principais do Kanban.

**Expected Result:** Suprimentos identifica rapidamente, sem abrir cada pedido, a situação geral do fluxo operacional.

---

### US-3.2: Definir responsável pelo pedido
**As a** usuário Suprimentos
**I want to** atribuir um responsável a um pedido
**So that** fique claro quem está conduzindo aquele pedido

**Acceptance Criteria:**
- [ ] Ação de definir/alterar responsável disponível no detalhe do pedido (e/ou no card do Kanban).
- [ ] Responsável definido é persistido e exibido no detalhe do pedido e no card do Kanban.
- [ ] Alteração de responsável gera evento de histórico com valor anterior, valor novo, autor e data/hora.
- [ ] Apenas Suprimentos pode alterar o responsável; Obra e Gestão não têm essa ação disponível.

**Expected Result:** Todo pedido pode ter um responsável rastreável, com histórico de mudanças.

---

### US-3.3: Definir prioridade do pedido
**As a** usuário Suprimentos
**I want to** definir a prioridade de um pedido
**So that** os pedidos mais urgentes sejam identificáveis no fluxo operacional

**Acceptance Criteria:**
- [ ] Prioridade é um de quatro valores: `Baixa`, `Normal`, `Alta`, `Urgente`.
- [ ] Prioridade é definida/alterada apenas por Suprimentos.
- [ ] Prioridade é visível no card do Kanban, na listagem e no detalhe do pedido.
- [ ] Alteração de prioridade gera evento de histórico com valor anterior, valor novo, autor e data/hora.

**Expected Result:** Todo pedido carrega uma prioridade visível e consistente em todas as interfaces operacionais.

---

### US-3.4: Registrar e alterar previsão de entrega
**As a** usuário Suprimentos
**I want to** registrar e atualizar a previsão de entrega de um pedido
**So that** a Obra e Suprimentos saibam quando esperar a entrega

**Acceptance Criteria:**
- [ ] Previsão de entrega é uma data, editável apenas por Suprimentos.
- [ ] Previsão persistida é visível no detalhe do pedido e no card do Kanban.
- [ ] Previsão é consultável pela Obra na listagem/detalhe de seus pedidos.
- [ ] Toda alteração da previsão gera evento de histórico com valor anterior, valor novo, autor e data/hora.

**Expected Result:** A previsão de entrega é confiável, rastreável e visível a quem precisa dela.

---

### US-3.5: Mover pedido entre status pelo Kanban
**As a** usuário Suprimentos
**I want to** mover um pedido entre as colunas do Kanban
**So that** o status reflita o andamento real do pedido

**Acceptance Criteria:**
- [ ] Pedido pode ser movido via drag-and-drop entre as colunas do workflow oficial.
- [ ] Movimentação persiste o novo status imediatamente.
- [ ] Movimentação gera evento de histórico (status anterior, novo status, autor, data/hora).
- [ ] Nova posição do pedido é refletida em todas as demais visualizações (listagem, detalhe, dashboard) sem necessidade de recarregar manualmente dados divergentes.

**Expected Result:** O status do pedido no Kanban é sempre a fonte visual do estado real e persistido do pedido.

---

### US-3.6: Alterar status por meio acessível (sem drag-and-drop)
**As a** usuário Suprimentos
**I want to** alterar o status de um pedido por uma ação alternativa ao drag-and-drop
**So that** a movimentação de status seja possível mesmo sem interação por arrastar

**Acceptance Criteria:**
- [ ] Existe uma ação explícita (ex.: seletor/botão) para alterar o status de um pedido, disponível no detalhe do pedido ou no card.
- [ ] Essa ação respeita a mesma sequência de status do workflow oficial.
- [ ] O resultado é idêntico ao da movimentação por drag-and-drop: persiste, gera histórico e reflete nas demais visualizações.

**Expected Result:** Nenhum usuário de Suprimentos depende exclusivamente de drag-and-drop para operar o fluxo.

---

### US-3.7: Marcar pedido como Entregue
**As a** usuário Suprimentos
**I want to** marcar um pedido como Entregue
**So that** o fluxo do pedido seja concluído formalmente

**Acceptance Criteria:**
- [ ] Ação de marcar como `Entregue` disponível para Suprimentos a partir do status `Aguardando entrega` (ou conforme workflow vigente do pedido).
- [ ] Ao marcar como `Entregue`, o status é persistido como estado final do fluxo normal.
- [ ] A ação gera evento de histórico com autor e data/hora.
- [ ] O pedido entregue deixa de contar como pendente/atrasado no dashboard.

**Expected Result:** A conclusão do pedido é registrada de forma rastreável e refletida em todas as visões.

---

## 4. Acompanhamento do Pedido pela Obra

### US-4.1: Listar pedidos da própria obra
**As a** usuário Obra
**I want to** ver a lista de pedidos das obras às quais tenho acesso
**So that** eu acompanhe o andamento das minhas solicitações

**Acceptance Criteria:**
- [ ] Listagem mostra apenas pedidos das obras associadas ao usuário logado.
- [ ] Cada item da listagem mostra ao menos: identificador, obra, status, prioridade, responsável, previsão de entrega e condição de atraso.
- [ ] Nenhuma ação de edição de status/responsável/prioridade/previsão é exposta a este perfil.

**Expected Result:** A Obra tem visibilidade completa do andamento de seus próprios pedidos, sem poder operá-los.

---

### US-4.2: Consultar detalhe e histórico do pedido
**As a** usuário Obra
**I want to** abrir o detalhe de um pedido meu e ver seu histórico
**So that** eu entenda o que já aconteceu com aquela solicitação

**Acceptance Criteria:**
- [ ] Detalhe do pedido reúne dados da solicitação original, dados operacionais atuais (status, prioridade, responsável, previsão, condição de atraso) e a linha do tempo de histórico.
- [ ] Usuário Obra só acessa o detalhe de pedidos das obras às quais tem acesso; acesso a pedido de outra obra é bloqueado no backend.
- [ ] Histórico é exibido em ordem cronológica com evento, valor anterior, valor novo, autor e data/hora.

**Expected Result:** A Obra consegue entender integralmente o andamento e o passado de qualquer pedido seu.

---

## 5. Cancelamento de Pedido

### US-5.1: Cancelar um pedido
**As a** usuário Suprimentos
**I want to** cancelar um pedido que não deve prosseguir
**So that** o fluxo operacional reflita pedidos que não serão mais atendidos

**Acceptance Criteria:**
- [ ] Ação de cancelamento disponível apenas para Suprimentos; não exposta a Obra nem Gestão, e bloqueada no backend para esses perfis.
- [ ] Cancelamento é representado como estado terminal do pedido, fora das colunas principais do Kanban.
- [ ] Cancelamento gera evento de histórico registrando autor e data/hora.
- [ ] Pedido cancelado não pode retornar a um status ativo do workflow (ação irreversível na V0).

**Expected Result:** Pedidos que não devem prosseguir ficam claramente marcados e removidos do fluxo ativo, com rastro completo de quem cancelou e quando.

---

### US-5.2: Consultar pedidos cancelados
**As a** usuário Suprimentos ou Gestão
**I want to** localizar pedidos cancelados fora do Kanban ativo
**So that** eu ainda consiga auditar o que foi cancelado

**Acceptance Criteria:**
- [ ] Pedidos cancelados permanecem consultáveis via listagem/filtro por status `Cancelado`.
- [ ] Detalhe do pedido cancelado permanece acessível, incluindo seu histórico completo.
- [ ] Pedidos cancelados não são contabilizados como pendentes nem como atrasados no dashboard.

**Expected Result:** Nenhum dado de um pedido cancelado é perdido; ele só sai da operação ativa.

---

## 6. Histórico e Auditoria

### US-6.1: Registro automático de eventos relevantes
**As a** sistema
**I want to** registrar automaticamente todo evento relevante de um pedido
**So that** exista rastreabilidade completa sem depender de anotações manuais

**Acceptance Criteria:**
- [ ] Eventos registrados automaticamente: criação do pedido, mudança de status, alteração de responsável, alteração de prioridade, alteração de previsão de entrega, cancelamento (quando ocorrer) e marcação como entregue.
- [ ] Cada evento persiste: pedido, tipo de evento, valor anterior, valor novo, usuário responsável e data/hora.
- [ ] Histórico é persistente (tabela/estrutura própria), não depende de campo de texto livre editável pela UI.

**Expected Result:** Todo pedido possui uma trilha de auditoria completa e não editável manualmente pelos usuários.

---

### US-6.2: Consultar histórico por qualquer perfil autorizado
**As a** usuário autenticado (Obra, Suprimentos ou Gestão)
**I want to** consultar o histórico de um pedido ao qual tenho acesso
**So that** eu entenda a evolução daquele pedido

**Acceptance Criteria:**
- [ ] Histórico é exibido como linha do tempo no detalhe do pedido, disponível aos três perfis, respeitando o escopo de pedidos que cada perfil pode ver.
- [ ] Eventos aparecem em ordem cronológica, com evento, valor anterior, valor novo, autor e data/hora legíveis.

**Expected Result:** Qualquer usuário autorizado a ver um pedido também vê seu histórico completo.

---

## 7. Dashboard Gerencial (Gestão)

### US-7.1: Visualizar indicadores consolidados
**As a** usuário Gestão
**I want to** visualizar indicadores consolidados da operação
**So that** eu entenda rapidamente a situação geral dos pedidos

**Acceptance Criteria:**
- [ ] Dashboard exibe: volume total de pedidos, pedidos pendentes (não concluídos), pedidos atrasados, distribuição de pedidos por status, visão de prazos (datas necessárias) e distribuição de pedidos por obra.
- [ ] Indicadores refletem os dados persistidos no momento da consulta (sem necessidade de atualização manual do banco).
- [ ] Indicadores usam a regra de atraso definida no sistema de forma consistente com Kanban e listagens.

**Expected Result:** Gestão consegue avaliar a operação sem precisar abrir pedido por pedido.

---

### US-7.2: Filtrar o dashboard
**As a** usuário Gestão
**I want to** filtrar os indicadores do dashboard
**So that** eu analise a operação sob diferentes recortes

**Acceptance Criteria:**
- [ ] Filtros disponíveis: período, obra, status, prioridade e responsável.
- [ ] Aplicar um filtro atualiza todos os indicadores exibidos de forma consistente.
- [ ] Filtros podem ser combinados (ex.: obra + status).

**Expected Result:** Gestão consegue restringir a análise a um recorte específico da operação.

---

### US-7.3: Drill-down de indicador para a lista de pedidos
**As a** usuário Gestão
**I want to** clicar em um indicador e ver os pedidos que o compõem
**So that** eu investigue a causa de um número sem sair do dashboard

**Acceptance Criteria:**
- [ ] Ao menos os indicadores "Pendentes" e "Atrasados" permitem navegar para a listagem filtrada dos pedidos que os compõem (ex.: "7 atrasados" → lista dos 7 pedidos atrasados).
- [ ] A listagem resultante aplica automaticamente o mesmo filtro do indicador de origem.

**Expected Result:** Gestão consegue ir do número ao pedido individual em poucos cliques.

---

### US-7.4: Visualizar Kanban em modo somente leitura
**As a** usuário Gestão
**I want to** visualizar o Kanban operacional
**So that** eu acompanhe o fluxo sem poder alterá-lo

**Acceptance Criteria:**
- [ ] Gestão visualiza as mesmas colunas e cards que Suprimentos.
- [ ] Nenhuma ação de movimentação, definição de responsável, prioridade, previsão ou cancelamento é exposta a Gestão na UI.
- [ ] Backend rejeita qualquer tentativa de escrita sobre pedidos originada do perfil Gestão.

**Expected Result:** Gestão tem visibilidade total do fluxo operacional sem poder operá-lo.

---

## 8. Cálculo de Atraso

### US-8.1: Identificação automática e consistente de pedidos atrasados
**As a** sistema
**I want to** identificar automaticamente pedidos atrasados
**So that** todos os perfis enxerguem a mesma condição de atraso em qualquer tela

**Acceptance Criteria:**
- [ ] Um pedido é atrasado quando sua data necessária já passou e seu status ainda não é `Entregue`.
- [ ] Pedidos `Entregue` ou `Cancelado` nunca são contabilizados como atrasados, independentemente da data necessária.
- [ ] A mesma regra é aplicada de forma idêntica no Kanban, nas listagens, nos filtros e no dashboard.
- [ ] A condição de atraso é recalculada dinamicamente (não é um valor gravado que fica desatualizado) a cada consulta.

**Expected Result:** "Atrasado" significa exatamente a mesma coisa em qualquer parte do sistema.

---

## 9. Dados de Demonstração

### US-9.1: Popular e resetar dados de demonstração com segurança
**As a** equipe técnica responsável pela demonstração
**I want to** popular e limpar/recriar os dados de demonstração de forma controlada
**So that** a V0 possa ser apresentada ao cliente com um cenário realista, sem risco de misturar com dados reais

**Acceptance Criteria:**
- [ ] Existe um mecanismo controlado (script/rotina administrativa) para gerar dados de demonstração — não é uma tela pública de cadastro.
- [ ] O conjunto de dados de demonstração inclui: múltiplas obras, pedidos em diferentes status, diferentes prioridades, diferentes responsáveis, ao menos um pedido atrasado e ao menos um pedido entregue.
- [ ] O mesmo mecanismo permite limpar e recriar o ambiente de demonstração sem afetar outros ambientes.
- [ ] Dados de demonstração são claramente distinguíveis de dados reais (ex.: nomes/convenção identificável), evitando confusão durante a apresentação.

**Expected Result:** É possível preparar e reiniciar o ambiente de demonstração a qualquer momento, de forma segura e repetível.

---

## Appendix: User Story Status

| ID | Story | Priority | Status |
|----|-------|----------|--------|
| US-1.1 | Login por perfil | High | Pending |
| US-1.2 | Isolamento de dados por perfil e por obra | High | Pending |
| US-2.1 | Criar nova solicitação | High | Pending |
| US-2.2 | Solicitação não editável após envio | High | Pending |
| US-3.1 | Visualizar todos os pedidos no Kanban | High | Pending |
| US-3.2 | Definir responsável pelo pedido | High | Pending |
| US-3.3 | Definir prioridade do pedido | High | Pending |
| US-3.4 | Registrar e alterar previsão de entrega | High | Pending |
| US-3.5 | Mover pedido entre status pelo Kanban | High | Pending |
| US-3.6 | Alterar status por meio acessível (sem drag-and-drop) | Medium | Pending |
| US-3.7 | Marcar pedido como Entregue | High | Pending |
| US-4.1 | Listar pedidos da própria obra | High | Pending |
| US-4.2 | Consultar detalhe e histórico do pedido | High | Pending |
| US-6.1 | Registro automático de eventos relevantes | High | Pending |
| US-6.2 | Consultar histórico por qualquer perfil autorizado | High | Pending |
| US-7.1 | Visualizar indicadores consolidados | High | Pending |
| US-7.4 | Visualizar Kanban em modo somente leitura | High | Pending |
| US-8.1 | Identificação automática e consistente de pedidos atrasados | High | Pending |
| US-9.1 | Popular e resetar dados de demonstração com segurança | High | Pending |
| US-7.2 | Filtrar o dashboard | Medium | Pending |
| US-7.3 | Drill-down de indicador para a lista de pedidos | Medium | Pending |
| US-5.1 | Cancelar um pedido | Medium | Pending |
| US-5.2 | Consultar pedidos cancelados | Medium | Pending |
