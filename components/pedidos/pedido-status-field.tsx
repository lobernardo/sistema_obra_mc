"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { StatusControl } from "./status-control";
import { notifyError, notifySuccess } from "@/lib/toast";
import { moveStatus } from "@/app/suprimentos/actions";
import type { Status } from "@/lib/types/domain";

export interface PedidoStatusFieldProps {
  pedidoId: string;
  statusId: string;
  statuses: Status[];
}

/**
 * Detail-page wrapper around `StatusControl` (US-3.6) that owns its own
 * persistence. The Kanban board wires the same `StatusControl` to its own
 * optimistic move handler instead (Fase 7.1), but both ultimately call
 * `moveStatus` (Fase 7.2) → `updatePedidoStatus` (Fase 3.2), so a change
 * here and a drag-and-drop move produce the exact same persisted status and
 * `mudanca_status`/`entrega` event.
 */
export function PedidoStatusField({ pedidoId, statusId, statuses }: PedidoStatusFieldProps) {
  const router = useRouter();
  const [value, setValue] = useState(statusId);
  const [isPending, startTransition] = useTransition();

  function handleChange(status: Status) {
    const previous = value;
    setValue(status.id);

    startTransition(async () => {
      const result = await moveStatus(pedidoId, status.id);

      if (result.error) {
        setValue(previous);
        notifyError(result.error);
        return;
      }

      notifySuccess("Status atualizado.");
      router.refresh();
    });
  }

  return (
    <StatusControl
      statusId={value}
      statuses={statuses}
      disabled={isPending}
      onChange={handleChange}
    />
  );
}
