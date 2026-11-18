import { execFileSync } from 'node:child_process';

// Datos limpios en cada corrida: BotPedidos con pedidos de muestra y Oria con sus cuentas demo.
export default function globalSetup() {
    if (process.env.SHOWCASE_KEEP_DATA === '1') {
        return;
    }

    execFileSync(process.execPath, ['scripts/showcase.mjs', 'prepare'], { stdio: 'inherit' });
}
