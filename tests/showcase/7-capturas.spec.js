import { test } from '@playwright/test';
import { BOT, ORIA, botLogin, clearOriaLimits, oriaSignIn, oriaToken } from './helpers.js';

// "¿Se ve bien?" stays a person's call: each run leaves a picture of every screen, on a computer and on a
// phone, in artifacts/showcase/capturas, to look through after the automatic checks.
const DIR = 'artifacts/showcase/capturas';
const SIZES = { computadora: { width: 1440, height: 900 }, celular: { width: 390, height: 844 } };

const BOT_PAGES = {
    'pedidos-inicio': '/dashboard',
    'pedidos-operaciones': '/operations',
    'pedidos-lista': '/orders',
    'pedidos-revision': '/order-reviews',
    'pedidos-productos': '/products',
    'pedidos-clientes': '/customers',
    'pedidos-analitica': '/analytics',
    'pedidos-canales': '/channels',
    'pedidos-cierres': '/daily-order-closures',
    'pedidos-oria': '/settings/oria',
    'pedidos-notificaciones': '/settings/notifications',
    'pedidos-perfil': '/profile',
};
const ORIA_PAGES = {
    'oria-inicio': '',
    'oria-pagar': '#pagar',
    'oria-movimientos': '#movimientos',
    'oria-favoritos': '#favoritos',
    'oria-medios': '#medios',
    'oria-perfil': '#perfil',
    'oria-ayuda': '#ayuda',
};

for (const [size, viewport] of Object.entries(SIZES)) {
    test.describe(`Capturas (${size})`, () => {
        test.use({ viewport, isMobile: size === 'celular', hasTouch: size === 'celular' });
        test.beforeEach(() => clearOriaLimits());

        test(`CAP ${size}: BotPedidos`, async ({ page }) => {
            await page.goto(`${BOT}/login`);
            await page.screenshot({ path: `${DIR}/${size}/pedidos-login.png`, fullPage: true });
            await botLogin(page);
            for (const [name, url] of Object.entries(BOT_PAGES)) {
                await page.goto(BOT + url);
                await page.waitForLoadState('networkidle');
                await page.screenshot({ path: `${DIR}/${size}/${name}.png`, fullPage: true });
            }
        });

        test(`CAP ${size}: Oria`, async ({ page }) => {
            await oriaSignIn(page, await oriaToken('demo@oria.test'));
            for (const [name, hash] of Object.entries(ORIA_PAGES)) {
                await page.goto(`${ORIA}/${hash}`);
                await page.reload({ waitUntil: 'load' });
                await page.waitForTimeout(1500);
                await page.screenshot({ path: `${DIR}/${size}/${name}.png`, fullPage: true });
            }
            await oriaSignIn(page, await oriaToken('soporte@oria.test'), '#soporte');
            await page.waitForTimeout(1500);
            await page.screenshot({ path: `${DIR}/${size}/oria-soporte.png`, fullPage: true });

            await oriaSignIn(page, await oriaToken('soda@oria.test'));
            await page.goto(`${ORIA}/feria`);
            await page.reload({ waitUntil: 'load' });
            await page.waitForTimeout(1500);
            const start = page.locator('.feria-start');
            if (await start.isVisible()) await start.click();
            await page.screenshot({ path: `${DIR}/${size}/feria-cobrar.png`, fullPage: true });
        });
    });
}
