# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: operations-focus.spec.js >> operations focus mode guides one order through the full workflow
- Location: tests\e2e\operations-focus.spec.js:14:1

# Error details

```
Error: page.goto: net::ERR_ABORTED at http://127.0.0.1:8010/operations/focus?order=1220
Call log:
  - navigating to "http://127.0.0.1:8010/operations/focus?order=1220", waiting until "load"

```

# Page snapshot

```yaml
- generic [ref=f2e4]:
  - complementary [ref=f2e5]:
    - link [ref=f2e8] [cursor=pointer]:
      - /url: http://127.0.0.1:8010/dashboard
      - img "Benditio" [ref=f2e10]
    - navigation [ref=f2e11]:
      - link "Panel" [ref=f2e12] [cursor=pointer]:
        - /url: http://127.0.0.1:8010/dashboard
      - link "Analítica" [ref=f2e17] [cursor=pointer]:
        - /url: http://127.0.0.1:8010/analytics
      - link "Operaciones" [ref=f2e21] [cursor=pointer]:
        - /url: http://127.0.0.1:8010/operations
      - link "Modo simple" [ref=f2e25] [cursor=pointer]:
        - /url: http://127.0.0.1:8010/operations/focus
      - link "Pedidos" [ref=f2e29] [cursor=pointer]:
        - /url: http://127.0.0.1:8010/orders
      - link "Revisión de pedidos" [ref=f2e33] [cursor=pointer]:
        - /url: http://127.0.0.1:8010/order-reviews
      - link "Clientes" [ref=f2e37] [cursor=pointer]:
        - /url: http://127.0.0.1:8010/customers
      - link "Catálogo de productos" [ref=f2e41] [cursor=pointer]:
        - /url: http://127.0.0.1:8010/products
      - link "Sucursales" [ref=f2e45] [cursor=pointer]:
        - /url: http://127.0.0.1:8010/branches
      - link "Cierres diarios" [ref=f2e49] [cursor=pointer]:
        - /url: http://127.0.0.1:8010/daily-order-closures
      - link "Bandeja de mensajes" [ref=f2e53] [cursor=pointer]:
        - /url: http://127.0.0.1:8010/incoming-messages
      - generic [ref=f2e57]:
        - generic [ref=f2e58]: Channels
        - generic [ref=f2e59]:
          - link "WhatsApp Setup" [ref=f2e60] [cursor=pointer]:
            - /url: http://127.0.0.1:8010/channels/whatsapp
            - generic [ref=f2e63]: WhatsApp
            - generic [ref=f2e64]: Setup
          - link "Telegram" [ref=f2e65] [cursor=pointer]:
            - /url: http://127.0.0.1:8010/channels/telegram
          - link "Instagram Soon" [ref=f2e69] [cursor=pointer]:
            - /url: http://127.0.0.1:8010/channels/instagram
            - generic [ref=f2e72]: Instagram
            - generic [ref=f2e73]: Soon
      - generic [ref=f2e74]:
        - generic [ref=f2e75]: Configuracion
        - link "Notificaciones" [ref=f2e77] [cursor=pointer]:
          - /url: http://127.0.0.1:8010/settings/notifications
      - generic [ref=f2e81]:
        - generic [ref=f2e82]: Soporte
        - link "Configuraciones" [ref=f2e84] [cursor=pointer]:
          - /url: http://127.0.0.1:8010/setup-requests
      - generic [ref=f2e88]:
        - generic [ref=f2e89]: Developer
        - link "Developer Toolkit" [ref=f2e91] [cursor=pointer]:
          - /url: http://127.0.0.1:8010/developer/webhook-simulator
    - generic [ref=f2e96]:
      - generic [ref=f2e97]: Owner User
      - generic [ref=f2e98]: owner@local.test
      - generic [ref=f2e99]:
        - generic [ref=f2e100]: Activo
        - generic [ref=f2e101]: owner
      - button "Cerrar sesión" [ref=f2e103] [cursor=pointer]
  - generic [ref=f2e104]:
    - banner [ref=f2e105]:
      - generic [ref=f2e106]:
        - generic [ref=f2e108]:
          - generic [ref=f2e109]: Owner User
          - generic [ref=f2e110]: owner
        - generic [ref=f2e111]:
          - generic [ref=f2e112]: Sep 03, 2026
          - link "Pedidos pendientes" [ref=f2e113] [cursor=pointer]:
            - /url: http://127.0.0.1:8010/operations
    - main [ref=f2e114]:
      - generic [ref=f2e116]:
        - generic [ref=f2e118]:
          - generic [ref=f2e119]:
            - generic [ref=f2e120]: Sistema operativo
            - heading "Benditio" [level=1] [ref=f2e121]
            - paragraph [ref=f2e122]: Convierte mensajes en ventas.
          - generic [ref=f2e123]:
            - link "Revisar pedidos" [ref=f2e124] [cursor=pointer]:
              - /url: http://127.0.0.1:8010/order-reviews
            - link "Cierres diarios" [ref=f2e125] [cursor=pointer]:
              - /url: http://127.0.0.1:8010/daily-order-closures
        - generic [ref=f2e126]:
          - 'link "Pedidos hoy 34 Pedidos creados durante el día #" [ref=f2e127] [cursor=pointer]':
            - /url: http://127.0.0.1:8010/orders?status=pending_review
            - generic [ref=f2e128]:
              - generic [ref=f2e129]:
                - generic [ref=f2e130]: Pedidos hoy
                - generic [ref=f2e131]: "34"
                - generic [ref=f2e132]: Pedidos creados durante el día
              - generic [ref=f2e133]: "#"
          - link "Pendientes de revisión 165 Pedidos listos para validar ?" [ref=f2e134] [cursor=pointer]:
            - /url: http://127.0.0.1:8010/orders?status=pending_review
            - generic [ref=f2e135]:
              - generic [ref=f2e136]:
                - generic [ref=f2e137]: Pendientes de revisión
                - generic [ref=f2e138]: "165"
                - generic [ref=f2e139]: Pedidos listos para validar
              - generic [ref=f2e140]: "?"
          - link "Confirmados 0 Pedidos confirmados en el flujo ✓" [ref=f2e141] [cursor=pointer]:
            - /url: http://127.0.0.1:8010/orders?status=confirmed
            - generic [ref=f2e142]:
              - generic [ref=f2e143]:
                - generic [ref=f2e144]: Confirmados
                - generic [ref=f2e145]: "0"
                - generic [ref=f2e146]: Pedidos confirmados en el flujo
              - generic [ref=f2e147]: ✓
          - link "En preparación 0 Pedidos que avanzan a cocina ~" [ref=f2e148] [cursor=pointer]:
            - /url: http://127.0.0.1:8010/orders?status=preparing
            - generic [ref=f2e149]:
              - generic [ref=f2e150]:
                - generic [ref=f2e151]: En preparación
                - generic [ref=f2e152]: "0"
                - generic [ref=f2e153]: Pedidos que avanzan a cocina
              - generic [ref=f2e154]: ~
          - link "Despachados 0 Salidas registradas hoy →" [ref=f2e155] [cursor=pointer]:
            - /url: http://127.0.0.1:8010/orders?status=dispatched
            - generic [ref=f2e156]:
              - generic [ref=f2e157]:
                - generic [ref=f2e158]: Despachados
                - generic [ref=f2e159]: "0"
                - generic [ref=f2e160]: Salidas registradas hoy
              - generic [ref=f2e161]: →
          - link "Configuraciones pendientes 0 Solicitudes de soporte abiertas !" [ref=f2e162] [cursor=pointer]:
            - /url: http://127.0.0.1:8010/setup-requests
            - generic [ref=f2e163]:
              - generic [ref=f2e164]:
                - generic [ref=f2e165]: Configuraciones pendientes
                - generic [ref=f2e166]: "0"
                - generic [ref=f2e167]: Solicitudes de soporte abiertas
              - generic [ref=f2e168]: "!"
        - generic [ref=f2e169]:
          - generic [ref=f2e170]:
            - generic [ref=f2e171]:
              - generic [ref=f2e172]:
                - heading "Pedidos recientes" [level=2] [ref=f2e173]
                - paragraph [ref=f2e174]: Últimos pedidos detectados en el sistema.
              - link "Ver todos" [ref=f2e175] [cursor=pointer]:
                - /url: http://127.0.0.1:8010/orders
            - generic [ref=f2e176]:
              - article [ref=f2e177]:
                - generic [ref=f2e178]:
                  - generic [ref=f2e179]:
                    - generic [ref=f2e180]:
                      - generic [ref=f2e181]: "#1220"
                      - generic [ref=f2e182]: Pendiente de revisión
                    - generic [ref=f2e183]:
                      - generic [ref=f2e184]: Cliente Focus
                      - generic [ref=f2e185]: 50255510050 · Central Branch
                    - paragraph [ref=f2e186]: Ocupo 20 bloques para hoy a las 2 pm, me lo llevan y pago por SINPE
                  - link "Abrir" [ref=f2e188] [cursor=pointer]:
                    - /url: http://127.0.0.1:8010/orders/1220
              - article [ref=f2e189]:
                - generic [ref=f2e190]:
                  - generic [ref=f2e191]:
                    - generic [ref=f2e192]:
                      - generic [ref=f2e193]: "#1219"
                      - generic [ref=f2e194]: Pendiente de revisión
                    - generic [ref=f2e195]:
                      - generic [ref=f2e196]: Maria Lopez
                      - generic [ref=f2e197]: 50255571234 · Central Branch
                    - paragraph [ref=f2e198]: Ocupo 20 bloques para hoy a las 2 pm, me lo llevan y pago por SINPE
                  - link "Abrir" [ref=f2e200] [cursor=pointer]:
                    - /url: http://127.0.0.1:8010/orders/1219
              - article [ref=f2e201]:
                - generic [ref=f2e202]:
                  - generic [ref=f2e203]:
                    - generic [ref=f2e204]:
                      - generic [ref=f2e205]: "#1218"
                      - generic [ref=f2e206]: Pendiente de revisión
                    - generic [ref=f2e207]:
                      - generic [ref=f2e208]: Maria Lopez
                      - generic [ref=f2e209]: 50255571234 · Central Branch
                    - paragraph [ref=f2e210]: Ocupo 20 bloques para hoy a las 2 pm, me lo llevan y pago por SINPE
                  - link "Abrir" [ref=f2e212] [cursor=pointer]:
                    - /url: http://127.0.0.1:8010/orders/1218
              - article [ref=f2e213]:
                - generic [ref=f2e214]:
                  - generic [ref=f2e215]:
                    - generic [ref=f2e216]:
                      - generic [ref=f2e217]: "#1217"
                      - generic [ref=f2e218]: Pendiente de revisión
                    - generic [ref=f2e219]:
                      - generic [ref=f2e220]: Maria Lopez
                      - generic [ref=f2e221]: 50255571234 · Central Branch
                    - paragraph [ref=f2e222]: Ocupo 20 bloques para hoy a las 2 pm, me lo llevan y pago por SINPE
                  - link "Abrir" [ref=f2e224] [cursor=pointer]:
                    - /url: http://127.0.0.1:8010/orders/1217
              - article [ref=f2e225]:
                - generic [ref=f2e226]:
                  - generic [ref=f2e227]:
                    - generic [ref=f2e228]:
                      - generic [ref=f2e229]: "#1216"
                      - generic [ref=f2e230]: Pendiente de revisión
                    - generic [ref=f2e231]:
                      - generic [ref=f2e232]: Maria Lopez
                      - generic [ref=f2e233]: 50255571234 · Central Branch
                    - paragraph [ref=f2e234]: Ocupo 20 bloques para hoy a las 2 pm, me lo llevan y pago por SINPE
                  - link "Abrir" [ref=f2e236] [cursor=pointer]:
                    - /url: http://127.0.0.1:8010/orders/1216
          - generic [ref=f2e237]:
            - generic [ref=f2e238]:
              - generic [ref=f2e239]:
                - heading "Productos activos y cierres" [level=2] [ref=f2e240]
                - paragraph [ref=f2e241]: Estado del catálogo y actividad de cierres.
              - link "Ir al catálogo" [ref=f2e242] [cursor=pointer]:
                - /url: http://127.0.0.1:8010/products
            - generic [ref=f2e243]:
              - generic [ref=f2e244]:
                - generic [ref=f2e245]: Productos activos
                - generic [ref=f2e246]: "9"
                - generic [ref=f2e247]: Catálogo disponible para venta
              - generic [ref=f2e248]:
                - generic [ref=f2e249]: Total de productos
                - generic [ref=f2e250]: "9"
                - generic [ref=f2e251]: Incluye activos e inactivos
              - generic [ref=f2e252]:
                - generic [ref=f2e253]: Cierres recientes
                - generic [ref=f2e254]: "0"
                - generic [ref=f2e255]: Últimos cierres cargados
              - generic [ref=f2e256]:
                - generic [ref=f2e257]: Total de cierres
                - generic [ref=f2e258]: "0"
                - generic [ref=f2e259]: Cierres acumulados del ámbito
            - generic [ref=f2e260]:
              - generic [ref=f2e261]:
                - heading "Cierres recientes" [level=3] [ref=f2e262]
                - generic [ref=f2e263]: Últimos registros
              - generic [ref=f2e264]: No hay cierres recientes disponibles.
        - generic [ref=f2e266]:
          - link "Revisión pendiente 1 Pedidos listos para validar manualmente." [ref=f2e267] [cursor=pointer]:
            - /url: http://127.0.0.1:8010/order-reviews
            - generic [ref=f2e268]: Revisión pendiente
            - generic [ref=f2e269]: "1"
            - generic [ref=f2e270]: Pedidos listos para validar manualmente.
          - link "Catálogo 9 Productos activos para la operación diaria." [ref=f2e271] [cursor=pointer]:
            - /url: http://127.0.0.1:8010/products
            - generic [ref=f2e272]: Catálogo
            - generic [ref=f2e273]: "9"
            - generic [ref=f2e274]: Productos activos para la operación diaria.
          - link "Cierres diarios 0 Cierres hechos hoy dentro del ámbito visible." [ref=f2e275] [cursor=pointer]:
            - /url: http://127.0.0.1:8010/daily-order-closures
            - generic [ref=f2e276]: Cierres diarios
            - generic [ref=f2e277]: "0"
            - generic [ref=f2e278]: Cierres hechos hoy dentro del ámbito visible.
```

# Test source

```ts
  1   | import { test, expect } from '@playwright/test';
  2   | import { promises as fs } from 'node:fs';
  3   | import path from 'node:path';
  4   | 
  5   | const artifactRoot = path.resolve(process.cwd(), process.env.PILOT_ARTIFACT_DIR ?? 'artifacts/pilot/latest');
  6   | const screenshotDir = path.resolve(artifactRoot, 'screenshots');
  7   | 
  8   | test.describe.configure({ mode: 'serial' });
  9   | 
  10  | test.beforeAll(async () => {
  11  |     await fs.mkdir(screenshotDir, { recursive: true });
  12  | });
  13  | 
  14  | test('operations focus mode guides one order through the full workflow', async ({ page, browserName }) => {
  15  |     const consoleErrors = [];
  16  |     const pageErrors = [];
  17  |     const http500Responses = [];
  18  |     const failedRequests = [];
  19  | 
  20  |     page.on('console', (message) => {
  21  |         if (message.type() === 'error' || /Alpine Expression Error/i.test(message.text())) {
  22  |             consoleErrors.push(message.text());
  23  |         }
  24  |     });
  25  | 
  26  |     page.on('pageerror', (error) => {
  27  |         pageErrors.push(error.message);
  28  |     });
  29  | 
  30  |     page.on('response', (response) => {
  31  |         if (response.status() >= 500) {
  32  |             http500Responses.push({ url: response.url(), status: response.status() });
  33  |         }
  34  |     });
  35  | 
  36  |     page.on('requestfailed', (request) => {
  37  |         failedRequests.push({
  38  |             url: request.url(),
  39  |             failure: request.failure()?.errorText ?? 'request failed',
  40  |         });
  41  |     });
  42  | 
  43  |     await page.goto('/login');
  44  |     await page.locator('#email').fill('owner@local.test');
  45  |     await page.locator('#password').fill('password');
  46  |     await page.getByRole('button', { name: 'Entrar' }).click();
  47  |     await expect(page).toHaveURL(/\/dashboard/);
  48  | 
  49  |     const csrfToken = await page.locator('meta[name="csrf-token"]').getAttribute('content');
  50  |     if (!csrfToken) {
  51  |         throw new Error('Missing CSRF token on dashboard.');
  52  |     }
  53  | 
  54  |     await resetToolkitData(page, csrfToken);
  55  |     await postToolkitScenario(page, csrfToken);
  56  | 
  57  |     const feedResponse = await page.request.get('/operations/feed');
  58  |     expect(feedResponse.ok()).toBeTruthy();
  59  |     const feed = await feedResponse.json();
  60  |     expect(Array.isArray(feed.inbox) ? feed.inbox.length : 0).toBeGreaterThan(0);
  61  | 
  62  |     const customOrder = Array.isArray(feed.inbox)
  63  |         ? feed.inbox.find((order) =>
  64  |             String(order?.customer_name ?? '') === 'Cliente Focus'
  65  |             || String(order?.original_message ?? '').includes('Ocupo 20 bloques para hoy a las 2 pm, me lo llevan y pago por SINPE'))
  66  |         : null;
  67  | 
  68  |     expect(customOrder?.id, 'expected the generated custom message order to be present in the feed').toBeTruthy();
  69  | 
> 70  |     await page.goto(`/operations/focus?order=${customOrder.id}`);
      |                ^ Error: page.goto: net::ERR_ABORTED at http://127.0.0.1:8010/operations/focus?order=1220
  71  |     await page.screenshot({ path: path.resolve(screenshotDir, '01-focus-clean-summary.png'), fullPage: true });
  72  | 
  73  |     await expect(page.locator('[role="dialog"]')).toHaveCount(0);
  74  |     await expect(page.locator('[data-focus-inline-workspace]')).toBeVisible();
  75  |     await expect(page.locator('[role="dialog"]')).toHaveCount(0);
  76  |     await expect(page.locator('[data-focus-inline-review]')).toContainText('Qué preparar');
  77  |     await expect(page.locator('[data-focus-inline-review]')).toContainText('20 bloques');
  78  |     await expect(page.locator('[data-focus-inline-review]')).toContainText('Entrega');
  79  |     await expect(page.locator('[data-focus-inline-review]')).toContainText('SINPE');
  80  |     await expect(page.locator('[data-focus-inline-review]')).toContainText('Hoy a las 2:00 p. m.');
  81  |     const reviewedItem = page.locator('[data-focus-inline-review] .space-y-3 > div.rounded-2xl.border.border-slate-100.bg-white').first();
  82  |     await expect(reviewedItem).toContainText('20 bloques');
  83  |     await expect(reviewedItem).not.toContainText('me lo llevan');
  84  |     await expect(reviewedItem).not.toContainText('pago por sinpe');
  85  |     await expect(page.locator('[data-focus-inline-review]')).toContainText('20 bloques');
  86  |     await expect(page.locator('[data-focus-inline-review]')).toContainText('Hoy a las 2:00 p. m.');
  87  |     await page.screenshot({ path: path.resolve(screenshotDir, '02-focus-inline-review.png'), fullPage: true });
  88  | 
  89  |     const confirmButton = page.getByRole('button', { name: 'Confirmar pedido' });
  90  |     await expect(confirmButton).toBeVisible();
  91  |     await confirmButton.click();
  92  |     await expect(page.getByRole('button', { name: 'Empezar preparación' })).toBeVisible();
  93  | 
  94  |     await page.getByRole('button', { name: 'Empezar preparación' }).click();
  95  |     const checkboxes = page.locator('[data-focus-item-checkbox]');
  96  |     await expect(checkboxes).toHaveCount(1);
  97  |     await expect(page.getByText('Revisá cada artículo')).toBeVisible();
  98  |     await checkboxes.first().check();
  99  |     await expect(page.locator('[data-focus-progress]')).toContainText('1 de 1 artículos revisados');
  100 |     await page.screenshot({ path: path.resolve(screenshotDir, '03-focus-preparing.png'), fullPage: true });
  101 | 
  102 |     const readyButton = page.getByRole('button', { name: 'Marcar como listo' });
  103 |     await expect(readyButton).toBeEnabled();
  104 |     await readyButton.click();
  105 |     await expect(page.getByRole('button', { name: 'Despachar pedido' })).toBeVisible();
  106 |     await page.screenshot({ path: path.resolve(screenshotDir, '04-focus-ready.png'), fullPage: true });
  107 | 
  108 |     await page.getByRole('button', { name: 'Despachar pedido' }).click();
  109 |     await expect(page.getByText('Este pedido ya quedó listo.')).toBeVisible();
  110 |     await page.screenshot({ path: path.resolve(screenshotDir, '05-focus-completed.png'), fullPage: true });
  111 | 
  112 |     const continueButton = page.getByRole('button', { name: 'Ir al siguiente pedido' });
  113 |     if (await continueButton.isVisible()) {
  114 |         await continueButton.click();
  115 |     }
  116 | 
  117 |     if (pageErrors.length > 0 || http500Responses.length > 0 || failedRequests.length > 0) {
  118 |         throw new Error(`Focus mode browser run recorded errors: ${JSON.stringify({
  119 |             browserName,
  120 |             consoleErrors: consoleErrors.length,
  121 |             pageErrors: pageErrors.length,
  122 |             http500Responses: http500Responses.length,
  123 |             failedRequests: failedRequests.length,
  124 |         })}`);
  125 |     }
  126 | });
  127 | 
  128 | async function postToolkitScenario(page, csrfToken) {
  129 |     const response = await page.request.post('/developer/webhook-simulator/generate', {
  130 |         headers: {
  131 |             'X-CSRF-TOKEN': csrfToken,
  132 |             'X-Requested-With': 'XMLHttpRequest',
  133 |         },
  134 |         form: {
  135 |             action: 'business_custom_message',
  136 |             provider: 'whatsapp',
  137 |             customer_mode: 'new',
  138 |             customer_name: 'Cliente Focus',
  139 |             customer_phone: '50255510050',
  140 |             message: 'Ocupo 20 bloques para hoy a las 2 pm, me lo llevan y pago por SINPE',
  141 |         },
  142 |     });
  143 | 
  144 |     expect(response.ok()).toBeTruthy();
  145 | }
  146 | 
  147 | async function resetToolkitData(page, csrfToken) {
  148 |     const response = await page.request.post('/developer/toolkit/reset-demo-data', {
  149 |         headers: {
  150 |             'X-CSRF-TOKEN': csrfToken,
  151 |             'X-Requested-With': 'XMLHttpRequest',
  152 |         },
  153 |     });
  154 | 
  155 |     expect(response.ok()).toBeTruthy();
  156 | }
  157 | 
```