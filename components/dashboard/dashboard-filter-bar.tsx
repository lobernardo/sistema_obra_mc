"use client";

import { useCallback } from "react";
import { usePathname, useRouter, useSearchParams } from "next/navigation";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Button } from "@/components/ui/button";
import type { Obra, Priority, Profile, Status } from "@/lib/types/domain";

const ALL = "all";

export interface DashboardFilterBarProps {
  obras: Obra[];
  suprimentosProfiles: Profile[];
  priorities: Priority[];
  statuses: Status[];
}

/**
 * Combinable dashboard filters (US-7.2, PRD §23): período, obra, status,
 * prioridade e responsável. Writes straight to the URL query string using
 * the same keys `parsePedidoFilters` reads, so applying a filter here
 * updates every indicator in Fase 8.1 consistently — they're all computed
 * from the same `listPedidos(filters)` call the URL drives.
 */
export function DashboardFilterBar({
  obras,
  suprimentosProfiles,
  priorities,
  statuses,
}: DashboardFilterBarProps) {
  const router = useRouter();
  const pathname = usePathname();
  const searchParams = useSearchParams();

  const setParam = useCallback(
    (key: string, value: string | null) => {
      const params = new URLSearchParams(searchParams.toString());
      if (!value || value === ALL) {
        params.delete(key);
      } else {
        params.set(key, value);
      }
      const query = params.toString();
      router.push(query ? `${pathname}?${query}` : pathname);
    },
    [pathname, router, searchParams],
  );

  const obraItems: Record<string, string> = { [ALL]: "Todas as obras" };
  for (const obra of obras) obraItems[obra.id] = obra.name;

  const statusItems: Record<string, string> = { [ALL]: "Todos os status" };
  for (const status of statuses) statusItems[status.id] = status.name;

  const priorityItems: Record<string, string> = { [ALL]: "Todas as prioridades" };
  for (const priority of priorities) priorityItems[priority.id] = priority.name;

  const responsibleItems: Record<string, string> = { [ALL]: "Todos os responsáveis" };
  for (const profile of suprimentosProfiles) responsibleItems[profile.id] = profile.full_name;

  return (
    <div className="flex flex-wrap items-end gap-3">
      <div className="flex flex-col gap-1.5">
        <Label htmlFor="dashboard-filter-requested-from">Período (de)</Label>
        <Input
          id="dashboard-filter-requested-from"
          type="date"
          defaultValue={searchParams.get("requestedFrom") ?? ""}
          onChange={(e) => setParam("requestedFrom", e.target.value)}
          className="w-40"
        />
      </div>

      <div className="flex flex-col gap-1.5">
        <Label htmlFor="dashboard-filter-requested-to">Período (até)</Label>
        <Input
          id="dashboard-filter-requested-to"
          type="date"
          defaultValue={searchParams.get("requestedTo") ?? ""}
          onChange={(e) => setParam("requestedTo", e.target.value)}
          className="w-40"
        />
      </div>

      <div className="flex flex-col gap-1.5">
        <Label htmlFor="dashboard-filter-obra">Obra</Label>
        <Select
          value={searchParams.get("obraId") ?? ALL}
          onValueChange={(value) => setParam("obraId", value)}
          items={obraItems}
        >
          <SelectTrigger id="dashboard-filter-obra" className="w-40">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value={ALL}>Todas as obras</SelectItem>
            {obras.map((obra) => (
              <SelectItem key={obra.id} value={obra.id}>
                {obra.name}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>

      <div className="flex flex-col gap-1.5">
        <Label htmlFor="dashboard-filter-status">Status</Label>
        <Select
          value={searchParams.get("statusId") ?? ALL}
          onValueChange={(value) => setParam("statusId", value)}
          items={statusItems}
        >
          <SelectTrigger id="dashboard-filter-status" className="w-40">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value={ALL}>Todos os status</SelectItem>
            {statuses.map((status) => (
              <SelectItem key={status.id} value={status.id}>
                {status.name}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>

      <div className="flex flex-col gap-1.5">
        <Label htmlFor="dashboard-filter-priority">Prioridade</Label>
        <Select
          value={searchParams.get("priorityId") ?? ALL}
          onValueChange={(value) => setParam("priorityId", value)}
          items={priorityItems}
        >
          <SelectTrigger id="dashboard-filter-priority" className="w-36">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value={ALL}>Todas as prioridades</SelectItem>
            {priorities.map((priority) => (
              <SelectItem key={priority.id} value={priority.id}>
                {priority.name}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>

      <div className="flex flex-col gap-1.5">
        <Label htmlFor="dashboard-filter-responsible">Responsável</Label>
        <Select
          value={searchParams.get("responsibleId") ?? ALL}
          onValueChange={(value) => setParam("responsibleId", value)}
          items={responsibleItems}
        >
          <SelectTrigger id="dashboard-filter-responsible" className="w-40">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value={ALL}>Todos os responsáveis</SelectItem>
            {suprimentosProfiles.map((profile) => (
              <SelectItem key={profile.id} value={profile.id}>
                {profile.full_name}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>

      {searchParams.toString() ? (
        <Button type="button" variant="ghost" size="sm" onClick={() => router.push(pathname)}>
          Limpar filtros
        </Button>
      ) : null}
    </div>
  );
}
