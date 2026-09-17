"use client";

import { useTransition } from "react";
import { useRouter } from "next/navigation";
import { PackageCheck } from "lucide-react";
import { Button } from "@/components/ui/button";
import { notifyError, notifySuccess } from "@/lib/toast";
import { moveStatus } from "@/app/suprimentos/actions";

export interface MarcarEntregueButtonProps {
  pedidoId: string;
  /** id of the `entregue` status row. */
  entregueStatusId: string;
}

/**
 * Dedicated one-click action to close the workflow (US-3.7, PRD §9.5) — a
 * `moveStatus` call to `entregue`, the same underlying persistence and
 * `entrega` event as picking "Entregue" from `StatusControl`, just surfaced
 * as an obvious, explicit action.
 */
export function MarcarEntregueButton({ pedidoId, entregueStatusId }: MarcarEntregueButtonProps) {
  const router = useRouter();
  const [isPending, startTransition] = useTransition();

  function handleClick() {
    startTransition(async () => {
      const result = await moveStatus(pedidoId, entregueStatusId);

      if (result.error) {
        notifyError(result.error);
        return;
      }

      notifySuccess("Pedido marcado como entregue.");
      router.refresh();
    });
  }

  return (
    <Button type="button" variant="outline" onClick={handleClick} disabled={isPending}>
      <PackageCheck data-icon="inline-start" />
      {isPending ? "Marcando..." : "Marcar como Entregue"}
    </Button>
  );
}
