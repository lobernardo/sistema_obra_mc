# Fatia 3 de 3 — navegacao-sidebar-listagens

Plano mestre completo (fonte do texto de produto): `.spec/features/solicitacao-historico-finalizacao/.handoff/master-plan.md`.
Escopo desta fatia: §16–18, §22–28, §36–41; §43/§44 como restrições.
Não reinterpretar requisitos de produto já decididos no plano mestre.

## Contratos herdados (ler, não reespecificar)
- Fatia 1 `.spec/features/obras-associacoes-cadastro-convites/{SPEC.md,PLAN.md}`: User↔Obra N:N; obra status A iniciar/Em andamento/Concluído (ativa = ≠ Concluído, helper/escopo definido lá); tela Obras, associações, convites, Novo Cadastro; gates novos (ex. `manage-obras` para Gestão+Suprimentos; `manage-users` só Gestão); entradas adicionadas à toolbar ATUAL.
- Fatia 2 `.spec/features/solicitacao-historico-finalizacao/{SPEC.md,PLAN.md}` (quando existir PLAN): Nova Solicitação para Obra+Suprimentos (rota/permissão), opção "Outra" e sua representação canônica (obra ou referência), Data da solicitação / Preciso para / Data prevista (+3 dias úteis), anexos, romaneio, status Finalizado integrado ao domínio (StatusSlug, classificadores, Kanban, indicadores), histórico padronizado, observações, Entregue pela Obra, visibilidade de pedidos "Outra". Entradas provisórias na toolbar ATUAL.
Esta fatia CONSOME esses contratos; não redefine modelos, permissões, status, workflows ou regras de negócio. Se precisar de algo que as fatias 1/2 não expõem, registrar como dependência/lacuna explícita em vez de reimplementar.

## Responsabilidades desta fatia (exclusivas)
- Listagens de pedidos (Obra `Acompanhamento`, Suprimentos `TodosPedidos`, Gestão `TodosPedidos`): identificação "solicitante + obra/referência" em tabela desktop e cards mobile (§16); rótulo "Itens → Descrição" no Acompanhamento (desktop e mobile) (§17); coluna/campo "Previsão" mostra a Data prevista da fatia 2 (§18).
- Homes: `/home` de Suprimentos e de Gestão → Pedidos; Visão Geral e Dashboard continuam acessíveis (§22–23).
- Ordenação padrão da listagem de Suprimentos: mais antigo → mais novo, determinística (§24).
- Filtro "Solicitado" compacto com presets Hoje / Últimos 3 dias / Últimos 7 dias / Último mês / Personalizado (De/Até só em Personalizado) (§25); filtros compactos e horizontais no desktop, sem remover funcionalidade (§26); filtros usáveis no mobile sem overflow (§27); filtro "Somente obras ativas" em Todos os Pedidos de Gestão e Suprimentos, com tratamento coerente de pedidos "Outra" (§28). Manter a convenção travada `#[Url]` como único estado de filtro (FilterUrlStateComplianceTest) e `visibleTo` antes de qualquer filtro.
- Sidebar substituindo a toolbar em todo o sistema (desktop + mobile, seção atual indicada, só itens autorizados por gate/policy, logout, áreas administrativas) (§36); "+ Nova Solicitação" destacada para Obra e Suprimentos (§37); conteúdo por perfil (§38–40), incluindo Obras e associações (fatia 1) e Nova Solicitação para Suprimentos (fatia 2).
- Responsividade (§41): passe transversal final — sidebar, listagens e filtros desta fatia, mais verificação browser mobile das telas das fatias 1 e 2 dentro da nova sidebar (as fatias 1 e 2 já garantem o mobile de suas próprias telas; aqui é a regressão integrada).

## Critérios de aceite (derivados do plano mestre — fonte da verdade)
1. Nas três listagens, cada pedido mostra quem solicitou e para qual obra/referência ("João Silva / Residencial Aurora"; pedidos Outra mostram a referência ou "Outra"), em tabela desktop e card mobile.
2. No Acompanhamento, "Itens" passa a "Descrição" em desktop e mobile.
3. A coluna/campo "Previsão" nas listagens mostra a Data prevista (+3 dias úteis) definida pela fatia 2.
4. `/home` leva Suprimentos e Gestão a Pedidos; Visão Geral (Suprimentos) e Dashboard/Kanban (Gestão) seguem acessíveis pela navegação.
5. Listagem de Suprimentos ordena por padrão do mais antigo para o mais novo, com desempate determinístico.
6. Filtro "Solicitado" com os 5 presets; presets aplicam o período automaticamente; De/Até só aparecem em Personalizado; estado via `#[Url]`; drill-down do dashboard continua funcionando.
7. Filtros de todas as listagens aplicáveis ficam compactos e horizontais no desktop e reorganizados no mobile sem overflow, sem perder nenhum filtro existente.
8. "Somente obras ativas" em Todos os Pedidos (Gestão e Suprimentos) restringe a obras com status ≠ Concluído, sem alterar dados; pedidos "Outra" têm tratamento coerente e explícito.
9. Sidebar substitui a toolbar: desktop e mobile, indica a seção atual, mostra só o autorizado para o papel, mantém logout e áreas administrativas; "+ Nova Solicitação" destacada para Obra e Suprimentos em qualquer página; Gestão não a vê.
10. Conteúdo por papel: Obra (Nova Solicitação, acompanhamento/pedidos, nada administrativo); Suprimentos (Nova Solicitação, Pedidos, Visão Geral, Obras, associações, Kanban e demais autorizadas); Gestão (Pedidos, Obras, Usuários, associações, Dashboard, Kanban e demais autorizadas).
11. Nenhuma regressão (§44) e nenhuma rota perde autorização server-side ao sair da toolbar; testes que fixam a toolbar atual (ex. Gestão com N itens) são atualizados, não apagados.

## Pontos de atenção (marcar [NEEDS CLARIFICATION] se o plano mestre não responder)
- "Último mês": últimos 30 dias ou mês calendário anterior? Base de data = Data da solicitação (`requested_at`)?
- "Somente obras ativas" com pedidos Outra: incluídos ou excluídos quando o filtro está ligado?
- Ordenação "mais antigo → mais novo": por Data da solicitação, com desempate por id?
- Quais listagens contam como "aplicáveis" para filtros compactos (as três de pedidos; a de usuários/obras também?).
- A "segunda referência visual fornecida" não está no repositório — tratar layout como FLEXIBLE descrito em texto.
