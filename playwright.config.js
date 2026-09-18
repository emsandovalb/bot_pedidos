import { defineConfig } from '@playwright/test';
import { rmSync } from 'node:fs';
import path from 'node:path';

const port = process.env.PILOT_APP_PORT ?? '8010';
const baseURL = process.env.PLAYWRIGHT_BASE_URL ?? `http://127.0.0.1:${port}`;

// Causa real de los ERR_ABORTED/timeouts intermitentes: si `npm run dev` se
// mató a la fuerza (taskkill) alguna vez sin dejarlo cerrar solo, Vite no
// alcanza a borrar `public/hot`. Con ese archivo presente, @vite() en las
// vistas apunta TODOS los assets a http://[::1]:5173 (el dev server de Vite)
// aunque no esté corriendo -> JS que nunca carga -> páginas inestables.
// Se borra acá para que el e2e nunca dependa de si quedó un `npm run dev`
// colgado de una sesión anterior; `npm run build` (abajo) regenera el manifest.
rmSync(path.resolve('public/hot'), { force: true });

// Base de datos propia para el e2e: aislada del `database/database.sqlite`
// que se usa en desarrollo interactivo, para que una corrida no le vaya
// agregando datos a la BD de desarrollo ni arrastre estado de corridas previas.
const e2eDatabasePath = path.resolve('database/e2e.sqlite');

const e2eEnv = {
    // 'local' (no 'testing') para que el developer toolkit se comporte igual
    // que en desarrollo: sus rutas se gatean con app()->environment('local').
    APP_ENV: 'local',
    DB_CONNECTION: 'sqlite',
    DB_DATABASE: e2eDatabasePath,
    // El toolkit ya fuerza modo síncrono por su cuenta, pero por si se postea
    // un webhook real durante el e2e, que no dependa de un worker corriendo.
    MESSAGING_INBOUND_QUEUE: 'false',
    QUEUE_CONNECTION: 'sync',
    WHATSAPP_APP_SECRET: 'e2e-whatsapp-secret',
};

export default defineConfig({
    testDir: './tests/e2e',
    timeout: 120000,
    expect: {
        timeout: 15000,
    },
    fullyParallel: false,
    retries: 0,
    reporter: [
        ['list'],
        ['html', { outputFolder: 'playwright-report', open: 'never' }],
    ],
    use: {
        baseURL,
        headless: true,
        screenshot: 'only-on-failure',
        video: 'retain-on-failure',
        trace: 'retain-on-failure',
    },
    webServer: {
        // migrate:fresh dejan la BD del e2e limpia en cada corrida.
        command: `php artisan migrate:fresh --seed --force && npm run build && php artisan serve --host 127.0.0.1 --port ${port}`,
        url: baseURL,
        reuseExistingServer: false,
        timeout: 120000,
        env: e2eEnv,
    },
});
