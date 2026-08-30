# Preparación técnica — piloto Benditio / pedidos automáticos

**Estado:** preparado para una prueba controlada de ingestión y revisión humana; **no listo todavía para confirmar con seguridad pedidos reales completos**. Esta evaluación no habilita ni cambia WhatsApp, IA ni ninguna funcionalidad de la aplicación.

## 1. Lo que el sistema ya soporta

### Catálogo y aliases

- Hay catálogo por organización, con nombre normalizado, SKU, unidad, activación y orden (`app/Models/Product.php`).
- Los aliases también se normalizan y pueden activarse, ponderarse y asociarse a un producto (`app/Models/ProductAlias.php`).
- Existe alta, edición y carga masiva de catálogo. La carga acepta `nombre | SKU | unidad | alias 1, alias 2` (`app/Services/ProductBulkImportService.php`, `app/Http/Controllers/ProductImportController.php`).
- La resolución compara texto normalizado contra aliases activos por organización: coincidencia exacta o por frase contenida, y devuelve producto, texto coincidente y confianza (`app/Services/ProductMatchingService.php`). Está cubierta por `tests/Feature/ProductCatalogTest.php`, `tests/Feature/ProductBulkImportTest.php` y `tests/Feature/ProductMatchingTest.php`.

### Recepción de mensajes

- La capa de mensajería persiste cada mensaje entrante, resuelve/crea la identidad del cliente y evita duplicados por proveedor + ID externo (`app/Services/Messaging/MessagingIngestionService.php`, `app/Models/IncomingMessage.php`).
- El proveedor de WhatsApp ya tiene una ruta de webhook y extrae mensajes de texto; las pruebas verifican creación de mensaje/pedido, resolución por `phone_number_id`, mensajes no-texto y deduplicación (`app/Services/Messaging/Providers/WhatsAppProvider.php`, `app/Http/Controllers/WebhookController.php`, `tests/Feature/WhatsAppIncomingWebhookTest.php`). No se propone activarlo en este trabajo.

### Parser e interpretación

- El parser estructurado extrae líneas, cantidades, unidades y notas del texto (`app/Services/OrderParserService.php`).
- Cada ítem se contrasta con el catálogo durante la ingestión. Si no se resuelve alguno, el pedido queda con estado parser `unmatched`, requiere revisión y conserva el ítem sin `product_id` (`app/Services/OrderIngestionService.php`).
- La política documentada evita tratar saludos, texto vacío o productos no encontrados como pedidos operativos válidos (`docs/parser-quality/STATE_AND_REVIEW_POLICY.md`).

### Creación de pedidos, estados y revisión

- Un pedido extraído se crea siempre como `pending_review`, con mensaje original, resultado del parser, confianza, ítems, historial de estado y posible detección de duplicado (`app/Services/OrderIngestionService.php`, `app/Models/Order.php`).
- La cola y detalle de revisión muestran producto reconocido/no asociado, coincidencia y confianza (`app/Http/Controllers/OrderReviewController.php`, `app/Http/Controllers/OrderController.php`).
- Una persona autenticada puede editar cantidades, unidad, texto y notas; se guarda una auditoría antes/después de la edición (`ManualReview`) (`app/Http/Controllers/OrderController.php`; cobertura en `tests/Feature/OrderReviewTest.php`).
- Los estados operativos son `pending_review`, `confirmed`, `preparing`, `ready_for_dispatch`, `dispatched`, `cancelled` y `rejected`, y cada transición registra quién la hizo y cuándo. La confirmación sólo se invoca desde la UI por una persona autenticada; no hay una confirmación automática (`app/Http/Controllers/OrderController.php`, `tests/Feature/OrderWorkflowTest.php`).

### Telegram (aplica como canal de prueba existente)

- Hay polling de Telegram, lectura de mensajes de texto y envío de acuse de recibo cuando la ingestión de pedidos está habilitada (`app/Console/Commands/TelegramPollCommand.php`, `app/Services/Telegram/TelegramBotService.php`).
- Las pruebas verifican que el pedido entra como `pending_review`, que no se confirman pedidos automáticamente y que se evita reprocesar la misma actualización (`tests/Feature/TelegramIntakeTest.php`).

## 2. Información que falta completar en `PILOT_INTAKE.md`

La ficha está totalmente vacía. Antes de una prueba con un negocio real debe completarse:

1. Nombre del negocio, responsable, horario y confirmar el canal inicial.
2. Un catálogo operativo completo: nombre comercial, presentación/unidad, **precio**, aliases frecuentes y disponibilidad de cada producto. Conviene incluir variantes de tamaño, marca o empaque como productos/aliases distintos cuando cambien el precio o la disponibilidad.
3. Las reglas que la persona revisora debe comprobar: retiro/entrega, zonas, medios de pago, pedido mínimo y hora límite.
4. Veinte mensajes reales anonimizados, cada uno con una sola clasificación esperada y notas de revisión. Deben cubrir abreviaturas, errores ortográficos, cantidades/unidades, combinaciones de productos, pedidos incompletos, productos ausentes, consultas y saludos.
5. La marcación de los cuatro criterios de inicio una vez que catálogo, reglas y mensajes hayan sido cargados/revisados por el responsable.

No deben incluirse nombres, teléfonos, direcciones u otros datos personales en los ejemplos del documento.

## 3. Brechas técnicas para el flujo real

| Prioridad | Brecha concreta | Evidencia e impacto |
| --- | --- | --- |
| P0 | La confirmación no aplica precondiciones de seguridad del negocio. | `OrderController::confirm` permite confirmar cualquier pedido `pending_review`; no exige ítems resueltos, revisión registrada, disponibilidad, modalidad, zona/dirección, pago, total o regla de horario. Hoy una persona puede confirmar un pedido con `product_id` nulo. |
| P0 | El catálogo no almacena precios y el pedido no calcula ni guarda total. | La migración/modelo de `products` y `Product::$fillable` sólo incluyen nombre, SKU, unidad, activo y orden; `ProductBulkImportService` sólo importa cuatro campos. Por ello no se puede comprobar el total exigido por el intake antes de confirmar. |
| P0 | Las reglas del negocio piloto no tienen modelo ni validación. | El intake pide zonas, pedido mínimo y horarios límite. Aunque `FulfillmentPlan` puede extraer modalidad, pago y dirección del mensaje, no hay configuración por negocio ni un evaluador que contraste esas reglas antes de confirmar. |
| P1 | La revisión no permite asociar un ítem no encontrado a un producto del catálogo. | La edición de pedido modifica cantidad, unidad, texto y notas, pero no `order_items.product_id` (`OrderController::update` y `syncItemsFromJson`). El operador ve “Sin producto asociado”, pero no puede resolverlo dentro de la misma revisión. |
| P1 | Los 20 mensajes del intake no alimentan aún un conjunto de evaluación ejecutable. | Hay infraestructura de calidad del parser (`docs/parser-quality/dataset/` y `ParserEvaluationDatasetValidator`), pero `PILOT_INTAKE.md` es una ficha manual y no existe importación/trazabilidad de sus casos al dataset ni una métrica de aceptación para ese negocio. |
| P2 | La recepción real tiene límites de formato. | El proveedor actual procesa texto; las pruebas de WhatsApp confirman que mensajes no-texto se ignoran. Para el piloto se debe acordar que las pruebas sean mensajes de texto y decidir después el tratamiento de fotos, audios o catálogos. |
| P2 | El envío de avisos no debe asumirse como confirmación al cliente. | El dispatcher registra evaluaciones y puede enviar Telegram si se habilita explícitamente, pero no intenta envío para otros canales (`OrderNotificationDispatcher::shouldAttemptSending`). La confirmación operativa por persona existe; la comunicación posterior al cliente deberá validarse como trabajo separado. |

## 4. Siguiente tarea de código recomendada

**Añadir una barrera de confirmación segura en servidor.** Es la tarea más pequeña de alto impacto porque cierra el riesgo inmediato sin activar canales ni cambiar el parser: un pedido no debe pasar de `pending_review` a `confirmed` si tiene un ítem sin producto asociado. La respuesta debe explicar qué falta y mantenerlo en revisión para que una persona lo corrija.

Archivos probables:

- `app/Http/Controllers/OrderController.php`: validar las precondiciones justo antes de la transición `confirm`.
- `app/Models/Order.php` o un servicio nuevo, por ejemplo `app/Services/OrderConfirmationReadinessService.php`: concentrar la regla y hacerla reutilizable por UI/API.
- `resources/views/orders/show.blade.php` y/o la cola de revisión: presentar claramente el bloqueo y la acción requerida.
- `tests/Feature/OrderReviewTest.php` y `tests/Feature/OrderWorkflowTest.php`: comprobar que un pedido sin `product_id` no se confirma, que no crea historial de confirmación y que uno totalmente resuelto sí se confirma.

Al completar esa barrera, la siguiente iteración debería modelar precios, total y reglas del negocio a partir del intake, antes de cualquier prueba real de confirmación.
