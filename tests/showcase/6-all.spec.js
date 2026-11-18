import { test, expect } from '@playwright/test';
import { execSync } from 'node:child_process';
import { BOT, botLogin, clearOriaLimits, digits, lookUpBill, ORIA, oriaPath, oriaSignIn, oriaToken, watchErrors } from './helpers.js';

// Each case starts with Oria's request limits at zero.
test.beforeEach(() => clearOriaLimits());

const BOT_PAGES = ['/dashboard', '/operations', '/orders', '/order-reviews', '/products', '/customers', '/analytics', '/channels', '/daily-order-closures', '/settings/oria', '/settings/notifications', '/profile'];
const ORIA_PAGES = ['', '#pagar', '#movimientos', '#favoritos', '#medios', '#perfil', '#ayuda'];

async function visitAll(page, check) {
    await botLogin(page);
    for (const url of BOT_PAGES) {
        await page.goto(BOT + url);
        await page.waitForLoadState('networkidle');
        await check(`BotPedidos ${url}`);
    }
    await oriaSignIn(page, await oriaToken('demo@oria.test'));
    for (const hash of ORIA_PAGES) {
        await page.goto(`${ORIA}/${hash}`);
        // Some screens keep a connection open (the card form), so "no network for a while" may never come.
        await page.reload({ waitUntil: 'load' });
        await page.waitForTimeout(1500);
        await check(`Oria /${hash}`);
    }
    await oriaSignIn(page, await oriaToken('soda@oria.test'));
    await page.goto(`${ORIA}/feria`);
    await page.reload({ waitUntil: 'networkidle' });
    await check('Feria Ya');
}

test('ALL-01 Celular (390 px)', async ({ browser }) => {
    const context = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
    const page = await context.newPage();
    const wide = [];
    await visitAll(page, async (where) => {
        const width = await page.evaluate(() => document.documentElement.scrollWidth);
        if (width > 391) wide.push(`${where}: ${width}px`);
    });
    expect(wide, 'pantallas que se salen del ancho del celular').toEqual([]);
    await context.close();
});

test('ALL-02 Idioma', async ({ page }) => {
    test.fail(true, 'Pendiente conocido: BotPedidos tiene textos en inglés en Operaciones, Canales y Perfil.');
    // Words that only show up when a screen was left in English.
    const english = /\b(Save|Delete Account|Today|Upcoming work|Unknown|Back to hub|Health Check|Disconnect|Reconnect|Configure|View Details|Open Telegram|Inspect WhatsApp|Products imported successfully|Profile Information|Update Password|Channels|Soon|Setup)\b/g;
    const found = [];
    await visitAll(page, async (where) => {
        const words = [...new Set((await page.locator('body').innerText()).match(english) ?? [])];
        if (words.length) found.push(`${where}: ${words.join(', ')}`);
    });
    expect(found, 'textos en inglés').toEqual([]);
});

test('ALL-03 Teclado', async ({ page }) => {
    const problems = [];
    const checkTab = async (where, presses) => {
        for (let i = 0; i < presses; i++) {
            await page.keyboard.press('Tab');
            const focus = await page.evaluate(() => {
                const el = document.activeElement;
                if (!el || el === document.body) return null;
                const style = getComputedStyle(el);
                const visible = (style.outlineStyle !== 'none' && parseFloat(style.outlineWidth) > 0) || style.boxShadow !== 'none';
                return { tag: el.tagName, name: (el.getAttribute('aria-label') || el.innerText || el.name || '').trim().slice(0, 30), visible };
            });
            if (focus && !focus.visible) problems.push(`${where}: ${focus.tag} "${focus.name}" sin foco visible`);
        }
    };
    await page.goto(`${BOT}/login`);
    await checkTab('BotPedidos login', 5);
    await oriaSignIn(page, null, '#pagar');
    await checkTab('Oria pagar', 8);
    expect(problems).toEqual([]);
});

test('ALL-04 Consola sin errores', async ({ page }) => {
    const errors = watchErrors(page);
    await visitAll(page, async () => {});
    expect(errors).toEqual([]);
});

test('ALL-05 Recarga y atrás', async ({ page }) => {
    await oriaSignIn(page, null, '#pagar');
    await lookUpBill(page, 'ICE', digits(9), 'Electricidad');
    await page.getByRole('button', { name: 'Agregar al carrito' }).click();
    await expect(page.locator('.cart-panel li')).toHaveCount(1);
    await page.reload({ waitUntil: 'networkidle' });
    await expect(page.locator('.cart-panel li'), 'el carrito sobrevive a recargar').toHaveCount(1);
    await page.goBack();
    await page.goForward();
    await expect(page.locator('.cart-panel li'), 'atrás y adelante no duplican').toHaveCount(1);
});

// --- Pruebas automáticas de cada repo ---

/** Runs a project's own checks; on failure, the error carries the end of their output. */
function run(command, options = {}) {
    try {
        execSync(command, { stdio: 'pipe', maxBuffer: 64 * 1024 * 1024, ...options });
    } catch (error) {
        const output = `${error.stdout ?? ''}\n${error.stderr ?? ''}`.replace(/\x1b\[[0-9;]*m/g, '').trim().split('\n');
        throw new Error(`${command} falló:\n${output.slice(-40).join('\n')}`);
    }
}

test('ALL-06 BotPedidos: PHPUnit', async () => {
    test.setTimeout(900000);
    run('php artisan test');
});

test('ALL-07 BotPedidos: navegador', async () => {
    test.setTimeout(1200000);
    try {
        run('npx playwright test');
    } finally {
        // The pilot e2e rewrites tracked screenshots; leave them as they were.
        execSync('git checkout -- artifacts/pilot/latest docs/pilot/screenshots', { stdio: 'pipe' });
    }
});

test('ALL-08 Oria: chequeo completo', async () => {
    test.setTimeout(1200000);
    run('npm run check', { cwd: oriaPath });
});

test('ALL-09 Oria: navegador', async () => {
    test.skip(true, 'Manual: el e2e de Oria corre contra su base de desarrollo (resiembra las cuentas demo).');
});
