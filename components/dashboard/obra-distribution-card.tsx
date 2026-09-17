import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { EmptyState } from "@/components/shared/empty-state";
import type { ObraCount } from "@/lib/pedidos/dashboard";

/**
 * "Visão por obra" indicator (PRD §22) — pedido count per obra in scope.
 * `porObra` covers every obra the profile can access (needed so its counts
 * sum to "Volume total"), but only obras with at least one pedido in the
 * current filters are worth a row — an org with many obras and a narrow
 * filter (e.g. a single obra selected) would otherwise bury the one row
 * that matters under a long tail of zeroes, against the "leitura rápida"
 * goal (PRD §27).
 */
export function ObraDistributionCard({ porObra }: { porObra: ObraCount[] }) {
  const withPedidos = porObra.filter(({ count }) => count > 0);

  return (
    <Card>
      <CardHeader>
        <CardTitle>Visão por obra</CardTitle>
      </CardHeader>
      <CardContent className="flex flex-col gap-2">
        {withPedidos.length === 0 ? (
          <EmptyState title="Nenhum pedido no escopo" className="p-2" />
        ) : (
          withPedidos.map(({ obra, count }) => (
            <div key={obra.id} className="flex items-center justify-between gap-2">
              <span className="text-sm">{obra.name}</span>
              <span className="text-sm font-medium tabular-nums">{count}</span>
            </div>
          ))
        )}
      </CardContent>
    </Card>
  );
}
