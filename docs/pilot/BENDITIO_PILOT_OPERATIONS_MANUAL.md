# Manual de operaciones del piloto Benditio

**Versión actual del sistema**

Este documento describe el comportamiento que existe hoy en el piloto de Benditio. No describe ideas futuras ni comportamiento esperado a futuro.

## 1. Qué hace Benditio

Benditio organiza pedidos que llegan por mensajería, identifica al cliente, interpreta lo que pide, calcula el plan de cumplimiento y lo presenta en una vista operativa para que una persona revise y avance el pedido.

En la práctica, Benditio hoy:

- Recibe mensajes de prueba o reales según el canal disponible.
- Crea pedidos y artículos desde texto libre.
- Resuelve identidades de cliente por canal, teléfono y otros datos del proveedor.
- Detecta duplicados técnicos y posibles duplicados de negocio.
- Calcula fecha, hora, entrega, recogida, pago y prioridad.
- Ordena la cola operativa en `Hacer ahora`, `Siguiente` y `Completados`.
- Permite que el operador confirme, prepare, marque listo y despache pedidos.
- Mantiene historial de estados, historial de notificaciones y contexto del cliente.

## 2. Qué no hace hoy

Hoy Benditio no hace estas cosas de forma completa:

- No sustituye al operador humano.
- No ejecuta despacho físico ni logística externa.
- No gestiona cobros ni confirma pagos con un sistema financiero real.
- No integra WhatsApp Cloud, Telegram o Instagram de extremo a extremo en producción.
- No tiene Instagram operativo; Instagram sigue como marcador de posición.
- No resuelve todos los casos ambiguos de texto sin revisión manual.
- No convierte cualquier mensaje en pedido aprobado automáticamente.

## 3. Cómo entran los pedidos por canal

### WhatsApp

WhatsApp es el canal más cercano a un flujo real de entrada. El sistema acepta payloads de webhook y también mensajes generados desde el simulador de negocio. En este piloto, la parte de conexión externa sigue siendo parcial y la validación de credenciales se comporta como soporte local o de prueba.

### Telegram

Telegram se usa hoy sobre todo desde el simulador de negocio y el flujo de carga controlada. La lectura de webhook directo no está implementada todavía como canal operativo completo.

### Instagram

Instagram está marcado como `coming soon`. Solo existe como placeholder y no debe presentarse como canal operativo ya disponible.

## 4. Flujo completo del pedido

1. El cliente escribe por un canal soportado.
2. El mensaje entra al sistema de ingestión.
3. Se resuelve o crea la identidad del cliente.
4. Se crea el pedido con sus artículos detectados.
5. Se calcula el plan de cumplimiento.
6. Se evalúa si hay duplicado técnico o posible duplicado de negocio.
7. El pedido aparece en la vista de operaciones.
8. El operador revisa, confirma y avanza el estado.
9. Cuando queda `Despachado`, pasa a `Completados` después de refrescar.

## 5. Comportamiento de identidad del cliente

La identidad se resuelve por este orden general:

- Coincidencia exacta de proveedor.
- Coincidencia por chat o usuario externo.
- Coincidencia por teléfono normalizado.
- Creación de cliente nuevo cuando no hay coincidencia confiable.

El mismo cliente puede tener más de una identidad si escribe desde varios canales. Eso es esperado.

## 6. Duplicado técnico vs duplicado de negocio

### Duplicado técnico

Es el mismo mensaje entrante repetido por error técnico, por ejemplo el mismo `external_message_id` en el mismo canal.

Resultado esperado:

- Se reutiliza el registro existente.
- No se crean un segundo mensaje ni un segundo pedido.
- El sistema marca la ingestión como duplicada.

### Posible duplicado de negocio

Es un segundo pedido que puede representar la misma intención del cliente, aunque técnicamente sea un mensaje distinto.

Se evalúa con señales como:

- mismo cliente,
- mismos o muy parecidos artículos,
- ventana temporal cercana,
- mensajes o patrones muy similares.

Resultado esperado:

- El pedido puede marcarse como posible duplicado.
- El operador sigue decidiendo si continúa o no.

## 7. Interpretación de cumplimiento

### Fecha

El sistema reconoce expresiones como `hoy`, `mañana`, días de la semana y fechas explícitas. Si no hay fecha, el pedido queda sin compromiso de fecha.

### Hora

Reconoce horas explícitas y expresiones como `antes de las 2`, `a las 10`, `temprano`, `por la tarde` o `por la noche` según el lenguaje detectado.

### Entrega

Puede interpretar:

- `delivery`,
- `pickup`,
- `express`,
- `third_party`,
- o ambigüedad cuando el texto mezcla entrega y recogida.

### Recogida

Si el cliente dice que pasa por el pedido, recoge en tienda o no menciona entrega a domicilio, el plan puede quedar como `pickup`.

### Pago

Puede interpretar, entre otros:

- `SINPE`,
- `cash`,
- `card`,
- `transfer`.

### Urgencia

El sistema sube prioridad cuando ve:

- `urgente`,
- fecha para hoy,
- compromiso muy cercano,
- riesgo alto o crítico,
- SLA vencido,
- o señales de cliente VIP o duplicado.

## 8. Prioridad y compromiso

La prioridad no es solo tiempo; también mezcla urgencia del lenguaje, fecha, cliente frecuente, entrega, duplicado y confianza del parser.

En la práctica:

- `urgente` empuja el pedido arriba.
- `hoy` pesa más que `mañana`.
- Un pedido con `delivery` puede subir prioridad.
- Un posible duplicado también influye.

El compromiso operativo se refleja en:

- fecha de compromiso,
- hora de compromiso,
- ventana horaria,
- SLA restante,
- nivel de riesgo.

## 9. Experiencia actual de Operaciones

La vista de operaciones tiene tres zonas de cola:

- `DO NOW` = Hacer ahora.
- `NEXT` = Siguiente.
- `COMPLETED` = Completados.

También muestra:

- indicador en vivo,
- tarjetas de pedido,
- panel lateral de detalle,
- acción primaria guiada.

### Tarjeta

La tarjeta muestra lo esencial para decidir rápido:

- cliente,
- compromiso,
- entrega,
- pago,
- ventana horaria,
- sucursal,
- antigüedad,
- botón principal.

### Detalle lateral

El drawer de detalle muestra:

- artículos,
- detalles del parser,
- plan de cumplimiento,
- prioridad y riesgo,
- análisis de duplicado,
- línea de tiempo,
- historial de notificaciones,
- contexto del cliente,
- notas.

### Acción guiada

La acción primaria cambia según el estado del pedido:

- `Nuevos` -> `Confirmar pedido`
- `Confirmado` -> `Iniciar preparación`
- `Preparando` -> `Marcar listo`
- `Listo` -> `Despachar`
- `Despachado` -> `Ver historial`
- `Cancelado` y `Rechazado` -> sin acción operativa adicional

## 10. Estados exactos del pedido

Estados actuales:

- `pending_review` = `Nuevos`
- `confirmed` = `Confirmado`
- `preparing` = `Preparando`
- `ready_for_dispatch` = `Listo`
- `dispatched` = `Despachado`
- `cancelled` = `Cancelado`
- `rejected` = `Rechazado`

### Siguiente acción válida por estado

| Estado | Acción primaria actual | Acciones secundarias |
|---|---|---|
| `pending_review` | `Confirmar pedido` | `Rechazar`, `Cancelar` |
| `confirmed` | `Iniciar preparación` | `Cancelar` |
| `preparing` | `Marcar listo` | `Cancelar` |
| `ready_for_dispatch` | `Despachar` | `Cancelar` |
| `dispatched` | Ninguna | `Ver historial` |
| `cancelled` | Ninguna | Ninguna |
| `rejected` | Ninguna | Ninguna |

## 11. Cómo procesar un pedido de principio a fin

1. Abrir `DO NOW`.
2. Entrar al pedido correcto desde la tarjeta.
3. Revisar cliente, artículos y plan de cumplimiento.
4. Confirmar que la entrega, la recogida y el pago tienen sentido.
5. Revisar si hay posible duplicado.
6. Pulsar `Confirmar pedido`.
7. Pulsar `Iniciar preparación`.
8. Pulsar `Marcar listo`.
9. Pulsar `Despachar`.
10. Refrescar la página y verificar que el pedido aparece en `COMPLETED`.

## 12. Cómo comprobar que sigue en Completados tras refrescar

Después de despachar:

- recargar la vista de Operaciones,
- abrir `COMPLETED`,
- confirmar que el pedido sigue visible allí,
- confirmar que su estado sigue siendo `Despachado`,
- revisar que el pedido no volvió a `DO NOW` ni a `NEXT`.

Si desaparece de Completados, las causas más probables son:

- el despacho no quedó persistido,
- cambió la fecha de referencia,
- el filtro activo oculta el pedido,
- o la vista no terminó de refrescar.

## 13. Casos excepcionales frecuentes

- Mensaje vacío o incompleto.
- Pedido con ambos indicios de entrega y recogida.
- Pedido con hora específica ambigua.
- Pedido duplicado entre canales.
- Pedido que queda sin artículos reconocidos.
- Pedido viejo que sube de prioridad por antigüedad.
- Pedido despachado pero no visible por filtros.

## 14. Troubleshooting

### Si el indicador live muestra `Reconectando...`

- Esperar unos segundos y dejar que la vista reintente.
- Revisar que el servidor del piloto siga activo.
- Confirmar que no haya errores 500 en la consola o en la red.

### Si no aparece un pedido nuevo

- Verificar que el mensaje llegó por el canal correcto.
- Revisar si cayó como duplicado técnico.
- Revisar si el cliente o el teléfono están vacíos.
- Confirmar que no haya filtros activos en Operaciones.

### Si el drawer no abre

- Hacer clic sobre la tarjeta, no solo sobre texto aislado.
- Confirmar que el pedido pertenece a la misma organización.
- Probar la URL de snapshot del pedido.

### Si la acción primaria no cambia

- Confirmar que el pedido está en el estado correcto.
- Revisar si el botón anterior ya fue consumido.
- Revisar si el pedido se despachó pero la página todavía no refrescó.

## 15. Limitaciones del piloto actual

- Instagram no está operativo.
- Telegram no tiene recepción real de webhook completa.
- WhatsApp externo no está presentado como integración final cerrada.
- La deduplicación de negocio es asistida, no automática en términos de decisión final.
- El operador sigue siendo responsable de confirmar el pedido.
- La experiencia está optimizada para el flujo del piloto, no para reemplazar procesos completos de backoffice.

## 16. Escalation y soporte

Si algo falla durante el piloto:

1. Anotar el `order_id`, canal, hora y estado.
2. Capturar la pantalla o el error exacto.
3. Revisar `artifacts/pilot/latest/report.html`.
4. Revisar `artifacts/pilot/latest/e2e-summary.json`.
5. Validar si el pedido aparece en `/operations/feed`.
6. Escalar al facilitador del piloto con evidencia concreta.

