"use server";

import { createClient } from "@/lib/supabase/server";
import { createAdminClient } from "@/lib/supabase/admin";
import { getCurrentProfile } from "@/lib/auth/session";
import { createPedido } from "@/lib/pedidos/service";
import { ForbiddenError, ValidationError } from "@/lib/pedidos/errors";

export interface NovaSolicitacaoState {
  error?: string;
  pedido?: { code: string };
}

const GENERIC_ERROR_MESSAGE = "Não foi possível criar a solicitação. Tente novamente.";

export async function createSolicitacao(
  _prevState: NovaSolicitacaoState,
  formData: FormData,
): Promise<NovaSolicitacaoState> {
  const obraId = String(formData.get("obra_id") ?? "");
  const neededAt = String(formData.get("needed_at") ?? "");
  const itemsDescription = String(formData.get("items_description") ?? "").trim();

  const db = await createClient();
  const profile = await getCurrentProfile(db);

  if (!profile) {
    return { error: "Sessão expirada. Faça login novamente." };
  }

  try {
    // `pedido_events` has no client-writable RLS policy by design (see
    // Fase 4.2's RLS migration) — the creation event it writes alongside the
    // pedido must go through the service-role client, with `profile` (from
    // the RLS-scoped session above) as the trusted authorization boundary.
    const adminDb = createAdminClient();
    const pedido = await createPedido(
      adminDb,
      { obra_id: obraId, needed_at: neededAt, items_description: itemsDescription },
      profile,
    );
    return { pedido: { code: pedido.code } };
  } catch (err) {
    if (err instanceof ValidationError || err instanceof ForbiddenError) {
      return { error: err.message };
    }
    return { error: GENERIC_ERROR_MESSAGE };
  }
}
