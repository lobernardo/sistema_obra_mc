import { execSync } from "node:child_process";
import { test, expect, type Locator, type Page } from "@playwright/test";
import { DEMO_OBRAS, DEMO_PASSWORD, DEMO_USERS } from "@/lib/demo/data";

/**
 * E2E validation of the PRD §46 "Roteiro Oficial de Demonstração" (Phase
 * 10.1) — the exact 25-step script used to sign off the V0 Demo. Runs
 * against the Fase 9 demo dataset (re-seeded here, the project's own
 * controlled mechanism — never a manual database edit) but drives its own
 * brand-new pedido through the whole workflow via the UI only, so re-runs
 * never depend on — or corrupt — the fixed demo pedidos' seeded state.
 *
 * Three roles are live at once via three separate browser contexts, exactly
 * like a real demo running in three browser windows side by side — no
 * `storageState` fixture, since the point is to prove the login screen
 * itself gates each area (workflow 1).
 */

const OBRA_USER = DEMO_USERS.find((user) => user.key === "obra1")!;
const RESPONSAVEL_USER = DEMO_USERS.find((user) => user.key === "suprimentos2")!;
const SUPRIMENTOS_USER = DEMO_USERS.find((user) => user.key === "suprimentos1")!;
const GESTAO_USER = DEMO_USERS.find((user) => user.key === "gestao1")!;
const OBRA_NAME = DEMO_OBRAS.find((obra) => obra.key === "residencial-jardins")!.name;

const ITEMS_DESCRIPTION = `[E2E] Roteiro oficial de demonstração — execução ${Date.now()}`;

test.beforeAll(() => {
  // US-9.1's own controlled mechanism (`npm run seed:demo`) — idempotent, so
  // this never duplicates the Fase 9 obras/users/pedidos on repeated runs.
  execSync("npm run seed:demo", { stdio: "inherit" });
});

async function login(page: Page, email: string): Promise<void> {
  await page.goto("/login");
  await page.getByLabel("E-mail").fill(email);
  await page.getByLabel("Senha").fill(DEMO_PASSWORD);
  await page.getByRole("button", { name: "Entrar" }).click();
  // Next dev compiles each route (and the login server action) on first
  // hit, which can comfortably outrun the default 5s expect timeout — wait
  // for the post-login redirect explicitly instead of racing it.
  await page.waitForURL(/\/(obra|suprimentos|gestao)$/, { timeout: 45_000 });
}

/**
 * Opens a `Calendar`-backed date popover and picks a day that's always in a
 * yet-to-be-rendered month, so the click target can never collide with a
 * grayed-out "outside" day from an adjacent month regardless of what today's
 * date happens to be.
 */
async function pickFutureDate(page: Page, trigger: Locator, monthsAhead: number, day: number) {
  await trigger.click();
  const nextMonth = page.getByRole("button", { name: "Go to the Next Month" });
  for (let i = 0; i < monthsAhead; i += 1) {
    await nextMonth.click();
  }
  await page.getByRole("button", { name: String(day), exact: true }).click();
}

function indicatorValueLocator(page: Page, title: string): Locator {
  return page
    .locator('[data-slot="card"]')
    .filter({ has: page.getByText(title, { exact: true }) })
    .locator('[data-slot="card-content"] p')
    .first();
}

async function readIndicator(page: Page, title: string): Promise<number> {
  const text = await indicatorValueLocator(page, title).innerText();
  return Number(text.trim());
}

test("Roteiro oficial de demonstração ponta a ponta (PRD §46)", async ({ browser }) => {
  test.setTimeout(180_000);

  const obraContext = await browser.newContext();
  const suprimentosContext = await browser.newContext();
  const gestaoContext = await browser.newContext();
  const obra = await obraContext.newPage();
  const suprimentos = await suprimentosContext.newPage();
  const gestao = await gestaoContext.newPage();

  let pedidoCode = "";
  let volumeBefore = 0;
  let pendentesBeforeEntrega = 0;
  let previsaoDisplay = "";

  try {
    await test.step("1. Entrar como usuário de Obra", async () => {
      await login(obra, OBRA_USER.email);
      await expect(obra).toHaveURL(/\/obra$/);
      await expect(obra.getByRole("heading", { name: "Meus Pedidos" })).toBeVisible();
    });

    await test.step("Login como Gestão e captura do Volume total antes da solicitação", async () => {
      await login(gestao, GESTAO_USER.email);
      await expect(gestao).toHaveURL(/\/gestao$/);
      volumeBefore = await readIndicator(gestao, "Volume total");
    });

    await test.step("2. Abrir Nova Solicitação", async () => {
      await obra.getByRole("link", { name: "Nova Solicitação", exact: true }).click();
      await expect(obra).toHaveURL(/\/obra\/novo$/);
    });

    await test.step("3-5. Selecionar a obra, informar data necessária e descrever itens", async () => {
      await obra.locator("#obra_id").click();
      await obra.locator('[data-slot="select-content"]').getByText(OBRA_NAME, { exact: true }).click();
      await pickFutureDate(obra, obra.getByRole("button", { name: "Selecione a data" }), 1, 10);
      await obra.getByLabel("Itens/quantidades").fill(ITEMS_DESCRIPTION);
    });

    await test.step("6-7. Enviar e confirmar criação do pedido", async () => {
      await obra.getByRole("button", { name: "Enviar solicitação" }).click();
      await expect(obra.getByRole("heading", { name: "Solicitação criada" })).toBeVisible();
      pedidoCode = (
        await obra.locator('p:has-text("Identificador:") span').innerText()
      ).trim();
      expect(pedidoCode).toMatch(/^PED-\d{6}$/);
    });

    await test.step("8. Visualizar o pedido em acompanhamento", async () => {
      await obra.getByRole("link", { name: "Ver detalhe" }).click();
      await expect(obra).toHaveURL(new RegExp(`/obra/${pedidoCode}$`));
      await expect(obra.getByText(ITEMS_DESCRIPTION)).toBeVisible();
      await expect(obra.getByText("Solicitado", { exact: true })).toBeVisible();
    });

    await test.step("9-10. Acessar visão de Suprimentos e localizar o novo pedido", async () => {
      await login(suprimentos, SUPRIMENTOS_USER.email);
      await expect(suprimentos).toHaveURL(/\/suprimentos$/);
      await expect(suprimentos.getByRole("heading", { name: "Kanban" })).toBeVisible();
    });

    await test.step("11. Visualizar o pedido em Solicitado no Kanban", async () => {
      const solicitadoColumn = suprimentos.locator('[data-status="solicitado"]');
      await expect(solicitadoColumn.getByText(pedidoCode)).toBeVisible();
      await solicitadoColumn.getByText(pedidoCode).click();
      await expect(suprimentos).toHaveURL(new RegExp(`/suprimentos/pedidos/${pedidoCode}$`));
    });

    await test.step("12. Definir responsável", async () => {
      await suprimentos.locator('[aria-label="Responsável"]').click();
      await suprimentos
        .locator('[data-slot="select-content"]')
        .getByText(RESPONSAVEL_USER.fullName, { exact: true })
        .click();
      await expect(suprimentos.getByText("Responsável atualizado.")).toBeVisible();
    });

    await test.step("13. Definir prioridade", async () => {
      await suprimentos.locator('[aria-label="Prioridade"]').click();
      await suprimentos.locator('[data-slot="select-content"]').getByText("Alta", { exact: true }).click();
      await expect(suprimentos.getByText("Prioridade atualizada.")).toBeVisible();
    });

    await test.step("14. Informar previsão de entrega", async () => {
      await pickFutureDate(
        suprimentos,
        suprimentos.getByRole("button", { name: "Selecione a data" }),
        2,
        20,
      );
      await expect(suprimentos.getByText("Previsão de entrega atualizada.")).toBeVisible();
      previsaoDisplay = (
        await suprimentos.getByRole("button").filter({ hasText: /^\d{2}\/\d{2}\/\d{4}$/ }).innerText()
      ).trim();
    });

    await test.step("15. Mover para Em análise", async () => {
      await suprimentos.locator('[aria-label="Status"]').click();
      await suprimentos.locator('[data-slot="select-content"]').getByText("Em análise", { exact: true }).click();
      await expect(suprimentos.getByText("Status atualizado.")).toBeVisible();
    });

    await test.step("16. Mover para Em compra/preparação", async () => {
      await suprimentos.locator('[aria-label="Status"]').click();
      await suprimentos
        .locator('[data-slot="select-content"]')
        .getByText("Em compra/preparação", { exact: true })
        .click();
      await expect(suprimentos.getByText("Status atualizado.")).toBeVisible();
    });

    await test.step("17. Mover para Aguardando entrega", async () => {
      await suprimentos.locator('[aria-label="Status"]').click();
      await suprimentos
        .locator('[data-slot="select-content"]')
        .getByText("Aguardando entrega", { exact: true })
        .click();
      await expect(suprimentos.getByText("Status atualizado.")).toBeVisible();
    });

    await test.step("18. Consultar histórico", async () => {
      await suprimentos.reload();
      const events = suprimentos.locator("ol > li");
      // criação, responsável, prioridade, previsão, 3x mudança de status.
      await expect(events).toHaveCount(7);
      await expect(events.last()).toContainText("aguardando_entrega");
    });

    await test.step("19. Demonstrar acompanhamento pela Obra", async () => {
      await obra.reload();
      await expect(obra.getByText("Aguardando entrega", { exact: true })).toBeVisible();
      await expect(obra.getByText(RESPONSAVEL_USER.fullName, { exact: true })).toBeVisible();
      await expect(obra.getByText("Alta", { exact: true })).toBeVisible();
      await expect(obra.getByText(previsaoDisplay, { exact: true })).toBeVisible();
      await expect(obra.locator("ol > li")).toHaveCount(7);
    });

    await test.step("20-21. Abrir dashboard como Gestão e demonstrar indicadores", async () => {
      await gestao.reload();
      const volumeAfterCreation = await readIndicator(gestao, "Volume total");
      expect(volumeAfterCreation).toBe(volumeBefore + 1);
      pendentesBeforeEntrega = await readIndicator(gestao, "Pendentes");
      expect(pendentesBeforeEntrega).toBeGreaterThan(0);
    });

    await test.step("22. Retornar ao pedido", async () => {
      await suprimentos.goto(`/suprimentos/pedidos/${pedidoCode}`);
      await expect(suprimentos.getByRole("heading", { name: pedidoCode })).toBeVisible();
    });

    await test.step("23. Marcar como Entregue", async () => {
      await suprimentos.getByRole("button", { name: "Marcar como Entregue" }).click();
      await expect(suprimentos.getByText("Pedido marcado como entregue.")).toBeVisible();
      await expect(suprimentos.getByText("Entregue", { exact: true })).toBeVisible();
      await expect(suprimentos.getByRole("button", { name: "Marcar como Entregue" })).toHaveCount(0);
    });

    await test.step("24. Demonstrar atualização do histórico", async () => {
      await suprimentos.reload();
      const events = suprimentos.locator("ol > li");
      await expect(events).toHaveCount(8);
      await expect(events.last()).toContainText("Entrega");
      await expect(events.last()).toContainText("aguardando_entrega → entregue");
    });

    await test.step("25. Demonstrar atualização do dashboard", async () => {
      await gestao.reload();
      const volumeAfterEntrega = await readIndicator(gestao, "Volume total");
      expect(volumeAfterEntrega).toBe(volumeBefore + 1);
      const pendentesAfterEntrega = await readIndicator(gestao, "Pendentes");
      expect(pendentesAfterEntrega).toBe(pendentesBeforeEntrega - 1);

      await obra.reload();
      await expect(obra.getByText("Entregue", { exact: true })).toBeVisible();
      await expect(obra.locator("ol > li")).toHaveCount(8);
    });
  } finally {
    await obraContext.close();
    await suprimentosContext.close();
    await gestaoContext.close();
  }
});
