# Respostas do desenvolvedor — fatia 3 (2026-09-22) — todas as recomendações aceitas

- NC-01: presets do filtro "Solicitado", base `requested_at`, calendário em America/Sao_Paulo (coerente com a fatia 2, que mantém UTC no banco e converte na borda): "Hoje" = dia corrente; "Últimos 3 dias" = hoje + 2 dias anteriores; "Últimos 7 dias" = hoje + 6 dias anteriores; "Último mês" = últimos 30 dias incluindo hoje; "Personalizado" mostra De/Até.
- NC-02: com "Somente obras ativas" ligado, pedidos "Outra" (sem obra) são INCLUÍDOS; o filtro exclui apenas pedidos cuja obra está Concluída.
- NC-03: ordenação padrão de Suprimentos literal mais antigo → mais novo por `requested_at` asc, desempate `id` asc; pedidos terminais ficam misturados (os filtros de status/pendente existentes resolvem).
- NC-04: filtros compactos + presets aplicam-se às 3 listagens de pedidos (Obra Acompanhamento, Suprimentos TodosPedidos, Gestão TodosPedidos). Dashboard de Gestão mantém De/Até e o drill-down continua funcionando (URLs com requestedFrom/requestedTo resolvem como "Personalizado"). Usuários/Obras/Associações não entram no §26.
- NC-05: critério verificável de "compacto": desktop ≥1280px — todos os filtros em uma linha, no máximo 2 linhas; mobile — filtros recolhidos atrás de um botão "Filtros" com contador de filtros ativos, sem overflow horizontal.
- NC-06: "Descrição" (em vez de "Itens") e "Preciso para" (em vez de "Data necessária") nas 3 listagens (componente de tabela compartilhado), desktop e mobile.

Marcadores da fatia 2 referenciados por esta SPEC — agora resolvidos lá (ver `.spec/features/solicitacao-historico-finalizacao/.handoff/clarifier-answers.md`):
- NC-01 (fatia 2): coluna "Previsão" = Data prevista automática (+3 dias úteis); "Previsão de entrega" manual continua existindo no detalhe.
- NC-02(b) (fatia 2): "Hoje" e presets usam America/Sao_Paulo; banco em UTC.
- NC-09 (fatia 2): Finalizado é status/coluna após Entregue; aparece normalmente no filtro de status (lista vem da tabela statuses).

Decisões já tomadas no SPEC e confirmadas: Obra e Gestão mantêm mais novo primeiro; "Solicitado" tem padrão neutro (sem período); presets relativos ao dia em que a URL é aberta; URL só com De/Até = Personalizado; preset desconhecido = neutro; preset relativo vence datas custom; "+ Nova Solicitação" sempre visível (desktop sem rolar; mobile sem abrir o menu); Suprimentos/Gestão também abrem a consulta com `visibleTo`; sidebar de superfície clara com vermelho da marca só no item ativo; BrandIdentityComplianceTest (f) reescrito (app layout com exatamente um `<aside>`, auth layout nenhum), não apagado.
