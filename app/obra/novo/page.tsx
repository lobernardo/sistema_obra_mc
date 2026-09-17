import { createClient } from "@/lib/supabase/server";
import { getCurrentProfile } from "@/lib/auth/session";
import { listObrasAcessiveis } from "@/lib/pedidos/queries";
import { NovaSolicitacaoForm } from "@/components/pedidos/nova-solicitacao-form";

export default async function NovaSolicitacaoPage() {
  const db = await createClient();
  const profile = await getCurrentProfile(db);
  const obras = await listObrasAcessiveis(db, profile!);

  return (
    <div className="flex flex-1 flex-col gap-4 p-4 sm:p-6">
      <div className="flex flex-col gap-1">
        <h1 className="text-lg font-semibold tracking-tight">Nova Solicitação</h1>
        <p className="text-muted-foreground text-sm">
          Registre uma nova solicitação de compra para uma das suas obras.
        </p>
      </div>
      <NovaSolicitacaoForm obras={obras} />
    </div>
  );
}
