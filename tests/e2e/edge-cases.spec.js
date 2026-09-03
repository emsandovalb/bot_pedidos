import { test, expect } from '@playwright/test';

/**
 * Pruebas de casos borde / robustez.
 *
 * No dependen de datos generados por el developer toolkit: usan sólo los
 * usuarios sembrados por DatabaseSeeder (owner@local.test / viewer@local.test,
 * contraseña "password"). Cada test comprueba que la app degrada con gracia
 * (sin 500, sin fuga de stack trace, con redirección/az 404 correctos).
 */

const PROTECTED_ROUTES = ['/dashboard', '/operations', '/orders', '/order-reviews', '/products', '/customers', '/channels', '/analytics'];

async function login(page, email = 'owner@local.test') {
    await page.goto('/login');
    await page.locator('#email').fill(email);
    await page.locator('#password').fill('password');
    await page.getByRole('button', { name: 'Entrar' }).click();
    await expect(page).toHaveURL(/\/dashboard/);
}

test.describe('auth: rutas protegidas', () => {
    for (const route of PROTECTED_ROUTES) {
        test(`invitado en ${route} -> redirige a /login`, async ({ page }) => {
            const response = await page.goto(route, { waitUntil: 'domcontentloaded' });
            expect(response.status(), `${route} no debe devolver 5xx`).toBeLessThan(500);
            await expect(page).toHaveURL(/\/login/);
        });
    }
});

test.describe('auth: login', () => {
    test('contraseña incorrecta no crea sesión y muestra error', async ({ page }) => {
        await page.goto('/login');
        await page.locator('#email').fill('owner@local.test');
        await page.locator('#password').fill('contraseña-mala');
        await page.getByRole('button', { name: 'Entrar' }).click();

        await expect(page).toHaveURL(/\/login/);
        await expect(page.locator('body')).toContainText(/credenciales|credentials|incorrect|no coinciden/i);

        // La sesión sigue cerrada: /dashboard redirige.
        const check = await page.request.get('/dashboard', { maxRedirects: 0 });
        expect(check.status()).toBe(302);
    });

    test('rate limiting tras varios intentos fallidos', async ({ page }) => {
        // Email desechable: el throttle de Breeze es por email+IP, así no se
        // bloquea la cuenta real que usan los otros tests.
        const victim = `throttle-${Date.now()}@local.test`;
        for (let i = 0; i < 7; i++) {
            await page.goto('/login');
            await page.locator('#email').fill(victim);
            await page.locator('#password').fill(`mala-${i}`);
            await page.getByRole('button', { name: 'Entrar' }).click();
            await page.waitForLoadState('networkidle');
        }
        await expect(page.locator('body')).toContainText(/demasiados|too many|throttle|segundos|seconds/i);
    });
});

test.describe('webhooks: entradas hostiles', () => {
    test('proveedor desconocido -> 404', async ({ request }) => {
        const res = await request.get('/webhooks/proveedor-inexistente', { failOnStatusCode: false });
        expect(res.status()).toBe(404);
    });

    test('handshake con verify_token incorrecto no devuelve el challenge', async ({ request }) => {
        const res = await request.get('/webhooks/whatsapp', {
            params: { 'hub.mode': 'subscribe', 'hub.verify_token': 'token-invalido', 'hub.challenge': 'ECHO_ME_123' },
            failOnStatusCode: false,
        });
        expect(res.status()).not.toBe(200);
        expect((await res.text()).trim()).not.toBe('ECHO_ME_123');
    });

    test('POST sin firma X-Hub-Signature-256 se rechaza sin crash', async ({ request }) => {
        const res = await request.post('/webhooks/whatsapp', {
            headers: { 'Content-Type': 'application/json' },
            data: { object: 'whatsapp_business_account', entry: [{ id: '0', changes: [{ field: 'messages', value: { messages: [{ from: '5215555', id: 'wamid.TEST', type: 'text', text: { body: 'hola' } }] } }] }] },
            failOnStatusCode: false,
        });
        // No debe ser un 500 sin manejar ni filtrar stack trace. En un entorno sin
        // WHATSAPP_APP_SECRET configurado la app responde 503 con texto limpio
        // ("signature validation is not configured"); con secreto sería 403.
        expect(res.status()).not.toBe(500);
        expect([403, 401, 400, 503]).toContain(res.status());
        expect(await res.text()).not.toMatch(/Stack trace|vendor[\\/]laravel[\\/]framework/i);
    });

    test('POST con cuerpo basura no produce 500 ni stack trace', async ({ request }) => {
        const res = await request.post('/webhooks/whatsapp', {
            headers: { 'Content-Type': 'application/json' },
            data: '::: esto no es json valido :::',
            failOnStatusCode: false,
        });
        expect(res.status()).not.toBe(500);
        expect(await res.text()).not.toMatch(/Stack trace|vendor[\\/]laravel[\\/]framework/i);
    });
});

test.describe('robustez: recursos inexistentes (autenticado)', () => {
    test.beforeEach(async ({ page }) => login(page));

    test('/customers/<id-inexistente> -> 404, no 500', async ({ page }) => {
        const res = await page.goto('/customers/99999999', { waitUntil: 'domcontentloaded' });
        expect(res.status()).toBe(404);
        await expect(page.locator('body')).not.toContainText(/Stack trace|vendor\/laravel\/framework/i);
    });

    test('/operations/focus?order=<inexistente> no devuelve 500', async ({ page }) => {
        const res = await page.goto('/operations/focus?order=99999999', { waitUntil: 'domcontentloaded' });
        expect(res.status()).toBeLessThan(500);
    });

    test('URL desconocida -> 404', async ({ page }) => {
        const res = await page.goto('/ruta/que/no/existe', { waitUntil: 'domcontentloaded' });
        expect(res.status()).toBe(404);
    });

    test('healthcheck /up responde 200', async ({ request }) => {
        const res = await request.get('/up', { failOnStatusCode: false });
        expect(res.status()).toBe(200);
    });
});

test.describe('layout: sidebar', () => {
    // Regresión: <nav> del sidebar era flex-1 sin overflow -> en pantallas de
    // portátil (~768px) los últimos ítems y el botón "Cerrar sesión" quedaban
    // recortados y sin forma de hacer scroll.
    for (const vp of [{ w: 1280, h: 720 }, { w: 1440, h: 768 }]) {
        test(`el sidebar hace scroll y el pie es alcanzable @ ${vp.w}x${vp.h}`, async ({ page }) => {
            await page.setViewportSize({ width: vp.w, height: vp.h });
            await login(page);

            const nav = page.locator('aside nav');
            const metrics = await nav.evaluate((el) => ({
                overflowY: getComputedStyle(el).overflowY,
                overflows: el.scrollHeight > el.clientHeight,
            }));
            expect(metrics.overflowY).toBe('auto');

            // El pie con "Cerrar sesión" queda dentro del viewport (fijo abajo).
            const logout = page.getByRole('button', { name: 'Cerrar sesión' });
            const box = await logout.boundingBox();
            expect(box.y + box.height).toBeLessThanOrEqual(vp.h + 1);

            // Si hay overflow, el último ítem del nav se alcanza haciendo scroll.
            if (metrics.overflows) {
                await nav.evaluate((el) => el.scrollTo(0, el.scrollHeight));
                await expect(nav.locator('a').last()).toBeInViewport();
            }
        });
    }
});

test.describe('roles y estados vacíos', () => {
    test('el rol viewer puede abrir el dashboard', async ({ page }) => {
        await login(page, 'viewer@local.test');
        const res = await page.goto('/customers', { waitUntil: 'domcontentloaded' });
        expect(res.status()).toBe(200);
    });

    // Regresión: el filtro de búsqueda de /customers tipaba mal el closure de
    // orWhereExists (Eloquent\Builder en vez de Query\Builder) y CUALQUIER término
    // de búsqueda tiraba un TypeError -> HTTP 500. Ver CustomerController::index.
    test('búsqueda de cliente (con y sin coincidencias) no revienta', async ({ page }) => {
        await login(page);
        for (const term of ['Sample', '502555', 'zzz-cliente-que-no-existe-zzz']) {
            const res = await page.goto(`/customers?search=${encodeURIComponent(term)}`, { waitUntil: 'domcontentloaded' });
            expect(res.status(), `search=${term}`).toBe(200);
            await expect(page.locator('body')).not.toContainText(/Stack trace/i);
        }
    });
});
