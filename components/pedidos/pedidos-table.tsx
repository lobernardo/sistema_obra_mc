import Link from "next/link";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import { EmptyState } from "@/components/shared/empty-state";
import { StatusBadge } from "./status-badge";
import { PriorityBadge } from "./priority-badge";
import { AtrasoIndicator } from "./atraso-indicator";
import { formatDate } from "@/lib/format";
import { isPedidoAtrasado } from "@/lib/pedidos/atraso";
import type { PedidoComRelacoes } from "@/lib/types/domain";

export interface PedidosTableProps {
  pedidos: PedidoComRelacoes[];
  /** Prefixed to `pedido.code` to build each row's detail link, e.g. `/obra`. */
  linkBasePath: string;
  emptyTitle?: string;
  emptyDescription?: string;
}

/**
 * Read-only pedidos listing shared by the Obra, Suprimentos and Gestão
 * listagens (US-4.1) — same columns and atraso rule everywhere, only the
 * detail link's base path and the empty-state copy vary by caller.
 */
export function PedidosTable({
  pedidos,
  linkBasePath,
  emptyTitle = "Nenhum pedido encontrado",
  emptyDescription,
}: PedidosTableProps) {
  if (pedidos.length === 0) {
    return <EmptyState title={emptyTitle} description={emptyDescription} />;
  }

  return (
    <Table>
      <TableHeader>
        <TableRow>
          <TableHead>Identificador</TableHead>
          <TableHead>Obra</TableHead>
          <TableHead>Status</TableHead>
          <TableHead>Prioridade</TableHead>
          <TableHead>Responsável</TableHead>
          <TableHead>Previsão de entrega</TableHead>
          <TableHead>Atraso</TableHead>
        </TableRow>
      </TableHeader>
      <TableBody>
        {pedidos.map((pedido) => (
          <TableRow key={pedido.id}>
            <TableCell>
              <Link
                href={`${linkBasePath}/${pedido.code}`}
                className="font-medium underline-offset-4 hover:underline"
              >
                {pedido.code}
              </Link>
            </TableCell>
            <TableCell>{pedido.obra.name}</TableCell>
            <TableCell>
              <StatusBadge status={pedido.status} />
            </TableCell>
            <TableCell>
              <PriorityBadge priority={pedido.priority} />
            </TableCell>
            <TableCell>{pedido.responsible?.full_name ?? "—"}</TableCell>
            <TableCell>{formatDate(pedido.expected_delivery_at)}</TableCell>
            <TableCell>
              {isPedidoAtrasado(pedido) ? (
                <AtrasoIndicator pedido={pedido} />
              ) : (
                <span className="text-muted-foreground text-xs">Dentro do prazo</span>
              )}
            </TableCell>
          </TableRow>
        ))}
      </TableBody>
    </Table>
  );
}
