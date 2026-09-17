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
  /**
   * Renders the board with no drag-and-drop, status select, or other
   * mutation control — used by Gestão's read-only Kanban (US-7.4, PRD §18).
   * The database's RLS policies (Fase 4.2) independently reject any write
   * attempt on `pedidos`, so this is a UI convenience, not the authorization
   * boundary.
   */
  readOnly?: boolean;
  /** Prefixed to each card's `pedido.code` to build its detail link. */
  linkBasePath?: string;
}

/**
 * Suprimentos Kanban board (US-3.1, PRD §18) — one column per active
 * status. Owns the single `moveCard` handler that both drag-and-drop
 * (US-3.5) and each card's accessible status select (US-3.6) call, so the
 * two triggers always produce the exact same optimistic update, persistence
 * call and revert-on-failure behavior. Also reused read-only by Gestão
 * (Fase 8.3): `readOnly` disables every drop target and hides every card's
 * status select, so `moveCard` is wired but structurally unreachable.
 */
export function KanbanBoard({
  initialPedidos,
  statuses,
  readOnly = false,
  linkBasePath,
}: KanbanBoardProps) {
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
          readOnly={readOnly}
          linkBasePath={linkBasePath}
        />
      ))}
    </div>
  );
}
