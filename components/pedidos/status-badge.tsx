import { Badge } from "@/components/ui/badge";
import { cn } from "@/lib/utils";
import type { Status, StatusSlug } from "@/lib/types/domain";

/**
 * One color treatment per status, shared by every consumer (Kanban,
 * listagens, detalhe) so "o que uma cor significa" never drifts between
 * screens. `cancelado` gets a muted/struck-through look instead of a color,
 * since it's a terminal state outside the active workflow.
 */
const STATUS_STYLES: Record<StatusSlug, string> = {
  solicitado: "bg-blue-100 text-blue-800 dark:bg-blue-500/20 dark:text-blue-300",
  em_analise: "bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-300",
  em_compra_preparacao: "bg-violet-100 text-violet-800 dark:bg-violet-500/20 dark:text-violet-300",
  aguardando_entrega: "bg-cyan-100 text-cyan-800 dark:bg-cyan-500/20 dark:text-cyan-300",
  entregue: "bg-emerald-100 text-emerald-800 dark:bg-emerald-500/20 dark:text-emerald-300",
  cancelado: "bg-muted text-muted-foreground line-through decoration-1",
};

export function StatusBadge({
  status,
  className,
}: {
  status: Pick<Status, "slug" | "name">;
  className?: string;
}) {
  const style = STATUS_STYLES[status.slug as StatusSlug];

  return <Badge className={cn(style, className)}>{status.name}</Badge>;
}
