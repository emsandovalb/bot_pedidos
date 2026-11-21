import { defineConfig } from '@playwright/test';

// Auditoría del entorno de muestra (scripts/showcase.mjs): BotPedidos + Oria juntos. Cada prueba lleva el código
// de su caso en el plan de pruebas (BOT-01, LINK-08…). Los datos se reinician al empezar, salvo con SHOWCASE_KEEP_DATA=1.
//
//   npx playwright test -c playwright.showcase.config.js
export default defineConfig({
    testDir: './tests/showcase',
    globalSetup: './tests/showcase/global-setup.js',
    timeout: 180000,
    expect: {
        timeout: 15000,
    },
    fullyParallel: false,
    workers: 1,
    retries: 0,
    outputDir: 'artifacts/showcase/test-results',
    reporter: [
        ['list'],
        ['json', { outputFile: 'artifacts/showcase/results.json' }],
    ],
    use: {
        headless: true,
        viewport: { width: 1440, height: 900 },
        locale: 'es-CR',
        timezoneId: 'America/Costa_Rica',
        screenshot: 'only-on-failure',
        trace: 'retain-on-failure',
    },
    webServer: {
        command: 'node scripts/showcase.mjs serve',
        url: 'http://127.0.0.1:3100',
        reuseExistingServer: true,
        timeout: 180000,
    },
});
