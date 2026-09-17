import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { StatusBadge } from "@/components/pedidos/status-badge";
import type { StatusCount } from "@/lib/pedidos/dashboard";

/** "Distribuição por status" indicator (PRD §22) — every status, including `cancelado`, in `sort_order`. */
export function StatusDistributionCard({ porStatus }: { porStatus: StatusCount[] }) {
  return (
    <Card>
      <CardHeader>
        <CardTitle>Distribuição por status</CardTitle>
      </CardHeader>
      <CardContent className="flex flex-col gap-2">
        {porStatus.map(({ status, count }) => (
          <div key={status.id} className="flex items-center justify-between gap-2">
            <StatusBadge status={status} />
            <span className="text-sm font-medium tabular-nums">{count}</span>
          </div>
        ))}
      </CardContent>
    </Card>
  );
}
