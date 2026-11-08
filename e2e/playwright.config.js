// @ts-check
const { defineConfig } = require('@playwright/test');

module.exports = defineConfig({
  testDir: './tests',
  timeout: 60_000,
  workers: 1,               // os testes compartilham o mesmo banco de teste
  retries: 0,
  reporter: [['list']],
  use: {
    baseURL: process.env.E2E_URL || 'http://localhost:8080',
    locale: 'pt-BR',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },
});
