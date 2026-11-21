import { expect } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { readFileSync, existsSync, statSync } from 'node:fs';
import path from 'node:path';

export const BOT = 'http://127.0.0.1:8020';
export const ORIA = 'http://127.0.0.1:3100';
export const API = 'http://127.0.0.1:8100/api';
export const PASSWORD = 'secreta123';

export const oriaPath = process.env.ORIA_PATH ?? 'C:/Users/Ema/Downloads/services-payment-prototype/services-payment-prototype';
export const oriaApiPath = path.join(oriaPath, 'apps', 'api');
const botDatabase = path.resolve('database', 'showcase.sqlite');
const oriaMailLog = path.join(oriaApiPath, 'storage', 'logs', 'mail.log');

/** A random string of digits, so each run looks up a bill nobody paid yet this month. */
export const digits = (length) => Array.from({ length }, (_, i) => (i === 0 ? 1 + Math.floor(Math.random() * 9) : Math.floor(Math.random() * 10))).join('');
export const rejectedNise = () => `99999${digits(3)}1`;
export const unique = (prefix) => `${prefix}-${Date.now().toString(36)}`;

const oriaDatabase = path.join(oriaApiPath, 'database', 'showcase.sqlite');

function sqlite(database, sql, params) {
    const script = '$db = new PDO("sqlite:" . $argv[1]); $q = $db->prepare($argv[2]); $q->execute(json_decode($argv[3], true)); echo json_encode($q->fetchAll(PDO::FETCH_ASSOC));';
    return JSON.parse(execFileSync('php', ['-r', script, database, sql, JSON.stringify(params)], { encoding: 'utf-8' }) || '[]');
}

/** Rows from BotPedidos' showcase database, to check what the screens can't show directly. */
export function botSql(sql, params = []) {
    return sqlite(botDatabase, sql, params);
}

/**
 * Oria counts requests per minute in its cache (the showcase database). Each case starts with the
 * counters at zero, so one case's sign-ins don't block the next one.
 */
export function clearOriaLimits() {
    sqlite(oriaDatabase, 'delete from cache', []);
}

/** The plan is read again every 10 minutes; tests clear the mark so the next page asks Oria now. */
export function forgetOriaPlanCheck() {
    botSql('update oria_connections set plan_checked_at = null');
}

// --- BotPedidos ---

export async function botLogin(page, email = 'owner@local.test', password = 'password') {
    await page.goto(`${BOT}/login`);
    await page.locator('#email').fill(email);
    await page.locator('#password').fill(password);
    await page.getByRole('button', { name: 'Entrar' }).click();
    await expect(page).toHaveURL(/\/dashboard/);
}

export async function csrf(page) {
    return page.locator('meta[name="csrf-token"]').getAttribute('content');
}

/** A customer message through the Developer Toolkit, processed right away like a real WhatsApp message. */
export async function sendMessage(page, message, { name = 'Cliente Auditoría', phone = `5025${digits(7)}` } = {}) {
    const response = await page.request.post(`${BOT}/developer/webhook-simulator/generate`, {
        headers: { 'X-CSRF-TOKEN': await csrf(page), 'X-Requested-With': 'XMLHttpRequest' },
        form: { action: 'business_custom_message', provider: 'whatsapp', customer_mode: 'new', customer_name: name, customer_phone: phone, message },
    });
    expect(response.ok()).toBeTruthy();

    return { name, phone };
}

/** The newest order of a customer, by the phone used in sendMessage. */
export function latestOrderFor(phone) {
    return botSql(
        `select o.* from orders o join customers c on c.id = o.customer_id
         where c.phone like ? order by o.id desc limit 1`,
        [`%${phone}%`],
    )[0];
}

/** Submits the order action form (confirm, prepare…) of an order's page, filling any reason it asks for. */
export async function orderAction(page, orderId, action) {
    await page.goto(`${BOT}/orders/${orderId}`);
    const form = page.locator(`form[action$="/orders/${orderId}/${action}"]`).first();
    await expect(form).toHaveCount(1);
    for (const field of await form.locator('textarea:visible, input[type=text]:visible').all()) {
        await field.fill('Prueba automática');
    }
    for (const select of await form.locator('select:visible').all()) {
        const value = await select.locator('option:not([value=""])').first().getAttribute('value');
        if (value) await select.selectOption(value);
    }
    await form.locator('button[type=submit], button:not([type])').first().click();
    await page.waitForLoadState('networkidle');
}

// --- Oria ---

const tokens = new Map();

/**
 * A call to Oria's API. Oria limits requests per minute; when a limit is hit the call waits
 * what Oria asks (Retry-After) and tries again, since the limit is not what the cases check.
 */
export async function api(pathname, { method = 'GET', body, token } = {}) {
    let response;
    for (let attempt = 0; attempt < 4; attempt++) {
        response = await fetch(API + pathname, {
            method,
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', ...(token ? { Authorization: `Bearer ${token}` } : {}) },
            body: body ? JSON.stringify(body) : undefined,
        });
        if (response.status !== 429) break;
        await new Promise((resolve) => setTimeout(resolve, (Number(response.headers.get('retry-after')) || 30) * 1000 + 500));
    }
    const text = await response.text();
    let json = null;
    try { json = text ? JSON.parse(text) : null; } catch { json = text; }

    return { status: response.status, json, headers: response.headers };
}

/**
 * An API token for a demo account. Login is rate limited, so the token is made straight in Oria's showcase database;
 * the login itself is tested on its own (ORIA-02).
 */
export async function oriaToken(email) {
    if (!tokens.has(email)) {
        tokens.set(email, newOriaToken(email));
    }

    return tokens.get(email);
}

/** A token of its own, for a case that signs out (signing out revokes the token the other cases share). */
export function newOriaToken(email) {
    const token = execFileSync('php', ['artisan', 'tinker', '--execute',
        `echo App\\Models\\User::where('email', '${email}')->firstOrFail()->createToken('auditoria')->plainTextToken;`,
    ], { cwd: oriaApiPath, encoding: 'utf-8', env: { ...process.env, DB_CONNECTION: 'sqlite', DB_DATABASE: oriaDatabase } }).trim().split('\n').pop();
    expect(token, `token de ${email}`).toMatch(/^\d+\|/);

    return token;
}

export async function register(prefix = 'auditoria') {
    const email = `${prefix}+${Date.now()}${digits(3)}@example.com`;
    const { status, json } = await api('/auth/register', { method: 'POST', body: { name: 'Emma Prueba', email, password: PASSWORD } });
    expect(status).toBe(201);

    return { email, token: json.token };
}

/** Signs Oria's web in with a token (faster than the dialog). */
export async function oriaSignIn(page, token, hash = '') {
    await page.goto(`${ORIA}/`);
    await page.evaluate((t) => { localStorage.clear(); sessionStorage.clear(); if (t) localStorage.setItem('oria.token', t); }, token);
    await page.goto(`${ORIA}/${hash}`);
    await page.reload({ waitUntil: 'networkidle' });
}

/** Walks the pay flow up to the bill. Providers with one service skip the service step. */
export async function lookUpBill(page, provider, identifier, service) {
    await page.locator('.provider', { hasText: provider }).click();
    await page.getByRole('button', { name: 'Continuar →' }).click();
    if (service) {
        await page.locator('.product', { hasText: service }).click();
        await page.getByRole('button', { name: 'Continuar →' }).click();
    }
    await page.locator('.input-label input').fill(identifier);
    await page.getByRole('button', { name: /Consultar factura/ }).click();
    await expect(page.locator('.pay-body')).toBeVisible();
}

/** Fills ONVO's test card form (test mode: needs internet). */
export async function fillOnvoCard(page, number = '4242424242424242') {
    const iframe = page.locator('.secure-sheet iframe:visible, .feria-card-pay iframe:visible, iframe[src*="onvo"]:visible').first();
    await expect(iframe).toBeVisible({ timeout: 30000 });
    await page.waitForTimeout(2500);
    const frame = await (await iframe.elementHandle()).contentFrame();
    for (const [name, value] of [['cardholderName', 'Emma Prueba'], ['cardNumber', number], ['expDate', '1230'], ['code', '123']]) {
        const field = frame.locator(`input[name="${name}"], input#${name}`).first();
        await field.click();
        await field.pressSequentially(value, { delay: 30 });
    }
}

/** Mail Oria wrote to its log (MAIL_MAILER=log) after a given size of the file. */
export function mailLogSize() {
    return existsSync(oriaMailLog) ? statSync(oriaMailLog).size : 0;
}

export function mailSince(offset) {
    if (!existsSync(oriaMailLog)) return '';
    return readFileSync(oriaMailLog).subarray(offset).toString('utf-8').replace(/=\r?\n/g, '').replace(/=3D/g, '=');
}

/** Stops Oria's API (port 8100) to see how BotPedidos behaves while Oria is down. */
export function stopOriaApi() {
    execFileSync('powershell', ['-NoProfile', '-Command',
        'Get-NetTCPConnection -LocalPort 8100 -State Listen -ErrorAction SilentlyContinue | ForEach-Object { Stop-Process -Id $_.OwningProcess -Force }']);
}

export async function startOriaApi() {
    execFileSync(process.execPath, ['scripts/showcase.mjs', 'oria-api'], { stdio: 'ignore' });
    await expect.poll(async () => (await fetch(`${API.replace('/api', '')}/up`).catch(() => null))?.status ?? 0, { timeout: 30000 }).toBe(200);
}

// --- Both ---

/** Console errors, uncaught errors and 5xx answers seen by a page. */
export function watchErrors(page) {
    const errors = [];
    page.on('pageerror', (error) => errors.push(`pageerror ${page.url()}: ${error.message}`));
    page.on('console', (message) => {
        if (message.type() === 'error') errors.push(`console ${page.url()}: ${message.text().slice(0, 200)}`);
    });
    page.on('response', (response) => {
        if (response.status() >= 500) errors.push(`HTTP ${response.status()} ${response.url()}`);
    });

    return errors;
}

export function acceptDialogs(page) {
    page.on('dialog', (dialog) => dialog.accept());
}
