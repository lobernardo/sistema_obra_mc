"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { notifyError, notifySuccess } from "@/lib/toast";
import { setPrioridade } from "@/app/suprimentos/actions";
import type { Priority } from "@/lib/types/domain";

export interface PrioridadeControlProps {
  pedidoId: string;
  priorityId: string | null;
  /** The 4 V0 priority levels (PRD §13), ordered by severity. */
  priorities: Priority[];
}

/**
 * Suprimentos-only control to set a pedido's priority (US-3.3) — calls
 * `updatePedidoPrioridade` (Fase 3.2), which persists the change and
 * records `alteracao_prioridade` in the timeline.
 */
export function PrioridadeControl({ pedidoId, priorityId, priorities }: PrioridadeControlProps) {
  const router = useRouter();
  const [value, setValue] = useState(priorityId);
  const [isPending, startTransition] = useTransition();

  // Select.Root only renders the selected item's label in the closed
  // trigger when given this value→label map (see @base-ui/react's `items`
  // prop docs) — otherwise it falls back to the raw priority id.
  const items: Record<string, string> = {};
  for (const priority of priorities) {
    items[priority.id] = priority.name;
  }

  function handleChange(next: string | null) {
    if (!next || next === value) return;
    const previous = value;
    setValue(next);

    startTransition(async () => {
      const result = await setPrioridade(pedidoId, next);

      if (result.error) {
        setValue(previous);
        notifyError(result.error);
        return;
      }

      notifySuccess("Prioridade atualizada.");
      router.refresh();
    });
  }

  return (
    <Select value={value} onValueChange={handleChange} disabled={isPending} items={items}>
      <SelectTrigger aria-label="Prioridade" className="w-full">
        <SelectValue placeholder="Selecione a prioridade" />
      </SelectTrigger>
      <SelectContent>
        {priorities.map((priority) => (
          <SelectItem key={priority.id} value={priority.id}>
            {priority.name}
          </SelectItem>
        ))}
      </SelectContent>
    </Select>
  );
}
