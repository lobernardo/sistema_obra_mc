-- profiles: mirrors auth.users, one row per authenticated user, carries the role.

create table public.profiles (
  id uuid primary key references auth.users (id) on delete cascade,
  full_name varchar not null,
  role_id uuid not null references public.roles (id),
  is_active boolean not null default true,
  is_demo boolean not null default false,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create trigger set_updated_at
  before update on public.profiles
  for each row execute function public.set_updated_at();

-- Down (rollback):
-- drop table if exists public.profiles;
