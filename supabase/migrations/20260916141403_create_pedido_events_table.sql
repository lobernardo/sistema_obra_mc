-- pedido_events: append-only history/audit trail for pedidos. Insert-only —
-- no updated_at column and no update trigger, since history is never edited.

create table public.pedido_events (
  id uuid primary key default gen_random_uuid(),
  pedido_id uuid not null references public.pedidos (id),
  event_type_id uuid not null references public.event_types (id),
  previous_value text,
  new_value text,
  actor_id uuid not null references public.profiles (id),
  created_at timestamptz not null default now()
);

-- Down (rollback):
-- drop table if exists public.pedido_events;
