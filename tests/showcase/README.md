# Auditoría automática (BotPedidos + Oria)

Recorre el plan de pruebas completo contra el entorno de muestra: BotPedidos, la web de Oria, la consola de
soporte, Feria Ya y la conexión entre BotPedidos y Oria. Cada caso del plan es una prueba con el mismo número
(`BOT-06`, `LINK-03`, `FERIA-11`…).

## Correrla

Desde la carpeta de bot_pedidos:

```bash
npx playwright test -c playwright.showcase.config.js
```

Antes de empezar reinicia las dos bases de muestra (`node scripts/showcase.mjs prepare`) y levanta las tres apps
si no están arriba (BotPedidos en 8020, la API de Oria en 8100 y la web en 3100). Oria se busca en
`ORIA_PATH` (por defecto la copia en Descargas).

- Una sola parte: `-g "FERIA"` o `-g "BOT-06|LINK-03"`.
- Sin reiniciar los datos (más rápido al repetir un caso): `SHOWCASE_KEEP_DATA=1`.
- Sin las pruebas propias de cada repo, que son las más lentas: `--grep-invert "ALL-0[678]"`.

Los pagos con tarjeta y SINPE usan el modo de prueba de ONVO, así que necesitan internet.

## Resultados

- `artifacts/showcase/results.json`: el resultado de cada caso.
- `artifacts/showcase/capturas/`: cada pantalla en computadora y en celular, para revisar a ojo cómo se ve
  (lo visual no se juzga solo).
- `artifacts/showcase/test-results/`: captura, video y traza de cada caso que falló.

## Pendientes conocidos

Algunos casos verifican reglas que la app todavía no cumple. Están marcados con `pending(...)` o
`test.fail(...)`: el reporte los cuenta como "esperado que falle" y se vuelven rojos el día que empiezan a
pasar. Cuando se arregla algo en la app, se quita la marca en el mismo cambio.

| Caso | Regla |
| --- | --- |
| BOT-14 | Operaciones en español y sin tarjetas "Sin productos visibles". |
| ALL-02 | Sin textos en inglés en Operaciones, Canales y Perfil. |

## Cómo está armada

- Los mensajes de clientes (WhatsApp y Telegram) entran por el emulador del Developer Toolkit.
- Las sesiones de Oria se crean directo en su base de muestra (`oriaToken`) y cada caso empieza con los límites
  de intentos de Oria en cero, así un caso no bloquea al siguiente. El inicio de sesión real se prueba aparte
  (ORIA-02, LINK-03).
- Varias reglas de Feria Ya se verifican por la misma API que usan sus pantallas; el cobro, por la pantalla.
