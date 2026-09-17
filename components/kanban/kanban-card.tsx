"use client";

import Link from "next/link";
import { Card, CardContent, CardHeader } from "@/components/ui/card";
import { PriorityBadge } from "@/components/pedidos/priority-badge";
import { AtrasoIndicator } from "@/components/pedidos/atraso-indicator";
import { StatusControl } from "@/components/pedidos/status-control";
import { formatDate } from "@/lib/format";
import { cn } from "@/lib/utils";
import type { PedidoComRelacoes, Status } from "@/lib/types/domain";

export interface KanbanCardProps {
  pedido: PedidoComRelacoes;
  /** The active workflow statuses (excludes `cancelado`), for the accessible status select. */
  statuses: Status[];
  isMoving: boolean;
  onMove: (pedido: PedidoComRelacoes, status: Status) => void;
  /** Hides drag-and-drop and the accessible status select — used by Gestão's read-only Kanban (US-7.4, PRD §18). */
  readOnly?: boolean;
  /** Prefixed to `pedido.code` to build the card's detail link. */
  linkBasePath?: string;
}

/**
 * Kanban card (US-3.1, PRD §18) — identifier, obra, resumo da necessidade,
 * data necessária, prioridade, responsável, previsão de entrega e condição
 * de atraso, plus the accessible status select (US-3.6) wired to the same
 * `onMove` handler drag-and-drop uses. In `readOnly` mode (Gestão), neither
 * of those mutation triggers renders at all.
 */
export function KanbanCard({
  pedido,
  statuses,
  isMoving,
  onMove,
  readOnly = false,
  linkBasePath = "/suprimentos/pedidos",
}: KanbanCardProps) {
  return (
    <Card
      draggable={!readOnly}
      onDragStart={
        readOnly
          ? undefined
          : (event) => {
              event.dataTransfer.setData("text/plain", pedido.id);
              event.dataTransfer.effectAllowed = "move";
            }
      }
      className={cn(
        "gap-2 py-3",
        !readOnly && "cursor-grab active:cursor-grabbing",
        isMoving && "opacity-60",
      )}
      aria-busy={isMoving}
    >
      <CardHeader className="flex flex-col gap-1 px-3">
        <div className="flex items-center justify-between gap-2">
          <Link
            href={`${linkBasePath}/${pedido.code}`}
            className="text-sm font-medium underline-offset-4 hover:underline"
          >
            {pedido.code}
          </Link>
          <PriorityBadge priority={pedido.priority} />
        </div>
        <span className="text-muted-foreground text-xs">{pedido.obra.name}</span>
      </CardHeader>
      <CardContent className="flex flex-col gap-2 px-3">
        <p className="line-clamp-2 text-sm">{pedido.items_description}</p>
        <div className="text-muted-foreground flex flex-wrap items-center gap-x-3 gap-y-1 text-xs">
          <span>Necessário em {formatDate(pedido.needed_at)}</span>
          <span>Previsão: {formatDate(pedido.expected_delivery_at)}</span>
        </div>
        <div className="flex items-center justify-between gap-2">
          <span className="text-muted-foreground text-xs">
            {pedido.responsible?.full_name ?? "Sem responsável"}
          </span>
          <AtrasoIndicator pedido={pedido} />
        </div>
        {readOnly ? null : (
          <StatusControl
            statusId={pedido.status_id}
            statuses={statuses}
            disabled={isMoving}
            onChange={(status) => onMove(pedido, status)}
          />
        )}
      </CardContent>
    </Card>
  );
}
