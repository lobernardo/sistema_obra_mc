# Respostas do desenvolvedor — fatia 2 (2026-09-22) — todas as recomendações aceitas

Mapeamento para as perguntas do clarifier (Q-01..Q-12) / marcadores NC-01..NC-10:

- Q-01 / NC-01: coexistem. "Data prevista" é automática, fixa, calculada na criação (Data da solicitação + 3 dias úteis). "Previsão de entrega" (`expected_delivery_at`, `UpdatePedidoPrevisaoAction`) continua manual e auditada por Suprimentos. A coluna "Previsão" das listagens (§18, fatia 3) mostra a Data prevista (CT-06).
- Q-02 / NC-02: dias úteis = segunda a sexta excluindo feriados nacionais brasileiros fixos e móveis, em lista literal no código (sem dependência nova). Timestamps continuam gravados em UTC (`app.timezone` NÃO muda); o "dia" da solicitação, o cálculo dos dias úteis e a exibição de data/hora no histórico usam America/Sao_Paulo, convertendo só na borda.
- Q-03 / NC-03: anexos = JPG, PNG, WEBP, PDF, DOCX, XLSX; máx. 10 MB por arquivo; máx. 10 anexos por pedido; romaneio = PDF, JPG, PNG; anexos comuns somente na criação do pedido. Verificação de MIME no servidor (não pela extensão/nome). Ajuste de upload_max_filesize/post_max_size é tarefa do planner (RNF-07).
- Q-04 / NC-04: armazenamento em disco privado local montado sobre um Volume do Railway (sem nova dependência Composer). Configurar o volume é passo operacional documentado (runbook), não executado nesta fase de planejamento; sem deploy agora.
- Q-05 / NC-05: Finalizar permitido a partir de Entregue e de qualquer status ativo. Entregue deixa de ser terminal APENAS para a transição Entregue → Finalizado (para todo o resto continua terminal: guard 409 para outras mutações, exceto as liberadas em Q-07). Cancelado e Finalizado são terminais. Finalizado é terminal.
- Q-06 / NC-06: Obra marca Entregue a partir de qualquer status ativo.
- Q-07 / NC-07: observação permitida em qualquer status (inclusive terminais). Romaneio permitido em status ativos e em Entregue; recusado (409) em Cancelado e Finalizado.
- Q-08 / NC-08: vários romaneios por pedido; cada envio adiciona um anexo do tipo romaneio e um evento; nenhum substitui outro; nada é apagado.
- Q-09 / NC-09: Finalizado é coluna do Kanban depois de Entregue; KPI `entregues` NÃO conta Finalizado; Finalizado aparece em `porStatus` e na Visão Geral de Suprimentos; `porObra` ganha o grupo "Outra" para pedidos sem obra.
- Q-10 / NC-10: não. Usuário sem nenhuma obra ativa associada não cria pedido, nem "Outra"; backend recusa com 422; UI mantém o estado vazio da fatia 1 (UI-09).
- Q-11 (gap): atraso/pendente/prazo continuam medidos por `needed_at` ("Preciso para"); Data prevista é informativa.
- Q-12: "usuário Obra autorizado" a marcar Entregue = qualquer usuário `obra` que pode ver o pedido (`PedidoPolicy::view`: associado à obra do pedido, ou solicitante de um pedido "Outra").
- Observação: limite de 2000 caracteres, promovido a regra RIGID (RF-25).

Decisões já tomadas no SPEC e confirmadas pelo desenvolvedor: Finalizado terminal; pedido "Outra" visível a usuários obra apenas para o solicitante; caminho único para Finalizado (Kanban/UpdatePedidoStatusAction/forjado rejeitados); downloads com Content-Disposition: attachment + nosniff, SVG/HTML recusados; anexos e observações nunca apagados; Gestão somente leitura.
