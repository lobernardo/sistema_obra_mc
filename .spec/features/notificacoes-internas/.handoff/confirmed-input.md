# Confirmed input — notificacoes-internas

**Summary:** Nova área "Notificações Internas": eventos relevantes de pedidos viram notificações in-app escopadas por papel (página na sidebar + sino global com não lidas) e também e-mail ao destinatário.

**Tier:** complete

## Original description (developer, PT-BR)

Preciso criar uma nova aba no menu "Notificações Internas". Existirão pedidos que receberão informações diversas quanto a falta de produto, troca, atraso, ou qualquer outra coisa referente ao pedido, bem como alteração de status; O objetivo é tornar tudo isso rastreavel e de facil acompanhamento e visualização. Quero que qualquer observação de pedido, qualquer alteração de status e o que mais for relevante vire notificação. Nos perfis de gestão, deve receber notificacoes gerais, no perfil de suprimentos, notificacoes referentes a obras que eles estejam associados e cuidando. E no perfil de obras, notificacoes que sejam referentes a obra deles. Quero que além do menu no sidebar, contendo todas as informações relevantes dessas notificacoes, tenha tambem um icone de sino fixo com todas as notificacoes nao lidas, novas notificacoes. E que, as notificacoes alem de acusarem no sistema, seja notificada tambem via e-mail.

## Confirmed acceptance criteria (source of truth)

1. Recipients:
   - Gestão: todas as notificações.
   - Suprimentos: notificações de pedidos das obras às quais o usuário está **associado** (`obra_profile`). NÃO limitado ao `responsible_id` do pedido; todos os Suprimentos associados à obra recebem.
   - Obra: apenas notificações de pedidos das próprias obras (associadas).
   - Nenhum usuário recebe notificação de pedido que não poderia ver (`PedidoPolicy::view`).
2. Usuários inativos (`is_active = false`) não recebem notificação nem e-mail.
3. O autor da ação não recebe notificação nem e-mail da própria ação.
4. Eventos inicialmente relevantes: nova observação/comentário; alteração de status (inclui Entregue, Cancelado, Finalizado); alteração de responsável; alteração de prioridade; alteração de previsão/data relevante; registro de falta de produto; troca/substituição de produto; atraso; problemas de entrega; outras ocorrências relevantes registradas no pedido; inclusão de romaneio ou documento relevante. Criação de novo pedido: gerar notificação SE fizer sentido no fluxo atual — analisar com base no funcionamento existente e justificar na SPEC.
5. Arquitetura extensível: novos tipos de evento podem ser adicionados depois sem reconstruir o mecanismo.
6. Cada notificação também gera e-mail ao destinatário, sinalizando e descrevendo a notificação com link para o pedido. O envio NÃO pode deixar lentas operações como alterar status ou incluir observação. Analisar a arquitetura atual (envio síncrono, sem worker/fila/scheduler em produção) e propor a forma mais simples e segura; se fila/background job for necessário, sinalizar claramente na SPEC, sem complexidade desnecessária.
7. Página "Notificações Internas" na sidebar (3 papéis) com as informações relevantes (pedido, obra, tipo, conteúdo, autor, data/hora), acesso direto ao pedido, marcação individual como lida e "marcar todas como lidas".
8. Sino global fixo no layout com contador de não lidas e as novas notificações não lidas.
9. Notificações são append-only exceto o estado de leitura; cada usuário vê só as suas.

## Notes for the specifier

- "Falta de produto", "troca", "atraso", "problemas de entrega", "outras ocorrências" NÃO existem hoje como eventos registráveis: hoje só há observação livre (`observacao`). Model how these are registered (e.g. categoria de ocorrência na observação) and mark as [NEEDS CLARIFICATION] where it is a product decision (who may register each, whether "atraso" is manual vs. automatic — note there is no scheduler in production).
- Suprimentos today sees ALL pedidos regardless of association; notification scoping by association is narrower than visibility — state it explicitly.
- Gestão has no `obra_profile` associations.
- Pedidos "Outra" (`obra_id` null) have no obra: decide/flag who among Suprimentos/Obra receives.
