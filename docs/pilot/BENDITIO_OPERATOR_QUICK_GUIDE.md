# Guía rápida del operador

## Qué hace Benditio

Benditio recibe mensajes, los convierte en pedidos, interpreta fecha, hora, entrega y pago, y te ayuda a decidir qué atender primero.

## Qué significan las secciones

- `DO NOW`: pedidos urgentes o con vencimiento cercano.
- `NEXT`: pedidos que vienen después.
- `COMPLETED`: pedidos ya despachados en el día.

## Cómo abrir un pedido

1. Entra a `DO NOW` o `NEXT`.
2. Haz clic en la tarjeta del pedido.
3. Revisa el panel lateral.

## Cómo validar el pedido

Revisa siempre:

- artículos,
- fecha,
- hora,
- entrega,
- pago,
- posible duplicado,
- nivel de riesgo.

## Cómo usar la acción principal

La acción principal cambia según el estado:

- `Nuevos` -> `Confirmar pedido`
- `Confirmado` -> `Iniciar preparación`
- `Preparando` -> `Marcar listo`
- `Listo` -> `Despachar`

## Qué significan los indicadores

- `Delivery` = entrega a domicilio.
- `Pickup` = pasa a recoger.
- `SINPE` = pago por SINPE.
- `Cash` = efectivo.
- `Live` = la cola está conectada.
- `Reconectando...` = el sistema reintenta.

## Qué hacer con posibles duplicados

- No cierres el pedido por automático.
- Compara cliente, artículos y canal.
- Si parece repetido, avisa o revisa antes de avanzar.

## Dónde aparecen los despachados

Los pedidos despachados aparecen en `COMPLETED` después de refrescar la vista.

## Si el estado live dice `Reconectando...`

- Espera unos segundos.
- No sigas avanzando pedidos si la página no termina de estabilizarse.
- Avísale al facilitador si el estado no vuelve a `Live`.

## Soporte

Si algo no cuadra:

1. Anota el `order_id`.
2. Captura la pantalla.
3. Revisa `artifacts/pilot/latest/report.html`.
4. Escala al facilitador del piloto.

