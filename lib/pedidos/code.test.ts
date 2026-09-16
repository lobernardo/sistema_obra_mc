import { describe, expect, it } from "vitest";
import { PEDIDO_CODE_PATTERN, generatePedidoCode } from "./code";
import { createTestDb } from "./testing";

describe("generatePedidoCode", () => {
  const db = createTestDb();

  it("matches the ^PED-\\d{6}$ format", async () => {
    const code = await generatePedidoCode(db);
    expect(code).toMatch(PEDIDO_CODE_PATTERN);
  });

  it("never produces a duplicate code under concurrent calls", async () => {
    const codes = await Promise.all(Array.from({ length: 25 }, () => generatePedidoCode(db)));

    expect(codes).toHaveLength(25);
    expect(new Set(codes).size).toBe(codes.length);
    for (const code of codes) {
      expect(code).toMatch(PEDIDO_CODE_PATTERN);
    }
  });
});
