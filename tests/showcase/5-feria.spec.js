import { test, expect } from '@playwright/test';
import { api, clearOriaLimits, digits, fillOnvoCard, mailLogSize, mailSince, ORIA, oriaSignIn, oriaToken, unique } from './helpers.js';

// Each case starts with Oria's request limits at zero.
test.beforeEach(() => clearOriaLimits());

// Feria Ya's rules are checked through the same API its screens call; the charge flow also through the screen.
const owner = () => oriaToken('soda@oria.test');
const rid = () => `audit-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;

async function feria(pathname, options = {}) {
    return api(`/feria${pathname}`, { token: await owner(), ...options });
}

async function products() {
    const { json } = await feria('/products');
    return Array.isArray(json) ? json : json.data;
}

async function openRegister() {
    const { json } = await feria('/cash');
    // `current` is the register open now; `lastClosed` is the one closed before.
    if (!json.current) {
        const opened = await feria('/cash/open', { method: 'POST', body: { openingCash: 10000 } });
        expect([200, 201, 409]).toContain(opened.status);
    }
}

async function closeRegister() {
    const closed = await feria('/cash/close', { method: 'POST', body: { countedCash: 0 } });
    expect([200, 409]).toContain(closed.status);
}

async function cashSale(body) {
    return feria('/charges/cash', { method: 'POST', body: { clientRequestId: rid(), ...body } });
}

/** Opens the POS signed in as the owner of Soda La Esquina. */
async function openPos(page, email = 'soda@oria.test') {
    await oriaSignIn(page, await oriaToken(email));
    await page.goto(`${ORIA}/feria`);
    await page.reload({ waitUntil: 'networkidle' });
    // "Tocá para empezar a cobrar": the first tap lets the phone speak when a payment comes in.
    const start = page.locator('.feria-start');
    if (await start.isVisible()) await start.click();
    // A sale left on screen by an earlier case is closed (or its QR canceled), so each case starts on an empty sale.
    const next = page.getByRole('button', { name: 'Nuevo cobro' });
    if (await next.isVisible()) await next.click();
    const cancel = page.getByRole('button', { name: 'Cancelar cobro' });
    if (await cancel.isVisible()) {
        page.once('dialog', (dialog) => dialog.accept());
        await cancel.click();
    }
    await expect(page.locator('.feria-generate')).toBeVisible();
}

// --- Cobrar ---

test('FERIA-01 Cobro por QR con productos', async ({ page, browser }) => {
    test.setTimeout(240000);
    await openPos(page);
    await page.locator('.feria-product', { hasText: 'Café' }).click();
    const created = page.waitForResponse((r) => r.url().endsWith('/api/feria/charges') && r.request().method() === 'POST');
    await page.locator('.feria-generate').click();
    const charge = await (await created).json();
    test.info().attach('cobro', { body: JSON.stringify(charge, null, 2) });
    await expect(page.locator('img[src^="data:image"], canvas, svg').first()).toBeVisible();

    const payUrl = charge.payUrl ?? charge.url ?? `${ORIA}/cobro/${charge.code}`;
    const customer = await (await browser.newContext()).newPage();
    await customer.goto(payUrl);
    await customer.getByRole('button', { name: /Tarjeta/ }).first().click();
    await fillOnvoCard(customer);
    await customer.getByRole('button', { name: /^Pagar/ }).last().click();
    await expect.poll(async () => (await feria(`/charges/${charge.id}`)).json.status, { timeout: 120000 }).toBe('paid');
    await expect(page.locator('body')).toContainText(/pagado|listo|recibido/i, { timeout: 30000 });
});

test('FERIA-02 Cobro por monto', async ({ page }) => {
    await openPos(page);
    await page.getByRole('tab', { name: 'Monto', exact: true }).click();
    await expect(page.getByLabel('Monto a cobrar')).toBeVisible();
    const { status, json } = await feria('/charges', { method: 'POST', body: { amount: 2500, clientRequestId: rid() } });
    expect(status).toBe(201);
    expect(json.amount).toBe(250000);
});

test('FERIA-03 Efectivo con caja cerrada', async ({ page }) => {
    await closeRegister();
    const refused = await cashSale({ amount: 2000 });
    expect(refused.status).toBe(409);
    await openPos(page);
    await expect(page.getByRole('button', { name: 'Abrir caja' })).toBeVisible();
});

test('FERIA-04 Efectivo con caja abierta', async () => {
    await openRegister();
    const before = (await feria('/cash')).json;
    const sale = await cashSale({ amount: 2000 });
    expect(sale.status).toBe(201);
    expect(sale.json.status).toBe('paid');
    const after = (await feria('/cash')).json;
    test.info().attach('caja', { body: JSON.stringify({ before, after }, null, 2) });
    expect(JSON.stringify(after)).not.toBe(JSON.stringify(before));
});

test('FERIA-05 Producto agotado', async ({ page }) => {
    await openRegister();
    const cafe = (await products()).find((p) => p.name === 'Café');
    expect((await feria(`/products/${cafe.id}`, { method: 'PATCH', body: { soldOut: true } })).status).toBe(200);
    try {
        await openPos(page);
        await expect(page.locator('.feria-product', { hasText: 'Café' })).toBeDisabled();
        const refused = await cashSale({ items: [{ productId: cafe.id, quantity: 1 }] });
        expect([409, 422]).toContain(refused.status);
    } finally {
        await feria(`/products/${cafe.id}`, { method: 'PATCH', body: { soldOut: false } });
    }
});

test('FERIA-06 Descuento', async () => {
    await openRegister();
    // A discount goes on a sale with products or an "otro monto" (`extra`); a plain amount can't carry one.
    const refused = await cashSale({ amount: 1000, discount: { type: 'amount', value: 100 } });
    expect(refused.status, 'monto suelto con descuento').toBe(422);
    const { status, json } = await cashSale({ extra: 1000, discount: { type: 'amount', value: 100 } });
    expect(status, JSON.stringify(json)).toBe(201);
    expect(json.amount).toBe(90000);
});

test('FERIA-07 Cliente frecuente y puntos', async () => {
    await openRegister();
    expect((await feria('/points', { method: 'PATCH', body: { enabled: true, earnColones: 500, valueColones: 25 } })).status).toBe(200);
    const customer = await feria('/customers', { method: 'POST', body: { name: unique('Ana Auditoría'), phone: `8${digits(7)}`, consent: true } });
    expect(customer.status, JSON.stringify(customer.json)).toBe(201);
    const sale = await cashSale({ amount: 5000, customerId: customer.json.id });
    expect(sale.status).toBe(201);
    expect(sale.json.pointsEarned).toBeGreaterThan(0);
});

// --- Después de la venta ---

test('FERIA-08 Anular', async () => {
    const pending = await feria('/charges', { method: 'POST', body: { amount: 1500, clientRequestId: rid() } });
    expect((await feria(`/charges/${pending.json.id}/cancel`, { method: 'POST' })).status).toBe(200);
    expect((await feria(`/charges/${pending.json.id}`)).json.status).not.toBe('pending');

    await openRegister();
    const sale = await cashSale({ amount: 1200 });
    const voided = await feria(`/charges/${sale.json.id}/void`, { method: 'POST' });
    expect(voided.status).toBe(200);
    expect(voided.json.status).toBe('void');
});

test('FERIA-09 Reembolso con tarjeta', async () => {
    // One day's sales, by the business's day (Costa Rica).
    const today = new Intl.DateTimeFormat('en-CA', { timeZone: 'America/Costa_Rica' }).format(new Date());
    const { json } = await feria(`/sales?date=${today}`);
    const sales = Array.isArray(json) ? json : json.data ?? json.sales ?? [];
    const card = sales.find((sale) => /card|tarjeta/i.test(JSON.stringify(sale.method ?? sale.paymentMethod ?? '')) && sale.status === 'paid');
    test.skip(!card, 'No hay una venta pagada con tarjeta (FERIA-01 necesita internet para ONVO).');
    const refund = await feria(`/charges/${card.id}/refund`, { method: 'POST', body: { amount: Math.round(card.amount / 100), reason: 'damaged' } });
    expect(refund.status, JSON.stringify(refund.json)).toBe(200);
});

test('FERIA-10 Devolución en efectivo', async () => {
    await openRegister();
    const sale = await cashSale({ amount: 3000 });
    const back = await feria(`/charges/${sale.json.id}/refund`, { method: 'POST', body: { amount: 1000, method: 'cash', reason: 'damaged' } });
    expect(back.status, JSON.stringify(back.json)).toBe(200);
    expect(back.json.returns).toHaveLength(1);
    const report = await feria('/report');
    expect(report.status).toBe(200);
});

test('FERIA-11 Comprobante por correo', async () => {
    await openRegister();
    const sale = await cashSale({ amount: 1800 });
    const mail = mailLogSize();
    const sent = await feria(`/charges/${sale.json.id}/receipt-email`, { method: 'POST', body: { email: 'cliente.auditoria@correo.com' } });
    expect(sent.status, JSON.stringify(sent.json)).toBeLessThan(300);
    await expect.poll(() => mailSince(mail)).toContain('cliente.auditoria@correo.com');
});

// --- Gestión ---

test('FERIA-12 Mis productos', async ({ page }) => {
    const name = unique('Tamal');
    const created = await feria('/products', { method: 'POST', body: { name, price: 1500, unit: 'unidad' } });
    expect(created.status).toBe(201);
    expect((await feria(`/products/${created.json.id}`, { method: 'PATCH', body: { price: 1700 } })).status).toBe(200);

    const ids = (await products()).map((p) => p.id);
    const reordered = [created.json.id, ...ids.filter((id) => id !== created.json.id)];
    expect((await feria('/products/order', { method: 'POST', body: { ids: reordered } })).status).toBe(200);
    expect((await products())[0].id).toBe(created.json.id);

    await openPos(page);
    // The grid shows the favorites first; any other product is found with the search box.
    await page.getByLabel('Buscar producto').fill(name);
    await expect(page.locator('.feria-product', { hasText: name })).toBeVisible();
    expect((await feria(`/products/${created.json.id}`, { method: 'DELETE' })).status).toBeLessThan(300);
    expect((await products()).map((p) => p.id)).not.toContain(created.json.id);
});

test('FERIA-13 Inventario', async () => {
    await openRegister();
    const product = (await feria('/products', { method: 'POST', body: { name: unique('Queque'), price: 2000, unit: 'unidad' } })).json;
    expect((await feria(`/products/${product.id}/stock`, { method: 'POST', body: { kind: 'receive', quantity: '5', note: 'Auditoría' } })).status).toBe(200);
    expect((await cashSale({ items: [{ productId: product.id, quantity: 2 }] })).status).toBe(201);
    const now = (await products()).find((p) => p.id === product.id);
    test.info().attach('producto', { body: JSON.stringify(now, null, 2) });
    expect(Number(now.stock ?? now.inStock ?? now.quantity)).toBe(3);
});

test('FERIA-14 Caja y cierre del día', async () => {
    await openRegister();
    const closed = await feria('/cash/close', { method: 'POST', body: { countedCash: 1 } });
    expect(closed.status).toBe(200);
    expect(closed.json.lastClosed?.difference, 'muestra la diferencia').not.toBe(0);
    const report = await feria('/report');
    expect(report.status).toBe(200);
});

test('FERIA-15 Reporte del mes', async () => {
    expect((await feria('/month')).status).toBe(200);
    const response = await fetch('http://127.0.0.1:8100/api/feria/month/export', { headers: { Authorization: `Bearer ${await owner()}` } });
    expect(response.status).toBe(200);
    expect(response.headers.get('content-type')).toMatch(/spreadsheet|excel|csv|octet/);
});

test('FERIA-16 Equipo y permisos', async () => {
    const cashier = await oriaToken('cajera@oria.test');
    const manager = await oriaToken('encargado@oria.test');
    await openRegister();
    expect((await api('/feria/charges/cash', { method: 'POST', token: cashier, body: { amount: 1000, clientRequestId: rid() } })).status, 'la cajera cobra').toBe(201);
    expect((await api('/feria/report', { token: cashier })).status, 'la cajera no ve reportes').toBe(403);
    expect((await api('/feria/team', { token: cashier })).status, 'la cajera no ve el equipo').toBe(403);
    expect((await api('/feria/report', { token: manager })).status, 'el encargado ve reportes').toBe(200);
});

test('FERIA-17 Agricultor sin Pro', async ({ page }) => {
    const token = await oriaToken('feria@oria.test');
    const stand = await api('/feria/stand', { token });
    expect(stand.status).toBe(200);
    expect(JSON.stringify(stand.json)).toContain('Verduras');
    expect((await api('/feria/cash', { token })).status, 'sin caja Pro').toBe(403);
    expect((await api('/feria/team', { token })).status, 'sin equipo Pro').toBe(403);
    await oriaSignIn(page, token, '#pagar');
    await expect(page.locator('.main-nav a', { hasText: 'Pagar servicios' })).toHaveCount(0);
});
