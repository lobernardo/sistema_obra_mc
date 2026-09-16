-- Lookup tables: roles, statuses, priorities, event_types.
-- Every categorical field in the schema is a lookup table with an FK, never a
-- database enum (see database-schema.md "Notes & Conventions").

create function public.set_updated_at() returns trigger
  language plpgsql
  as $$
begin
  new.updated_at = now();
  return new;
end;
$$;

create table public.roles (
  id uuid primary key default gen_random_uuid(),
  name varchar not null,
  slug varchar not null unique,
  description text,
  is_active boolean not null default true,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create trigger set_updated_at
  before update on public.roles
  for each row execute function public.set_updated_at();

create table public.statuses (
  id uuid primary key default gen_random_uuid(),
  name varchar not null,
  slug varchar not null unique,
  description text,
  sort_order integer not null unique,
  is_active boolean not null default true,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create trigger set_updated_at
  before update on public.statuses
  for each row execute function public.set_updated_at();

create table public.priorities (
  id uuid primary key default gen_random_uuid(),
  name varchar not null,
  slug varchar not null unique,
  sort_order integer not null unique,
  is_active boolean not null default true,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create trigger set_updated_at
  before update on public.priorities
  for each row execute function public.set_updated_at();

create table public.event_types (
  id uuid primary key default gen_random_uuid(),
  name varchar not null,
  slug varchar not null unique,
  description text,
  is_active boolean not null default true,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create trigger set_updated_at
  before update on public.event_types
  for each row execute function public.set_updated_at();

-- Down (rollback):
-- drop table if exists public.event_types;
-- drop table if exists public.priorities;
-- drop table if exists public.statuses;
-- drop table if exists public.roles;
-- drop function if exists public.set_updated_at();
