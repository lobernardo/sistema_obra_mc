# Correções decididas a partir da revisão cruzada (router, 2026-09-23)

Fonte: `.spec/features/navegacao-sidebar-listagens/.handoff/cross-review.md` (F-01..F-17).
Regra: nenhum requisito de produto já decidido é reinterpretado. Cada correção tem UM dono (fatia) — não duplicar.

## Decisão transversal de fuso (F-01) — aplicada nas fatias 2 e 3
Todo conceito de "dia" e toda exibição de data/hora usam America/Sao_Paulo; o banco continua em UTC (`app.timezone` não muda). Isso estende a decisão A2/B1 do desenvolvedor para as regras de "hoje" que ainda usavam UTC:
- Fatia 2 (dono de domínio + DashboardIndicatorsService): AtrasoClassifier, PendenteClassifier (se usar data), PrazoClassifier (PHP e scopes SQL), `entreguesHoje`, e o filtro de período do Dashboard passam a usar limites de dia local (via `App\Support\LocalTime`) convertidos para UTC nas consultas. `DashboardDrillDownTest` deve continuar com paridade exata.
- Fatia 3: "Personalizado" (De/Até) usa os MESMOS limites de dia local convertidos para UTC, pela mesma classe de período (`RequestedPeriodFilter` ou equivalente) que o Dashboard da fatia 2 passa a usar — uma só implementação. Remover a Open Question #1 e o risco correspondente.
- Marcar ao desenvolvedor como decisão tomada pelo router (reversível): antes, atraso/entreguesHoje viravam o dia às 21:00 de Brasília.

## Por fatia
### Fatia 1 — obras-associacoes-cadastro-convites
- F-05: T27 (/ai-context + CLAUDE.md) sai da Phase 8 de código e vira fase própria após T28, OU passo do desenvolvedor pós-Ralph em commit documental separado. Registrar que a invocação de `/ai-context` por Ralph headless não está verificada; se não for possível, o passo é do desenvolvedor.
- F-10 (SPEC UI-08): declarar que as entradas "Obras"/"Associações" na toolbar são provisórias até a fatia 3 (RF-08).
- F-13 (SPEC RF-11): registrar que Suprimentos associar a si mesmo (e a outros Suprimentos) é intencional (§1, §3 do plano mestre) e que é isso que dá elegibilidade de criação na fatia 2; Associações exibe um aviso visual quando o alvo é o próprio usuário (sem bloquear).
- F-14(a) (T01): documentar `down()` como rollback com perda de dados (status/responsável).

### Fatia 2 — solicitacao-historico-finalizacao
- F-01: ver bloco de fuso acima (classificadores, entreguesHoje, filtro de período do Dashboard via classe única de período local).
- F-02: T14/T15 listam e migram para as chaves de CT-01 (`obra_selection`, `descricao`): `tests/Feature/Livewire/PedidoDetalheObraTest.php`, `PedidoDetalheGestaoTest.php`, `ObraScreensRouteTest.php`, `tests/Feature/Authorization/BypassUiAuthorizationTest.php`, `tests/Feature/Security/Adversarial/CrossObraTest.php`, e o `ZeroObraUserTest` da fatia 1 — mantendo a intenção (obra alheia forjada falha na regra de associação, não por campo ausente). Incluir na allow-list de reescrita de T34.
- F-03: DemoSeeder associa idempotentemente o usuário Suprimentos demo às obras demo ativas; runbook T32 ganha passo "associar usuários Suprimentos às obras em /associacoes antes de anunciar a Nova Solicitação"; fluxo T30 inclui uma criação por Suprimentos.
- F-04: toda fase fecha verde — mover as atualizações de asserção dirigidas por Finalizado (`KanbanBoardTest`, `GestaoKanbanReadOnlyTest`, `DashboardIndicatorsTest`, `VisaoGeralTest`) para a mesma fase de T04/T05 (ou mover T04/T05 para junto de T10/T11).
- F-06: T22 renomeia "Itens e quantidades" → "Descrição" no `pedido-summary` (e AC); SPEC UI-03.
- F-07: T11 renomeia o campo dos cards do Kanban (`kanban/pedido-card`, `gestao/pedido-card-read-only`) para "Previsão de entrega".
- F-08 (parte fatia 2): T14 reescreve as mensagens "Informe a data necessária."/"Informe uma data necessária válida." para "Preciso para".
- F-09: `criacao_pedido` grava snapshot de `obraLabel()` em `new_value` para pedidos novos; renderização usa o snapshot, com fallback ao valor atual só para eventos legados.
- F-12: datas/horas da lista de convites (fatia 1 T12), de `obra_admin_events` e dos cards do Kanban/Dashboard passam por `LocalTime` (tarefa na fatia 2, que cria `LocalTime`).
- F-14(b)(c): documentar `down()` de T04 como destrutivo-condicional; backfill de T02 usa cópia congelada da regra de dias úteis dentro da migration (não chama código vivo).
- F-17: T15 texto de estado vazio por papel (Suprimentos: "Fale com a Gestão ou associe-se em Associações."; texto de obra byte-idêntico ao da fatia 1).
- Nota (não-bloqueante, confirmar com o desenvolvedor): lista de feriados = 9 fixos + Sexta-feira Santa; Carnaval e Corpus Christi excluídos (ponto facultativo).

### Fatia 3 — navegacao-sidebar-listagens
- F-01: Personalizado em dia local via a mesma classe de período da fatia 2 (T08/T11, Risks, Open Questions atualizados; SPEC RF-18).
- F-08 (parte fatia 3): T14 legenda "Preciso para" no fieldset De/Até da data de necessidade.
- F-11: SPEC Metadata/CT-02 com os nomes exatos das fatias 1/2 (`suprimentos.nova-solicitacao`, `create-pedido`, `obras.index`, `associacoes.index`, `manage-obras`, etc.).
- F-15: SPEC UI-06 registra que a "segunda referência visual" não está no repositório e que UI-06 (texto + critérios NC-05) é o contrato até o desenvolvedor anexar a imagem em `.spec/features/navegacao-sidebar-listagens/.handoff/`.
- F-16: T22 inclui um teste Feature de fluxo §45 (Novo Cadastro → associação por Suprimentos/Gestão → criação de pedido na obra associada).
- F-10: nada a mudar (a sidebar remove as entradas provisórias; já previsto).
