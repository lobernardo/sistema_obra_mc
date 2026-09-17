import { Suspense } from "react";
import { createClient } from "@/lib/supabase/server";
import { getCurrentProfile } from "@/lib/auth/session";
import {
  listObrasAcessiveis,
  listPedidos,
  listPriorities,
  listStatuses,
  listSuprimentosProfiles,
} from "@/lib/pedidos/queries";
import { parsePedidoFilters, type PedidoFilterSearchParams } from "@/lib/pedidos/filters";
import { PedidosTable } from "@/components/pedidos/pedidos-table";
import { PedidosFilterBar } from "@/components/pedidos/pedidos-filter-bar";
import { ListSkeleton } from "@/components/shared/skeletons";

export default async function SuprimentosPedidosPage({
  searchParams,
}: {
  searchParams: Promise<PedidoFilterSearchParams>;
}) {
  const params = await searchParams;
  const filters = parsePedidoFilters(params);

  const db = await createClient();
  const profile = await getCurrentProfile(db);
  const [obras, priorities, statuses, suprimentosProfiles] = await Promise.all([
    listObrasAcessiveis(db, profile!),
    listPriorities(db),
    listStatuses(db),
    listSuprimentosProfiles(db),
  ]);

  return (
    <div className="flex flex-1 flex-col gap-4 p-4 sm:p-6">
      <div className="flex flex-col gap-1">
        <h1 className="text-lg font-semibold tracking-tight">Todos os Pedidos</h1>
        <p className="text-muted-foreground text-sm">
          Consulte, filtre e busque pedidos de todas as obras.
        </p>
      </div>

      <Suspense>
        <PedidosFilterBar
          obras={obras}
          suprimentosProfiles={suprimentosProfiles}
          priorities={priorities}
          statuses={statuses}
        />
      </Suspense>

      <Suspense fallback={<ListSkeleton />}>
        <FilteredPedidos filters={filters} />
      </Suspense>
    </div>
  );
}

async function FilteredPedidos({
  filters,
}: {
  filters: ReturnType<typeof parsePedidoFilters>;
}) {
  const db = await createClient();
  const pedidos = await listPedidos(db, filters);

  return (
    <PedidosTable
      pedidos={pedidos}
      linkBasePath="/suprimentos/pedidos"
      emptyTitle="Nenhum pedido encontrado"
      emptyDescription="Ajuste os filtros ou a busca para encontrar pedidos."
    />
  );
}
