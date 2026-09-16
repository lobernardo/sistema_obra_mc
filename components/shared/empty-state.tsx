import type { ReactNode } from "react";
import { Inbox } from "lucide-react";
import { cn } from "@/lib/utils";

export function EmptyState({
  title,
  description,
  icon,
  className,
}: {
  title: string;
  description?: string;
  icon?: ReactNode;
  className?: string;
}) {
  return (
    <div
      className={cn(
        "text-muted-foreground flex flex-1 flex-col items-center justify-center gap-2 p-8 text-center",
        className,
      )}
    >
      {icon ?? <Inbox className="size-8" />}
      <p className="text-foreground text-sm font-medium">{title}</p>
      {description ? <p className="text-sm">{description}</p> : null}
    </div>
  );
}
