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

    const actionableOrder = Array.isArray(feed.inbox)
        ? feed.inbox.find((order) => ['pending_review', 'confirmed', 'preparing', 'ready_for_dispatch'].includes(String(order?.status ?? '')))
        : null;

    expect(actionableOrder?.id, 'expected at least one actionable order in the generated scenario').toBeTruthy();

    await page.goto(`/operations/focus?order=${actionableOrder.id}`);
    await page.screenshot({ path: path.resolve(screenshotDir, '01-my-day.png'), fullPage: true });

    const dialog = page.getByRole('dialog');
    await expect(dialog).toBeVisible();
    await expect(dialog.locator('[data-focus-stage]')).toBeVisible();
    await expect(dialog.getByText('Más detalles')).toBeVisible();
    await dialog.getByText('Más detalles').click();
    await expect(dialog.getByText('Mensaje original')).toBeVisible();
    await page.screenshot({ path: path.resolve(screenshotDir, '02-review-order.png'), fullPage: true });

    const workflowButton = dialog.getByRole('button', { name: /Confirmar pedido|Comenzar preparación/ });
    await expect(workflowButton).toBeVisible();

    if ((await workflowButton.textContent())?.includes('Confirmar pedido')) {
        await workflowButton.click();
        await expect(workflowButton).toHaveText('Comenzar preparación');
    }

    await workflowButton.click();
    await expect(dialog.locator('[data-focus-item-checkbox]').first()).toBeVisible();
    await expect(dialog.getByText('Revisá cada artículo')).toBeVisible();
    await page.screenshot({ path: path.resolve(screenshotDir, '03-preparing-checklist.png'), fullPage: true });

    const checkboxes = dialog.locator('[data-focus-item-checkbox]');
    const checkboxCount = await checkboxes.count();
    expect(checkboxCount).toBeGreaterThan(0);

    for (let index = 0; index < checkboxCount; index += 1) {
        await checkboxes.nth(index).check();
    }

    await expect(dialog.locator('[data-focus-progress]')).toContainText(`${checkboxCount} de ${checkboxCount} artículos revisados`);
    await expect(dialog.getByText('100%')).toBeVisible();

    const readyButton = dialog.getByRole('button', { name: 'Marcar pedido como listo' });
    await expect(readyButton).toBeEnabled();
    await readyButton.click();

    await expect(dialog.getByRole('heading', { name: 'Pedido listo para entregar' }).first()).toBeVisible();
    await expect(dialog.getByRole('button', { name: /Despachar pedido|Entregar al cliente/ })).toBeVisible();
    await page.screenshot({ path: path.resolve(screenshotDir, '04-ready-order.png'), fullPage: true });

    const dispatchButton = dialog.getByRole('button', { name: /Despachar pedido|Entregar al cliente/ });
    await dispatchButton.click();

    await expect(dialog.getByRole('heading', { name: 'Pedido completado' }).first()).toBeVisible();
    await page.screenshot({ path: path.resolve(screenshotDir, '05-completed-order.png'), fullPage: true });

    const completedCustomerName = (await dialog.getByRole('heading').first().textContent())?.trim() ?? '';
    const continueButton = dialog.getByRole('button', { name: 'Continuar con el siguiente pedido' });

    if (await continueButton.isVisible()) {
        await continueButton.click();
    }

    await page.screenshot({ path: path.resolve(screenshotDir, '06-next-order.png'), fullPage: true });

    await expect(page.getByText('Cargando pedido...')).toBeHidden();

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
            action: 'quick',
            provider: 'whatsapp',
            count: '1',
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
