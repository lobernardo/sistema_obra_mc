import { TriangleAlert } from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { cn } from "@/lib/utils";
import { isPedidoAtrasado } from "@/lib/pedidos/atraso";

type AtrasoIndicatorProps =
  | { atrasado: boolean; pedido?: undefined; today?: undefined; className?: string }
  | {
      pedido: Parameters<typeof isPedidoAtrasado>[0];
      today?: Date;
      atrasado?: undefined;
      className?: string;
    };

/**
 * Shared "atrasado" marker for Kanban, listagens e dashboard (US-8.1). Takes
 * either the precomputed boolean or a pedido to run `isPedidoAtrasado`
 * itself, so every consumer shares the exact same rule. Renders nothing when
 * the pedido isn't atrasado.
 */
export function AtrasoIndicator(props: AtrasoIndicatorProps) {
  const atrasado = props.pedido ? isPedidoAtrasado(props.pedido, props.today) : props.atrasado;

  if (!atrasado) {
    return null;
  }

  return (
    <Badge variant="destructive" className={cn("gap-1", props.className)}>
      <TriangleAlert />
      Atrasado
    </Badge>
  );
}
