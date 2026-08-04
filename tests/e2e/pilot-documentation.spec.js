import { test, expect } from '@playwright/test';
import { promises as fs } from 'node:fs';
import path from 'node:path';

const artifactRoot = path.resolve(process.cwd(), process.env.PILOT_ARTIFACT_DIR ?? 'docs/pilot');
const screenshotDir = path.resolve(artifactRoot, 'screenshots');
const summaryPath = path.resolve(artifactRoot, 'e2e-summary.json');

test.describe.configure({ mode: 'serial' });

test.beforeAll(async () => {
    await fs.mkdir(screenshotDir, { recursive: true });
});

test('pilot documentation package captures the Benditio flow end to end', async ({ page, browserName }) => {
    const consoleErrors = [];
    const pageErrors = [];
    const http500Responses = [];
    const failedRequests = [];
    const warnings = [];

    const screenshotPaths = {
        login: 'screenshots/01-login.png',
        developerToolkit: 'screenshots/02-developer-toolkit.png',
        operationsHome: 'screenshots/03-operations-home.png',
        doNowOrder: 'screenshots/04-do-now-order.png',
        orderDrawer: 'screenshots/05-order-drawer.png',
        orderPreparing: 'screenshots/06-order-preparing.png',
        orderReady: 'screenshots/07-order-ready.png',
        orderDispatched: 'screenshots/08-order-dispatched.png',
        completedAfterRefresh: 'screenshots/09-completed-after-refresh.png',
        customerDetail: 'screenshots/10-customer-detail.png',
    };

    page.on('console', (message) => {
        if (message.type() === 'error') {
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
    await page.screenshot({ path: path.resolve(screenshotDir, '01-login.png'), fullPage: true });

    await page.locator('#email').fill('owner@local.test');
    await page.locator('#password').fill('password');
    await page.getByRole('button', { name: 'Entrar' }).click();
    await expect(page).toHaveURL(/\/dashboard/);

    await page.goto('/developer/webhook-simulator');
    await expect(page.getByText('Business Scenario Simulator')).toBeVisible();

    const csrfToken = await page.locator('meta[name="csrf-token"]').getAttribute('content');
    if (!csrfToken) {
        throw new Error('Missing CSRF token on developer toolkit page.');
    }

    const customMessageSection = page.locator('section').filter({ hasText: 'Create Custom Customer Message' });
    await customMessageSection.locator('select[name="customer_mode"]').selectOption('new');
    await customMessageSection.locator('select[name="provider"]').selectOption('whatsapp');
    await customMessageSection.locator('input[name="customer_name"]').fill('Maria Lopez');
    await customMessageSection.locator('input[name="customer_phone"]').fill('50255571234');
    await customMessageSection.locator('textarea[name="message"]').fill('Ocupo 20 bloques para hoy a las 2 pm, me lo llevan y pago por SINPE');
    await page.getByRole('button', { name: 'Inject Message' }).click();
    await expect(page.getByText('Custom message injected through the production pipeline.')).toBeVisible();
    await page.screenshot({ path: path.resolve(screenshotDir, '02-developer-toolkit.png'), fullPage: true });

    await postBusinessMessage(page, csrfToken, {
        provider: 'telegram',
        customer_name: 'Ana Ruiz',
        customer_phone: '50255573333',
        message: '3 pinturas para manana temprano, yo paso por ellas y pago en efectivo',
    });

    await postBusinessMessage(page, csrfToken, {
        provider: 'telegram',
        customer_name: 'Maria Lopez',
        customer_phone: '50255571234',
        message: 'Ocupo 20 bloques para hoy a las 2 pm, me lo llevan y pago por SINPE',
    });

    const feed = await fetchOperationsFeed(page);
    const firstOrder = findOrder(feed.inbox, {
        customerName: 'Maria Lopez',
        provider: 'whatsapp',
        text: '20 bloques',
    });

    const secondOrder = findOrder(feed.inbox, {
        customerName: 'Ana Ruiz',
        provider: 'telegram',
        text: 'pinturas',
    });

    if (!firstOrder) {
        throw new Error('Could not find the primary WhatsApp order in the operations feed.');
    }

    if (!secondOrder) {
        throw new Error('Could not find the Telegram pickup order in the operations feed.');
    }

    await page.goto('/operations');
    await expect(page.getByText('DO NOW', { exact: true })).toBeVisible();
    await expect(page.getByText('NEXT', { exact: true })).toBeVisible();
    await expect(page.getByText('COMPLETED', { exact: true })).toBeVisible();
    await page.screenshot({ path: path.resolve(screenshotDir, '03-operations-home.png'), fullPage: true });

    const doNowSection = page.locator('section').filter({ hasText: 'DO NOW' });
    const doNowCard = doNowSection.locator('article').filter({ hasText: 'Maria Lopez' }).first();
    await expect(doNowCard).toBeVisible();
    await doNowCard.screenshot({ path: path.resolve(screenshotDir, '04-do-now-order.png') });

    await Promise.all([
        page.waitForResponse((response) => response.url().includes(`/operations/orders/${firstOrder.id}/snapshot`) && response.status() === 200),
        doNowCard.click(),
    ]);

    const drawer = page.locator('aside').filter({ hasText: 'Detail drawer' });
    await expect(drawer).toBeVisible();
    await expect(drawer.getByText('Items', { exact: true })).toBeVisible();
    await expect(drawer).toContainText('Prepare');
    await page.screenshot({ path: path.resolve(screenshotDir, '05-order-drawer.png'), fullPage: true });

    await transitionOrder(page, drawer, 'Prepare', 'Prepare', 'Confirmado');

    await transitionOrder(page, drawer, 'Prepare', 'Ready', 'Preparando');
    await page.screenshot({ path: path.resolve(screenshotDir, '06-order-preparing.png'), fullPage: true });

    await transitionOrder(page, drawer, 'Ready', 'Dispatch', 'Listo');
    await page.screenshot({ path: path.resolve(screenshotDir, '07-order-ready.png'), fullPage: true });

    await transitionOrder(page, drawer, 'Dispatch', null, 'Despachado');
    await expect(drawer).toContainText('Complete');
    await page.screenshot({ path: path.resolve(screenshotDir, '08-order-dispatched.png'), fullPage: true });

    await drawer.getByRole('button', { name: 'Close' }).click();
    await page.reload();
    await expect(page.getByText('DO NOW', { exact: true })).toBeVisible();
    await page.locator('details').filter({ hasText: 'COMPLETED' }).locator('summary').click();
    await expect(page.locator('details').filter({ hasText: 'COMPLETED' })).toContainText('Maria Lopez');
    await page.screenshot({ path: path.resolve(screenshotDir, '09-completed-after-refresh.png'), fullPage: true });

    await page.goto('/customers', { waitUntil: 'networkidle' });
    await expect(page.getByRole('heading', { name: 'Clientes', exact: true })).toBeVisible();
    const customerCard = page.locator('article').filter({ hasText: 'Maria Lopez' }).first();
    await expect(customerCard).toBeVisible();
    await customerCard.getByRole('link', { name: 'Ver cliente' }).click();
    await expect(page.getByRole('heading', { name: 'Cliente', exact: true })).toBeVisible();
    await page.screenshot({ path: path.resolve(screenshotDir, '10-customer-detail.png'), fullPage: true });

    const summary = {
        browser: browserName,
        console_errors: consoleErrors,
        page_errors: pageErrors,
        http_500_responses: http500Responses,
        failed_requests: failedRequests,
        warnings,
        failures: [],
        screenshots: screenshotPaths,
        backend_status_dispatched: true,
        workflow_completed: true,
        metrics: {
            whatsapp_tested: true,
            telegram_tested: true,
            login_visible: true,
            developer_toolkit_visible: true,
            operations_home_visible: true,
            operations_drawer_visible: true,
            operations_transitions_completed: true,
            completed_after_refresh_visible: true,
            customer_detail_visible: true,
            completed_ui_inconsistent: false,
        },
    };

    await fs.writeFile(summaryPath, JSON.stringify(summary, null, 2) + '\n');

    if (consoleErrors.length > 0 || pageErrors.length > 0 || http500Responses.length > 0 || failedRequests.length > 0) {
        throw new Error(`Pilot documentation browser run recorded errors: ${JSON.stringify({
            consoleErrors: consoleErrors.length,
            pageErrors: pageErrors.length,
            http500Responses: http500Responses.length,
            failedRequests: failedRequests.length,
        })}`);
    }
});

async function postBusinessMessage(page, csrfToken, payload) {
    const response = await page.request.post('/developer/webhook-simulator/generate', {
        headers: {
            'X-CSRF-TOKEN': csrfToken,
            'X-Requested-With': 'XMLHttpRequest',
        },
        form: {
            _token: csrfToken,
            action: 'business_custom_message',
            customer_mode: 'new',
            provider: payload.provider,
            customer_name: payload.customer_name,
            customer_phone: payload.customer_phone,
            message: payload.message,
        },
    });

    expect(response.ok()).toBeTruthy();
}

async function fetchOperationsFeed(page) {
    const response = await page.request.get('/operations/feed');
    expect(response.ok()).toBeTruthy();
    return response.json();
}

function findOrder(orders, criteria) {
    return orders.find((order) => {
        const customerName = String(order.customer_name ?? '');
        const provider = String(order.channel_key ?? order.source_channel ?? '').toLowerCase();
        const preview = String(order.preview ?? order.raw_message_text ?? order.summary ?? '').toLowerCase();

        return customerName === criteria.customerName
            && provider === criteria.provider
            && preview.includes(criteria.text.toLowerCase());
    });
}

async function transitionOrder(page, drawer, buttonName, nextButtonName, nextStatus) {
    await Promise.all([
        page.waitForResponse((response) => response.request().method() === 'POST' && response.status() === 200 && response.url().includes('/orders/')),
        drawer.getByRole('button', { name: buttonName }).click(),
    ]);

    if (nextButtonName) {
        await expect(drawer.getByRole('button', { name: nextButtonName })).toBeVisible();
    }

    await expect(drawer).toContainText(nextStatus);
}
