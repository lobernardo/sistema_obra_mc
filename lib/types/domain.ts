import type {
  EventTypeRow,
  ObraProfileRow,
  ObraRow,
  PedidoEventRow,
  PedidoRow,
  PriorityRow,
  ProfileRow,
  RoleRow,
  StatusRow,
} from "./database";

export type Role = RoleRow;
export type Status = StatusRow;
export type Priority = PriorityRow;
export type EventType = EventTypeRow;
export type Obra = ObraRow;
export type ObraProfile = ObraProfileRow;
export type Pedido = PedidoRow;
export type PedidoEvent = PedidoEventRow;

export interface Profile extends ProfileRow {
  role: Role;
}

/** `roles.slug` values seeded for V0 (see database-schema.md). */
export type RoleSlug = "obra" | "suprimentos" | "gestao";

/** `statuses.slug` values seeded for V0, in workflow order. */
export type StatusSlug =
  | "solicitado"
  | "em_analise"
  | "em_compra_preparacao"
  | "aguardando_entrega"
  | "entregue"
  | "cancelado";

/** `priorities.slug` values seeded for V0. */
export type PrioritySlug = "baixa" | "normal" | "alta" | "urgente";

/** `event_types.slug` values seeded for V0. */
export type EventTypeSlug =
  | "criacao_pedido"
  | "mudanca_status"
  | "alteracao_responsavel"
  | "alteracao_prioridade"
  | "alteracao_previsao"
  | "cancelamento"
  | "entrega";

/** A pedido with its lookup/actor relations resolved via join. */
export interface PedidoComRelacoes extends Pedido {
  obra: Obra;
  status: Status;
  priority: Priority | null;
  requester: Profile;
  responsible: Profile | null;
}

/** A single history entry with its type and author resolved. */
export interface PedidoEventComRelacoes extends PedidoEvent {
  eventType: EventType;
  actor: Profile;
}

/** A pedido with relations and its chronologically ordered event history. */
export interface PedidoComHistorico extends PedidoComRelacoes {
  events: PedidoEventComRelacoes[];
}
