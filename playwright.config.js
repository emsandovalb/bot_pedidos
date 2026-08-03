import { defineConfig } from '@playwright/test';

export default defineConfig({
    testDir: './tests/e2e',
    timeout: 120000,
    expect: {
        timeout: 15000,
    },
    fullyParallel: false,
    retries: 0,
    reporter: [
        ['list'],
        ['html', { outputFolder: 'playwright-report', open: 'never' }],
    ],
    use: {
        baseURL: process.env.PLAYWRIGHT_BASE_URL ?? `http://127.0.0.1:${process.env.PILOT_APP_PORT ?? '8010'}`,
        headless: true,
        screenshot: 'only-on-failure',
        video: 'retain-on-failure',
        trace: 'retain-on-failure',
    },
    webServer: {
        command: `php artisan serve --host 127.0.0.1 --port ${process.env.PILOT_APP_PORT ?? '8010'}`,
        url: process.env.PLAYWRIGHT_BASE_URL ?? `http://127.0.0.1:${process.env.PILOT_APP_PORT ?? '8010'}`,
        reuseExistingServer: false,
        timeout: 120000,
    },
});
