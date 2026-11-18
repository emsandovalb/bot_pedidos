import { test, expect } from '@playwright/test';
import { BOT, acceptDialogs, botLogin, botSql, csrf, digits, latestOrderFor, orderAction, sendMessage, unique, watchErrors } from './helpers.js';

test.beforeEach(({ page }) => acceptDialogs(page));

/** A rule the app doesn't meet yet: reported as expected to fail, and red the day it passes (remove the mark then). */
function pending(what) {
    test.fail(true, `Pendiente conocido: ${what}.`);
}

// --- Acceso y permisos ---

test('BOT-01 Iniciar y cerrar sesión', async ({ page }) => {
    await page.goto(`${BOT}/login`);
    await page.locator('#email').fill('owner@local.test');
    await page.locator('#password').fill('clave-mala');
    await page.getByRole('button', { name: 'Entrar' }).click();
    await expect(page).toHaveURL(/\/login/);
    await expect(page.locator('body')).toContainText(/credenciales|incorrect|no coinciden|These credentials/i);

    await botLogin(page);
    await page.getByRole('button', { name: 'Cerrar sesión' }).click();
    await expect(page).toHaveURL(/\/login/);
});

test('BOT-02 Vendedor no ve configuración', async ({ page }) => {
    await botLogin(page, 'seller-1@local.test');
    for (const url of ['/settings/oria', '/channels/whatsapp']) {
        const response = await page.goto(BOT + url);
        expect(response.status(), url).toBe(403);
    }
    await page.goto(`${BOT}/dashboard`);
    await expect(page.locator('aside, nav').getByRole('link', { name: 'Oria', exact: true })).toHaveCount(0);
    await expect(page.locator('aside, nav').getByRole('link', { name: 'Notificaciones' })).toHaveCount(0);
});

test('BOT-03 Viewer es solo lectura', async ({ page }) => {
    pending('el usuario de solo lectura ve "Confirmar pedido" y "Editar pedido"');
    const order = botSql("select id from orders where status = 'pending_review' order by id limit 1")[0];
    await botLogin(page, 'viewer@local.test');
    const response = await page.goto(`${BOT}/orders/${order.id}`);
    expect(response.status()).toBe(200);
    // The server refusing is checked first, so the rule on the buttons can't hide a hole in the permissions.
    const attempt = await page.request.post(`${BOT}/orders/${order.id}/confirm`, { headers: { 'X-CSRF-TOKEN': await csrf(page) } });
    expect(attempt.status()).toBe(403);
    expect(botSql('select status from orders where id = ?', [order.id])[0].status).toBe('pending_review');
    await page.goto(`${BOT}/orders/${order.id}`);
    await expect(page.getByRole('button', { name: 'Confirmar pedido' }), 'no ofrece Confirmar').toHaveCount(0);
    await expect(page.getByRole('link', { name: 'Editar pedido' }), 'no ofrece Editar').toHaveCount(0);
});

test('BOT-04 Vendedor ve solo su sucursal', async ({ page }) => {
    const other = botSql('select id from orders where branch_id is not null and branch_id != 1 limit 1')[0];
    const own = botSql('select count(*) as n from orders where branch_id = 1')[0].n;
    await botLogin(page, 'seller-1@local.test');
    await page.goto(`${BOT}/orders`);
    await expect(page.getByRole('link', { name: 'Ver pedido' })).toHaveCount(own);
    if (other) {
        const response = await page.goto(`${BOT}/orders/${other.id}`);
        expect([403, 404]).toContain(response.status());
    }
});

test('BOT-05 Perfil', async ({ page }) => {
    const name = unique('Admin Auditoría');
    await botLogin(page, 'admin@local.test');
    await page.goto(`${BOT}/profile`);
    const info = page.locator('form').filter({ has: page.locator('#name') });
    await page.locator('#name').fill(name);
    await info.locator('button[type=submit], button:not([type])').first().click();
    await page.goto(`${BOT}/dashboard`);
    await expect(page.locator('body')).toContainText(name);

    await page.goto(`${BOT}/profile`);
    const passwordForm = page.locator('form').filter({ has: page.locator('#update_password_password') });
    await page.locator('#update_password_current_password').fill('password');
    await page.locator('#update_password_password').fill('Nueva-clave-123');
    await page.locator('#update_password_password_confirmation').fill('Nueva-clave-123');
    await passwordForm.locator('button[type=submit], button:not([type])').first().click();
    await page.waitForLoadState('networkidle');
    await page.getByRole('button', { name: 'Cerrar sesión' }).click();
    await botLogin(page, 'admin@local.test', 'Nueva-clave-123');

    // Back to the seeded password, so the next run starts the same.
    await page.goto(`${BOT}/profile`);
    await page.locator('#update_password_current_password').fill('Nueva-clave-123');
    await page.locator('#update_password_password').fill('password');
    await page.locator('#update_password_password_confirmation').fill('password');
    await passwordForm.locator('button[type=submit], button:not([type])').first().click();
    await page.waitForLoadState('networkidle');
});

// --- Entrada de pedidos ---

test('BOT-06 Mensaje simulado crea un pedido', async ({ page }) => {
    pending('el parser vuelve productos "Hola" y "me los llevan", y no reconoce "pinturas blancas"');
    await botLogin(page);
    // Messages as customers write them. Rules: greetings and delivery phrases ("Hola", "me los llevan") are not
    // products, and plurals or capitals still find the product ("2 pinturas blancas" is "Pintura blanca").
    const expected = {
        'Hola, ocupo 2 tubos pvc y 3 sacos de cemento para mañana': { 'Tubos PVC': 2, 'Sacos de cemento': 3 },
        'Ocupo 4 bloques de concreto para hoy, me los llevan': { 'Bloques de concreto': 4 },
        'Quiero 2 pinturas blancas': { 'Pintura blanca': 2 },
    };
    const found = {};
    for (const message of Object.keys(expected)) {
        const { phone } = await sendMessage(page, message);
        const order = latestOrderFor(phone);
        expect(order, `"${message}" creó un pedido`).toBeTruthy();
        const lines = botSql(
            "select coalesce(p.name, '(sin reconocer) ' || i.raw_text) as name, i.quantity from order_items i left join products p on p.id = i.product_id where i.order_id = ?",
            [order.id],
        );
        found[message] = Object.fromEntries(lines.map((line) => [line.name, Number(line.quantity)]));
    }
    expect(found, 'cada mensaje da exactamente sus productos').toEqual(expected);

    await page.goto(`${BOT}/incoming-messages`);
    await expect(page.locator('body')).toContainText('tubos pvc');
});

test('BOT-07 Producto desconocido', async ({ page }) => {
    await botLogin(page);
    const { phone } = await sendMessage(page, 'Buenas, me manda 4 cajas de unicornios morados');
    const order = latestOrderFor(phone);
    expect(order, 'se creó un pedido').toBeTruthy();
    expect(order.status).toBe('pending_review');
    expect(botSql('select count(*) as n from order_items where order_id = ? and product_id is null', [order.id])[0].n).toBeGreaterThan(0);
});

test('BOT-08 Pedido duplicado', async ({ page }) => {
    await botLogin(page);
    const phone = `5025${digits(7)}`;
    const message = 'Ocupo 10 bloques de concreto para hoy, paso a recogerlos';
    await sendMessage(page, message, { phone, name: 'Cliente Duplicado' });
    await sendMessage(page, message, { phone, name: 'Cliente Duplicado' });
    const second = latestOrderFor(phone);
    expect(second.possible_duplicate_of_order_id ?? second.duplicate_reason, 'el segundo queda marcado').toBeTruthy();
});

test('BOT-09 Telegram (emulador)', async ({ page }) => {
    // The Developer Toolkit builds the same update Telegram sends to the webhook and processes it.
    await botLogin(page);
    const text = `Buenas, me manda 3 sacos de cemento (${digits(6)})`;
    const response = await page.request.post(`${BOT}/developer/webhook-simulator/send`, {
        headers: { 'X-CSRF-TOKEN': await csrf(page) },
        form: { provider: 'telegram', payload_source: 'fields', customer_name: 'Cliente Telegram', customer_phone: `7${digits(8)}`, message_id: digits(9), message_text: text },
    });
    expect(response.ok()).toBeTruthy();
    const message = botSql("select * from incoming_messages where provider = 'telegram' and raw_text = ? order by id desc limit 1", [text])[0];
    expect(message, 'el mensaje entró por Telegram').toBeTruthy();
    expect(message.order_id, 'creó un pedido').toBeTruthy();
    expect(botSql('select source_channel from orders where id = ?', [message.order_id])[0].source_channel).toBe('telegram');
});

// --- Revisión y flujo del pedido ---

test('BOT-10 Editar y resolver línea', async ({ page }) => {
    await botLogin(page);
    const { phone } = await sendMessage(page, 'Me manda 3 cajas de tornillos raros');
    const order = latestOrderFor(phone);
    const item = botSql('select id from order_items where order_id = ? and product_id is null limit 1', [order.id])[0];
    const product = botSql("select id, price from products where name = 'Tubos PVC'")[0];

    await page.goto(`${BOT}/orders/${order.id}/edit`);
    await page.locator(`#item-${item.id}-product`).selectOption(String(product.id));
    await page.locator(`#item-${item.id}-quantity`).fill('5');
    await page.getByRole('button', { name: 'Guardar cambios' }).click();
    await page.waitForLoadState('networkidle');

    const saved = botSql('select product_id, quantity, line_total from order_items where id = ?', [item.id])[0];
    expect(saved.product_id).toBe(product.id);
    expect(Number(saved.quantity)).toBe(5);
    if (product.price) expect(Number(saved.line_total)).toBeCloseTo(Number(product.price) * 5, 2);
    await page.goto(`${BOT}/orders/${order.id}`);
    await expect(page.getByRole('button', { name: 'Confirmar pedido' })).toBeVisible();
});

test('BOT-11 Confirmar → preparar → listo → despachar', async ({ page }) => {
    await botLogin(page);
    const { phone } = await sendMessage(page, 'Quiero 3 sacos de cemento');
    const order = latestOrderFor(phone);
    expect(order.status).toBe('pending_review');
    for (const action of ['confirm', 'prepare', 'ready-for-dispatch', 'dispatch']) {
        await orderAction(page, order.id, action);
    }
    expect(botSql('select status from orders where id = ?', [order.id])[0].status).toBe('dispatched');
    const history = botSql('select to_status, changed_by_user_id, created_at from order_status_histories where order_id = ? order by id', [order.id]);
    expect(history.map((h) => h.to_status)).toEqual(expect.arrayContaining(['confirmed', 'preparing', 'ready_for_dispatch', 'dispatched']));
    expect(history.filter((h) => h.to_status !== 'pending_review').every((h) => h.changed_by_user_id && h.created_at)).toBe(true);
});

test('BOT-12 Rechazar y cancelar', async ({ page }) => {
    await botLogin(page);
    const first = await sendMessage(page, 'Quiero 2 pinturas blancas');
    const second = await sendMessage(page, 'Quiero 3 sacos de cemento');
    const rejected = latestOrderFor(first.phone);
    const cancelled = latestOrderFor(second.phone);

    await orderAction(page, rejected.id, 'reject');
    await orderAction(page, cancelled.id, 'confirm');
    await orderAction(page, cancelled.id, 'cancel');
    expect(botSql('select status from orders where id = ?', [rejected.id])[0].status).toBe('rejected');
    expect(botSql('select status from orders where id = ?', [cancelled.id])[0].status).toBe('cancelled');

    await page.goto(`${BOT}/orders?status=pending_review`);
    await expect(page.locator(`a[href$="/orders/${rejected.id}"]`)).toHaveCount(0);
});

test('BOT-13 Filtros de Pedidos', async ({ page }) => {
    await botLogin(page);
    for (const status of ['pending_review', 'confirmed', 'dispatched']) {
        const errors = watchErrors(page);
        await page.goto(`${BOT}/orders?status=${status}`);
        const expected = botSql('select count(*) as n from orders where status = ?', [status])[0].n;
        await expect(page.getByRole('link', { name: 'Ver pedido' }), status).toHaveCount(Math.min(expected, 15));
        expect(errors).toEqual([]);
    }
});

// --- Operaciones ---

test('BOT-14 Vista de hoy', async ({ page }) => {
    pending('Operaciones tiene textos en inglés y tarjetas con "Sin productos visibles"');
    await botLogin(page);
    await page.goto(`${BOT}/operations`);
    const text = await page.locator('main').innerText();
    const english = ['Today', 'Upcoming work', 'Maximum 5 cards', 'Unknown', 'Good Afternoon', 'Good Morning', 'Completed today', 'Secondary toolbar']
        .filter((word) => text.includes(word));
    const withoutProducts = (text.match(/Sin productos visibles/g) ?? []).length;
    expect.soft(english, 'textos en inglés').toEqual([]);
    expect(withoutProducts, 'tarjetas con "Sin productos visibles"').toBe(0);
});

test('BOT-15 Botón Preparar desde la tarjeta', async ({ page }) => {
    pending('el botón de las tarjetas dice "Prepare" (en inglés) y en un pedido por revisar lo confirma');
    await botLogin(page);
    await page.goto(`${BOT}/operations`);
    // Rule: each card's button says in Spanish what it does ("Confirmar", "Preparar", "Listo", "Despachar").
    const english = page.getByRole('button', { name: /^(Confirm|Prepare|Ready|Dispatch|Complete)$/ });
    expect(await english.count(), 'botones de tarjeta en inglés').toBe(0);
    const before = Object.fromEntries(botSql('select id, status from orders').map((o) => [o.id, o.status]));
    await page.getByRole('button', { name: /^Preparar$/ }).first().click();
    let changed = [];
    await expect.poll(() => {
        changed = botSql('select id, status from orders').filter((o) => before[o.id] !== o.status).map((o) => ({ from: before[o.id], to: o.status }));
        return changed.length;
    }, { timeout: 15000 }).toBe(1);
    expect(changed[0], '"Preparar" deja el pedido en preparación').toEqual({ from: 'confirmed', to: 'preparing' });
});

test('BOT-16 Modo simple / foco', async ({ page }) => {
    await botLogin(page);
    await page.goto(`${BOT}/operations/focus`);
    await page.getByRole('button', { name: 'Revisar pedido' }).first().click();
    await expect(page.locator('[data-focus-inline-workspace]')).toBeVisible();
    await expect(page.locator('[data-focus-inline-review]')).toBeVisible();
});

test('BOT-17 Actualización en vivo', async ({ page, context }) => {
    await botLogin(page);
    await page.goto(`${BOT}/operations`);
    const other = await context.newPage();
    await other.goto(`${BOT}/dashboard`);
    const name = unique('Cliente En Vivo');
    await sendMessage(other, 'Ocupo 4 bloques de concreto para hoy a las 5 pm, me los llevan', { name });
    await expect(page.locator('main')).toContainText(name, { timeout: 45000 });
});

// --- Catálogo, clientes y cierres ---

test('BOT-18 Crear y editar producto', async ({ page }) => {
    const name = unique('Martillo Auditoría');
    const alias = `martillito ${digits(3)}`;
    await botLogin(page);
    await page.goto(`${BOT}/products/create`);
    await page.locator('#name').fill(name);
    await page.locator('#sku').fill(unique('AUD'));
    await page.locator('#unit_label').fill('unidad');
    await page.locator('#price').fill('4500');
    await page.getByRole('button', { name: 'Crear producto' }).click();
    await page.waitForLoadState('networkidle');
    const product = botSql('select id from products where name = ?', [name])[0];
    expect(product, 'producto creado').toBeTruthy();

    await page.goto(`${BOT}/products/${product.id}/edit`);
    const aliasForm = page.locator(`form[action$="/products/${product.id}/aliases"]`);
    await aliasForm.locator('input[type=text], input:not([type])').first().fill(alias);
    await aliasForm.locator('button').first().click();
    await page.waitForLoadState('networkidle');

    let { phone } = await sendMessage(page, `Me manda 2 ${alias}`);
    expect(botSql('select product_id from order_items where order_id = ?', [latestOrderFor(phone).id]).map((i) => i.product_id)).toContain(product.id);

    await page.goto(`${BOT}/products`);
    await page.locator(`form[action$="/products/${product.id}/toggle"] button`).click();
    await page.waitForLoadState('networkidle');
    ({ phone } = await sendMessage(page, `Me manda 2 ${alias}`));
    expect(botSql('select product_id from order_items where order_id = ?', [latestOrderFor(phone).id]).map((i) => i.product_id)).not.toContain(product.id);
});

test('BOT-19 Importación masiva', async ({ page }) => {
    const sku = unique('IMP');
    await botLogin(page);
    await page.goto(`${BOT}/products/import`);
    await page.locator('#content').fill(`Tornillo importado | ${sku} | caja | 1500 | tornillito, tornillos\n | SIN-NOMBRE | caja`);
    await page.getByRole('button', { name: 'Importar' }).click();
    await page.waitForLoadState('networkidle');
    expect(botSql('select price from products where sku = ?', [sku])[0]?.price, 'producto importado').toBeTruthy();
    // The line without a name is skipped and counted, without stopping the import.
    await expect(page.locator('main')).toContainText(/(Skipped lines|Líneas omitidas)\s*1/);
});

test('BOT-20 Clientes', async ({ page }) => {
    await botLogin(page);
    await page.goto(`${BOT}/customers`);
    await page.getByRole('link', { name: 'Ver cliente' }).first().click();
    const id = Number(page.url().split('/').pop());
    const customer = botSql('select name from customers where id = ?', [id])[0];
    await expect(page.locator('main')).toContainText(customer.name);
    const orders = botSql('select count(*) as n from orders where customer_id = ?', [id])[0].n;
    if (orders) await expect(page.locator('main a[href*="/orders/"]').first()).toBeVisible();
});

test('BOT-21 Cierre diario', async ({ page }) => {
    await botLogin(page);
    await page.goto(`${BOT}/daily-order-closures/create`);
    await page.locator('#branch_id').selectOption({ index: 1 });
    await page.locator('#notes').fill('Cierre de la auditoría automática');
    await page.getByRole('button', { name: 'Crear cierre' }).click();
    await expect(page).toHaveURL(/daily-order-closures\/\d+$/);
    const download = page.waitForEvent('download');
    await page.locator('a[href$="/export"]').first().click();
    expect((await download).suggestedFilename()).toMatch(/\.(csv|xlsx)$/);
});

test('BOT-22 Analítica', async ({ page }) => {
    const errors = watchErrors(page);
    await botLogin(page);
    await page.goto(`${BOT}/analytics`);
    await page.waitForLoadState('networkidle');
    await expect(page.locator('main')).toContainText(/\d/);
    expect(errors).toEqual([]);
});

// --- Canales y configuración ---

test('BOT-23 Hub de canales', async ({ page }) => {
    await botLogin(page);
    await page.goto(`${BOT}/channels`);
    for (const channel of ['WhatsApp', 'Telegram', 'Instagram']) {
        await expect(page.locator('main')).toContainText(channel);
    }
});

test('BOT-24 Configurar WhatsApp', async ({ page }) => {
    const secret = `secreto-${digits(8)}`;
    // Test credentials leave the channel as a draft, and then simulated messages make no orders: put it back after.
    const original = botSql("select * from channel_connections where channel = 'whatsapp'");
    test.info().attach('canal original', { body: JSON.stringify(original) });
    await botLogin(page);
    await page.goto(`${BOT}/channels/whatsapp/configuration`);
    const form = page.locator('form[action$="/channels/whatsapp/configuration"]');
    await form.locator('[name=provider_app_id]').fill('123456789012345');
    await form.locator('[name=provider_app_secret]').fill(secret);
    await form.locator('[name=provider_phone_number_id]').fill('109876543210987');
    await form.locator('[name=provider_access_token]').fill(`token-${secret}`);
    await form.locator('button[type=submit], button:not([type])').last().click();
    await page.waitForLoadState('networkidle');
    await page.goto(`${BOT}/channels/whatsapp/configuration`);
    await expect(page.locator('[name=provider_phone_number_id]')).toHaveValue('109876543210987');
    expect(await page.content(), 'no muestra secretos').not.toContain(secret);

    for (const row of original) {
        const columns = Object.keys(row).filter((column) => column !== 'id');
        botSql(`update channel_connections set ${columns.map((c) => `${c} = ?`).join(', ')} where id = ?`, [...columns.map((c) => row[c]), row.id]);
    }
});

test('BOT-25 Notificaciones', async ({ page }) => {
    await botLogin(page);
    await page.goto(`${BOT}/settings/notifications`);
    const box = page.locator('input[type=checkbox][name="settings[telegram][order_confirmed][is_enabled]"]');
    const before = await box.isChecked();
    await box.setChecked(!before);
    await page.getByRole('button', { name: 'Guardar configuración' }).click();
    await page.waitForLoadState('networkidle');
    await page.reload();
    await expect(box).toBeChecked({ checked: !before });
});
