-- Pedido code generation: a dedicated sequence backs `next_pedido_code()`, so
-- concurrent callers get distinct, monotonically increasing codes without a
-- retry-on-conflict loop (`nextval` is atomic under concurrent transactions).

create sequence public.pedidos_code_seq;

create function public.next_pedido_code() returns varchar
  language sql
  as $$
  select 'PED-' || lpad(nextval('public.pedidos_code_seq')::text, 6, '0');
$$;

-- create_pedido: creates a pedido and its `criacao_pedido` history event in a
-- single transaction. If the event insert fails, the whole function call
-- (including the pedido insert) rolls back — a pedido can never exist
-- without its creation event.
create function public.create_pedido(
  p_obra_id uuid,
  p_requester_id uuid,
  p_needed_at date,
  p_items_description text
) returns public.pedidos
  language plpgsql
  as $$
declare
  v_status_id uuid;
  v_event_type_id uuid;
  v_pedido public.pedidos;
begin
  select id into strict v_status_id
    from public.statuses
    order by sort_order asc
    limit 1;

  select id into strict v_event_type_id
    from public.event_types
    where slug = 'criacao_pedido';

  insert into public.pedidos (
    code, obra_id, requester_id, needed_at, items_description, status_id, requested_at
  )
  values (
    public.next_pedido_code(), p_obra_id, p_requester_id, p_needed_at, p_items_description,
    v_status_id, now()
  )
  returning * into v_pedido;

  insert into public.pedido_events (pedido_id, event_type_id, actor_id, new_value)
  values (v_pedido.id, v_event_type_id, p_requester_id, row_to_json(v_pedido)::text);

  return v_pedido;
end;
$$;

-- Down (rollback):
-- drop function if exists public.create_pedido(uuid, uuid, date, text);
-- drop function if exists public.next_pedido_code();
-- drop sequence if exists public.pedidos_code_seq;
