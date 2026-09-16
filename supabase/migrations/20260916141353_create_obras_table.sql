-- obras: construction sites/units that pedidos are associated with.

create table public.obras (
  id uuid primary key default gen_random_uuid(),
  name varchar not null,
  is_active boolean not null default true,
  is_demo boolean not null default false,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create trigger set_updated_at
  before update on public.obras
  for each row execute function public.set_updated_at();

-- Down (rollback):
-- drop table if exists public.obras;
