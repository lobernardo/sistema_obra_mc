"use client";

import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import type { Status } from "@/lib/types/domain";

export interface StatusControlProps {
  statusId: string;
  /** The active workflow statuses (excludes `cancelado`), ordered by `sort_order`. */
  statuses: Status[];
  disabled?: boolean;
  /** Invoked with the newly chosen status; the caller owns persistence so this stays the exact same code path as drag-and-drop (US-3.5/US-3.6). */
  onChange: (status: Status) => void;
}

/**
 * Accessible alternative to Kanban drag-and-drop (US-3.6) — offers the same
 * workflow sequence as an explicit select. Fully controlled by `statusId`
 * (no internal state), so it always reflects the caller's source of truth —
 * including a revert after a failed move. Callers wire `onChange` to the
 * same move handler used by drag-and-drop, so both trigger identical
 * persistence and history events.
 */
export function StatusControl({ statusId, statuses, disabled, onChange }: StatusControlProps) {
  function handleChange(next: string | null) {
    if (!next || next === statusId) return;
    const status = statuses.find((s) => s.id === next);
    if (!status) return;

    onChange(status);
  }

  // Select.Root only renders the selected item's label in the closed
  // trigger when given this value→label map (see @base-ui/react's `items`
  // prop docs) — otherwise it falls back to the raw status id.
  const items: Record<string, string> = {};
  for (const status of statuses) {
    items[status.id] = status.name;
  }

  return (
    <Select value={statusId} onValueChange={handleChange} disabled={disabled} items={items}>
      <SelectTrigger aria-label="Status" className="w-full">
        <SelectValue />
      </SelectTrigger>
      <SelectContent>
        {statuses.map((status) => (
          <SelectItem key={status.id} value={status.id}>
            {status.name}
          </SelectItem>
        ))}
      </SelectContent>
    </Select>
  );
}
