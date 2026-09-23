# Fatia 2 de 3 — solicitacao-historico-finalizacao

Plano mestre completo (fonte do texto de produto): `.spec/features/solicitacao-historico-finalizacao/.handoff/master-plan.md`.
Escopo desta fatia: §10–15, §19–21, §29–35, §42 (itens de pedido), §47–48; §41 apenas para as telas desta fatia; §43/§44 como restrições.
Não reinterpretar requisitos de produto já decididos no plano mestre.

## Contrato herdado da fatia 1 (já especificada/planejada — ler, não reespecificar)
`.spec/features/obras-associacoes-cadastro-convites/{SPEC.md,PLAN.md,PHASES.md}`:
- User ↔ Obra N:N (`obra_profile`), usuários `obra` e `suprimentos` com 0..N obras; `gestao` sem obras.
- Obras com status A iniciar / Em andamento / Concluído (coluna `is_active` removida); obra ativa = status ≠ Concluído; escopo/nome do helper conforme o PLAN da fatia 1 (ex.: `->active()`).
- Obra Concluída já é recusada para novas solicitações no fluxo de criação existente (fatia 1). Esta fatia estende o mesmo critério ao novo fluxo (Obra + Suprimentos) sem duplicar a regra — reutiliza o que a fatia 1 criou.
- Usuário `obra` pode existir sem associação; Novo Cadastro público sempre perfil `obra`; convites e telas de associação são da fatia 1.
- Navegação: a fatia 1 adiciona entradas na toolbar ATUAL. A fatia 3 substitui a toolbar por sidebar.

## Fronteira com a fatia 3 (NÃO especificar aqui — pertence à fatia 3)
- Listagens (Acompanhamento, Todos os Pedidos de Suprimentos e de Gestão): coluna/linha "solicitante + obra/referência" (§16), rótulo "Itens → Descrição" (§17), coluna "Previsão" mostrando a Data prevista (§18), ordenação (§24), filtros compactos/"Solicitado"/"Somente obras ativas" (§25–28).
- Sidebar, destaque visual "+ Nova Solicitação" na navegação, homes = Pedidos (§22–23, §36–40).
- Passe final de responsividade transversal (§41) e suíte browser de navegação.
Esta fatia DEVE, porém, entregar para a fatia 3 os contratos que ela vai consumir: (a) representação canônica de "obra ou referência Outra" de um pedido (um único ponto de apresentação, ex. accessor/presenter), (b) a Data prevista persistida/derivável de forma consultável em SQL (para listagem/ordenação), (c) o novo status Finalizado integrado em StatusSlug/classificadores/Kanban/indicadores, (d) visibilidade (`visibleTo`) correta para pedidos "Outra". A página "+ Nova Solicitação" para Suprimentos deve ficar alcançável nesta fatia via uma entrada na toolbar atual (a fatia 3 só a move/destaca na sidebar).

## Critérios de aceite (derivados do plano mestre — fonte da verdade)
1. Obra e Suprimentos criam solicitações pelo mesmo fluxo de Nova Solicitação; Gestão não tem essa permissão (backend: gate/policy + Action).
2. Obra vê no seletor apenas suas obras associadas não Concluídas; Suprimentos vê suas obras associadas não Concluídas; `obra_id` forjado (não associado, inexistente ou Concluído) é rejeitado no backend.
3. O seletor tem a opção "Outra" que abre um texto livre opcional; "Outra" não cria obra, não associa, não concede acesso; pedido "Outra" sem texto é representado simplesmente como "Outra".
4. O pedido tem três datas distintas: Data da solicitação (registro), Preciso para (informada), Data prevista = Data da solicitação + 3 dias úteis (não 72h, não corridos); estrutura preparada para no futuro exibir "3 dias" sem implementar isso agora.
5. Anexos (imagens e documentos) no pedido, com tipos permitidos, limite de tamanho, armazenamento privado, nome seguro, download autorizado; quem não pode ver o pedido não acessa o anexo por URL direta; arquivos inválidos rejeitados.
6. Histórico padronizado nos detalhes: cada evento mostra ação, descrição/contexto, data/hora e autor (ex.: "Pedido criado / Solicitação registrada para Residencial Aurora. / 16/09/2026 · João Silva"); nada é destruído.
7. Obra e Suprimentos adicionam observações livres no detalhe; cada uma vira um evento novo com conteúdo, autor, data/hora e pedido; não substitui a anterior.
8. Usuário Obra autorizado marca "Entregue" somente no detalhe do pedido (não na listagem); backend autoriza; evento com autor e data/hora.
9. Suprimentos anexa Romaneio, identificado tecnicamente como romaneio (não pelo nome do arquivo); aparece no pedido e no Histórico ("Romaneio anexado / <arquivo> / data · autor").
10. Novo status "Finalizado", distinto de "Entregue"; só Suprimentos finaliza; exige romaneio válido verificado no backend; sem romaneio: bloqueia, não altera status, sem estado parcial, erro visual "Não foi possível finalizar o pedido. Anexe o romaneio antes de finalizar."; com romaneio: status Finalizado + evento "Pedido finalizado / Pedido finalizado por Suprimentos.".
11. Nenhuma regressão (§44): pedido_events append-only, visibleTo antes de qualquer filtro, guards das Actions, estado terminal, policies, rate limiting, auditoria; sem limpeza destrutiva em migration (§43); telas desta fatia funcionam em mobile.

## Pontos já sabidos que precisam de atenção (não decidir produto sozinho — marcar [NEEDS CLARIFICATION] se o plano mestre não responder)
- Hoje existe `pedidos.expected_delivery_at` (nullable) preenchido manualmente por Suprimentos via `UpdatePedidoPrevisaoAction`, e o dashboard/`entreguesHoje` NÃO usa essa coluna. §14/§18 introduzem a Data prevista calculada. Relação entre as duas precisa ser definida.
- Dias úteis: feriados considerados ou só seg–sex? Fuso/“data da solicitação” em America/Sao_Paulo?
- Transições atuais permitem ir a `entregue` de qualquer status ativo; `entregue` e `cancelado` são terminais hoje. Onde Finalizado entra (a partir de quais status; Entregue deixa de ser terminal para permitir Finalizar depois de Entregue?), e se Finalizado é terminal.
- Obra marcar Entregue: a partir de quais status; requer associação à obra do pedido; pedidos "Outra" (sem obra) — quem pode?
- Visibilidade de pedidos "Outra" para usuários `obra` (apenas o próprio solicitante?).
- Observações e anexos em pedido terminal: permitidos?
- Romaneio: pode haver mais de um? substituir? tipos permitidos?
- Kanban e dashboard com o novo status (coluna? contagem em indicadores/prazos/pendentes?).
