import type { ReactNode } from "react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { StatusBadge } from "./status-badge";
import { PriorityBadge } from "./priority-badge";
import { AtrasoIndicator } from "./atraso-indicator";
import { PedidoHistoryTimeline } from "./pedido-history-timeline";
import { formatDate, formatDateTime } from "@/lib/format";
import { isPedidoAtrasado } from "@/lib/pedidos/atraso";
import type { PedidoComHistorico } from "@/lib/types/domain";

function DetailField({ label, value }: { label: string; value: ReactNode }) {
  return (
    <div className="flex flex-col gap-1">
      <span className="text-muted-foreground text-xs">{label}</span>
      <div className="text-sm">{value}</div>
    </div>
  );
}

export interface PedidoDetailLayoutProps {
  pedido: PedidoComHistorico;
  /** Hides every edit control — used by Obra and Gestão. */
  readOnly: boolean;
  /** Editable controls, rendered only when `readOnly` is false — wired in by Suprimentos screens (Phase 7.2). */
  statusControl?: ReactNode;
  priorityControl?: ReactNode;
  responsibleControl?: ReactNode;
  previsaoControl?: ReactNode;
}

/**
 * Structures the pedido detail into the three sections of PRD §19:
 * Solicitação (always read-only), Operação (editable only for Suprimentos)
 * and Histórico. Reused as-is by the Obra, Suprimentos and Gestão detail
 * screens (US-4.2, US-6.2) — only `readOnly` and the control slots change.
 */
export function PedidoDetailLayout({
  pedido,
  readOnly,
  statusControl,
  priorityControl,
  responsibleControl,
  previsaoControl,
}: PedidoDetailLayoutProps) {
  const atrasado = isPedidoAtrasado(pedido);

  return (
    <div className="flex flex-col gap-4">
      <div className="grid gap-4 md:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle>Solicitação</CardTitle>
          </CardHeader>
          <CardContent className="grid gap-4 sm:grid-cols-2">
            <DetailField label="Identificador" value={pedido.code} />
            <DetailField label="Obra" value={pedido.obra.name} />
            <DetailField label="Solicitante" value={pedido.requester.full_name} />
            <DetailField label="Data da solicitação" value={formatDateTime(pedido.requested_at)} />
            <DetailField label="Data necessária" value={formatDate(pedido.needed_at)} />
            <div className="sm:col-span-2">
              <DetailField
                label="Itens/quantidades"
                value={<p className="whitespace-pre-wrap">{pedido.items_description}</p>}
              />
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Operação</CardTitle>
          </CardHeader>
          <CardContent className="grid gap-4 sm:grid-cols-2">
            <DetailField
              label="Status"
              value={
                !readOnly && statusControl ? statusControl : <StatusBadge status={pedido.status} />
              }
            />
            <DetailField
              label="Prioridade"
              value={
                !readOnly && priorityControl ? (
                  priorityControl
                ) : (
                  <PriorityBadge priority={pedido.priority} />
                )
              }
            />
            <DetailField
              label="Responsável"
              value={
                !readOnly && responsibleControl
                  ? responsibleControl
                  : (pedido.responsible?.full_name ?? "—")
              }
            />
            <DetailField
              label="Previsão de entrega"
              value={
                !readOnly && previsaoControl
                  ? previsaoControl
                  : formatDate(pedido.expected_delivery_at)
              }
            />
            <div className="sm:col-span-2">
              <DetailField
                label="Condição de atraso"
                value={
                  atrasado ? (
                    <AtrasoIndicator atrasado />
                  ) : (
                    <span className="text-sm">Dentro do prazo</span>
                  )
                }
              />
            </div>
          </CardContent>
        </Card>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Histórico</CardTitle>
        </CardHeader>
        <CardContent>
          <PedidoHistoryTimeline events={pedido.events} />
        </CardContent>
      </Card>
    </div>
  );
}
