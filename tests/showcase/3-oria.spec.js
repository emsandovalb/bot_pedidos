import { test, expect } from '@playwright/test';
import { api, clearOriaLimits, digits, fillOnvoCard, lookUpBill, mailLogSize, mailSince, ORIA, oriaSignIn, oriaToken, PASSWORD, register, rejectedNise } from './helpers.js';

// Each case starts with Oria's request limits at zero.
test.beforeEach(() => clearOriaLimits());

// Card and SINPE payments go through ONVO's test mode, so those cases need internet.
const PAID = 'Listo, tu pago quedó aplicado';

async function guestPay(page) {
    await oriaSignIn(page, null, '#pagar');
}

// --- Cuenta ---

test('ORIA-01 Registro', async ({ page }) => {
    const email = `registro+${Date.now()}@example.com`;
    const mail = mailLogSize();
    await oriaSignIn(page, null);
    await page.getByRole('button', { name: 'Iniciar sesión' }).first().click();
    await page.getByRole('tab', { name: 'Crear cuenta' }).click();
    await page.locator('.auth-panel input[autocomplete="name"]').fill('Emma Registro');
    await page.locator('.auth-panel input[type=email]').fill(email);
    await page.locator('#auth-password').fill(PASSWORD);
    await page.locator('.auth-submit').click();
    await expect(page.locator('.auth-panel')).toHaveCount(0);
    await expect(page.locator('body')).toContainText('Emma');
    await expect.poll(() => mailSince(mail), { message: 'correo de verificación en el log' }).toContain(email);
});

test('ORIA-02 Inicio y cierre de sesión', async ({ page }) => {
    const { email } = await register('login');
    await oriaSignIn(page, null);
    await page.getByRole('button', { name: 'Iniciar sesión' }).first().click();
    await page.locator('.auth-panel input[type=email]').fill(email);
    await page.locator('#auth-password').fill('clave-incorrecta');
    await page.locator('.auth-submit').click();
    await expect(page.locator('.auth-panel .inline-error')).toBeVisible();

    await page.locator('#auth-password').fill(PASSWORD);
    await page.locator('.auth-submit').click();
    await expect(page.locator('.auth-panel')).toHaveCount(0);
    await page.getByRole('button', { name: 'Cerrar sesión' }).first().click();
    await expect(page.getByRole('button', { name: 'Iniciar sesión' }).first()).toBeVisible();
    expect(await page.evaluate(() => localStorage.getItem('oria.token'))).toBeNull();
});

test('ORIA-03 Recuperar contraseña', async ({ page }) => {
    const { email } = await register('olvido');
    const mail = mailLogSize();
    await oriaSignIn(page, null);
    await page.getByRole('button', { name: 'Iniciar sesión' }).first().click();
    await page.locator('.auth-forgot').click();
    await page.locator('.auth-panel input[type=email]').fill(email);
    await page.locator('.auth-submit').click();
    await expect(page.locator('.auth-notice')).toBeVisible();

    let link;
    await expect.poll(() => (link = mailSince(mail).match(/https?:\/\/[^\s"'<>]*reset=[^\s"'<>]+/)?.[0]), { message: 'enlace en el correo' }).toBeTruthy();
    await page.goto(link.replace(/&amp;/g, '&'));
    await page.locator('#auth-password').fill('Nueva-clave-456');
    await page.locator('.auth-panel input[autocomplete="new-password"]').last().fill('Nueva-clave-456');
    const reset = page.waitForResponse((r) => r.url().endsWith('/auth/reset-password'));
    await page.locator('.auth-submit').click();
    const answer = await reset;
    expect(answer.status(), await answer.text()).toBe(200);
    await expect(page.locator('.auth-panel .inline-error')).toHaveCount(0);

    expect((await api('/auth/login', { method: 'POST', body: { email, password: 'Nueva-clave-456' } })).status, 'entra con la nueva').toBe(200);
    expect((await api('/auth/login', { method: 'POST', body: { email, password: PASSWORD } })).status, 'no entra con la vieja').toBe(422);
});

test('ORIA-04 Perfil y seguridad', async ({ page }) => {
    const { email, token } = await register('perfil');
    const other = (await api('/auth/login', { method: 'POST', body: { email, password: PASSWORD } })).json.token;
    await oriaSignIn(page, token, '#perfil');

    await page.locator('.profile-card input[autocomplete="name"]').fill('Emma Perfil Nuevo');
    await page.getByRole('button', { name: 'Guardar cambios' }).click();
    await expect(page.locator('.profile-ok').first()).toBeVisible();
    expect((await api('/me', { token })).json.user?.name ?? (await api('/me', { token })).json.name).toBe('Emma Perfil Nuevo');

    await expect(page.locator('.session-list li')).toHaveCount(2);
    await page.getByRole('button', { name: 'Cerrar las demás' }).click();
    await expect(page.locator('.session-list li')).toHaveCount(1);
    expect((await api('/me', { token: other })).status, 'la otra sesión ya no entra').toBe(401);
});

// --- Pagar ---

test('ORIA-05 Pago con tarjeta aprobada', async ({ page }) => {
    await oriaSignIn(page, await oriaToken('demo@oria.test'), '#pagar');
    await lookUpBill(page, 'ICE', digits(9), 'Electricidad');
    await page.getByRole('button', { name: 'Ingresar tarjeta y pagar' }).click();
    await fillOnvoCard(page);
    await page.locator('.secure-foot .pay-button').click();
    await expect(page.locator('.paid-body h2')).toHaveText(PAID, { timeout: 90000 });
    await page.getByRole('button', { name: 'Ver comprobante' }).click();
    await expect(page.locator('.receipt-qr')).toHaveAttribute('src', /^data:image\/png/);
});

test('ORIA-06 Tarjeta rechazada', async ({ page }) => {
    await guestPay(page);
    await lookUpBill(page, 'AyA', digits(7));
    await page.getByRole('button', { name: 'Ingresar tarjeta y pagar' }).click();
    await fillOnvoCard(page, '4000000000000002');
    await page.locator('.secure-foot .pay-button').click();
    await expect(page.locator('.secure-foot .inline-error')).toBeVisible({ timeout: 60000 });
    await expect(page.locator('.secure-foot .pay-button')).toBeEnabled();
});

test('ORIA-07 SINPE Móvil', async ({ page }) => {
    test.setTimeout(240000);
    await guestPay(page);
    await lookUpBill(page, 'ICE', digits(9), 'Electricidad');
    await page.locator('.method-option', { hasText: 'SINPE' }).click();
    await page.getByLabel('Tu número SINPE Móvil').fill('8888-8888');
    await page.getByLabel('Número de identificación').fill('113931919');
    await page.getByRole('button', { name: 'Continuar con SINPE Móvil' }).click();
    await expect(page.locator('.sinpe-body h2')).toHaveText('Transfiere por SINPE Móvil');
    await expect(page.locator('.paid-body h2')).toHaveText(PAID, { timeout: 120000 });
});

test('ORIA-08 Pago queda en revisión', async ({ page }) => {
    await guestPay(page);
    await lookUpBill(page, 'CNFL', rejectedNise());
    await page.getByRole('button', { name: 'Ingresar tarjeta y pagar' }).click();
    await fillOnvoCard(page);
    await page.locator('.secure-foot .pay-button').click();
    await expect(page.locator('.status-mark.in-review')).toBeVisible({ timeout: 90000 });
    await expect(page.locator('.review-note')).toContainText('No vuelvas a pagar');
});

test('ORIA-09 Varios recibos juntos', async ({ page }) => {
    await guestPay(page);
    await lookUpBill(page, 'ICE', digits(9), 'Electricidad');
    await page.getByRole('button', { name: 'Agregar al carrito' }).click();
    await lookUpBill(page, 'CNFL', digits(9));
    await page.getByRole('button', { name: 'Agregar y pagar todo' }).click();
    await expect(page.locator('.bundle-checkout .stub-label')).toContainText('2 recibos');
    await page.locator('#bundle-email').fill('invitado@example.com');
    await page.getByRole('button', { name: 'Ingresar tarjeta y pagar' }).click();
    await fillOnvoCard(page);
    await page.locator('.secure-foot .pay-button').click();
    await expect(page.locator('.paid-body h2')).toHaveText('Listo, tus 2 recibos quedaron aplicados', { timeout: 90000 });
    await expect(page.locator('.bundle-items .movement-status')).toHaveText(['Aplicado', 'Aplicado']);
});

test('ORIA-10 Sin cuenta', async ({ page }) => {
    await guestPay(page);
    await lookUpBill(page, 'ICE', digits(9), 'Electricidad');
    await page.locator('#receipt-email').fill('carlos@correo');
    await page.getByRole('button', { name: 'Ingresar tarjeta y pagar' }).click();
    await expect(page.locator('.field-error')).toContainText('Revisa el correo');
    await page.locator('#receipt-email').fill('carlos@correo.com');
    await page.getByRole('button', { name: 'Ingresar tarjeta y pagar' }).click();
    await fillOnvoCard(page);
    await page.locator('.secure-foot .pay-button').click();
    await expect(page.locator('.paid-body h2')).toHaveText(PAID, { timeout: 90000 });
});

test('ORIA-11 Tarjeta guardada', async ({ page }) => {
    const { token } = await register('tarjeta');
    // Three bills: the first payment takes one and saves the card; "Pagar varios" needs two left for the second.
    for (const [provider, service, id] of [['ice', 'electricity', digits(9)], ['aya', 'water', digits(7)], ['ice', 'electricity', digits(9)]]) {
        await api('/favorites', { method: 'POST', token, body: { providerCode: provider, serviceCode: service, serviceIdentifier: id } });
    }
    await oriaSignIn(page, token, '#favoritos');
    await page.getByRole('button', { name: 'Pagar varios' }).click();
    for (const tile of [2, 1]) await page.locator('.favorite-tile.picked').nth(tile).locator('.bundle-pick').click();
    await page.locator('.bundle-bar .primary').click();
    await page.getByRole('button', { name: 'Ingresar tarjeta y pagar' }).click();
    await page.locator('.save-card-toggle').click();
    await fillOnvoCard(page);
    await page.locator('.secure-foot .pay-button').click();
    await expect(page.locator('.bundle-items .movement-status')).toHaveText(['Aplicado'], { timeout: 90000 });

    await page.goto(`${ORIA}/#medios`);
    await page.reload({ waitUntil: 'networkidle' });
    await expect(page.locator('main')).toContainText('4242');

    await page.goto(`${ORIA}/#favoritos`);
    await page.reload({ waitUntil: 'networkidle' });
    await page.getByRole('button', { name: 'Pagar varios' }).click();
    await page.locator('.bundle-bar .primary').click();
    await expect(page.locator('.bundle-checkout .saved-card-option')).toContainText('4242');
    await page.locator('.bundle-checkout .pay-button').click();
    await expect(page.locator('.bundle-checkout.is-paid')).toBeVisible({ timeout: 90000 });
});

test('ORIA-12 Verificar comprobante', async ({ page }) => {
    await page.goto(`${ORIA}/verificar/oria_demo_ice_electricity_5`);
    await expect(page.locator('.verify-kicker')).toHaveText('PAGO VERIFICADO');
    await page.goto(`${ORIA}/verificar/oria_demo_review`);
    await expect(page.locator('.verify-kicker')).toHaveText('PAGO RECIBIDO · EN CONFIRMACIÓN');
    await page.goto(`${ORIA}/verificar/oria_inventado`);
    await expect(page.locator('.verify-kicker')).toHaveText('NO VERIFICADO');
});

// --- Historial y organización ---

test('ORIA-13 Movimientos', async ({ page }) => {
    const token = await oriaToken('demo@oria.test');
    const { json } = await api('/orders', { token });
    const orders = Array.isArray(json) ? json : json.data;
    await oriaSignIn(page, token, '#movimientos');
    await expect(page.locator('.movement-receipt').first()).toBeVisible();
    expect(await page.locator('.movement-receipt').count()).toBeGreaterThanOrEqual(Math.min(orders.length, 10));
    await expect(page.locator('main')).toContainText('En revisión');
    await expect(page.locator('main')).toContainText(/Rechazad|Fallid|No se cobró/);
});

test('ORIA-14 Gasto', async ({ page }) => {
    const token = await oriaToken('demo@oria.test');
    const spending = (await api('/spending', { token })).json;
    test.info().attach('gasto', { body: JSON.stringify(spending, null, 2) });
    expect(spending).toBeTruthy();
    await oriaSignIn(page, token, '');
    await expect(page.locator('main')).toContainText('₡');
});

test('ORIA-15 Favoritos y recordatorios', async ({ page }) => {
    const { token } = await register('favoritos');
    const created = (await api('/favorites', { method: 'POST', token, body: { providerCode: 'ice', serviceCode: 'electricity', serviceIdentifier: digits(9), nickname: 'Casa playa' } })).json;
    const favorite = created.data ?? created;
    await oriaSignIn(page, token, '#favoritos');
    await expect(page.locator('main')).toContainText('Casa playa');

    expect((await api(`/favorites/${favorite.id}`, { method: 'PATCH', token, body: { nickname: 'Casa montaña', remind: true, remindDaysBefore: 3 } })).status).toBe(200);
    await page.reload({ waitUntil: 'networkidle' });
    await expect(page.locator('main')).toContainText('Casa montaña');

    expect((await api(`/favorites/${favorite.id}`, { method: 'DELETE', token })).status).toBeLessThan(300);
    await page.reload({ waitUntil: 'networkidle' });
    await expect(page.locator('main')).not.toContainText('Casa montaña');
});

test('ORIA-16 Guardados para después', async ({ page }) => {
    await guestPay(page);
    await lookUpBill(page, 'ICE', digits(9), 'Electricidad');
    await page.getByRole('button', { name: 'Agregar al carrito' }).click();
    await lookUpBill(page, 'AyA', digits(7));
    await page.getByRole('button', { name: 'Agregar y seguir' }).click();
    await page.getByRole('button', { name: 'Guardar para después' }).click();
    await page.getByRole('tab', { name: 'Crear cuenta' }).click();
    await page.locator('.auth-panel input[autocomplete="name"]').fill('Emma Guarda');
    await page.locator('.auth-panel input[type=email]').fill(`guardar+${Date.now()}@example.com`);
    await page.locator('#auth-password').fill(PASSWORD);
    await page.locator('.auth-submit').click();
    await expect(page.locator('.app-toast')).toContainText('Guardamos 2 recibos para después');
    await expect(page.locator('.saved-panel li')).toHaveCount(2);
    await page.locator('.saved-panel li').first().getByRole('button', { name: 'Al carrito' }).click();
    await expect(page.locator('.cart-panel li')).toHaveCount(1);
    await expect(page.locator('.saved-panel li')).toHaveCount(1);
});

test('ORIA-17 Notificaciones', async ({ page }) => {
    const token = await oriaToken('demo@oria.test');
    await oriaSignIn(page, token, '');
    await expect(page.locator('.bell-count')).toBeVisible();
    await page.locator('.bell').click();
    await page.getByRole('button', { name: 'Marcar todo como leído' }).click();
    await expect(page.locator('.bell-count')).toHaveCount(0);
    const { json } = await api('/notifications', { token });
    test.info().attach('notificaciones', { body: JSON.stringify(json).slice(0, 2000) });
    expect(JSON.stringify(json)).not.toMatch(/"readAt":null|"read_at":null/);
});

test('ORIA-18 Tema oscuro', async ({ browser }) => {
    const context = await browser.newContext({ colorScheme: 'light' });
    const page = await context.newPage();
    const theme = () => page.evaluate(() => document.documentElement.dataset.theme);
    await oriaSignIn(page, await oriaToken('demo@oria.test'), '#movimientos');
    expect(await theme()).toBe('light');
    await page.getByRole('button', { name: 'Usar modo oscuro' }).click();
    expect(await theme()).toBe('dark');
    await page.reload({ waitUntil: 'networkidle' });
    expect(await theme()).toBe('dark');
    await context.close();
});
