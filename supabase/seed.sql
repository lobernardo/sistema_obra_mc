-- Lookup seeds. Idempotent: re-running never duplicates rows, matched on slug.

insert into public.roles (name, slug, description)
values
  ('Obra', 'obra', 'Solicita necessidades e acompanha os próprios pedidos.'),
  ('Suprimentos', 'suprimentos', 'Conduz operacionalmente todos os pedidos através do Kanban.'),
  ('Gestão', 'gestao', 'Visão consolidada e indicadores gerenciais, somente leitura sobre o fluxo.')
on conflict (slug) do nothing;

insert into public.statuses (name, slug, description, sort_order)
values
  ('Solicitado', 'solicitado', 'Pedido criado pela Obra, aguardando triagem.', 1),
  ('Em análise', 'em_analise', 'Suprimentos está avaliando o pedido.', 2),
  ('Em compra/preparação', 'em_compra_preparacao', 'Compra ou preparação do pedido em andamento.', 3),
  ('Aguardando entrega', 'aguardando_entrega', 'Pedido comprado/preparado, aguardando entrega.', 4),
  ('Entregue', 'entregue', 'Pedido entregue — estado final do fluxo normal.', 5),
  ('Cancelado', 'cancelado', 'Pedido cancelado por Suprimentos — estado terminal fora do Kanban.', 6)
on conflict (slug) do nothing;

insert into public.priorities (name, slug, sort_order)
values
  ('Baixa', 'baixa', 1),
  ('Normal', 'normal', 2),
  ('Alta', 'alta', 3),
  ('Urgente', 'urgente', 4)
on conflict (slug) do nothing;

insert into public.event_types (name, slug, description)
values
  ('Criação do pedido', 'criacao_pedido', 'Pedido criado pela Obra.'),
  ('Mudança de status', 'mudanca_status', 'Pedido movido entre status do workflow.'),
  ('Alteração de responsável', 'alteracao_responsavel', 'Responsável do pedido definido ou alterado.'),
  ('Alteração de prioridade', 'alteracao_prioridade', 'Prioridade do pedido definida ou alterada.'),
  ('Alteração de previsão', 'alteracao_previsao', 'Previsão de entrega definida ou alterada.'),
  ('Cancelamento', 'cancelamento', 'Pedido cancelado por Suprimentos.'),
  ('Entrega', 'entrega', 'Pedido marcado como entregue.')
on conflict (slug) do nothing;
