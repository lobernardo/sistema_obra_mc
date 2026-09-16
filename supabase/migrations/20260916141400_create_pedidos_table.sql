-- pedidos: the trackable purchase request at the center of the domain.

create table public.pedidos (
  id uuid primary key default gen_random_uuid(),
  code varchar not null unique,
  obra_id uuid not null references public.obras (id),
  requester_id uuid not null references public.profiles (id),
  requested_at timestamptz not null default now(),
  needed_at date not null,
  items_description text not null,
  status_id uuid not null references public.statuses (id),
  priority_id uuid references public.priorities (id),
  responsible_id uuid references public.profiles (id),
  expected_delivery_at date,
  is_demo boolean not null default false,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create trigger set_updated_at
  before update on public.pedidos
  for each row execute function public.set_updated_at();

-- Down (rollback):
-- drop table if exists public.pedidos;
