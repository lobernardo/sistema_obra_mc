import { Suspense } from "react";
import Link from "next/link";
import { createClient } from "@/lib/supabase/server";
import { listPedidos } from "@/lib/pedidos/queries";
import { PedidosTable } from "@/components/pedidos/pedidos-table";
import { ListSkeleton } from "@/components/shared/skeletons";
import { Button } from "@/components/ui/button";

export default function ObraPage() {
  return (
    <div className="flex flex-1 flex-col gap-4 p-4 sm:p-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex flex-col gap-1">
          <h1 className="text-lg font-semibold tracking-tight">Meus Pedidos</h1>
          <p className="text-muted-foreground text-sm">
            Acompanhe o andamento das solicitações das suas obras.
          </p>
        </div>
        <Button render={<Link href="/obra/novo" />}>+ Nova Solicitação</Button>
      </div>

      <Suspense fallback={<ListSkeleton />}>
        <MeusPedidos />
      </Suspense>
    </div>
  );
}

/**
 * RLS scopes `pedidos` SELECT to the Obra profile's own obras (Fase 4.2), so
 * this never needs to filter by obra — the query only ever sees rows the
 * signed-in profile is allowed to see.
 */
async function MeusPedidos() {
  const db = await createClient();
  const pedidos = await listPedidos(db, {});

  return (
    <PedidosTable
      pedidos={pedidos}
      linkBasePath="/obra"
      emptyTitle="Nenhum pedido encontrado"
      emptyDescription="Suas solicitações aparecerão aqui assim que forem criadas."
    />
  );
}
