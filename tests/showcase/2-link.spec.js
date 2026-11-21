import { test, expect } from '@playwright/test';
import { acceptDialogs, api, BOT, botLogin, clearOriaLimits, forgetOriaPlanCheck, newOriaToken, ORIA, oriaToken, PASSWORD, startOriaApi, stopOriaApi } from './helpers.js';

// Each case starts with Oria's request limits at zero.
test.beforeEach(() => clearOriaLimits());

const payServicesLink = (page) => page.locator('aside, nav').getByRole('link', { name: /Pagar servicios/ });

test.beforeEach(({ page }) => acceptDialogs(page));

async function disconnectIfNeeded(page) {
    await page.goto(`${BOT}/settings/oria`);
    const button = page.getByRole('button', { name: 'Desconectar' });
    if (await button.count()) {
        await button.click();
        await expect(page.getByRole('button', { name: 'Conectar con Oria' })).toBeVisible();
    }
}

async function startConnect(page) {
    await page.goto(`${BOT}/settings/oria`);
    await page.getByRole('button', { name: 'Conectar con Oria' }).click();
    await page.waitForURL(/\/vincular\//);
}

/** On Oria's approval page, signs in as `email` (token in storage) and reloads. */
async function signInOnApproval(page, email = 'demo@oria.test', { own = false } = {}) {
    const token = own ? newOriaToken(email) : await oriaToken(email);
    await page.evaluate((t) => localStorage.setItem('oria.token', t), token);
    await page.reload();
    await expect(page.getByRole('button', { name: /Conectar con BotPedidos/ })).toBeVisible();
}

async function connect(page) {
    await disconnectIfNeeded(page);
    await startConnect(page);
    await signInOnApproval(page);
    await page.getByRole('button', { name: /Conectar con BotPedidos/ }).click();
    await page.waitForURL(`${BOT}/settings/oria**`);
    await expect(page.locator('main')).toContainText('Conectado con Oria');
}

async function activeLink() {
    const { json } = await api('/support/partner-links', { token: await oriaToken('soporte@oria.test') });
    const links = Array.isArray(json) ? json : json.data;

    return links.find((link) => /activ/i.test(JSON.stringify(link.status ?? link.state ?? 'active'))) ?? links[0];
}

async function setPlan(plan) {
    const link = await activeLink();
    const { status } = await api(`/support/partner-links/${link.id}`, { method: 'PATCH', token: await oriaToken('soporte@oria.test'), body: { plan } });
    expect(status).toBe(200);
    forgetOriaPlanCheck();
}

// --- Conectar ---

test('LINK-01 Sin configurar', async () => {
    test.skip(true, 'Se valida en PHPUnit (OriaPlusTest): este entorno siempre tiene Oria configurada.');
});

test('LINK-05 No, gracias', async ({ page }) => {
    await botLogin(page);
    await disconnectIfNeeded(page);
    await startConnect(page);
    await signInOnApproval(page);
    await page.getByRole('button', { name: 'No, gracias' }).click();
    await page.waitForURL(`${BOT}/settings/oria**`);
    await expect(page.locator('main')).toContainText('No se conectó con Oria');
    await expect(page.getByRole('button', { name: 'Conectar con Oria' })).toBeVisible();
});

test('LINK-06 Enlace vencido', async ({ page }) => {
    await botLogin(page);
    await disconnectIfNeeded(page);
    await startConnect(page);
    const approval = page.url();
    await signInOnApproval(page);
    await page.getByRole('button', { name: 'No, gracias' }).click();
    await page.waitForURL(`${BOT}/settings/oria**`);
    await page.goto(approval);
    await expect(page.locator('h1')).toContainText('Este enlace ya se usó o venció');
});

test('LINK-04 Usar otra cuenta', async ({ page }) => {
    await botLogin(page);
    await disconnectIfNeeded(page);
    await startConnect(page);
    // "Usar otra cuenta" signs out, which revokes the token: use one no other case needs.
    await signInOnApproval(page, 'demo@oria.test', { own: true });
    await page.getByRole('button', { name: 'Usar otra cuenta' }).click();
    await expect(page.getByRole('button', { name: 'Iniciar sesión' })).toBeVisible();
    expect(await page.evaluate(() => localStorage.getItem('oria.token'))).toBeNull();
});

test('LINK-03 Conectar sin sesión', async ({ page }) => {
    await botLogin(page);
    await disconnectIfNeeded(page);
    await startConnect(page);
    await page.getByRole('button', { name: 'Iniciar sesión' }).click();
    await page.locator('.auth-panel input[type=email]').fill('demo@oria.test');
    await page.locator('#auth-password').fill(PASSWORD);
    await page.locator('.auth-submit').click();
    await page.getByRole('button', { name: /Conectar con BotPedidos/ }).click();
    await page.waitForURL(`${BOT}/settings/oria**`);
    await expect(page.locator('main')).toContainText('Conectado con Oria');
});

test('LINK-02 Conectar con sesión abierta en Oria', async ({ page }) => {
    await botLogin(page);
    await connect(page);
    await expect(page.locator('main')).toContainText('Plan Básico');
    await expect(page.locator('main')).toContainText('Laura Méndez Rojas');
});

// --- Planes y Pagar servicios ---

test('LINK-07 Básico no muestra Pagar servicios', async ({ page }) => {
    await botLogin(page);
    await connect(page);
    await expect(payServicesLink(page)).toHaveCount(0);
    await expect(page.locator('main')).toContainText('El plan Plus lo activa');
});

test('LINK-08 Activar Plus', async ({ page, browser }) => {
    await botLogin(page);
    await connect(page);

    const support = await (await browser.newContext()).newPage();
    await support.goto(`${ORIA}/`);
    await support.evaluate((t) => localStorage.setItem('oria.token', t), await oriaToken('soporte@oria.test'));
    await support.goto(`${ORIA}/#soporte`);
    await support.reload({ waitUntil: 'networkidle' });
    await support.getByRole('tab', { name: 'Negocios vinculados' }).click();
    await support.locator('.support-row').first().click();
    await support.getByRole('button', { name: 'Activar plan Plus' }).click();
    await expect(support.getByRole('button', { name: 'Pasar a plan Básico' })).toBeVisible();

    forgetOriaPlanCheck();
    await page.goto(`${BOT}/settings/oria`);
    await expect(page.locator('main')).toContainText('Plan Plus');
    await expect(payServicesLink(page)).toBeVisible();
});

test('LINK-09 Abrir Oria sin volver a entrar', async ({ page }) => {
    await botLogin(page);
    await page.goto(`${BOT}/settings/oria`);
    if (!(await payServicesLink(page).count())) await setPlan('plus');
    await page.goto(`${BOT}/settings/oria`);
    await payServicesLink(page).click();
    await page.waitForURL(`${ORIA}/**`);
    await expect(page).toHaveURL(/#pagar/);
    await expect(page.locator('body')).toContainText('Laura');
    expect(await page.evaluate(() => localStorage.getItem('oria.token'))).toBeTruthy();
});

test('LINK-10 Volver a Básico', async ({ page }) => {
    await botLogin(page);
    await setPlan('plus');
    await page.goto(`${BOT}/settings/oria`);
    await expect(payServicesLink(page)).toBeVisible();
    await setPlan('basico');
    await page.goto(`${BOT}/settings/oria`);
    await expect(payServicesLink(page)).toHaveCount(0);
    await expect(page.locator('main')).toContainText('Plan Básico');
});

test('LINK-11 Oria caída', async ({ page }) => {
    await botLogin(page);
    await setPlan('plus');
    await page.goto(`${BOT}/settings/oria`);
    await expect(page.locator('main')).toContainText('Plan Plus');

    stopOriaApi();
    try {
        forgetOriaPlanCheck();
        const dashboard = await page.goto(`${BOT}/dashboard`);
        expect(dashboard.status()).toBe(200);
        await page.goto(`${BOT}/settings/oria`);
        await expect(page.locator('main'), 'conserva el último plan').toContainText('Plan Plus');
        await payServicesLink(page).click();
        await expect(page).toHaveURL(`${BOT}/settings/oria`);
        await expect(page.getByRole('alert')).toBeVisible();
    } finally {
        await startOriaApi();
    }
});

test('LINK-12 Desconectar', async ({ page }) => {
    await botLogin(page);
    await page.goto(`${BOT}/settings/oria`);
    if (!(await page.getByRole('button', { name: 'Desconectar' }).count())) await connect(page);
    const link = await activeLink();
    await page.getByRole('button', { name: 'Desconectar' }).click();
    await expect(page.locator('main')).toContainText('Sin conectar');
    await expect(page.locator('main')).toContainText('ya no está conectado');

    const { json } = await api(`/support/partner-links/${link.id}`, { token: await oriaToken('soporte@oria.test') });
    test.info().attach('vínculo en Oria', { body: JSON.stringify(json, null, 2) });
    expect(JSON.stringify(json)).toMatch(/unlink|inactive|revoked|desvincul|"active":false|"isActive":false/i);
});
