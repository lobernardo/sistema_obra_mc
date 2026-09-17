import { createClient } from "@/lib/supabase/server";
import { listPedidos, listStatuses } from "@/lib/pedidos/queries";
import { KanbanBoard } from "@/components/kanban/kanban-board";

export default async function SuprimentosKanbanPage() {
  const db = await createClient();
  const [pedidos, allStatuses] = await Promise.all([listPedidos(db, {}), listStatuses(db)]);

  // The Kanban only shows the 5 active workflow columns (PRD §18) —
  // `cancelado` is a terminal state handled outside the board (US-5.1/5.2).
  const activeStatuses = allStatuses.filter((status) => status.slug !== "cancelado");
  const activePedidos = pedidos.filter((pedido) => pedido.status.slug !== "cancelado");

  return (
    <div className="flex flex-1 flex-col gap-4 p-4 sm:p-6">
      <div className="flex flex-col gap-1">
        <h1 className="text-lg font-semibold tracking-tight">Kanban</h1>
        <p className="text-muted-foreground text-sm">
          Conduza os pedidos pelo fluxo de Suprimentos.
        </p>
      </div>
      <KanbanBoard initialPedidos={activePedidos} statuses={activeStatuses} />
    </div>
  );
}
