"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { KanbanColumn } from "./kanban-column";
import { notifyError, notifySuccess } from "@/lib/toast";
import { moveStatus } from "@/app/suprimentos/actions";
import type { PedidoComRelacoes, Status } from "@/lib/types/domain";

export interface KanbanBoardProps {
  initialPedidos: PedidoComRelacoes[];
  /** The 5 active workflow statuses (excludes `cancelado`), ordered by `sort_order`. */
  statuses: Status[];
}

/**
 * Suprimentos Kanban board (US-3.1, PRD §18) — one column per active
 * status. Owns the single `moveCard` handler that both drag-and-drop
 * (US-3.5) and each card's accessible status select (US-3.6) call, so the
 * two triggers always produce the exact same optimistic update, persistence
 * call and revert-on-failure behavior.
 */
export function KanbanBoard({ initialPedidos, statuses }: KanbanBoardProps) {
  const router = useRouter();
  const [pedidos, setPedidos] = useState(initialPedidos);
  const [movingPedidoId, setMovingPedidoId] = useState<string | null>(null);
  const [, startTransition] = useTransition();

  function moveCard(pedido: PedidoComRelacoes, status: Status) {
    if (pedido.status_id === status.id) return;

    const previous = pedido.status;
    setMovingPedidoId(pedido.id);
    setPedidos((current) =>
      current.map((p) => (p.id === pedido.id ? { ...p, status_id: status.id, status } : p)),
    );

    startTransition(async () => {
      const result = await moveStatus(pedido.id, status.id);

      if (result.error) {
        setPedidos((current) =>
          current.map((p) =>
            p.id === pedido.id ? { ...p, status_id: previous.id, status: previous } : p,
          ),
        );
        notifyError(result.error);
        setMovingPedidoId(null);
        return;
      }

      notifySuccess("Status atualizado.");
      setMovingPedidoId(null);
      router.refresh();
    });
  }

  return (
    <div className="flex flex-1 gap-3 overflow-x-auto pb-2">
      {statuses.map((status) => (
        <KanbanColumn
          key={status.id}
          status={status}
          pedidos={pedidos.filter((p) => p.status_id === status.id)}
          allPedidos={pedidos}
          statuses={statuses}
          movingPedidoId={movingPedidoId}
          onMove={moveCard}
        />
      ))}
    </div>
  );
}
