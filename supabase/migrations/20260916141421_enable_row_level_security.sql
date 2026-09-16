-- Enable RLS on every public table. No policies are defined yet, so access via
-- the anon/authenticated keys is fail-closed by default until Phase 4.2 adds
-- policies. The service-role key still bypasses RLS for admin/seed routines.

alter table public.roles enable row level security;
alter table public.statuses enable row level security;
alter table public.priorities enable row level security;
alter table public.event_types enable row level security;
alter table public.profiles enable row level security;
alter table public.obras enable row level security;
alter table public.pedidos enable row level security;
alter table public.pedido_events enable row level security;
alter table public.obra_profile enable row level security;

-- Down (rollback):
-- alter table public.obra_profile disable row level security;
-- alter table public.pedido_events disable row level security;
-- alter table public.pedidos disable row level security;
-- alter table public.obras disable row level security;
-- alter table public.profiles disable row level security;
-- alter table public.event_types disable row level security;
-- alter table public.priorities disable row level security;
-- alter table public.statuses disable row level security;
-- alter table public.roles disable row level security;
