"use client";

import { useState } from "react";
import { KanbanCard } from "./kanban-card";
import { EmptyState } from "@/components/shared/empty-state";
import { cn } from "@/lib/utils";
import type { PedidoComRelacoes, Status } from "@/lib/types/domain";

export interface KanbanColumnProps {
  status: Status;
  /** Pedidos currently in this column's status. */
  pedidos: PedidoComRelacoes[];
  /** Every pedido on the board, regardless of column — needed to resolve a drop from another column. */
  allPedidos: PedidoComRelacoes[];
  /** The active workflow statuses, for each card's accessible status select. */
  statuses: Status[];
  movingPedidoId: string | null;
  onMove: (pedido: PedidoComRelacoes, status: Status) => void;
}

/**
 * One Kanban column (US-3.1, PRD §18) — a drop target for drag-and-drop
 * (US-3.5) that scrolls independently so a column with many cards never
 * pushes the board layout around.
 */
export function KanbanColumn({
  status,
  pedidos,
  allPedidos,
  statuses,
  movingPedidoId,
  onMove,
}: KanbanColumnProps) {
  const [isDragOver, setIsDragOver] = useState(false);

  function handleDrop(event: React.DragEvent<HTMLDivElement>) {
    event.preventDefault();
    setIsDragOver(false);
    const pedidoId = event.dataTransfer.getData("text/plain");
    const pedido = allPedidos.find((p) => p.id === pedidoId);
    if (pedido) {
      onMove(pedido, status);
    }
  }

  return (
    <div className="flex min-w-64 flex-1 flex-col gap-2">
      <div className="flex items-center justify-between gap-2 px-1">
        <h2 className="text-sm font-semibold tracking-tight">{status.name}</h2>
        <span className="text-muted-foreground text-xs">{pedidos.length}</span>
      </div>
      <div
        onDragOver={(event) => {
          event.preventDefault();
          setIsDragOver(true);
        }}
        onDragLeave={() => setIsDragOver(false)}
        onDrop={handleDrop}
        data-status={status.slug}
        className={cn(
          "flex max-h-[calc(100vh-16rem)] flex-1 flex-col gap-2 overflow-y-auto rounded-lg p-2 ring-1 ring-foreground/10",
          isDragOver && "bg-muted/50 ring-primary/40",
        )}
      >
        {pedidos.length === 0 ? (
          <EmptyState title="Nenhum pedido" className="p-4" />
        ) : (
          pedidos.map((pedido) => (
            <KanbanCard
              key={pedido.id}
              pedido={pedido}
              statuses={statuses}
              isMoving={movingPedidoId === pedido.id}
              onMove={onMove}
            />
          ))
        )}
      </div>
    </div>
  );
}
