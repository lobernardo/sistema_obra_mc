import Link from "next/link";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { cn } from "@/lib/utils";

export interface IndicatorCardProps {
  title: string;
  value: number;
  /** When given, the whole card becomes a drill-down link (US-7.3) to "Todos os Pedidos" filtered by exactly this indicator's criterion. */
  href?: string;
  description?: string;
}

/**
 * Generic dashboard stat tile shared by "Volume total", "Pendentes" and
 * "Atrasados" (PRD §22, §27) — same layout everywhere, only the value and
 * the optional drill-down link vary.
 */
export function IndicatorCard({ title, value, href, description }: IndicatorCardProps) {
  const content = (
    <Card className={cn(href && "transition-colors hover:bg-muted/50")}>
      <CardHeader>
        <CardTitle className="text-muted-foreground text-sm font-medium">{title}</CardTitle>
      </CardHeader>
      <CardContent className="flex flex-col gap-1">
        <p className="text-2xl font-semibold tabular-nums">{value}</p>
        {description ? <p className="text-muted-foreground text-xs">{description}</p> : null}
      </CardContent>
    </Card>
  );

  if (!href) {
    return content;
  }

  return (
    <Link
      href={href}
      className="focus-visible:outline-ring block rounded-xl focus-visible:outline-2 focus-visible:outline-offset-2"
    >
      {content}
    </Link>
  );
}
