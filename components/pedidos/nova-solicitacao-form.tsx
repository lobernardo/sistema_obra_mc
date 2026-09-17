"use client";

import { useActionState, useEffect, useState } from "react";
import Link from "next/link";
import { format } from "date-fns";
import { CalendarIcon } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { Calendar } from "@/components/ui/calendar";
import { notifyError, notifySuccess } from "@/lib/toast";
import type { Obra } from "@/lib/types/domain";
import { createSolicitacao, type NovaSolicitacaoState } from "@/app/obra/novo/actions";

const initialState: NovaSolicitacaoState = {};

export function NovaSolicitacaoForm({ obras }: { obras: Obra[] }) {
  const [state, formAction, isPending] = useActionState(createSolicitacao, initialState);
  const [obraId, setObraId] = useState<string | null>(null);
  const [neededAt, setNeededAt] = useState<Date | undefined>(undefined);
  const [itemsDescription, setItemsDescription] = useState("");

  useEffect(() => {
    if (state.pedido) {
      notifySuccess(`Solicitação ${state.pedido.code} criada.`);
    } else if (state.error) {
      notifyError(state.error);
    }
  }, [state]);

  if (state.pedido) {
    const { code } = state.pedido;

    return (
      <Card className="max-w-md">
        <CardHeader>
          <CardTitle>Solicitação criada</CardTitle>
        </CardHeader>
        <CardContent className="flex flex-col gap-4">
          <p className="text-sm">
            Identificador: <span className="font-semibold">{code}</span>
          </p>
          <div className="flex gap-2">
            <Button render={<Link href={`/obra/${code}`} />}>Ver detalhe</Button>
            <Button variant="outline" render={<Link href="/obra" />}>
              Ver Meus Pedidos
            </Button>
          </div>
        </CardContent>
      </Card>
    );
  }

  const isValid = Boolean(obraId) && Boolean(neededAt) && itemsDescription.trim().length > 0;

  return (
    <form action={formAction} className="flex max-w-md flex-col gap-4">
      <div className="flex flex-col gap-1.5">
        <Label htmlFor="obra_id">Obra</Label>
        <Select name="obra_id" required value={obraId} onValueChange={setObraId}>
          <SelectTrigger id="obra_id" className="w-full">
            <SelectValue placeholder="Selecione a obra" />
          </SelectTrigger>
          <SelectContent>
            {obras.map((obra) => (
              <SelectItem key={obra.id} value={obra.id}>
                {obra.name}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>

      <div className="flex flex-col gap-1.5">
        <Label htmlFor="needed_at_trigger">Data necessária</Label>
        <Popover>
          <PopoverTrigger
            id="needed_at_trigger"
            render={
              <Button
                variant="outline"
                type="button"
                className="w-full justify-start font-normal"
              />
            }
          >
            <CalendarIcon />
            {neededAt ? format(neededAt, "dd/MM/yyyy") : "Selecione a data"}
          </PopoverTrigger>
          <PopoverContent className="w-auto p-0">
            <Calendar mode="single" selected={neededAt} onSelect={setNeededAt} />
          </PopoverContent>
        </Popover>
        <input
          type="hidden"
          name="needed_at"
          value={neededAt ? format(neededAt, "yyyy-MM-dd") : ""}
        />
      </div>

      <div className="flex flex-col gap-1.5">
        <Label htmlFor="items_description">Itens/quantidades</Label>
        <Textarea
          id="items_description"
          name="items_description"
          rows={5}
          required
          value={itemsDescription}
          onChange={(e) => setItemsDescription(e.target.value)}
          placeholder="Ex.: 10 sacos de cimento, 5 baldes de tinta branca"
        />
      </div>

      {state.error ? (
        <p role="alert" className="text-destructive text-sm">
          {state.error}
        </p>
      ) : null}

      <Button type="submit" disabled={isPending || !isValid}>
        {isPending ? "Enviando..." : "Enviar solicitação"}
      </Button>
    </form>
  );
}
