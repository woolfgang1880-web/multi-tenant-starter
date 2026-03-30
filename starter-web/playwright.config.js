import { defineConfig, devices } from '@playwright/test'

const API_BASE =
  process.env.VITE_API_BASE_URL ||
  process.env.BASE_API_URL ||
  'http://127.0.0.1:8000/api/v1'

/**
 * E2E browser (real) — sin mocks.
 *
 * Browsers: por defecto Playwright usa **Chromium** descargado vía:
 * `npx playwright install chromium` (ver `starter-web/docs/E2E_PLATFORM.md`).
 */
export default defineConfig({
  testDir: './e2e',
  timeout: 120_000,
  expect: { timeout: 20_000 },
  fullyParallel: false,
  workers: 1,
  retries: 0,
  reporter: [['list']],
  use: {
    baseURL: process.env.E2E_BASE_URL || 'http://127.0.0.1:5173',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
    ...devices['Desktop Chrome'],
  },
  webServer: {
    command: 'npm run dev -- --host 127.0.0.1 --port 5173 --strictPort',
    url: 'http://127.0.0.1:5173',
    reuseExistingServer: !process.env.CI,
    timeout: 120_000,
    stdout: 'pipe',
    stderr: 'pipe',
    env: {
      VITE_API_BASE_URL: API_BASE,
    },
  },
})
