import { test, expect } from '@playwright/test';
import { api, clearOriaLimits, oriaSignIn, oriaToken, register, unique } from './helpers.js';

// Each case starts with Oria's request limits at zero.
test.beforeEach(() => clearOriaLimits());

async function openQueue(page) {
    await oriaSignIn(page, await oriaToken('soporte@oria.test'), '#soporte');
    await search(page, 'oria_demo');
}

/** Types in the search box and waits for the list that answers it (the box waits for a pause before asking). */
async function search(page, text) {
    const answered = page.waitForResponse((r) => r.url().includes('/support/orders?') && r.url().includes(`q=` + encodeURIComponent(text).replace(/%20/g, '+')));
    await page.getByLabel('Buscar pagos').fill(text);
    await answered;
    await page.waitForLoadState('networkidle');
}

/** Opens a customer's payment and waits until the panel shows that payment (not the list's previous pick). */
async function openPayment(page, customer) {
    await page.locator('.support-row', { hasText: customer }).click();
    await page.waitForLoadState('networkidle');
    await expect(page.locator('.support-facts')).toContainText(customer);
}

test('SUPPORT-04 Buscar', async ({ page }) => {
    await openQueue(page);
    await expect(page.locator('.support-row')).toHaveCount(2);
    await page.getByLabel('Buscar pagos').fill('Carlos Vega');
    await expect(page.locator('.support-row')).toHaveCount(1);
});

test('SUPPORT-01 Reintentar con el proveedor', async ({ page }) => {
    await openQueue(page);
    await openPayment(page, 'Carlos Vega');
    await page.getByRole('button', { name: /Reintentar con CNFL/ }).click();
    await expect(page.locator('.support-flash')).toContainText('El proveedor aplicó el pago');
    await expect(page.locator('.support-detail-head .movement-status')).toHaveText('Aplicado');
});

test('SUPPORT-02 Marcar como aplicado', async ({ page }) => {
    await openQueue(page);
    await page.locator('.support-row', { hasText: 'Laura Méndez Rojas' }).click();
    await page.getByRole('button', { name: 'Marcar como aplicado' }).click();
    await page.getByLabel(/Referencia de CNFL/).fill('CNFL-7F3A21');
    await page.getByLabel('¿Cómo lo confirmaste?').fill('Llamada a CNFL, aplicado hoy.');
    await page.getByRole('button', { name: 'Confirmar aplicación' }).click();
    await expect(page.locator('.support-flash')).toContainText('Pago marcado como aplicado');

    const { json } = await api('/orders', { token: await oriaToken('demo@oria.test') });
    const review = (Array.isArray(json) ? json : json.data).find((order) => JSON.stringify(order).includes('oria_demo_review'));
    expect(JSON.stringify(review), 'Laura lo ve aplicado').not.toMatch(/manual_review|en revisión/i);
});

test('SUPPORT-03 Notas', async ({ page }) => {
    const note = unique('Nota de auditoría');
    await openQueue(page);
    // The cases before resolved the demo payments; "Todos" still lists them.
    const all = page.waitForResponse((r) => r.url().includes('/support/orders?') && r.url().includes('q=oria_demo'));
    await page.getByRole('tab', { name: 'Todos' }).click();
    await all;
    await page.waitForLoadState('networkidle');
    // Typing before the payment loads is lost when the detail arrives.
    await openPayment(page, 'Carlos Vega');
    await page.getByLabel('Nota interna').fill(note);
    const saved = page.waitForResponse((r) => r.url().endsWith('/notes') && r.request().method() === 'POST');
    await page.getByRole('button', { name: 'Agregar nota' }).click();
    expect((await saved).status()).toBeLessThan(300);
    await expect(page.locator('.support-timeline')).toContainText(note);
    await expect(page.locator('.support-timeline')).toContainText('Ana Soporte');
});

test('SUPPORT-05 Crear puesto de feria', async () => {
    const { email } = await register('agricultor');
    const token = await oriaToken('soporte@oria.test');
    const created = await api('/support/feria/farmers', { method: 'POST', token, body: { email, standName: 'Puesto Auditoría' } });
    expect(created.status, JSON.stringify(created.json)).toBeLessThan(300);

    const login = await api('/auth/login', { method: 'POST', body: { email, password: 'secreta123' } });
    const stand = await api('/feria/stand', { token: login.json.token });
    expect(stand.status).toBe(200);
    expect(JSON.stringify(stand.json)).toContain('Puesto Auditoría');
});

test('SUPPORT-06 Editar puesto', async () => {
    const token = await oriaToken('soporte@oria.test');
    const { json } = await api('/support/feria/farmers', { token });
    const farmers = Array.isArray(json) ? json : json.data;
    const rosa = farmers.find((farmer) => JSON.stringify(farmer).includes('Verduras Doña Rosa'));
    const name = unique('Verduras Rosa');
    expect((await api(`/support/feria/farmers/${rosa.id}`, { method: 'PATCH', token, body: { standName: name } })).status).toBe(200);
    const stand = await api('/feria/stand', { token: await oriaToken('feria@oria.test') });
    expect(JSON.stringify(stand.json)).toContain(name);
});

test('SUPPORT-07 Negocios vinculados', async ({ page }) => {
    const { json } = await api('/support/partner-links', { token: await oriaToken('soporte@oria.test') });
    const links = Array.isArray(json) ? json : json.data;
    test.skip(!links?.length, 'Sin negocios vinculados: corre antes los casos LINK.');
    await oriaSignIn(page, await oriaToken('soporte@oria.test'), '#soporte');
    await page.getByRole('tab', { name: 'Negocios vinculados' }).click();
    await page.locator('.support-row').first().click();
    const detail = page.locator('main');
    await expect(detail).toContainText('BotPedidos');
    await expect(detail).toContainText('demo@oria.test');
    await expect(detail).toContainText(/Plan|Básico|Plus/);
});

test('SUPPORT-08 Usuario normal no entra', async ({ page }) => {
    await oriaSignIn(page, await oriaToken('demo@oria.test'), '#soporte');
    await expect(page.locator('.main-nav a', { hasText: 'Consola de soporte' })).toHaveCount(0);
    expect((await api('/support/partner-links', { token: await oriaToken('demo@oria.test') })).status).toBe(403);
});
