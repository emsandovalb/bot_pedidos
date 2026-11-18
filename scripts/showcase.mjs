// Entorno local de muestra: BotPedidos + Oria (API y web) con datos demo, conectados entre sí.
// Usa bases SQLite propias (database/showcase.sqlite en cada app), así que no toca las de desarrollo.
//
//   node scripts/showcase.mjs            reinicia los datos y levanta las tres apps (Ctrl+C las detiene)
//   node scripts/showcase.mjs prepare    solo reinicia los datos
//   node scripts/showcase.mjs serve      levanta las apps con los datos que ya hay
//   node scripts/showcase.mjs oria-api   levanta solo la API de Oria (la auditoría la apaga y la vuelve a levantar)
//
// ORIA_PATH apunta al repo de Oria si no está en la ruta de abajo.
import { execFileSync, execSync, spawn } from 'node:child_process';
import { createHash } from 'node:crypto';
import { existsSync, writeFileSync } from 'node:fs';
import path from 'node:path';

const botPath = process.cwd();
const oriaPath = process.env.ORIA_PATH ?? 'C:/Users/Ema/Downloads/services-payment-prototype/services-payment-prototype';
const oriaApiPath = path.join(oriaPath, 'apps', 'api');
const oriaWebPath = path.join(oriaPath, 'apps', 'web');

const botUrl = 'http://127.0.0.1:8020';
const oriaApiUrl = 'http://127.0.0.1:8100';
const oriaWebUrl = 'http://127.0.0.1:3100';

// Llave de socio solo para este entorno local; Oria guarda su SHA-256.
const partnerKey = 'showcase-local-partner-key';

const botEnv = {
    ...process.env,
    APP_ENV: 'local',
    APP_URL: botUrl,
    DB_CONNECTION: 'sqlite',
    DB_DATABASE: path.join(botPath, 'database', 'showcase.sqlite'),
    QUEUE_CONNECTION: 'sync',
    MESSAGING_INBOUND_QUEUE: 'false',
    ORIA_API_URL: `${oriaApiUrl}/api`,
    ORIA_PARTNER_KEY: partnerKey,
};

const oriaApiEnv = {
    ...process.env,
    APP_ENV: 'local',
    APP_URL: oriaApiUrl,
    FRONTEND_URL: oriaWebUrl,
    DB_CONNECTION: 'sqlite',
    DB_DATABASE: path.join(oriaApiPath, 'database', 'showcase.sqlite'),
    PARTNER_BOT_PEDIDOS_KEY_HASH: createHash('sha256').update(partnerKey).digest('hex'),
    PARTNER_BOT_PEDIDOS_RETURN_URLS: `${botUrl}/settings/oria/callback`,
};

const oriaWebEnv = {
    ...process.env,
    NUXT_PUBLIC_API_BASE: `${oriaApiUrl}/api`,
};

const mode = process.argv[2] ?? 'all';

if (!['all', 'prepare', 'serve', 'oria-api'].includes(mode)) {
    console.error(`Modo desconocido: ${mode}`);
    process.exit(1);
}

if (mode === 'all' || mode === 'prepare') {
    prepare();
}

if (mode !== 'prepare') {
    serve(mode === 'oria-api' ? ['oria-api'] : ['bot_pedidos', 'oria-api', 'oria-web'], { detached: mode === 'oria-api' });
}

function prepare() {
    for (const env of [botEnv, oriaApiEnv]) {
        if (!existsSync(env.DB_DATABASE)) {
            writeFileSync(env.DB_DATABASE, '');
        }
    }

    artisan(['migrate:fresh', '--seed', '--force'], botPath, botEnv);
    // Pedidos de muestra en todos los estados (los mismos que genera el Developer Toolkit).
    artisan(['tinker', '--execute', String.raw`
        $o = App\Models\Organization::first();
        app(App\Services\Developer\ScenarioGeneratorService::class)->generateSmallHardwareStoreScenario($o);
        app(App\Services\Developer\BusinessScenarioService::class)->simulateBusinessDay($o);
    `], botPath, botEnv);
    artisan(['migrate:fresh', '--force'], oriaApiPath, oriaApiEnv);
    artisan(['db:seed', '--class=DemoSeeder', '--force'], oriaApiPath, oriaApiEnv);

    if (!existsSync(path.join(botPath, 'public', 'build', 'manifest.json'))) {
        run('npm run build', botPath, botEnv);
    }
}

/**
 * Starts the apps and shows their output. `detached` (the oria-api mode, which the audit uses to bring Oria's
 * API back after stopping it) leaves the server running on its own and returns at once.
 */
function serve(apps, { detached = false } = {}) {
    // `artisan serve` no pasa al proceso hijo las variables de entorno, por eso se usa el servidor de PHP directo,
    // desde `public` como espera su router.
    const php = (appPath, port, env, name) => start(name, 'php', [
        '-S', `127.0.0.1:${port}`,
        path.join(appPath, 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php'),
    ], path.join(appPath, 'public'), env, detached);
    const starters = {
        'bot_pedidos': () => php(botPath, 8020, botEnv, 'bot_pedidos'),
        'oria-api': () => php(oriaApiPath, 8100, oriaApiEnv, 'oria-api'),
        'oria-web': () => start('oria-web', process.execPath, ['node_modules/nuxt/bin/nuxt.mjs', 'dev', '--host', '127.0.0.1', '--port', '3100'], oriaWebPath, oriaWebEnv),
    };
    const processes = apps.map((app) => starters[app]());
    if (detached) {
        return;
    }

    console.log(`
BotPedidos  ${botUrl}      owner@local.test / password
Oria        ${oriaWebUrl}      demo@oria.test / secreta123 (soporte@oria.test para cambiar el plan)
Oria API    ${oriaApiUrl}
`);

    const stop = () => {
        processes.forEach((child) => child.kill());
        process.exit(0);
    };
    process.on('SIGINT', stop);
    process.on('SIGTERM', stop);
}

function artisan(args, cwd, env) {
    console.log(`> php artisan ${args[0]}  (${path.basename(cwd)})`);
    execFileSync('php', ['artisan', ...args], { cwd, env, stdio: ['ignore', 'ignore', 'inherit'] });
}

function run(command, cwd, env) {
    console.log(`> ${command}  (${path.basename(cwd)})`);
    execSync(command, { cwd, env, stdio: 'inherit' });
}

function start(name, command, args, cwd, env, detached = false) {
    if (detached) {
        spawn(command, args, { cwd, env, detached: true, stdio: 'ignore', windowsHide: true }).unref();

        return null;
    }
    const child = spawn(command, args, { cwd, env });
    const prefix = (chunk) => chunk.toString().trimEnd().split('\n').map((line) => `[${name}] ${line}`).join('\n');
    child.stdout.on('data', (chunk) => console.log(prefix(chunk)));
    child.stderr.on('data', (chunk) => console.log(prefix(chunk)));
    child.on('exit', (code) => console.log(`[${name}] terminó (${code})`));

    return child;
}
