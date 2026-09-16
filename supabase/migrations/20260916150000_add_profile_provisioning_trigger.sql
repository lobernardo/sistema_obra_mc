-- Auto-provisions a `profiles` row whenever a user is created in `auth.users`
-- (via admin/seed — see Phase 4.1). `role_slug` and `full_name` travel in
-- `raw_user_meta_data`, set by the seed/admin routine creating the user —
-- never chosen by the user themselves, since no self-signup UI exists.

create function public.handle_new_user() returns trigger
  language plpgsql
  security definer
  set search_path = public
as $$
declare
  v_role_id uuid;
begin
  select id into v_role_id
    from public.roles
    where slug = new.raw_user_meta_data ->> 'role_slug';

  if v_role_id is null then
    raise exception 'handle_new_user: role_slug "%" not found in roles', new.raw_user_meta_data ->> 'role_slug';
  end if;

  insert into public.profiles (id, full_name, role_id)
  values (
    new.id,
    coalesce(new.raw_user_meta_data ->> 'full_name', new.email),
    v_role_id
  );

  return new;
end;
$$;

create trigger on_auth_user_created
  after insert on auth.users
  for each row execute function public.handle_new_user();

-- Down (rollback):
-- drop trigger if exists on_auth_user_created on auth.users;
-- drop function if exists public.handle_new_user();
