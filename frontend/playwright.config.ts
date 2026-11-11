import { defineConfig, devices } from '@playwright/test';

/**
 * Сквозные тесты по поднятому стеку (docker compose up): nginx + Slim + воркер + PostgreSQL + RabbitMQ.
 * BASE_URL переопределяет адрес, PW_CHANNEL=chrome позволяет взять системный Chrome вместо скачанного Chromium.
 */
export default defineConfig({
  testDir: './e2e',
  timeout: 120_000,
  expect: { timeout: 15_000 },
  fullyParallel: false,
  retries: process.env['CI'] ? 1 : 0,
  reporter: process.env['CI'] ? [['list'], ['html', { open: 'never' }]] : 'list',
  use: {
    baseURL: process.env['BASE_URL'] ?? 'http://localhost:8080',
    locale: 'ru-RU',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },
  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'], channel: process.env['PW_CHANNEL'] } }],
});
