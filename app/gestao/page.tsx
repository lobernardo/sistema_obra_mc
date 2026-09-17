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
import {
  parsePedidoFilters,
  serializePedidoFilters,
  type PedidoFilterSearchParams,
} from "@/lib/pedidos/filters";
import { computeDashboardIndicators } from "@/lib/pedidos/dashboard";
import type { PedidoFilters } from "@/lib/pedidos/queries";
import type { Obra, Status } from "@/lib/types/domain";
import { DashboardFilterBar } from "@/components/dashboard/dashboard-filter-bar";
import { IndicatorCard } from "@/components/dashboard/indicator-card";
import { StatusDistributionCard } from "@/components/dashboard/status-distribution-card";
import { PrazosCard } from "@/components/dashboard/prazos-card";
import { ObraDistributionCard } from "@/components/dashboard/obra-distribution-card";
import { DashboardIndicatorSkeleton } from "@/components/shared/skeletons";

export default async function GestaoPage({
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
        <h1 className="text-lg font-semibold tracking-tight">Dashboard</h1>
        <p className="text-muted-foreground text-sm">
          Indicadores consolidados da operação, com filtros e drill-down para os pedidos.
        </p>
      </div>

      <Suspense>
        <DashboardFilterBar
          obras={obras}
          suprimentosProfiles={suprimentosProfiles}
          priorities={priorities}
          statuses={statuses}
        />
      </Suspense>

      <Suspense fallback={<DashboardSkeletonGrid />}>
        <DashboardIndicators filters={filters} statuses={statuses} obras={obras} />
      </Suspense>
    </div>
  );
}

function DashboardSkeletonGrid() {
  return (
    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      {Array.from({ length: 6 }, (_, i) => (
        <DashboardIndicatorSkeleton key={i} />
      ))}
    </div>
  );
}

async function DashboardIndicators({
  filters,
  statuses,
  obras,
}: {
  filters: PedidoFilters;
  statuses: Status[];
  obras: Obra[];
}) {
  const db = await createClient();
  const pedidos = await listPedidos(db, filters);
  const indicators = computeDashboardIndicators(pedidos, statuses, obras);
  const baseQuery = serializePedidoFilters(filters);

  return (
    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <IndicatorCard title="Volume total" value={indicators.volumeTotal} />
      <IndicatorCard
        title="Pendentes"
        value={indicators.pendentes}
        description="Não entregues nem cancelados"
        href={`/gestao/pedidos?${appendQuery(baseQuery, "pendente", "true")}`}
      />
      <IndicatorCard
        title="Atrasados"
        value={indicators.atrasados}
        description="Data necessária vencida e ainda ativos"
        href={`/gestao/pedidos?${appendQuery(baseQuery, "atrasado", "true")}`}
      />
      <StatusDistributionCard porStatus={indicators.porStatus} />
      <PrazosCard prazos={indicators.prazos} />
      <ObraDistributionCard porObra={indicators.porObra} />
    </div>
  );
}

function appendQuery(baseQuery: string, key: string, value: string): string {
  const params = new URLSearchParams(baseQuery);
  params.set(key, value);
  return params.toString();
}
