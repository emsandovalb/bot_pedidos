# Despliegue del piloto (BotPedidos) — Render

Piloto de captura de pedidos por **Telegram** + revisión humana.
WhatsApp e IA están fuera del piloto.

## Arquitectura en Render

| Servicio Render | Qué es | Comando |
|---|---|---|
| `botpedidos-db` | PostgreSQL 16 gestionado | — |
| `botpedidos-web` | App (Docker: nginx + php-fpm) | (imagen) |
| `botpedidos-queue` | Worker de la cola — procesa los mensajes entrantes (parser + pedido) | `php artisan queue:work` |
| `botpedidos-telegram` | Poller del bot de Telegram | `php artisan telegram:poll --loop` |
| `botpedidos-scheduler` | Scheduler (sin tareas aún, dejado listo) | `php artisan schedule:work` |

Todo está declarado en [`render.yaml`](../render.yaml) (Blueprint). SQLite **no** se usa
en producción: el worker + el poller + la web escriben a la vez.

## Pasos

1. **Push** de la rama con `Dockerfile`, `render.yaml`, `.dockerignore`.
2. En Render: **New + → Blueprint**, seleccionar el repo. Crea la BD y los 4 servicios.
3. Rellenar los secretos (marcados `sync: false` en el blueprint), **los mismos en la web
   y en el env-var-group `botpedidos-shared`**:
   - `APP_KEY` → generar en local con `php artisan key:generate --show` y pegar
   - `APP_URL` → `https://botpedidos-web.onrender.com` (o tu dominio)
   - `TELEGRAM_BOT_TOKEN` → de @BotFather
   - `TELEGRAM_DEFAULT_BRANCH_ID` → id de la sucursal por defecto (se sabe tras el paso 5)
   - `WHATSAPP_APP_SECRET` → vacío por ahora
4. El primer deploy corre `preDeployCommand`: `migrate --force` + `config/route/view:cache`.
5. **Sembrar datos** (una vez, desde el Shell del servicio `botpedidos-web`):
   ```bash
   php artisan db:seed --class=PilotSeeder     # org + sucursales + catálogo demo
   ```
   Anotar el `id` de la sucursal de Telegram → ponerlo en `TELEGRAM_DEFAULT_BRANCH_ID`
   (web + grupo compartido) y redeploy.
   Luego cargar el **catálogo real** del negocio por la importación masiva de la UI
   (`nombre | SKU | unidad | alias 1, alias 2`).

## Variables de entorno clave

Definidas en `render.yaml`; las de producción que difieren de `.env.example`:

```
APP_ENV=production
APP_DEBUG=false
SESSION_SECURE_COOKIE=true
DB_CONNECTION=pgsql            # host/credenciales inyectados desde la BD de Render
QUEUE_CONNECTION=database
MESSAGING_INBOUND_QUEUE=true   # webhook encola el procesamiento; requiere el worker
```

Regla: **ningún `env()` fuera de `config/`** (rompe con `config:cache`). Auditar:
```bash
grep -rn "env(" app/ routes/ | grep -v "config/"
```

## Ingesta asíncrona (P0 #4 — hecho)

El webhook entrante (`/webhooks/whatsapp`) ahora **persiste el mensaje y encola** el
parser + creación de pedido en el job `App\Jobs\ProcessInboundMessage`, devolviendo 200
de inmediato. Requiere `botpedidos-queue` corriendo.

- `MESSAGING_INBOUND_QUEUE=false` → procesamiento síncrono (si no quieres worker).
- El **poller de Telegram** procesa síncrono a propósito (necesita el resultado para
  responderle al cliente al momento, y no está bajo timeout HTTP).
- El developer toolkit fuerza modo síncrono para ver resultados al instante.

## Post-deploy: smoke test

1. `GET https://.../up` → 200
2. Login con el owner del piloto (`PILOT_OWNER_EMAIL` en `config/pilot.php`)
3. Mandar un mensaje real al bot de Telegram → en unos segundos aparece en
   `/order-reviews` como `pending_review`
4. Confirmar → el estado avanza; revisar el historial
5. Logs de los 4 servicios sin errores

## Operación

- **Backups**: Render Postgres hace backups automáticos en planes de pago (verificar retención).
- **Healthcheck**: `botpedidos-web` apunta a `/health` (BD + métricas de ingesta; ver abajo).
- **Errores**: Sentry ya integrado (ver sección siguiente).
- **Roles**: crear los usuarios del negocio con el rol correcto
  (`owner`/`admin` todo, `seller` su sucursal, `viewer` solo lectura — ya lo gatea `OrderPolicy`).

## Error tracking con Sentry (P1 — hecho)

`sentry/sentry-laravel` instalado y cableado en [bootstrap/app.php](../bootstrap/app.php)
(`Integration::handles($exceptions)`), cubre web, el worker de la cola
(`ProcessInboundMessage`) y `telegram:poll` — los tres comparten el mismo bootstrap.

- Sin `SENTRY_LARAVEL_DSN` (dev/tests) es un no-op: no manda nada, no rompe nada.
- Para activarlo: crear proyecto en sentry.io → pegar el DSN en `SENTRY_LARAVEL_DSN`
  (en la web **y** en el grupo `botpedidos-shared` de `render.yaml`).
- `SENTRY_TRACES_SAMPLE_RATE=0.1` — 10% de las requests con tracing de performance;
  súbelo o bájalo según volumen. `send_default_pii` queda en `false` (no manda datos
  de clientes a Sentry).
- Config completa en `config/sentry.php` (publicada desde el paquete, valores por defecto sensatos).

## Precios y total del pedido (P1 — hecho)

- El catálogo tiene campo `price` (opcional). La ingesta captura el precio unitario
  del producto en cada línea (`order_items.unit_price` / `line_total`) y suma el
  total en `orders.items_total`. Editar cantidades en la revisión recalcula el total.
- Importación masiva: `nombre | sku | unidad | precio | alias` (5 campos = con precio;
  4 campos sigue funcionando sin precio). Reimportar sin precio no borra el existente.
- Cuando el catálogo ya tenga precios, poner `PILOT_ENFORCE_PRICING=true` para que
  no se pueda confirmar un pedido con una línea de catálogo sin precio.

## Pendiente antes de abrir al negocio (ver PILOT_READINESS.md)

- [ ] Catálogo real con **precios** y alias frecuentes (importación masiva)
- [ ] `PILOT_ENFORCE_PRICING=true` una vez cargados los precios
- [ ] Reglas de entrega / pago / pedido mínimo / horario límite
- [ ] 20 mensajes reales anonimizados como set de aceptación
- [ ] Pegar el DSN real de Sentry en producción (`SENTRY_LARAVEL_DSN`)

## Resolver ítem "sin producto" desde la revisión (P1 — hecho)

En `/orders/{id}/edit` cada línea tiene un selector de producto del catálogo.
Asignar un producto a un ítem no reconocido: setea `product_id`, re-deriva el
precio unitario del producto y recalcula el total. Si el revisor resuelve **todos**
los ítems de un pedido que estaba en `unmatched`, se quita el marcador de revisión
y el pedido ya se puede confirmar (la edición manual cuenta como revisión).
