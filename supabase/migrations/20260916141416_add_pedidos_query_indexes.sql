-- Support indexes for the Kanban, filtered listings and the history timeline
-- (see database-schema.md "Notes & Conventions" — indexes recomendados).

create index idx_pedidos_obra_id_status_id on public.pedidos (obra_id, status_id);
create index idx_pedidos_needed_at on public.pedidos (needed_at);
create index idx_pedido_events_pedido_id_created_at on public.pedido_events (pedido_id, created_at);

-- Down (rollback):
-- drop index if exists public.idx_pedido_events_pedido_id_created_at;
-- drop index if exists public.idx_pedidos_needed_at;
-- drop index if exists public.idx_pedidos_obra_id_status_id;
