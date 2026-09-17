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
import { setResponsavel } from "@/app/suprimentos/actions";
import type { Profile } from "@/lib/types/domain";

const UNASSIGNED = "unassigned";

export interface ResponsavelControlProps {
  pedidoId: string;
  responsibleId: string | null;
  suprimentosProfiles: Profile[];
}

/**
 * Suprimentos-only control to assign/reassign a pedido's responsible
 * (US-3.2) — calls `updatePedidoResponsavel` (Fase 3.2), which persists the
 * change and records `alteracao_responsavel` in the timeline.
 */
export function ResponsavelControl({
  pedidoId,
  responsibleId,
  suprimentosProfiles,
}: ResponsavelControlProps) {
  const router = useRouter();
  const [value, setValue] = useState(responsibleId ?? UNASSIGNED);
  const [isPending, startTransition] = useTransition();

  // Select.Root only renders the selected item's label in the closed
  // trigger when given this value→label map — otherwise it falls back to
  // the raw id (see @base-ui/react's `items` prop docs).
  const items: Record<string, string> = { [UNASSIGNED]: "Sem responsável" };
  for (const profile of suprimentosProfiles) {
    items[profile.id] = profile.full_name;
  }

  function handleChange(next: string | null) {
    if (!next || next === value) return;
    const previous = value;
    setValue(next);

    startTransition(async () => {
      const result = await setResponsavel(pedidoId, next === UNASSIGNED ? null : next);

      if (result.error) {
        setValue(previous);
        notifyError(result.error);
        return;
      }

      notifySuccess("Responsável atualizado.");
      router.refresh();
    });
  }

  return (
    <Select value={value} onValueChange={handleChange} disabled={isPending} items={items}>
      <SelectTrigger aria-label="Responsável" className="w-full">
        <SelectValue placeholder="Selecione o responsável" />
      </SelectTrigger>
      <SelectContent>
        <SelectItem value={UNASSIGNED}>Sem responsável</SelectItem>
        {suprimentosProfiles.map((profile) => (
          <SelectItem key={profile.id} value={profile.id}>
            {profile.full_name}
          </SelectItem>
        ))}
      </SelectContent>
    </Select>
  );
}
