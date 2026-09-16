import type { Db } from "@/lib/supabase/types";

/** Format every generated code must match: `PED-` + 6 digits. */
export const PEDIDO_CODE_PATTERN = /^PED-\d{6}$/;

/**
 * Generates the next unique, stable, user-facing pedido identifier
 * (`PED-000001`). Backed by the `pedidos_code_seq` Postgres sequence via the
 * `next_pedido_code()` SQL function, so `nextval` guarantees distinct codes
 * under concurrent callers without a retry-on-conflict loop.
 */
export async function generatePedidoCode(db: Db): Promise<string> {
  const { data, error } = await db.rpc("next_pedido_code");

  if (error) {
    throw new Error(`Failed to generate pedido code: ${error.message}`);
  }

  return data;
}
