import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import type { PrazoCount, PrazoSituacao } from "@/lib/pedidos/dashboard";

const LABELS: Record<PrazoSituacao, string> = {
  dentro_do_prazo: "Dentro do prazo",
  vencendo_em_breve: "Vencendo em breve",
  atrasado: "Atrasado",
};

/** "Prazos" indicator (PRD §22) — situação dos pedidos ativos frente à data necessária, reusando a regra de atraso compartilhada. */
export function PrazosCard({ prazos }: { prazos: PrazoCount[] }) {
  return (
    <Card>
      <CardHeader>
        <CardTitle>Prazos</CardTitle>
      </CardHeader>
      <CardContent className="flex flex-col gap-2">
        {prazos.map(({ situacao, count }) => (
          <div key={situacao} className="flex items-center justify-between gap-2">
            <span className="text-sm">{LABELS[situacao]}</span>
            <span className="text-sm font-medium tabular-nums">{count}</span>
          </div>
        ))}
      </CardContent>
    </Card>
  );
}
