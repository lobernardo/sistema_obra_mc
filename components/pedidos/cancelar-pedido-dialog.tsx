"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { notifyError, notifySuccess } from "@/lib/toast";
import { cancelarPedido } from "@/app/suprimentos/actions";

export interface CancelarPedidoDialogProps {
  pedidoId: string;
  pedidoCode: string;
}

/**
 * Suprimentos-only, irreversible cancellation with a confirmation dialog
 * (US-5.1, PRD §21) — calls `cancelarPedido`, which persists `status =
 * cancelado` and records the `cancelamento` event. Only ever rendered from
 * the Suprimentos pedido detail screen — never on Obra or Gestão screens.
 */
export function CancelarPedidoDialog({ pedidoId, pedidoCode }: CancelarPedidoDialogProps) {
  const router = useRouter();
  const [open, setOpen] = useState(false);
  const [isPending, startTransition] = useTransition();

  function handleConfirm() {
    startTransition(async () => {
      const result = await cancelarPedido(pedidoId);

      if (result.error) {
        notifyError(result.error);
        return;
      }

      notifySuccess(`Pedido ${pedidoCode} cancelado.`);
      setOpen(false);
      router.refresh();
    });
  }

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger render={<Button type="button" variant="destructive" />}>
        Cancelar Pedido
      </DialogTrigger>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Cancelar pedido {pedidoCode}?</DialogTitle>
          <DialogDescription>
            Essa ação é irreversível. O pedido sairá das colunas ativas do Kanban e seu histórico
            será preservado.
          </DialogDescription>
        </DialogHeader>
        <DialogFooter>
          <Button
            type="button"
            variant="outline"
            onClick={() => setOpen(false)}
            disabled={isPending}
          >
            Voltar
          </Button>
          <Button type="button" variant="destructive" onClick={handleConfirm} disabled={isPending}>
            {isPending ? "Cancelando..." : "Confirmar cancelamento"}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
