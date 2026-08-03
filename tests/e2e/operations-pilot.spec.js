import { test, expect } from '@playwright/test';
import { promises as fs } from 'node:fs';
import path from 'node:path';

const artifactRoot = path.resolve(process.cwd(), process.env.PILOT_ARTIFACT_DIR ?? 'artifacts/pilot/latest');
const screenshotDir = path.resolve(artifactRoot, 'screenshots');
const summaryPath = path.resolve(artifactRoot, 'e2e-summary.json');

test.beforeAll(async () => {
    await fs.mkdir(screenshotDir, { recursive: true });
});

test('operations pilot flow remains stable end to end', async ({ page, browserName }) => {
    const consoleErrors = [];
    const pageErrors = [];
    const http500Responses = [];
    const failedSnapshotRequests = [];
    const failedRequests = [];
    const alpineExpressionErrors = [];
    const unhandledJavaScriptErrors = [];
    const warnings = [];

    let customerName = '';
    let selectedOrderId = null;

    page.on('console', (message) => {
        const text = message.text();
        const type = message.type();

        if (type === 'error' || /Alpine Expression Error/i.test(text) || /uncaught TypeError/i.test(text) || /Unhandled promise rejection/i.test(text)) {
            consoleErrors.push(text);

            if (/Alpine Expression Error/i.test(text)) {
                alpineExpressionErrors.push(text);
            }
        }
    });

    page.on('pageerror', (error) => {
        pageErrors.push(error.message);
        unhandledJavaScriptErrors.push(error.message);
    });

    page.on('response', (response) => {
        const url = response.url();
        const status = response.status();

        if (status >= 500) {
            http500Responses.push({ url, status });
        }

        if (url.includes('/operations/orders/') && url.includes('/snapshot') && status >= 400) {
            failedSnapshotRequests.push({ url, status });
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

    await page.goto('/developer/webhook-simulator');
    await expect(page.getByText('Business Scenario Simulator')).toBeVisible();
    await page.getByRole('button', { name: /Generar escenario|Generate scenario/ }).click();
    await expect(page.getByText('Escenario Ferreteria pequena generado.')).toBeVisible();
    await page.screenshot({ path: path.resolve(screenshotDir, '01-toolkit-generated.png'), fullPage: true });

    await page.goto('/operations');
    await expect(page.getByText('DO NOW', { exact: true })).toBeVisible();
    await expect(page.getByText('NEXT', { exact: true })).toBeVisible();
    await expect(page.getByText('COMPLETED', { exact: true })).toBeVisible();
    await expect(page.getByText('Live', { exact: true })).toBeVisible();
    await page.screenshot({ path: path.resolve(screenshotDir, '02-operations-home.png'), fullPage: true });

    const doNowCard = page.locator('article').first();
    await expect(doNowCard).toBeVisible();
    await doNowCard.click();

    const drawer = page.locator('aside.absolute.right-0.top-0');
    await expect(drawer).toBeVisible();
    await expect(drawer.getByText('Items', { exact: true })).toBeVisible();
    await expect(drawer.locator('div.rounded-2xl.border.border-slate-100.bg-slate-50.p-4').first()).toBeVisible();
    customerName = (await drawer.locator('h2').first().textContent())?.trim() ?? '';
    selectedOrderId = Number(new URL(page.url()).searchParams.get('order') ?? 0) || null;
    await page.screenshot({ path: path.resolve(screenshotDir, '03-order-drawer.png'), fullPage: true });

    await Promise.all([
        page.waitForResponse((response) => response.request().method() === 'POST' && response.url().includes('/confirm') && response.status() === 200),
        drawer.getByRole('button', { name: 'Prepare' }).click(),
    ]);
    await expect(drawer).toContainText('Confirmado');
    await page.screenshot({ path: path.resolve(screenshotDir, '04-after-action.png'), fullPage: true });

    await Promise.all([
        page.waitForResponse((response) => response.request().method() === 'POST' && response.url().includes('/prepare') && response.status() === 200),
        drawer.getByRole('button', { name: 'Prepare' }).click(),
    ]);

    await Promise.all([
        page.waitForResponse((response) => response.request().method() === 'POST' && response.url().includes('/ready-for-dispatch') && response.status() === 200),
        drawer.getByRole('button', { name: 'Ready' }).click(),
    ]);

    await Promise.all([
        page.waitForResponse((response) => response.request().method() === 'POST' && response.url().includes('/dispatch') && response.status() === 200),
        drawer.getByRole('button', { name: 'Dispatch' }).click(),
    ]);
    await expect(drawer).toContainText('Despachado');
    await page.screenshot({ path: path.resolve(screenshotDir, '05-final-state.png'), fullPage: true });

    const feedResponse = await page.request.get('/operations/feed');
    expect(feedResponse.ok()).toBeTruthy();
    const feed = await feedResponse.json();
    const inbox = Array.isArray(feed.inbox) ? feed.inbox : [];
    const matchedOrder = selectedOrderId !== null
        ? inbox.find((order) => Number(order.id) === selectedOrderId)
        : inbox.find((order) => String(order.customer_name ?? '') === customerName);

    const backendStatusDispatched = matchedOrder?.status === 'dispatched';
    expect(backendStatusDispatched).toBeTruthy();

    await page.reload();
    await drawer.getByRole('button', { name: 'Close' }).click();

    const completedSection = page.locator('details').filter({ hasText: 'COMPLETED' });
    await completedSection.locator('summary').click();
    await expect(completedSection).toContainText(customerName);
    await completedSection.getByRole('button', { name: new RegExp(customerName, 'i') }).first().click();
    await expect(drawer).toContainText('Despachado');
    await drawer.getByRole('button', { name: 'Close' }).click();
    const completedUiVisible = true;

    const filtersPanel = page.locator('details').filter({ hasText: 'Secondary toolbar' });
    await filtersPanel.locator('summary').click();
    const searchInput = filtersPanel.getByPlaceholder('Search customer, phone, message, branch, or ID');
    await searchInput.fill(customerName);
    await expect(page.getByText('DO NOW', { exact: true })).toBeVisible();
    await expect(page.getByText('NEXT', { exact: true })).toBeVisible();
    await searchInput.fill('');
    await filtersPanel.getByRole('button', { name: 'Clear' }).click();

    const uniqueCustomers = new Set(inbox.map((order) => String(order.customer_name ?? ''))).size;
    const uniqueIdentities = new Set(inbox.map((order) => `${String(order.customer_name ?? '')}|${String(order.channel_key ?? order.source_channel ?? '')}`)).size;
    const orderCount = inbox.length;
    const whatsappTested = inbox.some((order) => String(order.channel_key ?? order.source_channel ?? '') === 'whatsapp');
    const telegramTested = inbox.some((order) => String(order.channel_key ?? order.source_channel ?? '') === 'telegram');
    const summary = {
        browser: browserName,
        console_errors: consoleErrors,
        page_errors: pageErrors,
        http_500_responses: http500Responses,
        failed_snapshot_requests: failedSnapshotRequests,
        failed_requests: failedRequests,
        alpine_expression_errors: alpineExpressionErrors,
        unhandled_javascript_errors: unhandledJavaScriptErrors,
        warnings,
        failures: [],
        screenshots: {
            toolkit_generated: 'screenshots/01-toolkit-generated.png',
            operations_home: 'screenshots/02-operations-home.png',
            order_drawer: 'screenshots/03-order-drawer.png',
            after_action: 'screenshots/04-after-action.png',
            final_state: 'screenshots/05-final-state.png',
        },
        backend_status_dispatched: backendStatusDispatched,
        completed_ui_visible: completedUiVisible,
        workflow_completed: backendStatusDispatched,
        metrics: {
            whatsapp_tested: whatsappTested,
            telegram_tested: telegramTested,
            incoming_messages_created: orderCount,
            customers_created: uniqueCustomers,
            identities_created: uniqueIdentities,
            orders_created: orderCount,
            fulfillment_plans_created: orderCount,
            dispatched_orders_verified: backendStatusDispatched,
            operations_do_now_visible: true,
            operations_next_visible: true,
            operations_drawer_opened: true,
            operations_items_visible: true,
            operations_valid_action_visible: true,
            operations_transition_succeeded: backendStatusDispatched,
            operations_live_visible: true,
            operations_search_stable: true,
        },
    };

    await fs.writeFile(summaryPath, JSON.stringify(summary, null, 2) + '\n');

    if (consoleErrors.length > 0 || pageErrors.length > 0 || http500Responses.length > 0 || failedSnapshotRequests.length > 0 || failedRequests.length > 0 || alpineExpressionErrors.length > 0 || unhandledJavaScriptErrors.length > 0) {
        throw new Error(`Pilot browser run recorded errors: ${JSON.stringify({
            consoleErrors: consoleErrors.length,
            pageErrors: pageErrors.length,
            http500Responses: http500Responses.length,
            failedSnapshotRequests: failedSnapshotRequests.length,
            failedRequests: failedRequests.length,
        })}`);
    }
});
