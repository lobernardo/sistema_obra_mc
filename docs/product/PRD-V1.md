# PRD V1 — Sistema Interno de Solicitações e Compras

**Status:** Aprovado para V0 Demo
**Versão:** 1.0
**Primeiro milestone:** V0 Demo funcional
**Próximos milestones:** V1 Pré-produção → Produção

---

# 1. Resumo Executivo

O produto será um sistema interno para centralizar, padronizar e rastrear solicitações de compras originadas pelas obras.

O sistema deverá substituir solicitações dispersas ou informais por um fluxo único e rastreável.

A Obra registra uma necessidade através de um formulário simples. A solicitação passa a existir como um pedido rastreável. Suprimentos recebe e conduz esse pedido até a entrega. As alterações ficam registradas em histórico e os dados gerados alimentam uma visão operacional e gerencial.

O fluxo macro do produto é:

**Obra → Nova Solicitação → Suprimentos → Acompanhamento → Entrega → Histórico → Gestão**

Cada pedido deverá permitir acompanhamento de:

* responsável;
* prazo/data necessária;
* prioridade;
* status;
* previsão de entrega;
* histórico.

Suprimentos terá uma visão operacional em Kanban.

Gestão terá uma visão consolidada sobre:

* volume de pedidos;
* pendências;
* prazos;
* atrasos;
* distribuição por status;
* situação das obras.

---

# 2. Problema

Atualmente existe a necessidade de centralizar o fluxo interno de solicitações de compras realizadas pelas obras.

A principal dor é a ausência de um processo único, padronizado e rastreável que permita saber com clareza:

* o que foi solicitado;
* para qual obra;
* quando foi solicitado;
* para quando é necessário;
* quem está responsável;
* qual é a prioridade;
* em qual estágio está;
* qual é a previsão de entrega;
* se está atrasado;
* o que aconteceu durante o processamento;
* qual é a situação geral da operação.

O produto deverá transformar solicitações isoladas em dados operacionais estruturados e rastreáveis.

---

# 3. Objetivos

O produto deverá:

1. Centralizar as solicitações de compras das obras.
2. Padronizar a entrada sem burocratizar o solicitante.
3. Transformar cada solicitação em um pedido rastreável.
4. Permitir que Suprimentos conduza o andamento dos pedidos.
5. Permitir que a Obra acompanhe suas solicitações.
6. Registrar histórico das alterações relevantes.
7. Disponibilizar uma visão operacional através de Kanban.
8. Disponibilizar informações gerenciais atualizadas.
9. Identificar pendências e atrasos.
10. Permitir análise por obra.
11. Controlar acesso conforme perfil do usuário.
12. Manter rastreabilidade das operações relevantes.

---

# 4. Princípios do Produto

## 4.1 Simplicidade

Criar uma solicitação deve ser simples.

A experiência desejada é semelhante à de um formulário simples, evitando burocracia desnecessária.

## 4.2 Rastreabilidade

Depois do envio, a solicitação deixa de ser apenas uma mensagem ou necessidade informal e passa a existir como uma entidade rastreável.

## 4.3 Visibilidade

Obra, Suprimentos e Gestão devem possuir visões adequadas às suas responsabilidades.

## 4.4 Fluxo completo

A V0 deverá priorizar um fluxo ponta a ponta funcionando, em vez de muitas funcionalidades incompletas.

## 4.5 Segurança

Mesmo sendo inicialmente uma V0 Demo, autenticação, autorização e isolamento adequado de informações devem fazer parte da fundação do produto.

---

# 5. Fora do Escopo da V0

Não fazem parte da V0:

* integração com ERP;
* integração com sistemas externos;
* catálogo obrigatório de materiais;
* SKU obrigatório;
* fornecedores;
* processo formal de cotação;
* financeiro;
* pagamentos;
* ordens de compra;
* hierarquias complexas de aprovação;
* SLAs complexos;
* entrega parcial;
* notificações externas;
* automações externas;
* microserviços;
* infraestrutura distribuída desnecessária.

Esses itens poderão ser avaliados posteriormente.

---

# 6. Perfis

Existem três perfis na V0.

## 6.1 Obra / Solicitante

Responsável por registrar necessidades.

Pode:

* acessar o sistema;
* criar solicitações;
* visualizar pedidos da própria obra;
* abrir o detalhe de seus pedidos;
* acompanhar status;
* acompanhar previsão;
* consultar histórico disponibilizado.

Na V0, a Obra não poderá editar uma solicitação após seu envio.

---

## 6.2 Suprimentos

Responsável por conduzir operacionalmente os pedidos.

Pode:

* visualizar pedidos de todas as obras;
* visualizar detalhes;
* operar o Kanban;
* atualizar status;
* definir responsável;
* definir prioridade;
* registrar/alterar previsão de entrega;
* tratar cancelamentos;
* consultar histórico;
* consultar informações operacionais.

---

## 6.3 Gestão

Responsável pela visão consolidada da operação.

Pode:

* visualizar pedidos de todas as obras;
* consultar pedidos;
* visualizar Kanban em modo leitura;
* visualizar dashboard;
* consultar indicadores;
* filtrar informações por obra e demais dimensões disponíveis.

Na V0, Gestão não opera o fluxo dos pedidos.

---

# 7. Matriz de Permissões V0

| Funcionalidade              | Obra | Suprimentos           | Gestão |
| --------------------------- | ---- | --------------------- | ------ |
| Criar solicitação           | Sim  | Não                   | Não    |
| Ver pedidos da própria obra | Sim  | Sim                   | Sim    |
| Ver todas as obras          | Não  | Sim                   | Sim    |
| Editar solicitação original | Não  | Sim, quando aplicável | Não    |
| Atualizar status            | Não  | Sim                   | Não    |
| Definir responsável         | Não  | Sim                   | Não    |
| Definir prioridade          | Não  | Sim                   | Não    |
| Definir previsão            | Não  | Sim                   | Não    |
| Operar Kanban               | Não  | Sim                   | Não    |
| Visualizar Kanban           | Não  | Sim                   | Sim    |
| Visualizar dashboard        | Não  | Sim                   | Sim    |
| Consultar histórico         | Sim  | Sim                   | Sim    |
| Tratar cancelamento         | Não  | Sim                   | Não    |

A autorização deverá ser aplicada no backend e nos dados, não apenas escondendo elementos da interface.

---

# 8. Jornada Ponta a Ponta

Fluxo principal:

**Obra cria solicitação**

↓

**Sistema cria pedido rastreável**

↓

**Suprimentos recebe**

↓

**Pedido aparece no fluxo operacional/Kanban**

↓

**Suprimentos define e atualiza informações operacionais**

↓

**Pedido evolui pelo workflow**

↓

**Obra acompanha**

↓

**Suprimentos marca como entregue**

↓

**Histórico preserva as alterações**

↓

**Dashboard reflete os dados**

---

# 9. Workflow Oficial V0

O workflow aprovado para a V0 é:

**Solicitado → Em análise → Em compra/preparação → Aguardando entrega → Entregue**

## 9.1 Solicitado

Estado inicial de toda nova solicitação criada pela Obra.

## 9.2 Em análise

Indica que Suprimentos está analisando a necessidade.

## 9.3 Em compra/preparação

Indica que o pedido está sendo tratado operacionalmente por Suprimentos.

## 9.4 Aguardando entrega

Indica que o processo avançou e o pedido aguarda entrega.

## 9.5 Entregue

Estado final de conclusão normal do pedido.

Na V0, Suprimentos é responsável por marcar o pedido como Entregue.

---

# 10. Nova Solicitação

A criação deverá ser simples e direta.

A interface deverá possuir uma ação evidente:

**+ Nova Solicitação**

Ao acessar, será apresentado um formulário.

## Campos

### Data da solicitação

Registra quando a solicitação foi criada.

### Data necessária

Data para quando a Obra precisa receber os itens.

### Obra

Identifica a obra relacionada à necessidade.

### Itens e quantidades

Campo livre.

Exemplo:

> 20 sacos de cimento
> 15 tubos PVC 100mm
> 5 caixas de parafuso

Não haverá catálogo obrigatório na V0.

---

# 11. Criação do Pedido

Ao enviar uma solicitação válida:

1. o sistema deverá persistir os dados;
2. deverá criar um pedido rastreável;
3. o pedido deverá iniciar em **Solicitado**;
4. deverá estar associado à obra;
5. deverá estar associado ao solicitante;
6. deverá registrar data/hora de criação;
7. deverá ficar disponível para Suprimentos;
8. deverá aparecer nas visualizações correspondentes;
9. a criação deverá gerar registro no histórico.

O sistema deverá utilizar um identificador único para cada pedido.

A implementação e formato do identificador ficam a cargo da engenharia, desde que seja estável e utilizável para rastreamento.

---

# 12. Pedido

Cada pedido deverá permitir armazenar e consultar, no mínimo:

* identificador;
* obra;
* solicitante;
* data da solicitação;
* data necessária;
* descrição livre de itens/quantidades;
* status;
* responsável;
* prioridade;
* previsão de entrega;
* data de criação;
* data da última atualização;
* histórico.

---

# 13. Prioridade

Na V0 existem quatro níveis:

* Baixa;
* Normal;
* Alta;
* Urgente.

Suprimentos define a prioridade.

A prioridade deverá ser visível nas interfaces operacionais relevantes.

---

# 14. Responsável

Suprimentos poderá atribuir um responsável ao pedido.

O responsável deverá ser:

* persistido;
* exibido no detalhe;
* exibido no contexto operacional;
* registrado no histórico quando alterado.

---

# 15. Previsão de Entrega

Suprimentos poderá registrar uma previsão de entrega.

A previsão:

* deverá ser persistida;
* poderá ser consultada pela Obra;
* deverá aparecer para Suprimentos;
* deverá aparecer no detalhe do pedido;
* deverá gerar histórico quando alterada.

---

# 16. Regra de Atraso

Na V0, um pedido é considerado **atrasado** quando:

**a data necessária informada pela Obra já passou e o pedido ainda não está no status Entregue.**

Essa regra deverá ser utilizada consistentemente no:

* Kanban;
* listagens;
* filtros;
* dashboard;
* indicadores.

---

# 17. Gestão dos Pedidos

Suprimentos deverá possuir uma visão operacional dos pedidos.

Deverá ser possível:

* listar pedidos;
* abrir detalhes;
* identificar obra;
* identificar data necessária;
* identificar status;
* identificar prioridade;
* identificar responsável;
* identificar previsão;
* identificar atraso;
* filtrar pedidos.

---

# 18. Kanban

O Kanban será uma das principais interfaces operacionais de Suprimentos.

As colunas serão:

1. Solicitado
2. Em análise
3. Em compra/preparação
4. Aguardando entrega
5. Entregue

Cada pedido será representado por um card.

## Card

O card deverá permitir identificar rapidamente:

* pedido;
* obra;
* resumo da necessidade;
* data necessária;
* prioridade;
* responsável;
* previsão de entrega;
* condição de atraso.

A interface deverá possuir tratamento visual claro para:

* prioridade;
* pedidos atrasados;
* status.

## Movimentação

Suprimentos deverá conseguir alterar o status de um pedido através do Kanban.

A implementação poderá utilizar drag-and-drop, desde que a alteração:

* seja persistida;
* atualize o pedido;
* gere histórico;
* reflita nas demais visualizações.

Também deverá existir uma alternativa acessível para alteração de status caso necessário.

## Gestão

Gestão poderá visualizar o Kanban em modo somente leitura.

---

# 19. Detalhe do Pedido

O detalhe deverá centralizar todas as informações relevantes.

## Solicitação

* identificador;
* obra;
* solicitante;
* data da solicitação;
* data necessária;
* descrição dos itens/quantidades.

## Operação

* status;
* prioridade;
* responsável;
* previsão de entrega;
* condição de atraso.

## Histórico

Linha do tempo das alterações relevantes.

---

# 20. Histórico e Auditoria

O sistema deverá manter histórico das alterações relevantes.

Cada evento deverá registrar, quando aplicável:

* pedido;
* evento;
* valor anterior;
* valor novo;
* usuário responsável;
* data/hora.

Na V0 deverão ser registrados pelo menos:

* criação do pedido;
* mudança de status;
* alteração de responsável;
* alteração de prioridade;
* alteração da previsão de entrega;
* cancelamento, caso utilizado;
* marcação como entregue.

O histórico deverá ser persistente e não depender apenas de mensagens editáveis na interface.

---

# 21. Cancelamento

Na V0, cancelamentos serão tratados por Suprimentos.

O mecanismo exato de representação do cancelamento poderá ser refinado durante a especificação técnica, desde que:

* a Obra não cancele diretamente;
* Suprimentos seja responsável pela ação;
* o cancelamento fique registrado;
* o histórico preserve quem realizou a ação e quando.

Não transformar cancelamento em uma nova coluna principal do Kanban sem necessidade.

---

# 22. Dashboard Gerencial

O dashboard deverá permitir entender rapidamente o status real da operação.

## Indicadores obrigatórios V0

### Volume total

Quantidade total de pedidos no período/escopo selecionado.

### Pendentes

Pedidos que ainda não estão concluídos.

### Atrasados

Pedidos que atendem à regra de atraso definida neste PRD.

### Distribuição por status

Quantidade de pedidos em cada estágio.

### Prazos

Visão que permita identificar situação relacionada às datas necessárias.

### Visão por obra

Permitir compreender a distribuição dos pedidos entre as obras.

---

# 23. Filtros do Dashboard

A V0 deverá permitir filtros coerentes com os dados disponíveis, priorizando:

* período;
* obra;
* status;
* prioridade;
* responsável.

A combinação exata dos filtros poderá ser ajustada durante UX/implementação.

---

# 24. Drill-down

Sempre que tecnicamente simples e coerente com a experiência, indicadores deverão permitir chegar aos pedidos que compõem aquele número.

Exemplo:

**7 atrasados → visualizar os 7 pedidos atrasados.**

Isso deverá ser priorizado na V0 quando não gerar complexidade desnecessária.

---

# 25. Listagem e Busca

O sistema deverá possuir visualizações adequadas para consulta de pedidos.

Deverá permitir filtros por:

* obra;
* responsável;
* prioridade;
* status;
* período;
* data necessária;
* atraso.

Busca textual deverá permitir localizar pedidos por informações relevantes disponíveis.

---

# 26. Dashboard de Suprimentos

Suprimentos poderá acessar indicadores operacionais quando isso auxiliar a execução.

A prioridade da interface de Suprimentos, entretanto, é a operação dos pedidos e o Kanban.

---

# 27. Dashboard de Gestão

Gestão terá acesso à visão consolidada.

A interface deverá priorizar:

* leitura rápida;
* situação atual;
* exceções;
* atrasos;
* distribuição;
* possibilidade de chegar ao pedido quando necessário.

---

# 28. Requisitos Funcionais

## RF-001 — Autenticação

Usuários devem se autenticar para acessar o sistema.

## RF-002 — Autorização

O sistema deve aplicar permissões conforme perfil e obra.

## RF-003 — Nova solicitação

Obra deve conseguir criar solicitação.

## RF-004 — Persistência

Solicitação enviada deve gerar pedido persistente.

## RF-005 — Workflow

Pedidos devem possuir status conforme workflow aprovado.

## RF-006 — Meus pedidos

Obra deve visualizar pedidos da própria obra.

## RF-007 — Todos os pedidos

Suprimentos deve visualizar pedidos de todas as obras.

## RF-008 — Gestão de responsável

Suprimentos deve conseguir atribuir responsável.

## RF-009 — Prioridade

Suprimentos deve conseguir definir prioridade.

## RF-010 — Previsão

Suprimentos deve conseguir registrar previsão de entrega.

## RF-011 — Alteração de status

Suprimentos deve conseguir alterar o status.

## RF-012 — Kanban

Suprimentos deve possuir Kanban funcional.

## RF-013 — Kanban Gestão

Gestão deve visualizar Kanban em leitura.

## RF-014 — Histórico

Alterações relevantes devem gerar histórico.

## RF-015 — Dashboard

Gestão deve visualizar indicadores da operação.

## RF-016 — Atrasos

O sistema deve identificar automaticamente pedidos atrasados conforme regra definida.

## RF-017 — Filtros

Listagens, Kanban e dashboard devem disponibilizar filtros adequados.

## RF-018 — Detalhe

Usuários autorizados devem conseguir consultar detalhes dos pedidos.

## RF-019 — Segurança de acesso

Usuários não devem acessar dados incompatíveis com suas permissões.

## RF-020 — Auditoria

Ações relevantes devem registrar autoria e data/hora.

---

# 29. Requisitos Não Funcionais

## Segurança

O sistema deverá seguir boas práticas de segurança para aplicações corporativas.

## Usabilidade

A criação de solicitações deverá ser simples.

## Responsividade

A interface deverá funcionar adequadamente em desktop e dispositivos móveis, considerando possível utilização em ambiente de obra.

## Integridade

Pedidos e histórico deverão manter consistência.

## Desempenho

A experiência da V0 deverá possuir resposta adequada para demonstração e utilização operacional inicial.

Metas quantitativas serão definidas posteriormente.

## Escalabilidade

A arquitetura deverá permitir evolução sem introduzir complexidade prematura.

## Manutenibilidade

O código deverá possuir estrutura clara, tipagem, testes adequados e documentação suficiente para evolução por humanos e agentes de desenvolvimento.

---

# 30. Segurança

A V0 deverá possuir:

* autenticação;
* autorização;
* controle por perfil;
* isolamento de acesso por obra;
* proteção contra acesso indevido;
* validação de inputs;
* tratamento seguro de sessões;
* proteção de credenciais e secrets;
* rastreabilidade de alterações relevantes.

A autorização não deverá depender exclusivamente do frontend.

---

# 31. Stack Técnica V0

A stack definida para a V0 é:

## Aplicação

* Next.js 16
* TypeScript

## Interface

* Tailwind CSS
* shadcn/ui

## Backend

* Next.js server-side

## Dados

* PostgreSQL via Supabase

## Autenticação

* Supabase Auth

## Autorização

* regras na aplicação;
* PostgreSQL Row Level Security quando aplicável.

## Testes

* Vitest;
* Playwright.

## Deploy previsto

* Vercel;
* Supabase.

---

# 32. Arquitetura

A V0 utilizará arquitetura web monolítica em Next.js.

Estrutura conceitual:

**Usuário → Next.js → regras de aplicação/autorização → Supabase/PostgreSQL**

Não utilizar na V0 sem necessidade explícita:

* microserviços;
* Redis;
* filas;
* backend independente;
* event bus;
* infraestrutura distribuída;
* abstrações arquiteturais desnecessárias.

O objetivo é maximizar simplicidade e velocidade sem comprometer segurança e manutenibilidade.

---

# 33. Modelo Conceitual de Dados

A implementação deverá contemplar entidades equivalentes a:

## Usuário

Representa quem acessa o sistema.

Relacionado a:

* autenticação;
* perfil;
* obra, quando aplicável.

## Obra

Representa a unidade/obra à qual as solicitações estão associadas.

## Pedido/Solicitação

Representa cada necessidade registrada.

Deverá conter os dados definidos neste PRD.

## Histórico

Representa eventos e alterações relevantes do pedido.

A estrutura física definitiva será especificada pelo processo de engenharia/Harness.

---

# 34. Critérios de Aceite Macro

A V0 será considerada funcional quando for possível executar ponta a ponta:

1. autenticar um usuário;
2. entrar como Obra;
3. criar uma solicitação;
4. persistir a solicitação;
5. visualizar o pedido criado;
6. entrar como Suprimentos;
7. visualizar o novo pedido;
8. visualizar o pedido no Kanban;
9. definir responsável;
10. definir prioridade;
11. definir previsão;
12. movimentar o pedido pelo workflow;
13. persistir as mudanças;
14. registrar as mudanças no histórico;
15. permitir que a Obra acompanhe o andamento;
16. visualizar indicadores como Gestão;
17. identificar pedidos atrasados;
18. marcar o pedido como Entregue;
19. atualizar dashboard e demais visualizações;
20. preservar o histórico completo do fluxo.

---

# 35. V0 Demo — Primeiro Milestone

O primeiro milestone não é produção.

É uma **V0 Demo funcional e apresentável ao cliente**.

A V0 Demo deve possuir persistência real.

Não deverá ser apenas uma interface estática ou protótipo visual.

O roteiro principal da demonstração será:

**Login como Obra**

↓

**Nova Solicitação**

↓

**Pedido criado**

↓

**Login/visão Suprimentos**

↓

**Pedido aparece no Kanban**

↓

**Responsável + prioridade + previsão**

↓

**Movimentação pelo workflow**

↓

**Histórico atualizado**

↓

**Obra acompanha**

↓

**Gestão visualiza dashboard**

↓

**Pedido marcado como Entregue**

↓

**Dashboard/histórico refletem conclusão**

---

# 36. Qualidade Visual da V0 Demo

Como a primeira versão será apresentada ao cliente, a V0 deverá possuir qualidade visual suficiente para demonstração profissional.

Priorizar:

* interface limpa;
* hierarquia visual clara;
* navegação simples;
* estados de loading adequados;
* estados vazios;
* feedback de sucesso/erro;
* Kanban legível;
* cards objetivos;
* dashboard legível;
* responsividade básica;
* consistência entre telas.

Não sacrificar o fluxo funcional para produzir efeitos visuais desnecessários.

---

# 37. Dados de Demonstração

A V0 poderá possuir mecanismo controlado para disponibilizar dados de demonstração coerentes.

Os dados deverão permitir apresentar:

* múltiplas obras;
* pedidos em diferentes status;
* diferentes prioridades;
* pedidos atrasados;
* diferentes responsáveis;
* pedidos entregues.

Dados de demonstração não devem ser confundidos com dados reais.

A implementação deverá permitir limpar/recriar o ambiente de demonstração de maneira segura.

---

# 38. V1 Pré-produção

Depois da demonstração e coleta de feedback, o projeto entrará na fase V1 Pré-produção.

Essa fase deverá incorporar:

* feedback validado;
* correções funcionais;
* refinamento de UX;
* testes adicionais;
* revisão de permissões;
* hardening de segurança;
* tratamento robusto de erros;
* CI/CD;
* observabilidade;
* estratégia de backup;
* revisão de migrations;
* preparação de staging;
* validação operacional.

O escopo exato será definido depois da V0 Demo.

---

# 39. Produção

A entrada em produção ocorrerá somente após a V1 Pré-produção ser validada.

Deverá existir, antes do lançamento:

* ambiente adequado;
* configuração segura;
* migrations validadas;
* testes;
* controle de secrets;
* logs;
* observabilidade;
* estratégia de backup;
* smoke test;
* plano de rollback;
* validação de acesso e permissões.

---

# 40. Evoluções Futuras

Não fazem parte automaticamente da V0:

* notificações;
* anexos;
* comentários;
* catálogo de materiais;
* fornecedores;
* cotações;
* integrações com ERP;
* integrações externas;
* fluxos de aprovação;
* entrega parcial;
* dashboards avançados;
* análises históricas sofisticadas;
* automações.

Cada evolução deverá ser especificada e aprovada separadamente.

---

# 41. Restrições para Engenharia

Durante a implementação:

1. Não inventar funcionalidades além deste PRD.
2. Não transformar possibilidades futuras em requisitos.
3. Não adicionar arquitetura distribuída sem necessidade.
4. Não implementar ERP.
5. Não implementar fornecedores/cotações.
6. Não implementar catálogo obrigatório.
7. Não implementar entrega parcial.
8. Não implementar notificações externas na V0.
9. Não comprometer autorização por velocidade de implementação.
10. Não criar mocks estáticos como substituto do fluxo funcional.
11. Priorizar vertical slice ponta a ponta.
12. Priorizar código simples e manutenível.

---

# 42. Handoff para Beer and Code Harness

Este documento deverá ser utilizado como **fonte de verdade de produto** durante a inicialização do projeto.

O Harness deverá derivar deste PRD:

1. descrição estruturada do projeto;
2. user stories;
3. schema de banco;
4. fases de implementação.

A sequência esperada é:

**PRD-V1.md**

↓

**project-description.md**

↓

**user-stories.md**

↓

**database-schema.md**

↓

**project-phases.md**

↓

**implementação fase a fase**

---

# 43. Instruções para o Harness

O primeiro milestone é:

**V0 Demo funcional e visualmente apresentável ao cliente.**

O Harness deverá organizar as fases de modo que seja possível chegar primeiro a esse milestone.

A V0 deve priorizar:

* fluxo ponta a ponta;
* persistência real;
* autenticação;
* autorização básica;
* solicitação;
* acompanhamento;
* operação de Suprimentos;
* Kanban;
* histórico;
* dashboard;
* qualidade visual suficiente para demonstração.

Não exigir na V0 todo o hardening necessário para produção.

Separar conceitualmente:

### Milestone 1

V0 Demo

### Milestone 2

V1 Pré-produção

### Milestone 3

Produção

---

# 44. Fonte de Verdade

Em caso de conflito entre uma sugestão gerada durante planejamento e este PRD:

**este PRD prevalece para requisitos de produto.**

Caso uma decisão técnica seja necessária e não esteja definida aqui, Engenharia poderá tomar a decisão mais simples compatível com:

* stack definida;
* segurança;
* manutenibilidade;
* requisitos;
* velocidade da V0.

Não utilizar decisões técnicas para alterar regras de negócio.

---

# 45. Definition of Done — V0 Demo

A V0 Demo estará pronta para apresentação quando:

* o projeto executar sem erros críticos;
* autenticação funcionar;
* os três perfis puderem ser demonstrados;
* Obra conseguir criar pedido;
* dados forem persistidos;
* Suprimentos receber o pedido;
* Kanban funcionar;
* status persistir;
* responsável funcionar;
* prioridade funcionar;
* previsão funcionar;
* histórico funcionar;
* atraso for calculado;
* Obra conseguir acompanhar;
* dashboard funcionar;
* Gestão conseguir visualizar dados;
* permissões básicas funcionarem;
* fluxo completo puder ser demonstrado sem manipulação manual do banco;
* interface possuir qualidade suficiente para apresentação.

---

# 46. Roteiro Oficial de Demonstração

O roteiro de validação da V0 será:

1. Entrar como usuário de Obra.
2. Abrir **Nova Solicitação**.
3. Selecionar/informar a obra.
4. Informar data necessária.
5. Descrever itens e quantidades.
6. Enviar.
7. Confirmar criação do pedido.
8. Visualizar o pedido em acompanhamento.
9. Acessar visão de Suprimentos.
10. Localizar o novo pedido.
11. Visualizar o pedido em **Solicitado** no Kanban.
12. Definir responsável.
13. Definir prioridade.
14. Informar previsão.
15. Mover para **Em análise**.
16. Mover para **Em compra/preparação**.
17. Mover para **Aguardando entrega**.
18. Consultar histórico.
19. Demonstrar acompanhamento pela Obra.
20. Abrir dashboard como Gestão.
21. Demonstrar indicadores.
22. Retornar ao pedido.
23. Marcar como **Entregue**.
24. Demonstrar atualização do histórico.
25. Demonstrar atualização do dashboard.

Se esse roteiro puder ser executado integralmente com dados persistidos e sem intervenção técnica manual, o fluxo central da V0 está validado.
