# BotPedidos

BotPedidos es un producto de captura de pedidos por Telegram con revision operativa, catalogo de productos y despacho.

## Que hace

- Recibe pedidos automaticos desde Telegram.
- Guarda el mensaje original sin perder contexto.
- Muestra pedidos para revision, confirmacion y despacho.
- Administra productos y sus alias de coincidencia.
- Mantiene cierres diarios por sucursal.

## Flujo principal

1. El cliente envia un mensaje por Telegram.
2. Si `ORDER_INGESTION_ENABLED=true`, el sistema crea una orden.
3. El pedido entra a `pending_review`.
4. El equipo revisa, confirma y avanza el pedido a `preparing`.
5. Luego pasa a `ready_for_dispatch` y `dispatched`.

## Pantallas principales

- `/orders`
- `/order-reviews`
- `/products`
- `/dashboard`
- `/profile`

## Rutas legacy de loteria

Las rutas legacy de loteria estan deshabilitadas por defecto y no forman parte de la superficie oficial de BotPedidos.

Si necesitas migracion o debug puntual, puedes habilitarlas temporalmente con:

```bash
LEGACY_LOTTERY_ROUTES_ENABLED=true
```

Las rutas oficiales del producto siguen siendo `/orders`, `/order-reviews` y `/products`.

## Telegram

### Variables de entorno

- `TELEGRAM_ENABLED=true`
- `ORDER_INGESTION_ENABLED=true`
- `TELEGRAM_BOT_TOKEN=...`
- `TELEGRAM_DEFAULT_BRANCH_ID=...`
- `TELEGRAM_VERIFY_SSL=true`

### Prueba local

Procesa una pasada del bot:

```bash
php artisan telegram:poll
```

Procesa en bucle para pruebas locales:

```bash
php artisan telegram:poll --loop --sleep=3
```

Mensajes de ejemplo para probar el bot:

- `2 bolsas de jardin`
- `1 caja de vasos`
- `5 bolsas de apretados`

## Instalacion local

```bash
composer install
npm install
php artisan key:generate
php artisan migrate
```

## Desarrollo

```bash
php artisan serve
npm run dev
```

## Notas

- El flujo normal usa Telegram como canal de entrada.
- La ingestion legacy sigue disponible solo como seguridad temporal mientras se limpia el sistema.
- Los mensajes entrantes no deben descartarse silenciosamente.
