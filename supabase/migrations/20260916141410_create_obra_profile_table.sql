-- obra_profile: many-to-many pivot between obras and profiles of role "obra".

create table public.obra_profile (
  obra_id uuid not null references public.obras (id),
  profile_id uuid not null references public.profiles (id),
  created_at timestamptz not null default now(),
  primary key (obra_id, profile_id)
);

-- Down (rollback):
-- drop table if exists public.obra_profile;
