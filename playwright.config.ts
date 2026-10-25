import { defineConfig } from '@playwright/test';
export default defineConfig({ testDir: './tests/e2e', workers: 1, timeout: 45000, use: { baseURL: process.env.ZF_E2E_URL ?? 'http://localhost:8000', browserName: 'chromium', screenshot: 'only-on-failure', trace: 'retain-on-failure' }, reporter: 'list' });
