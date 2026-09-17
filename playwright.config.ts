import { defineConfig, devices } from "@playwright/test";

const PORT = 3000;
const baseURL = process.env.PLAYWRIGHT_BASE_URL ?? `http://127.0.0.1:${PORT}`;

export default defineConfig({
  testDir: "./e2e",
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  workers: process.env.CI ? 1 : undefined,
  reporter: "html",
  // Next dev compiles each route on first hit, which can comfortably
  // outrun the Playwright default (5s) — give assertions more room.
  expect: { timeout: 15_000 },
  use: {
    baseURL,
    trace: "on-first-retry",
  },
  projects: [
    {
      name: "smoke",
      testMatch: /smoke\.spec\.ts/,
      use: { ...devices["Desktop Chrome"] },
    },
    {
      name: "demo-roteiro",
      testMatch: /demo-roteiro\.spec\.ts/,
      use: { ...devices["Desktop Chrome"] },
    },
    {
      name: "obra",
      testDir: "./e2e/obra",
      use: { ...devices["Desktop Chrome"], storageState: "e2e/.auth/obra.json" },
    },
    {
      name: "suprimentos",
      testDir: "./e2e/suprimentos",
      use: { ...devices["Desktop Chrome"], storageState: "e2e/.auth/suprimentos.json" },
    },
    {
      name: "gestao",
      testDir: "./e2e/gestao",
      use: { ...devices["Desktop Chrome"], storageState: "e2e/.auth/gestao.json" },
    },
  ],
  webServer: {
    command: "npm run dev",
    url: baseURL,
    reuseExistingServer: !process.env.CI,
    timeout: 120_000,
  },
});
