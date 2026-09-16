/**
 * Hand-written mirror of the Postgres schema in `supabase/migrations/`.
 * Shape matches the `Database["public"]["Tables"][...]` convention used by
 * `supabase gen types typescript`, so `createClient<Database>()` and
 * `.from(...)`/`.rpc(...)` stay fully typed across the app.
 *
 * Every row shape below is a `type`, not an `interface` — TypeScript only
 * grants the "implicit index signature" that makes a shape assignable to
 * `Record<string, unknown>` (what supabase-js's `GenericTable` requires) to
 * plain object type aliases, not to declared interfaces.
 */

export type RoleRow = {
  id: string;
  name: string;
  slug: string;
  description: string | null;
  is_active: boolean;
  created_at: string;
  updated_at: string;
};

export type StatusRow = {
  id: string;
  name: string;
  slug: string;
  description: string | null;
  sort_order: number;
  is_active: boolean;
  created_at: string;
  updated_at: string;
};

export type PriorityRow = {
  id: string;
  name: string;
  slug: string;
  sort_order: number;
  is_active: boolean;
  created_at: string;
  updated_at: string;
};

export type EventTypeRow = {
  id: string;
  name: string;
  slug: string;
  description: string | null;
  is_active: boolean;
  created_at: string;
  updated_at: string;
};

export type ProfileRow = {
  id: string;
  full_name: string;
  role_id: string;
  is_active: boolean;
  is_demo: boolean;
  created_at: string;
  updated_at: string;
};

export type ObraRow = {
  id: string;
  name: string;
  is_active: boolean;
  is_demo: boolean;
  created_at: string;
  updated_at: string;
};

export type PedidoRow = {
  id: string;
  code: string;
  obra_id: string;
  requester_id: string;
  requested_at: string;
  needed_at: string;
  items_description: string;
  status_id: string;
  priority_id: string | null;
  responsible_id: string | null;
  expected_delivery_at: string | null;
  is_demo: boolean;
  created_at: string;
  updated_at: string;
};

export type PedidoEventRow = {
  id: string;
  pedido_id: string;
  event_type_id: string;
  previous_value: string | null;
  new_value: string | null;
  actor_id: string;
  created_at: string;
};

export type ObraProfileRow = {
  obra_id: string;
  profile_id: string;
  created_at: string;
};

type TableDef<Row, InsertDefaults extends keyof Row, Immutable extends keyof Row = never> = {
  Row: Row;
  Insert: Partial<Pick<Row, InsertDefaults>> & Omit<Row, InsertDefaults>;
  Update: Partial<Omit<Row, Immutable>>;
  Relationships: [];
};

export type Database = {
  public: {
    Tables: {
      roles: TableDef<RoleRow, "id" | "description" | "is_active" | "created_at" | "updated_at">;
      statuses: TableDef<
        StatusRow,
        "id" | "description" | "is_active" | "created_at" | "updated_at"
      >;
      priorities: TableDef<PriorityRow, "id" | "is_active" | "created_at" | "updated_at">;
      event_types: TableDef<
        EventTypeRow,
        "id" | "description" | "is_active" | "created_at" | "updated_at"
      >;
      profiles: TableDef<ProfileRow, "is_active" | "is_demo" | "created_at" | "updated_at", "id">;
      obras: TableDef<ObraRow, "id" | "is_active" | "is_demo" | "created_at" | "updated_at">;
      pedidos: TableDef<
        PedidoRow,
        | "id"
        | "requested_at"
        | "priority_id"
        | "responsible_id"
        | "expected_delivery_at"
        | "is_demo"
        | "created_at"
        | "updated_at"
      >;
      pedido_events: TableDef<PedidoEventRow, "id" | "previous_value" | "new_value" | "created_at">;
      obra_profile: TableDef<ObraProfileRow, "created_at">;
    };
    Views: Record<string, never>;
    Functions: {
      next_pedido_code: {
        Args: Record<string, never>;
        Returns: string;
      };
      create_pedido: {
        Args: {
          p_obra_id: string;
          p_requester_id: string;
          p_needed_at: string;
          p_items_description: string;
        };
        Returns: PedidoRow;
      };
    };
  };
};
