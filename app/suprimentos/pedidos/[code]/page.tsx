import { notFound } from "next/navigation";
import { createClient } from "@/lib/supabase/server";
import {
  getPedidoByIdOrCode,
  listPriorities,
  listStatuses,
  listSuprimentosProfiles,
} from "@/lib/pedidos/queries";
import { PedidoDetailLayout } from "@/components/pedidos/pedido-detail-layout";
import { ResponsavelControl } from "@/components/pedidos/responsavel-control";
import { PrioridadeControl } from "@/components/pedidos/prioridade-control";
import { PrevisaoControl } from "@/components/pedidos/previsao-control";
import { PedidoStatusField } from "@/components/pedidos/pedido-status-field";
import { MarcarEntregueButton } from "@/components/pedidos/marcar-entregue-button";
import { CancelarPedidoDialog } from "@/components/pedidos/cancelar-pedido-dialog";

const TERMINAL_STATUS_SLUGS = new Set(["entregue", "cancelado"]);

export default async function SuprimentosPedidoDetailPage({
  params,
}: {
  params: Promise<{ code: string }>;
}) {
  const { code } = await params;

  const db = await createClient();
  const [pedido, priorities, allStatuses, suprimentosProfiles] = await Promise.all([
    getPedidoByIdOrCode(db, code),
    listPriorities(db),
    listStatuses(db),
    listSuprimentosProfiles(db),
  ]);

  if (!pedido) {
    notFound();
  }

  const activeStatuses = allStatuses.filter((status) => status.slug !== "cancelado");
  const entregueStatus = allStatuses.find((status) => status.slug === "entregue")!;
  const isActive = !TERMINAL_STATUS_SLUGS.has(pedido.status.slug);

  return (
    <div className="flex flex-1 flex-col gap-4 p-4 sm:p-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h1 className="text-lg font-semibold tracking-tight">{pedido.code}</h1>
        {isActive ? <CancelarPedidoDialog pedidoId={pedido.id} pedidoCode={pedido.code} /> : null}
      </div>

      <PedidoDetailLayout
        pedido={pedido}
        readOnly={false}
        statusControl={
          <div className="flex flex-col gap-2">
            <PedidoStatusField
              pedidoId={pedido.id}
              statusId={pedido.status_id}
              statuses={activeStatuses}
            />
            {isActive ? (
              <MarcarEntregueButton pedidoId={pedido.id} entregueStatusId={entregueStatus.id} />
            ) : null}
          </div>
        }
        priorityControl={
          <PrioridadeControl
            pedidoId={pedido.id}
            priorityId={pedido.priority_id}
            priorities={priorities}
          />
        }
        responsibleControl={
          <ResponsavelControl
            pedidoId={pedido.id}
            responsibleId={pedido.responsible_id}
            suprimentosProfiles={suprimentosProfiles}
          />
        }
        previsaoControl={
          <PrevisaoControl pedidoId={pedido.id} expectedDeliveryAt={pedido.expected_delivery_at} />
        }
      />
    </div>
  );
}
