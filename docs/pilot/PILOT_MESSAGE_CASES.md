# Evaluación repetible de mensajes del piloto

`pilot-message-cases.json` es el conjunto local y versionado para evaluar mensajes anonimizados del negocio piloto. No activa canales, no usa IA y no modifica el parser. La prueba ejecuta el flujo real de `OrderIngestionService`, incluida la resolución de catálogo, y consulta la regla real de `OrderConfirmationReadinessService`.

## Formato

```json
{
  "schema_version": "1.0",
  "cases": [{
    "id": "PILOT-REAL-001",
    "anonymized_message": "3 cajas de producto anonimo",
    "expected_classification": "pedido",
    "expected_products": [{
      "name": "producto anonimo",
      "quantity": 3,
      "unit": "caja",
      "catalog_alias": "cajas de producto anonimo"
    }],
    "expected_status": "resolved",
    "can_confirm": true,
    "source": "lote-piloto-anonimizado"
  }]
}
```

Campos requeridos por caso no vacío:

- `id`: identificador nuevo, sin IDs de cliente, pedido ni conversación.
- `anonymized_message`: texto ya anonimizado. Deje `""` para conservar un borrador: la prueba lo omite.
- `expected_classification`: `pedido`, `pedido_incompleto`, `saludo_o_pregunta` o `producto_no_encontrado`.
- `expected_products`: lista de `name`, `quantity` positiva y `unit`; use `[]` cuando no haya producto que evaluar.
- `expected_status`: `resolved`, `pending_review` o `unmatched`; use `null` sólo para `saludo_o_pregunta`, porque no se crea un pedido.
- `can_confirm`: booleano esperado de la regla de confirmación. Para un saludo/pregunta, debe ser `false`.
- `source`: procedencia general sin identificadores personales ni externos.

`catalog_alias` es opcional. Sólo se usa por la prueba para crear un alias de catálogo efímero y evaluar la ruta `resolved` o un pedido incompleto con producto conocido. No representa ni modifica el catálogo del piloto. Omítalo para comprobar que un producto queda `unmatched`.

La clasificación se contrasta contra el resultado del pipeline: `pedido` debe quedar `resolved`, `pedido_incompleto` debe quedar `pending_review`, `producto_no_encontrado` debe quedar `unmatched`, y un `saludo_o_pregunta` no debe crear pedido.

## Incorporar los 20 mensajes

1. Copie sólo el texto necesario de cada mensaje y quite nombres, teléfonos, direcciones, identificadores de conversación/pedido y otros datos personales. Use marcadores como `[PERSONA]` si conservar el contexto es necesario.
2. Añada un objeto por mensaje a `docs/pilot/pilot-message-cases.json`. Asigne IDs secuenciales como `PILOT-REAL-001`; no reutilice IDs de producción.
3. Etiquete el resultado esperado tras revisarlo con el negocio. Para un producto conocido, incluya el `catalog_alias` que debe resolver la prueba; para uno que debe quedar sin catálogo, omítalo.
4. Ejecute únicamente esta evaluación desde la raíz del proyecto:

```powershell
php artisan test --filter=PilotMessageCasesTest
```

Los cuatro casos iniciales son fixtures seguros derivados de `baseline-cases.json`; no proceden de ningún mensaje real del negocio.
