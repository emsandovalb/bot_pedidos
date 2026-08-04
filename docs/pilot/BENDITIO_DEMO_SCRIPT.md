# Script de demostración del piloto Benditio

Duración objetivo: 30 a 45 minutos.

## 1. Antes de que llegue la audiencia

- Confirmar que el entorno local está levantado.
- Ejecutar `npm run pilot` y verificar que termina con `READY`.
- Abrir `artifacts/pilot/latest/report.html` y validar que no hubo errores de consola ni respuestas HTTP 500.
- Tener listas las rutas de referencia:
  - `/developer/webhook-simulator`
  - `/operations`
  - `/customers`
- Preparar el navegador a 1280x900 o similar.
- Cerrar herramientas de desarrollo y notificaciones del sistema.

## 2. Requisito de arranque

La demostración solo se inicia si `npm run pilot` ya reportó `READY`.

## 3. Apertura

Guion breve:

> Hoy vamos a ver cómo Benditio recibe mensajes, interpreta pedidos y ayuda a operar la preparación y el despacho sin que el equipo tenga que leer cada texto desde cero.

> La demostración se enfoca en lo que ya funciona hoy. No vamos a prometer automatizaciones que todavía no están cerradas.

## 4. Secuencia exacta de la demo

### Paso 1. Mostrar el origen del pedido

- Abrir el simulador o el flujo de carga controlada.
- Explicar que los mensajes entran por canal y terminan en un pedido operativo.
- Mostrar que la ingestión es real, no una maqueta estática.

### Paso 2. Inyectar el mensaje 1

Mensaje determinístico:

> `Ocupo 20 bloques para hoy antes de las 2, mándemelos urgente y pago por SINPE.`

Interpretación esperada:

- Pedido urgente.
- Entrega a domicilio.
- Pago por SINPE.
- Compromiso hoy, antes de las 2.

### Paso 3. Inyectar el mensaje 2

Mensaje determinístico:

> `Necesito 3 pinturas para mañana temprano, yo paso por ellas y pago en efectivo.`

Interpretación esperada:

- Pedido para mañana.
- Recogida en tienda.
- Pago en efectivo.
- Menor urgencia que el anterior.

### Paso 4. Inyectar el mensaje 3

Escenario determinístico de posible duplicado:

- Misma cliente.
- Mismos artículos.
- Canal distinto.
- Ventana temporal cercana.

Interpretación esperada:

- El sistema no lo trata como duplicado técnico.
- Sí lo marca como posible duplicado de negocio.

### Paso 5. Ir a Operaciones

- Abrir `DO NOW`, `NEXT` y `COMPLETED`.
- Señalar el indicador `Live`.
- Explicar que la cola prioriza por urgencia y compromiso, no solo por hora de llegada.

### Paso 6. Abrir una tarjeta

- Abrir el pedido del mensaje 1.
- Mostrar:
  - artículos,
  - plan de cumplimiento,
  - riesgo,
  - análisis de duplicado,
  - contexto del cliente.

### Paso 7. Ejercicio guiado del operador

Pedir al operador que haga una revisión de verdad:

1. Confirmar el pedido.
2. Iniciar preparación.
3. Marcar listo.
4. Despachar.
5. Refrescar y verificar que sigue en `COMPLETED`.

## 5. Mensajes que sí deben observar

- `Confirmar pedido`
- `Iniciar preparación`
- `Marcar listo`
- `Despachar`
- `Ver historial`
- `Live`
- `Reconectando...`
- `Sin conexion`

## 6. Qué observar en la usabilidad

- Si el operador entiende rápidamente la diferencia entre `DO NOW`, `NEXT` y `COMPLETED`.
- Si el drawer da suficiente contexto para no salir de la vista.
- Si la acción primaria es obvia en cada estado.
- Si el operador entiende la diferencia entre duplicado técnico y posible duplicado de negocio.
- Si el estado después de despachar sigue siendo confiable tras refrescar.

## 7. Criterios de éxito del piloto

- El mensaje entra sin error.
- El pedido se interpreta con fecha, hora, entrega y pago.
- El pedido aparece en la cola correcta.
- El operador puede avanzar el estado sin ambigüedad.
- El pedido queda en `COMPLETED` después de refrescar.
- No aparecen errores de consola, HTTP 500 ni estados rotos.

## 8. Qué no prometer

- No prometer automatización total.
- No prometer recepción real lista de Instagram.
- No prometer integración externa de WhatsApp o Telegram como si ya estuviera cerrada al 100%.
- No prometer que todos los textos ambiguos se resuelven solos.
- No prometer que el operador dejará de revisar pedidos.

## 9. Qué no explicar salvo que lo pregunten

- No entrar en detalles de clases, controladores o servicios internos.
- No explicar la jerarquía de modelos salvo que el público sea técnico.
- No enseñar cada test.
- No mostrar rutas internas si el público no lo pidió.

## 10. Cierre

Preguntas de cierre sugeridas:

- ¿Qué parte del flujo te hizo dudar más?
- ¿La tarjeta operativa te da suficiente contexto para actuar?
- ¿Qué información faltó para decidir más rápido?
- ¿En qué parte te gustaría que Benditio te ahorrara más tiempo?

