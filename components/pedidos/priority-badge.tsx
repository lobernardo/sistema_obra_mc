import { Badge } from "@/components/ui/badge";
import { cn } from "@/lib/utils";
import type { Priority, PrioritySlug } from "@/lib/types/domain";

/** Rising color intensity from `baixa` to `urgente`, shared across Kanban, listagens e detalhe. */
const PRIORITY_STYLES: Record<PrioritySlug, string> = {
  baixa: "bg-slate-100 text-slate-700 dark:bg-slate-500/20 dark:text-slate-300",
  normal: "bg-blue-100 text-blue-800 dark:bg-blue-500/20 dark:text-blue-300",
  alta: "bg-orange-100 text-orange-800 dark:bg-orange-500/20 dark:text-orange-300",
  urgente: "bg-red-100 text-red-800 dark:bg-red-500/20 dark:text-red-300",
};

export function PriorityBadge({
  priority,
  className,
}: {
  priority: Pick<Priority, "slug" | "name"> | null;
  className?: string;
}) {
  if (!priority) {
    return (
      <Badge variant="outline" className={cn("text-muted-foreground", className)}>
        Sem prioridade
      </Badge>
    );
  }

  const style = PRIORITY_STYLES[priority.slug as PrioritySlug];

  return <Badge className={cn(style, className)}>{priority.name}</Badge>;
}
