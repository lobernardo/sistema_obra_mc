import type { PrioritySlug, RoleSlug, StatusSlug } from "@/lib/types/domain";

/**
 * Identifies demo data during a live client presentation, in addition to
 * `is_demo` — a screen showing "[DEMO] Residencial Jardins" is unmistakably
 * not real, even to a viewer who can't see the underlying flag.
 */
export const DEMO_PREFIX = "[DEMO]";

/**
 * Fixed password for every demo account — intentionally shared/known, since
 * these credentials exist to be handed to whoever is running the client
 * presentation, never to protect real data.
 */
export const DEMO_PASSWORD = "Demo@12345";

export interface DemoObraSpec {
  key: string;
  name: string;
}

export interface DemoUserSpec {
  key: string;
  email: string;
  fullName: string;
  roleSlug: RoleSlug;
}

export interface DemoObraProfileLinkSpec {
  obraKey: string;
  profileKey: string;
}

export interface DemoPedidoSpec {
  /** Unique tag embedded as a prefix of `items_description` — both the naming convention marker and the idempotency key. */
  tag: string;
  obraKey: string;
  requesterKey: string;
  itemsDescription: string;
  neededAtOffsetDays: number;
  targetStatusSlug: StatusSlug;
  prioritySlug: PrioritySlug | null;
  responsibleKey: string | null;
  expectedDeliveryOffsetDays: number | null;
}

export const DEMO_OBRAS: readonly DemoObraSpec[] = [
  { key: "residencial-jardins", name: `${DEMO_PREFIX} Residencial Jardins` },
  { key: "condominio-vista-verde", name: `${DEMO_PREFIX} Condomínio Vista Verde` },
  { key: "galpao-industrial-sul", name: `${DEMO_PREFIX} Galpão Industrial Sul` },
];

export const DEMO_USERS: readonly DemoUserSpec[] = [
  {
    key: "obra1",
    email: "demo.obra1@sistema-obra.demo",
    fullName: "Marcos Silva (Demo)",
    roleSlug: "obra",
  },
  {
    key: "obra2",
    email: "demo.obra2@sistema-obra.demo",
    fullName: "Fernanda Costa (Demo)",
    roleSlug: "obra",
  },
  {
    key: "suprimentos1",
    email: "demo.suprimentos1@sistema-obra.demo",
    fullName: "Rafael Almeida (Demo)",
    roleSlug: "suprimentos",
  },
  {
    key: "suprimentos2",
    email: "demo.suprimentos2@sistema-obra.demo",
    fullName: "Juliana Pereira (Demo)",
    roleSlug: "suprimentos",
  },
  {
    key: "gestao1",
    email: "demo.gestao1@sistema-obra.demo",
    fullName: "Camila Rocha (Demo)",
    roleSlug: "gestao",
  },
];

/** `obra2` is deliberately linked to two obras — covers "profile Obra associado a mais de uma obra". */
export const DEMO_OBRA_PROFILE_LINKS: readonly DemoObraProfileLinkSpec[] = [
  { obraKey: "residencial-jardins", profileKey: "obra1" },
  { obraKey: "condominio-vista-verde", profileKey: "obra2" },
  { obraKey: "galpao-industrial-sul", profileKey: "obra2" },
];

export const DEMO_PEDIDOS: readonly DemoPedidoSpec[] = [
  {
    tag: "[DEMO-01]",
    obraKey: "residencial-jardins",
    requesterKey: "obra1",
    itemsDescription: "Cimento CP-II 50kg — 40 sacos; areia média — 6 m³",
    neededAtOffsetDays: 10,
    targetStatusSlug: "solicitado",
    prioritySlug: null,
    responsibleKey: null,
    expectedDeliveryOffsetDays: null,
  },
  {
    tag: "[DEMO-02]",
    obraKey: "residencial-jardins",
    requesterKey: "obra1",
    itemsDescription: "Blocos cerâmicos 14x19x29 — 2000 unidades",
    neededAtOffsetDays: 7,
    targetStatusSlug: "em_analise",
    prioritySlug: "normal",
    responsibleKey: "suprimentos1",
    expectedDeliveryOffsetDays: null,
  },
  {
    tag: "[DEMO-03]",
    obraKey: "condominio-vista-verde",
    requesterKey: "obra2",
    itemsDescription: "Tubos PVC soldável 100mm — 120 metros; conexões diversas",
    neededAtOffsetDays: 15,
    targetStatusSlug: "em_compra_preparacao",
    prioritySlug: "alta",
    responsibleKey: "suprimentos2",
    expectedDeliveryOffsetDays: 20,
  },
  {
    tag: "[DEMO-04]",
    obraKey: "condominio-vista-verde",
    requesterKey: "obra2",
    itemsDescription: "Vergalhão CA-50 10mm — 3 toneladas",
    // In the past on purpose — this is the required "atrasado" pedido (needed_at passed, status not entregue/cancelado).
    neededAtOffsetDays: -3,
    targetStatusSlug: "aguardando_entrega",
    prioritySlug: "urgente",
    responsibleKey: "suprimentos1",
    expectedDeliveryOffsetDays: 1,
  },
  {
    tag: "[DEMO-05]",
    obraKey: "galpao-industrial-sul",
    requesterKey: "obra2",
    itemsDescription: "Telhas metálicas termoacústicas — 300 m²",
    neededAtOffsetDays: -10,
    targetStatusSlug: "entregue",
    prioritySlug: "normal",
    responsibleKey: "suprimentos2",
    expectedDeliveryOffsetDays: -2,
  },
  {
    tag: "[DEMO-06]",
    obraKey: "residencial-jardins",
    requesterKey: "obra1",
    itemsDescription: "Tinta acrílica branca 18L — 30 latas",
    neededAtOffsetDays: 5,
    targetStatusSlug: "cancelado",
    prioritySlug: "baixa",
    responsibleKey: "suprimentos1",
    expectedDeliveryOffsetDays: null,
  },
  {
    tag: "[DEMO-07]",
    obraKey: "galpao-industrial-sul",
    requesterKey: "obra2",
    itemsDescription: "Perfis metálicos galvanizados — 80 barras de 6m",
    neededAtOffsetDays: 2,
    targetStatusSlug: "solicitado",
    prioritySlug: null,
    responsibleKey: null,
    expectedDeliveryOffsetDays: null,
  },
];
