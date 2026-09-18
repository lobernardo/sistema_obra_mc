# Reimplementação V0 — Laravel + Livewire

## 1. Objetivo

Reimplementar integralmente a V0 existente do **Sistema Interno de Solicitações e Compras** utilizando Laravel + Livewire, preservando todos os requisitos funcionais, regras de negócio, fluxos, permissões, critérios de aceite e comportamentos definidos no projeto atual.

A implementação Next.js existente deve ser utilizada como:

* referência funcional;
* referência visual;
* referência comportamental;
* fonte adicional para entendimento das regras já implementadas.

A aplicação final desta branch deve ser uma aplicação Laravel.

Esta não é uma tentativa de converter mecanicamente arquivos Next.js/React para PHP. A implementação deve ser reconstruída de forma idiomática no ecossistema Laravel, preservando o comportamento esperado do produto.

---

## 2. Branch da reimplementação

A implementação Laravel está sendo desenvolvida na branch:

`build/v0-demo-laravel`

A implementação Next.js anterior permanece preservada no histórico Git e na branch correspondente.

Não destruir o histórico da implementação anterior.

---

## 3. Fontes de verdade

Utilizar obrigatoriamente como referência:

* `docs/product/PRD-V1.md`
* `.spec/init/project-description.md`
* `.spec/init/user-stories.md`
* `.spec/init/database-schema.md`
* `.spec/init/project-phases.md`
* `docs/agents/project_overview.md`
* `docs/agents/architecture.md`
* `docs/agents/tech_stack.md`
* `docs/agents/coding_guidelines.md`
* `docs/agents/domain_rules.md`
* `docs/agents/api_contracts.md`
* `docs/agents/data_model.md`
* `docs/agents/dependencies.md`
* implementação V0 Next.js existente;
* migrations existentes;
* serviços e regras de domínio existentes;
* testes existentes;
* roteiro E2E existente;
* dados de demonstração existentes.

O PRD e as specs representam a intenção do produto.

A implementação atual representa evidência do comportamento já construído.

Nenhum requisito funcional existente deve ser silenciosamente removido durante a reimplementação.

Quando houver divergência entre código atual e PRD/specs, a divergência deve ser identificada explicitamente.

---

## 4. Stack alvo

A nova implementação deverá utilizar:

* Laravel;
* PHP;
* Blade;
* Livewire;
* Tailwind CSS;
* PostgreSQL;
* Eloquent ORM;
* autenticação do ecossistema Laravel;
* Policies e/ou Gates para autorização;
* validação Laravel e/ou Livewire;
* Pest e/ou PHPUnit;
* Laravel Boost;
* Composer;
* Vite;
* Node.js somente quando necessário para build dos assets frontend.

Priorizar soluções idiomáticas do ecossistema Laravel.

---

## 5. Laravel Boost

Laravel Boost deve fazer parte do ambiente de desenvolvimento.

Instalar e configurar Laravel Boost para integração com Claude Code.

A implementação deve aproveitar as guidelines, skills, MCP e demais recursos disponibilizados pelo Laravel Boost quando aplicáveis.

A instalação esperada é equivalente a:

`composer require laravel/boost --dev`

seguida da instalação/configuração recomendada pelo próprio pacote.

Não adicionar dependências desnecessárias apenas para satisfazer ferramentas de desenvolvimento.

---

## 6. Infraestrutura alvo

A aplicação será posteriormente implantada no Railway.

Arquitetura inicial desejada:

Railway Project

* Laravel App
* PostgreSQL

Não adicionar Redis, worker, filas, cron ou outros serviços nesta V0 sem necessidade funcional concreta.

Caso alguma necessidade real seja identificada durante a implementação, documentar antes de adicionar infraestrutura.

A aplicação deve estar preparada para configuração integral por environment variables.

---

## 7. Dependências que devem desaparecer da aplicação final

A implementação Laravel final não deve depender de:

* Next.js;
* React como framework principal da aplicação;
* Supabase;
* Supabase Auth;
* Supabase SDK;
* arquitetura específica do Supabase;
* Vitest como suíte principal de testes da aplicação;
* serviços específicos da implementação Next.js anterior.

O código anterior pode permanecer temporariamente durante a reimplementação quando necessário como referência.

Ao final da migração, a aplicação executável oficial desta branch deve ser Laravel.

---

## 8. Objetivo do produto

O sistema centraliza solicitações internas de compras originadas pelas obras.

O fluxo macro é:

Obra → Solicitação → Suprimentos → Processamento → Entrega → Histórico → Gestão

O sistema deve permitir responder:

* o que foi solicitado;
* para qual obra;
* quando foi solicitado;
* para quando é necessário;
* quem está responsável;
* qual é a prioridade;
* em qual estágio o pedido está;
* qual é a previsão de entrega;
* se existe atraso;
* quais alterações ocorreram;
* qual é a situação consolidada da operação.

---

## 9. Perfis

Existem três perfis principais.

### 9.1 Obra

Pode:

* autenticar;
* criar solicitações;
* visualizar pedidos das obras às quais possui acesso;
* acompanhar o andamento das solicitações;
* consultar detalhes;
* consultar histórico disponibilizado conforme regras da aplicação.

Na V0, a Obra não edita a solicitação depois do envio.

Um usuário Obra pode possuir acesso a mais de uma obra.

A autorização deve respeitar essa associação.

### 9.2 Suprimentos

Pode:

* autenticar;
* visualizar todas as obras;
* visualizar pedidos;
* operar pedidos;
* utilizar Kanban;
* definir responsável;
* definir prioridade;
* informar previsão de entrega;
* alterar status;
* tratar cancelamentos;
* consultar histórico.

Suprimentos é o principal perfil operacional.

### 9.3 Gestão

Pode:

* autenticar;
* visualizar todas as obras;
* consultar pedidos;
* acessar dashboard;
* utilizar filtros;
* visualizar indicadores;
* visualizar Kanban em modo somente leitura.

Gestão não deve executar ações operacionais de Suprimentos na V0.

---

## 10. Workflow oficial

Preservar o workflow:

Solicitado
→ Em análise
→ Em compra/preparação
→ Aguardando entrega
→ Entregue

As regras de transição existentes devem ser analisadas e preservadas.

Transições inválidas devem ser rejeitadas no backend.

A interface não é a única camada responsável por proteger o workflow.

---

## 11. Cancelamento

Cancelamento deve continuar suportado conforme PRD, specs e implementação atual.

O cancelamento deve:

* respeitar autorização;
* alterar o estado do pedido de forma consistente;
* gerar histórico/auditoria;
* impedir comportamentos incompatíveis com um pedido cancelado.

Preservar as regras existentes referentes a pedidos cancelados.

---

## 12. Prioridades

Preservar:

* Baixa
* Normal
* Alta
* Urgente

Suprimentos é responsável pela classificação operacional da prioridade conforme regras atuais.

---

## 13. Regra de atraso

Preservar a regra de atraso definida no produto.

Conceitualmente:

Pedido atrasado = data necessária já passou e o pedido ainda não foi entregue, respeitando também as regras existentes referentes a cancelamento.

Preferir cálculo derivado em vez de persistir um estado redundante de atraso no banco.

Centralizar a regra para evitar cálculos divergentes entre dashboard, listagens e Kanban.

---

## 14. Solicitação da Obra

A criação da solicitação deve continuar simples.

Campos essenciais incluem:

* data da solicitação;
* data necessária;
* obra;
* descrição livre dos itens e quantidades.

Não criar catálogo obrigatório de produtos na V0.

Não exigir SKU.

A descrição dos itens permanece livre.

Exemplo conceitual:

20 sacos de cimento
15 tubos PVC 100mm
5 caixas de parafuso

---

## 15. Modelo de domínio

Preservar conceitualmente o modelo existente.

Entidades/lookups atualmente especificados:

* roles
* statuses
* priorities
* event_types
* profiles
* obras
* obra_profile
* pedidos
* pedido_events

Na implementação Laravel, adaptar nomes e estruturas quando necessário para seguir convenções idiomáticas do framework.

Por exemplo, a autenticação poderá utilizar a entidade `users` como identidade principal em vez de reproduzir mecanicamente a arquitetura de `profiles` criada para Supabase.

Entretanto, qualquer adaptação deve preservar integralmente:

* perfis;
* relacionamentos;
* permissões;
* dados;
* comportamento.

---

## 16. Relacionamento usuário ↔ obra

Preservar suporte many-to-many entre usuários e obras quando aplicável.

Um usuário Obra pode estar associado a múltiplas obras.

A aplicação deve impedir acesso a pedidos de obras não autorizadas.

Essa proteção deve existir no backend.

---

## 17. Pedidos

Cada solicitação deve resultar em um pedido rastreável.

O pedido deve contemplar, conforme modelo final:

* identificador;
* obra;
* solicitante;
* data da solicitação;
* data necessária;
* descrição dos itens;
* status;
* prioridade;
* responsável;
* previsão de entrega;
* timestamps;
* demais campos necessários segundo PRD/specs.

Não adicionar complexidade desnecessária ao modelo.

---

## 18. Responsável

Suprimentos deve conseguir atribuir responsável ao pedido.

Alterações relevantes de responsável devem gerar evento no histórico.

A autorização deve ser aplicada no backend.

---

## 19. Previsão de entrega

Suprimentos deve conseguir informar e alterar a previsão de entrega conforme regras existentes.

Alterações relevantes devem gerar histórico.

A previsão deve aparecer nas interfaces pertinentes.

---

## 20. Histórico e auditoria

Preservar histórico de alterações relevantes.

Eventos incluem, no mínimo:

* criação;
* mudança de status;
* mudança de responsável;
* mudança de prioridade;
* alteração da previsão de entrega;
* cancelamento;
* entrega.

Quando aplicável, registrar:

* pedido;
* tipo do evento;
* usuário responsável;
* valor anterior;
* valor novo;
* data/hora;
* metadados necessários.

O histórico deve ser gerado pelo backend e não depender do frontend para sua integridade.

---

## 21. Autenticação

Substituir Supabase Auth por solução do ecossistema Laravel.

A V0 precisa suportar login dos usuários de demonstração.

Não é necessário disponibilizar self-signup público.

Provisionamento pode continuar controlado através de seeders/administração apropriada.

Aplicar práticas adequadas de hash de senha e sessão Laravel.

---

## 22. Autorização

Implementar autorização no backend utilizando mecanismos idiomáticos do Laravel, preferencialmente:

* Policies;
* Gates;
* middleware;
* scopes quando apropriados.

Não confiar apenas em:

* esconder botões;
* esconder links;
* bloquear telas no frontend.

Usuário Obra só pode acessar recursos permitidos para suas obras.

Suprimentos possui acesso operacional global conforme PRD.

Gestão possui acesso global de leitura conforme PRD.

Testar explicitamente tentativas de acesso não autorizado.

---

## 23. Interface da Obra

Implementar em Blade + Livewire.

Telas/fluxos obrigatórios:

### Login

Permitir autenticação.

### Nova Solicitação

Permitir criar pedido.

### Acompanhamento

Mostrar solicitações permitidas para o usuário.

### Detalhe

Mostrar informações do pedido e andamento.

A experiência deve ser simples e adequada para usuários de obra.

---

## 24. Interface de Suprimentos

Implementar:

* visão operacional;
* listagem quando aplicável;
* detalhe;
* Kanban;
* ações do pedido;
* responsável;
* prioridade;
* previsão;
* mudança de status;
* cancelamento;
* histórico.

Suprimentos deve conseguir conduzir o pedido durante todo o workflow.

---

## 25. Kanban

O Kanban deve possuir as colunas:

* Solicitado
* Em análise
* Em compra/preparação
* Aguardando entrega
* Entregue

Cards devem apresentar informações suficientes para operação, incluindo quando aplicável:

* pedido;
* obra;
* data necessária;
* prioridade;
* responsável;
* previsão;
* atraso.

A implementação pode utilizar recursos Livewire para interatividade.

Qualquer drag-and-drop eventualmente utilizado deve respeitar as regras de negócio no backend.

Não permitir que manipulação do navegador burle transições de status.

---

## 26. Interface da Gestão

Implementar:

* dashboard;
* indicadores;
* filtros;
* visão por obra;
* distribuição por status;
* pedidos atrasados;
* prazos;
* Kanban read-only;
* drill-down quando previsto pela V0.

Gestão não deve possuir controles operacionais exclusivos de Suprimentos.

---

## 27. Dashboard

Preservar os indicadores da V0:

* volume total;
* pendentes;
* atrasados;
* distribuição por status;
* prazos;
* visão por obra.

Preservar filtros previstos, incluindo quando aplicável:

* período;
* obra;
* status;
* prioridade;
* responsável.

Os números devem ser derivados da mesma fonte de dados/regras utilizada pelo restante da aplicação.

Evitar duplicação de lógica de atraso ou status.

---

## 28. UX e design

Utilizar a V0 Next.js existente como referência visual e funcional.

Não é necessário copiar código React.

Reconstruir as telas em Blade/Livewire.

Preservar ou melhorar:

* hierarquia visual;
* clareza;
* responsividade;
* feedback das ações;
* estados vazios;
* estados de loading;
* mensagens de erro;
* mensagens de sucesso;
* confirmações de ações destrutivas;
* legibilidade;
* consistência.

A V0 deve continuar visualmente apresentável para demonstração ao cliente.

---

## 29. Dados de demonstração

Criar seeders idempotentes ou mecanismo seguro equivalente para dados de demonstração.

Precisamos de usuários conhecidos representando:

* Obra;
* Suprimentos;
* Gestão.

Precisamos também de:

* obras;
* pedidos;
* diferentes status;
* diferentes prioridades;
* responsáveis;
* pedidos dentro do prazo;
* pedidos atrasados;
* histórico suficiente;
* dados para alimentar o dashboard.

As credenciais de demonstração devem ser claramente documentadas para ambiente local/demo.

Nunca incluir credenciais reais de produção no repositório.

---

## 30. Testes

Reimplementar os testes relevantes no ecossistema Laravel.

Utilizar Pest e/ou PHPUnit conforme padrão definido pelo projeto Laravel.

Cobrir no mínimo:

* autenticação;
* usuário não autenticado;
* autorização;
* isolamento por obra;
* associação usuário/obra;
* criação de pedido;
* validação do formulário;
* workflow;
* transições válidas;
* transições inválidas;
* atribuição de responsável;
* prioridade;
* previsão;
* cancelamento;
* cálculo de atraso;
* histórico;
* dashboard;
* permissões de Suprimentos;
* acesso read-only da Gestão.

Criar testes unitários e de feature conforme adequado.

---

## 31. Validação ponta a ponta

Preservar o roteiro oficial de demonstração.

Fluxo mínimo:

1. autenticar como Obra;
2. criar uma nova solicitação;
3. confirmar persistência;
4. acompanhar pedido;
5. autenticar como Suprimentos;
6. localizar pedido no Kanban;
7. definir responsável;
8. definir prioridade;
9. informar previsão;
10. mover pelo workflow;
11. verificar histórico;
12. autenticar novamente como Obra e confirmar atualização;
13. autenticar como Gestão;
14. verificar dashboard;
15. verificar Kanban read-only;
16. retornar como Suprimentos;
17. marcar como Entregue;
18. confirmar histórico;
19. confirmar atualização dos indicadores.

Automatizar partes relevantes quando tecnicamente razoável.

---

## 32. PostgreSQL

A nova aplicação deve utilizar PostgreSQL.

Não depender do Supabase para acesso ao PostgreSQL.

Localmente, permitir configuração através de environment variables.

Produção utilizará PostgreSQL fornecido pelo Railway.

Migrations Laravel devem ser suficientes para criar o banco a partir do zero.

---

## 33. Migrations

Criar migrations Laravel completas.

Requisitos:

* banco novo deve poder ser criado integralmente;
* constraints devem refletir as regras do domínio;
* foreign keys devem ser consistentes;
* índices relevantes devem ser criados;
* migrations devem funcionar em PostgreSQL;
* não depender das migrations Supabase antigas em produção.

As migrations antigas devem ser utilizadas como referência durante a reimplementação.

---

## 34. Eloquent

Criar Models e relacionamentos idiomáticos.

Centralizar regras relevantes em estruturas apropriadas como:

* Models;
* Actions;
* Services;
* Policies;
* Value Objects;
* Enums PHP quando justificável;
* Query Objects/Scopes quando justificável.

Evitar controllers ou componentes Livewire excessivamente grandes.

---

## 35. Livewire

Utilizar Livewire para interatividade relevante.

Priorizar:

* formulários;
* filtros;
* tabelas;
* Kanban;
* atualizações de estado;
* dashboard;
* feedback de ações.

Não transformar Livewire em substituto para regras de domínio.

As regras críticas devem permanecer em backend/domain services/actions apropriados.

---

## 36. Laravel Boost e Claude Code

Configurar Laravel Boost para o projeto.

Após a criação da aplicação Laravel:

* instalar Boost;
* executar instalação/configuração;
* garantir integração com Claude Code;
* permitir que o agente utilize documentação e guidelines adequadas à versão instalada do Laravel e seus pacotes.

Documentar a configuração necessária.

---

## 37. Railway

Preparar a aplicação para deploy no Railway.

Arquitetura inicial:

Railway Project
├── Laravel App
└── PostgreSQL

A aplicação deve funcionar utilizando environment variables.

Preparar documentação para configuração de:

* `APP_NAME`
* `APP_ENV`
* `APP_KEY`
* `APP_DEBUG`
* `APP_URL`
* conexão PostgreSQL;
* demais variáveis efetivamente necessárias.

Não colocar secrets no Git.

---

## 38. Produção

Preparar aplicação para:

* `APP_ENV=production`;
* `APP_DEBUG=false`;
* configuração segura de `APP_KEY`;
* HTTPS através da infraestrutura de deploy;
* migrations com `--force`;
* caches Laravel adequados;
* logs apropriados;
* conexão PostgreSQL Railway.

Não adicionar infraestrutura além da necessidade real da V0.

---

## 39. Railway Build/Deploy

A aplicação deverá possuir estrutura compatível com deploy automatizado a partir do GitHub.

O processo deve contemplar:

* instalação Composer;
* instalação/build dos assets;
* configuração das environment variables;
* migrations;
* caches de produção;
* start da aplicação.

Documentar o procedimento final.

---

## 40. Escopo explicitamente fora da V0

Não adicionar durante a reimplementação:

* ERP;
* fornecedores;
* cotações;
* financeiro;
* pagamentos;
* catálogo obrigatório;
* SKU obrigatório;
* entrega parcial;
* notificações externas;
* workflows complexos de aprovação;
* integrações externas;
* microserviços;
* Redis sem necessidade;
* filas sem necessidade;
* workers sem necessidade;
* cron sem necessidade;
* arquitetura distribuída.

A migração de tecnologia não deve ser usada para aumentar silenciosamente o escopo do produto.

---

## 41. Estratégia de reimplementação

A migração deve ocorrer de maneira controlada.

Estratégia recomendada:

1. analisar integralmente PRD, specs, documentação AS IS e implementação atual;
2. mapear funcionalidades Next.js para equivalentes Laravel;
3. identificar divergências entre PRD e implementação;
4. preparar aplicação Laravel;
5. instalar/configurar Livewire;
6. instalar/configurar Laravel Boost;
7. criar PostgreSQL schema via migrations;
8. criar Models e domínio;
9. implementar autenticação;
10. implementar autorização;
11. implementar histórico;
12. implementar fluxo Obra;
13. implementar Suprimentos;
14. implementar Kanban;
15. implementar Gestão;
16. implementar dashboard;
17. criar demo seeders;
18. migrar/reimplementar testes;
19. validar ponta a ponta;
20. remover dependências da aplicação Next/Supabase;
21. atualizar documentação;
22. preparar Railway.

O Harness pode reorganizar essas etapas em fases tecnicamente mais adequadas, desde que preserve o objetivo e a cobertura completa.

---

## 42. Remoção da implementação anterior

Não remover prematuramente a implementação Next.js.

Durante a reimplementação ela pode ser necessária como referência.

Somente remover ou substituir arquivos antigos quando:

* a funcionalidade equivalente Laravel existir;
* testes relevantes existirem;
* a implementação Laravel estiver validada;
* não houver perda de informação necessária.

O histórico Git continuará sendo a fonte para recuperação da V0 anterior.

---

## 43. Compatibilidade com desenvolvimento local

Ao final, um desenvolvedor deve conseguir clonar o projeto e, seguindo a documentação:

1. instalar dependências PHP;
2. instalar dependências frontend;
3. criar/configurar `.env`;
4. configurar PostgreSQL;
5. gerar `APP_KEY`;
6. executar migrations;
7. executar seed demo;
8. iniciar Laravel;
9. acessar a aplicação;
10. autenticar com usuários de demonstração.

O README deve documentar esse processo.

---

## 44. Segurança

A V0 deve possuir no mínimo:

* autenticação;
* autorização backend;
* isolamento de dados por obra;
* proteção CSRF nativa do Laravel;
* validação server-side;
* hashing seguro de senhas;
* proteção contra mass assignment;
* escaping adequado de conteúdo;
* tratamento seguro de secrets;
* `APP_DEBUG=false` em produção;
* auditoria das alterações relevantes.

Não expor secrets em logs, documentação ou Git.

---

## 45. Performance

Não realizar otimizações prematuras.

Entretanto:

* evitar N+1;
* utilizar eager loading quando necessário;
* criar índices relevantes;
* paginar listas potencialmente grandes;
* evitar queries duplicadas no dashboard;
* evitar recalcular informações de forma inconsistente.

A prioridade continua sendo uma V0 simples, correta e demonstrável.

---

## 46. Qualidade do código

A implementação deve:

* seguir convenções Laravel;
* utilizar nomes claros;
* possuir responsabilidades bem definidas;
* evitar duplicação;
* evitar abstrações prematuras;
* evitar componentes Livewire gigantes;
* manter regras críticas fora da camada puramente visual;
* possuir testes para regras importantes.

Executar ferramentas de qualidade disponíveis no projeto antes de considerar uma fase concluída.

---

## 47. Critérios de conclusão

A reimplementação só será considerada concluída quando:

1. a aplicação Laravel instalar corretamente;
2. Laravel Boost estiver instalado/configurado;
3. Livewire estiver funcional;
4. PostgreSQL estiver funcional;
5. migrations executarem do zero;
6. seed demo funcionar;
7. autenticação funcionar;
8. os três perfis funcionarem;
9. autorização backend estiver validada;
10. isolamento por obra estiver validado;
11. fluxo Obra funcionar;
12. fluxo Suprimentos funcionar;
13. Kanban funcionar;
14. workflow estiver protegido;
15. responsável funcionar;
16. prioridade funcionar;
17. previsão funcionar;
18. cancelamento funcionar;
19. histórico funcionar;
20. cálculo de atraso funcionar;
21. Gestão funcionar;
22. dashboard funcionar;
23. Kanban read-only funcionar para Gestão;
24. testes automatizados passarem;
25. build frontend passar;
26. aplicação iniciar localmente;
27. demo data estiver disponível;
28. roteiro oficial da V0 funcionar ponta a ponta;
29. aplicação final não depender de Supabase;
30. aplicação final não depender de Next.js;
31. documentação de execução estiver atualizada;
32. aplicação estiver preparada para Railway;
33. nenhum requisito relevante da V0 anterior tiver sido silenciosamente perdido.

---

## 48. Resultado esperado

Ao final desta iniciativa teremos:

**Sistema Interno de Solicitações e Compras — V0 Laravel**

Stack:

Laravel

* Livewire
* Blade
* Tailwind CSS
* PostgreSQL
* Laravel Boost

Infraestrutura alvo:

Railway
├── Laravel App
└── PostgreSQL

A aplicação deverá estar funcional, persistente, testada e adequada para demonstração ao cliente.

---

## 49. Restrição crítica final

Preservar 100% das informações, regras, requisitos e comportamentos relevantes da V0 existente.

Não otimizar a migração removendo requisitos.

Não interpretar mudança de stack como autorização para simplificar o produto.

Quando houver divergência entre código atual e PRD/specs:

1. identificar a divergência;
2. consultar as fontes de verdade do projeto;
3. preservar a intenção validada do produto;
4. não remover funcionalidade silenciosamente.

O objetivo é **reimplementar a V0 em Laravel**, e não criar um produto diferente.
