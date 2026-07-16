import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: '.',
  testMatch: '*.spec.mjs',
  timeout: 30_000,
  retries: 1,
  workers: 1,
  use: {
    baseURL: process.env.RAD_TEST_BASE_URL || 'http://127.0.0.1:8080',
    browserName: 'chromium',
    ignoreHTTPSErrors: true,
    screenshot: 'only-on-failure',
    trace: 'retain-on-failure',
  },
});
