"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { format } from "date-fns";
import { CalendarIcon } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { Calendar } from "@/components/ui/calendar";
import { notifyError, notifySuccess } from "@/lib/toast";
import { setPrevisao } from "@/app/suprimentos/actions";

export interface PrevisaoControlProps {
  pedidoId: string;
  expectedDeliveryAt: string | null;
}

/**
 * Suprimentos-only date picker to register/change a pedido's previsão de
 * entrega (US-3.4) — calls `updatePedidoPrevisao` (Fase 3.2), which
 * persists the change and records `alteracao_previsao` in the timeline.
 */
export function PrevisaoControl({ pedidoId, expectedDeliveryAt }: PrevisaoControlProps) {
  const router = useRouter();
  // Parsed without a `Z` suffix, so it lands on local midnight — matching
  // the local `Date` the Calendar hands back on selection (see
  // `handleSelect`). Mixing a UTC-anchored initial value with a
  // local-time-formatted one would roll the displayed day back by one in
  // any timezone behind UTC.
  const [date, setDate] = useState<Date | undefined>(
    expectedDeliveryAt ? new Date(`${expectedDeliveryAt}T00:00:00`) : undefined,
  );
  const [open, setOpen] = useState(false);
  const [isPending, startTransition] = useTransition();

  function handleSelect(next: Date | undefined) {
    if (!next) return;
    const previous = date;
    setDate(next);
    setOpen(false);
    const iso = format(next, "yyyy-MM-dd");

    startTransition(async () => {
      const result = await setPrevisao(pedidoId, iso);

      if (result.error) {
        setDate(previous);
        notifyError(result.error);
        return;
      }

      notifySuccess("Previsão de entrega atualizada.");
      router.refresh();
    });
  }

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger
        render={
          <Button
            variant="outline"
            type="button"
            disabled={isPending}
            className="w-full justify-start font-normal"
          />
        }
      >
        <CalendarIcon />
        {date ? format(date, "dd/MM/yyyy") : "Selecione a data"}
      </PopoverTrigger>
      <PopoverContent className="w-auto p-0">
        <Calendar mode="single" selected={date} onSelect={handleSelect} />
      </PopoverContent>
    </Popover>
  );
}
