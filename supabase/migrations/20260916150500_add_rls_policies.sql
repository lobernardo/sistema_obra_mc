-- Phase 4.2 — RLS policies enforcing the PRD §7 permission matrix at the data
-- layer, not just in the UI. `current_role_slug()`/`is_obra_member()` are
-- SECURITY DEFINER so policies can resolve the caller's own role/obra
-- membership without needing open policies on `profiles`/`obra_profile`
-- (which would otherwise recurse into RLS on those same tables).

create function public.current_role_slug() returns text
  language sql
  security definer
  stable
  set search_path = public
as $$
  select r.slug
  from public.profiles p
  join public.roles r on r.id = p.role_id
  where p.id = auth.uid();
$$;

create function public.is_obra_member(p_obra_id uuid) returns boolean
  language sql
  security definer
  stable
  set search_path = public
as $$
  select exists (
    select 1
    from public.obra_profile
    where obra_id = p_obra_id
      and profile_id = auth.uid()
  );
$$;

-- Lookup tables: read-only reference data every authenticated profile needs
-- to render statuses/priorities/roles/event types. Nothing sensitive here.
create policy roles_select on public.roles
  for select to authenticated using (true);

create policy statuses_select on public.statuses
  for select to authenticated using (true);

create policy priorities_select on public.priorities
  for select to authenticated using (true);

create policy event_types_select on public.event_types
  for select to authenticated using (true);

-- profiles: a profile always reads its own row (needed to resolve its own
-- session/role); suprimentos and gestao read every profile (needed to list
-- requesters/responsibles). No write policy — provisioning is trigger-only
-- (see handle_new_user), never client-writable.
create policy profiles_select on public.profiles
  for select to authenticated
  using (
    id = auth.uid()
    or public.current_role_slug() in ('suprimentos', 'gestao')
  );

-- obras: obra profiles see only their associated obras; suprimentos/gestao
-- see all. No write policy — obra management is administrative/seed-only.
create policy obras_select on public.obras
  for select to authenticated
  using (
    public.current_role_slug() in ('suprimentos', 'gestao')
    or public.is_obra_member(id)
  );

-- obra_profile: a profile reads its own associations; suprimentos/gestao
-- read all. No write policy — associations are seed/admin-managed.
create policy obra_profile_select on public.obra_profile
  for select to authenticated
  using (
    profile_id = auth.uid()
    or public.current_role_slug() in ('suprimentos', 'gestao')
  );

-- pedidos: obra sees only pedidos of its own obras; suprimentos/gestao see
-- all. Obra may insert only for an obra it belongs to, as itself (reinforces
-- createPedido). Only suprimentos may update, and only the four operational
-- columns — enforced with a column-level grant below, since RLS predicates
-- apply per row, not per column. No delete policy for any profile.
create policy pedidos_select on public.pedidos
  for select to authenticated
  using (
    public.current_role_slug() in ('suprimentos', 'gestao')
    or public.is_obra_member(obra_id)
  );

create policy pedidos_insert on public.pedidos
  for insert to authenticated
  with check (
    public.current_role_slug() = 'obra'
    and public.is_obra_member(obra_id)
    and requester_id = auth.uid()
  );

revoke update on public.pedidos from authenticated;
grant update (status_id, priority_id, responsible_id, expected_delivery_at)
  on public.pedidos to authenticated;

create policy pedidos_update_suprimentos on public.pedidos
  for update to authenticated
  using (public.current_role_slug() = 'suprimentos')
  with check (public.current_role_slug() = 'suprimentos');

-- pedido_events: readable by whoever can read the parent pedido (inherits
-- pedidos scope). No insert policy for any profile — history is written
-- exclusively by the Phase 3.2 service functions running under the
-- service-role client in server context, never by an end user's own session.
create policy pedido_events_select on public.pedido_events
  for select to authenticated
  using (
    exists (
      select 1
      from public.pedidos p
      where p.id = pedido_events.pedido_id
        and (
          public.current_role_slug() in ('suprimentos', 'gestao')
          or public.is_obra_member(p.obra_id)
        )
    )
  );

-- Down (rollback):
-- drop policy if exists pedido_events_select on public.pedido_events;
-- drop policy if exists pedidos_update_suprimentos on public.pedidos;
-- revoke update (status_id, priority_id, responsible_id, expected_delivery_at) on public.pedidos from authenticated;
-- grant update on public.pedidos to authenticated;
-- drop policy if exists pedidos_insert on public.pedidos;
-- drop policy if exists pedidos_select on public.pedidos;
-- drop policy if exists obra_profile_select on public.obra_profile;
-- drop policy if exists obras_select on public.obras;
-- drop policy if exists profiles_select on public.profiles;
-- drop policy if exists event_types_select on public.event_types;
-- drop policy if exists priorities_select on public.priorities;
-- drop policy if exists statuses_select on public.statuses;
-- drop policy if exists roles_select on public.roles;
-- drop function if exists public.is_obra_member(uuid);
-- drop function if exists public.current_role_slug();
