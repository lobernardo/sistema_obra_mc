import { notFound } from "next/navigation";
import { createClient } from "@/lib/supabase/server";
import { getPedidoByIdOrCode } from "@/lib/pedidos/queries";
import { PedidoDetailLayout } from "@/components/pedidos/pedido-detail-layout";

export default async function ObraPedidoDetailPage({ params }: PageProps<"/obra/[code]">) {
  const { code } = await params;

  const db = await createClient();
  const pedido = await getPedidoByIdOrCode(db, code);

  if (!pedido) {
    notFound();
  }

  return (
    <div className="flex flex-1 flex-col gap-4 p-4 sm:p-6">
      <PedidoDetailLayout pedido={pedido} readOnly />
    </div>
  );
}
