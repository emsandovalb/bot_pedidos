import { test, expect } from '@playwright/test';
import { promises as fs } from 'node:fs';
import path from 'node:path';

const artifactRoot = path.resolve(process.cwd(), process.env.PILOT_ARTIFACT_DIR ?? 'artifacts/pilot/latest');
const screenshotDir = path.resolve(artifactRoot, 'screenshots');

test.describe.configure({ mode: 'serial' });

test.beforeAll(async () => {
    await fs.mkdir(screenshotDir, { recursive: true });
});

test('operations focus mode guides one order through the full workflow', async ({ page, browserName }) => {
    const consoleErrors = [];
    const pageErrors = [];
    const http500Responses = [];
    const failedRequests = [];

    page.on('console', (message) => {
        if (message.type() === 'error' || /Alpine Expression Error/i.test(message.text())) {
            consoleErrors.push(message.text());
        }
    });

    page.on('pageerror', (error) => {
        pageErrors.push(error.message);
    });

    page.on('response', (response) => {
        if (response.status() >= 500) {
            http500Responses.push({ url: response.url(), status: response.status() });
        }
    });

    page.on('requestfailed', (request) => {
        failedRequests.push({
            url: request.url(),
            failure: request.failure()?.errorText ?? 'request failed',
        });
    });

    await page.goto('/login');
    await page.locator('#email').fill('owner@local.test');
    await page.locator('#password').fill('password');
    await page.getByRole('button', { name: 'Entrar' }).click();
    await expect(page).toHaveURL(/\/dashboard/);

    const csrfToken = await page.locator('meta[name="csrf-token"]').getAttribute('content');
    if (!csrfToken) {
        throw new Error('Missing CSRF token on dashboard.');
    }

    await resetToolkitData(page, csrfToken);
    await postToolkitScenario(page, csrfToken);

    const feedResponse = await page.request.get('/operations/feed');
    expect(feedResponse.ok()).toBeTruthy();
    const feed = await feedResponse.json();
    expect(Array.isArray(feed.inbox) ? feed.inbox.length : 0).toBeGreaterThan(0);

    const customOrder = Array.isArray(feed.inbox)
        ? feed.inbox.find((order) =>
            String(order?.customer_name ?? '') === 'Cliente Focus'
            || String(order?.original_message ?? '').includes('Ocupo 20 bloques para hoy a las 2 pm, me lo llevan y pago por SINPE'))
        : null;

    expect(customOrder?.id, 'expected the generated custom message order to be present in the feed').toBeTruthy();

    await page.goto(`/operations/focus?order=${customOrder.id}`);
    await page.screenshot({ path: path.resolve(screenshotDir, '01-focus-clean-summary.png'), fullPage: true });

    await expect(page.locator('[role="dialog"]')).toHaveCount(0);
    await expect(page.locator('[data-focus-inline-workspace]')).toBeVisible();
    await expect(page.locator('[role="dialog"]')).toHaveCount(0);
    await expect(page.locator('[data-focus-inline-review]')).toContainText('Qué preparar');
    await expect(page.locator('[data-focus-inline-review]')).toContainText('20 bloques');
    await expect(page.locator('[data-focus-inline-review]')).toContainText('Entrega');
    await expect(page.locator('[data-focus-inline-review]')).toContainText('SINPE');
    await expect(page.locator('[data-focus-inline-review]')).toContainText('Hoy a las 2:00 p. m.');
    const reviewedItem = page.locator('[data-focus-inline-review] .space-y-3 > div.rounded-2xl.border.border-slate-100.bg-white').first();
    await expect(reviewedItem).toContainText('20 bloques');
    await expect(reviewedItem).not.toContainText('me lo llevan');
    await expect(reviewedItem).not.toContainText('pago por sinpe');
    await expect(page.locator('[data-focus-inline-review]')).toContainText('20 bloques');
    await expect(page.locator('[data-focus-inline-review]')).toContainText('Hoy a las 2:00 p. m.');
    await page.screenshot({ path: path.resolve(screenshotDir, '02-focus-inline-review.png'), fullPage: true });

    const confirmButton = page.getByRole('button', { name: 'Confirmar pedido' });
    await expect(confirmButton).toBeVisible();
    await confirmButton.click();
    await expect(page.getByRole('button', { name: 'Empezar preparación' })).toBeVisible();

    await page.getByRole('button', { name: 'Empezar preparación' }).click();
    const checkboxes = page.locator('[data-focus-item-checkbox]');
    await expect(checkboxes).toHaveCount(1);
    await expect(page.getByText('Revisá cada artículo')).toBeVisible();
    await checkboxes.first().check();
    await expect(page.locator('[data-focus-progress]')).toContainText('1 de 1 artículos revisados');
    await page.screenshot({ path: path.resolve(screenshotDir, '03-focus-preparing.png'), fullPage: true });

    const readyButton = page.getByRole('button', { name: 'Marcar como listo' });
    await expect(readyButton).toBeEnabled();
    await readyButton.click();
    await expect(page.getByRole('button', { name: 'Despachar pedido' })).toBeVisible();
    await page.screenshot({ path: path.resolve(screenshotDir, '04-focus-ready.png'), fullPage: true });

    await page.getByRole('button', { name: 'Despachar pedido' }).click();
    await expect(page.getByText('Este pedido ya quedó listo.')).toBeVisible();
    await page.screenshot({ path: path.resolve(screenshotDir, '05-focus-completed.png'), fullPage: true });

    const continueButton = page.getByRole('button', { name: 'Ir al siguiente pedido' });
    if (await continueButton.isVisible()) {
        await continueButton.click();
    }

    if (pageErrors.length > 0 || http500Responses.length > 0 || failedRequests.length > 0) {
        throw new Error(`Focus mode browser run recorded errors: ${JSON.stringify({
            browserName,
            consoleErrors: consoleErrors.length,
            pageErrors: pageErrors.length,
            http500Responses: http500Responses.length,
            failedRequests: failedRequests.length,
        })}`);
    }
});

async function postToolkitScenario(page, csrfToken) {
    const response = await page.request.post('/developer/webhook-simulator/generate', {
        headers: {
            'X-CSRF-TOKEN': csrfToken,
            'X-Requested-With': 'XMLHttpRequest',
        },
        form: {
            action: 'business_custom_message',
            provider: 'whatsapp',
            customer_mode: 'new',
            customer_name: 'Cliente Focus',
            customer_phone: '50255510050',
            message: 'Ocupo 20 bloques para hoy a las 2 pm, me lo llevan y pago por SINPE',
        },
    });

    expect(response.ok()).toBeTruthy();
}

async function resetToolkitData(page, csrfToken) {
    const response = await page.request.post('/developer/toolkit/reset-demo-data', {
        headers: {
            'X-CSRF-TOKEN': csrfToken,
            'X-Requested-With': 'XMLHttpRequest',
        },
    });

    expect(response.ok()).toBeTruthy();
}
