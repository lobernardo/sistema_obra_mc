"use client";

import { useCallback, useEffect, useState } from "react";
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

const ATRASO_OPTIONS = [
  { value: ALL, label: "Todos" },
  { value: "true", label: "Somente atrasados" },
  { value: "false", label: "Excluir atrasados" },
];

export interface PedidosFilterBarProps {
  obras: Obra[];
  suprimentosProfiles: Profile[];
  priorities: Priority[];
  statuses: Status[];
}

/**
 * Combinable filters for "Todos os Pedidos" (PRD §17, §25) — every field
 * writes straight to the URL query string via `parsePedidoFilters`'s
 * matching keys, so `listPedidos`'s AND-combined filters (Fase 3.2/7.1)
 * stay the single source of truth for what "matches" means and the
 * selected filters survive a reload or a shared link.
 */
export function PedidosFilterBar({
  obras,
  suprimentosProfiles,
  priorities,
  statuses,
}: PedidosFilterBarProps) {
  const router = useRouter();
  const pathname = usePathname();
  const searchParams = useSearchParams();

  const [search, setSearch] = useState(searchParams.get("search") ?? "");

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

  useEffect(() => {
    const current = searchParams.get("search") ?? "";
    if (search === current) return;
    const timeout = setTimeout(() => setParam("search", search || null), 300);
    return () => clearTimeout(timeout);
    // Only re-run when the debounced text itself changes.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [search]);

  const obraItems: Record<string, string> = { [ALL]: "Todas as obras" };
  for (const obra of obras) obraItems[obra.id] = obra.name;

  const statusItems: Record<string, string> = { [ALL]: "Todos os status" };
  for (const status of statuses) statusItems[status.id] = status.name;

  const priorityItems: Record<string, string> = { [ALL]: "Todas as prioridades" };
  for (const priority of priorities) priorityItems[priority.id] = priority.name;

  const responsibleItems: Record<string, string> = { [ALL]: "Todos os responsáveis" };
  for (const profile of suprimentosProfiles) responsibleItems[profile.id] = profile.full_name;

  const atrasoItems: Record<string, string> = Object.fromEntries(
    ATRASO_OPTIONS.map((option) => [option.value, option.label]),
  );

  return (
    <div className="flex flex-wrap items-end gap-3">
      <div className="flex flex-col gap-1.5">
        <Label htmlFor="filter-search">Buscar</Label>
        <Input
          id="filter-search"
          placeholder="Identificador, obra ou item"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          className="w-56"
        />
      </div>

      <div className="flex flex-col gap-1.5">
        <Label htmlFor="filter-obra">Obra</Label>
        <Select
          value={searchParams.get("obraId") ?? ALL}
          onValueChange={(value) => setParam("obraId", value)}
          items={obraItems}
        >
          <SelectTrigger id="filter-obra" className="w-40">
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
        <Label htmlFor="filter-status">Status</Label>
        <Select
          value={searchParams.get("statusId") ?? ALL}
          onValueChange={(value) => setParam("statusId", value)}
          items={statusItems}
        >
          <SelectTrigger id="filter-status" className="w-40">
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
        <Label htmlFor="filter-priority">Prioridade</Label>
        <Select
          value={searchParams.get("priorityId") ?? ALL}
          onValueChange={(value) => setParam("priorityId", value)}
          items={priorityItems}
        >
          <SelectTrigger id="filter-priority" className="w-36">
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
        <Label htmlFor="filter-responsible">Responsável</Label>
        <Select
          value={searchParams.get("responsibleId") ?? ALL}
          onValueChange={(value) => setParam("responsibleId", value)}
          items={responsibleItems}
        >
          <SelectTrigger id="filter-responsible" className="w-40">
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

      <div className="flex flex-col gap-1.5">
        <Label htmlFor="filter-needed-from">Data necessária (de)</Label>
        <Input
          id="filter-needed-from"
          type="date"
          defaultValue={searchParams.get("neededAtFrom") ?? ""}
          onChange={(e) => setParam("neededAtFrom", e.target.value)}
          className="w-40"
        />
      </div>

      <div className="flex flex-col gap-1.5">
        <Label htmlFor="filter-needed-to">Data necessária (até)</Label>
        <Input
          id="filter-needed-to"
          type="date"
          defaultValue={searchParams.get("neededAtTo") ?? ""}
          onChange={(e) => setParam("neededAtTo", e.target.value)}
          className="w-40"
        />
      </div>

      <div className="flex flex-col gap-1.5">
        <Label htmlFor="filter-requested-from">Solicitado a partir de</Label>
        <Input
          id="filter-requested-from"
          type="date"
          defaultValue={searchParams.get("requestedFrom") ?? ""}
          onChange={(e) => setParam("requestedFrom", e.target.value)}
          className="w-40"
        />
      </div>

      <div className="flex flex-col gap-1.5">
        <Label htmlFor="filter-requested-to">Solicitado até</Label>
        <Input
          id="filter-requested-to"
          type="date"
          defaultValue={searchParams.get("requestedTo") ?? ""}
          onChange={(e) => setParam("requestedTo", e.target.value)}
          className="w-40"
        />
      </div>

      <div className="flex flex-col gap-1.5">
        <Label htmlFor="filter-atraso">Atraso</Label>
        <Select
          value={searchParams.get("atrasado") ?? ALL}
          onValueChange={(value) => setParam("atrasado", value)}
          items={atrasoItems}
        >
          <SelectTrigger id="filter-atraso" className="w-44">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            {ATRASO_OPTIONS.map((option) => (
              <SelectItem key={option.value} value={option.value}>
                {option.label}
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
