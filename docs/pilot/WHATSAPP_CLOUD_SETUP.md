# WhatsApp Cloud Setup

Guía para dejar Benditio listo para conectar WhatsApp Cloud en un entorno de prueba, sin enviar mensajes reales y sin exponer secretos.

Fecha de referencia: August 25, 2026.

## Objetivo

Preparar:

- verificación `GET` del webhook;
- recepción `POST` de mensajes entrantes;
- validación de firma `X-Hub-Signature-256`;
- asociación del webhook al `phone_number_id` correcto;
- pruebas de diagnóstico sin imprimir tokens ni secretos.

No cubre:

- envío de mensajes desde Benditio;
- activación productiva del canal;
- uso de credenciales reales en esta guía.

## Variables requeridas

### Variables de entorno de la app

Definir en `.env`:

```env
APP_URL=https://TU-DOMINIO-DE-PRUEBA
WHATSAPP_APP_SECRET=REEMPLAZAR_CON_APP_SECRET_DE_META
WHATSAPP_API_VERSION=v21.0
```

Notas:

- `APP_URL` debe apuntar al dominio público HTTPS donde Meta pueda alcanzar Benditio.
- `WHATSAPP_APP_SECRET` se usa para validar `X-Hub-Signature-256` del `POST` entrante.
- `WHATSAPP_API_VERSION` solo actúa como fallback; no afecta la verificación del webhook.

### Datos que deben cargarse en la configuración del canal

En la pantalla de configuración de WhatsApp de Benditio, guardar para la organización de prueba:

- `provider_app_id`
- `provider_app_secret`
- `provider_access_token`
- `provider_verify_token`
- `provider_webhook_secret`
- `provider_phone_number_id`
- `provider_business_account_id`
- `provider_api_version`

Notas:

- `provider_verify_token` es el valor que Meta enviará en el `GET` de verificación.
- `provider_webhook_secret` debe guardar el mismo `APP_SECRET` de Meta a nivel de organización.
- `provider_phone_number_id` debe coincidir con el número de prueba configurado en Meta.
- Aunque Benditio guarda `provider_app_secret` y `provider_webhook_secret`, la validación activa del webhook usa `WHATSAPP_APP_SECRET` desde entorno para calcular la firma.

## URL exacta del webhook

La ruta pública actual de Benditio es:

```text
/webhooks/whatsapp
```

La URL completa queda así:

```text
APP_URL/webhooks/whatsapp
```

Ejemplo:

```text
https://staging.example.com/webhooks/whatsapp
```

## Token de verificación

Elegir un valor aleatorio largo y usar el mismo valor en:

- Meta Developers, campo `Verify token`;
- Benditio, campo `provider_verify_token`.

Recomendación:

- longitud mínima de 32 caracteres;
- generado con un password manager o con OpenSSL.

Ejemplo de generación local:

```powershell
openssl rand -hex 32
```

No imprimir ni compartir el token en tickets, chat o logs.

## APP_SECRET

Usar el `App Secret` de la app de Meta solo para:

- `WHATSAPP_APP_SECRET` en entorno;
- `provider_webhook_secret` en la configuración del canal, para dejar consistente la configuración por organización.

Reglas:

- no reutilizar secretos de producción en staging;
- no incluir el valor en documentación, capturas ni logs;
- rotarlo antes de pasar a producción si fue compartido fuera del gestor de secretos.

## Pasos en Meta Developers

1. Entrar a Meta Developers.
2. Abrir o crear una app de tipo Business con producto WhatsApp.
3. Ir a `WhatsApp > API Setup`.
4. Confirmar que existe un número de prueba.
5. Copiar sin publicar en texto plano:
   - `App ID`
   - `App Secret`
   - `Temporary access token` o token de prueba equivalente
   - `WhatsApp Business Account ID`
   - `Phone number ID`
6. En `Webhooks`, agregar o editar la suscripción del producto WhatsApp.
7. Configurar:
   - `Callback URL`: `https://TU-DOMINIO-DE-PRUEBA/webhooks/whatsapp`
   - `Verify token`: el mismo valor que `provider_verify_token`
8. Ejecutar la verificación.
9. Después de verificar, suscribirse al campo `messages`.

## Configuración del número de prueba

Antes de probar recepción:

- usar el `phone_number_id` del número de prueba de Meta;
- guardar ese valor en Benditio como `provider_phone_number_id`;
- confirmar que la organización de prueba tiene una sucursal activa;
- confirmar que la conexión de canal WhatsApp pertenece a la organización correcta.

Importante:

- Benditio resuelve la organización entrante por `entry[].changes[].value.metadata.phone_number_id`;
- si Meta envía un `phone_number_id` no registrado, Benditio responderá `200` pero ignorará el evento.

## Suscripción al campo `messages`

Después de que Meta valide el webhook:

1. Ir al panel de `Webhooks` del producto WhatsApp.
2. Seleccionar la suscripción del objeto correspondiente.
3. Activar el campo `messages`.

Sin esa suscripción:

- el `GET` de verificación puede pasar;
- pero Benditio no recibirá mensajes entrantes reales para el número de prueba.

## Prueba de verificación GET

Objetivo: confirmar que Meta podría verificar el callback antes de usar la consola de Meta.

Reemplazar:

- `BASE_URL` por el dominio de prueba;
- `VERIFY_TOKEN` por el token real, sin exponerlo en terminal compartida.

### PowerShell

```powershell
$baseUrl = "https://TU-DOMINIO-DE-PRUEBA"
$verifyToken = Read-Host "Verify token"
$challenge = "benditio-test-challenge"

$url = "$baseUrl/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=$verifyToken&hub.challenge=$challenge"
Invoke-WebRequest -Method GET -Uri $url
```

Resultado esperado:

- `HTTP 200`;
- cuerpo exactamente igual a `benditio-test-challenge`.

Si falla:

- `403 Invalid webhook mode.`: el query string está mal formado;
- `403 Webhook verification failed.`: el token no coincide o la conexión no está lista;
- `404`: ruta o provider incorrecto.

## Prueba de recepción POST

Objetivo: validar que Benditio acepta un payload firmado sin enviar mensajes reales desde Meta.

### Payload de prueba

Guardar un JSON local, por ejemplo `storage/app/whatsapp-webhook-test.json`:

```json
{
  "object": "whatsapp_business_account",
  "entry": [
    {
      "id": "entry-local-test",
      "changes": [
        {
          "field": "messages",
          "value": {
            "messaging_product": "whatsapp",
            "metadata": {
              "display_phone_number": "+00000000000",
              "phone_number_id": "PHONE_NUMBER_ID_TEST"
            },
            "contacts": [
              {
                "profile": {
                  "name": "Test Customer"
                },
                "wa_id": "50255510099"
              }
            ],
            "messages": [
              {
                "from": "50255510099",
                "id": "wamid.local-test-001",
                "timestamp": "1719230400",
                "type": "text",
                "text": {
                  "body": "2 bolsas de jardin"
                }
              }
            ]
          }
        }
      ]
    }
  ]
}
```

Antes de enviar:

- reemplazar `PHONE_NUMBER_ID_TEST` por el `provider_phone_number_id` real del entorno de prueba;
- mantener un `message.id` único por intento para no activar deduplicación accidental.

### PowerShell con firma HMAC

```powershell
$baseUrl = "https://TU-DOMINIO-DE-PRUEBA"
$appSecret = Read-Host "App secret"
$payloadPath = "storage/app/whatsapp-webhook-test.json"
$body = Get-Content $payloadPath -Raw

$hmac = [System.Security.Cryptography.HMACSHA256]::new([Text.Encoding]::UTF8.GetBytes($appSecret))
$hashBytes = $hmac.ComputeHash([Text.Encoding]::UTF8.GetBytes($body))
$signature = "sha256=" + ([System.BitConverter]::ToString($hashBytes).Replace("-", "").ToLowerInvariant())

Invoke-WebRequest `
  -Method POST `
  -Uri "$baseUrl/webhooks/whatsapp" `
  -ContentType "application/json" `
  -Headers @{ "X-Hub-Signature-256" = $signature } `
  -Body $body
```

Resultado esperado:

- `HTTP 200`;
- texto similar a `WhatsApp webhook handled: 1 processed, 0 ignored, 0 failed.`

Si falla:

- `401 Invalid WhatsApp webhook signature.`: `APP_SECRET` incorrecto o cuerpo alterado;
- `503 WhatsApp webhook signature validation is not configured.`: falta `WHATSAPP_APP_SECRET` en entorno;
- `200` con `ignored`: `phone_number_id` no coincide o el payload no contiene mensaje de texto válido.

## Comandos de diagnóstico

Estos comandos están pensados para confirmar configuración sin imprimir secretos.

### Confirmar ruta pública configurada

```powershell
php artisan route:list --name=webhooks --path=webhooks
```

Debe mostrar:

- `GET|HEAD webhooks/{provider}`
- `POST webhooks/{provider}`

### Confirmar que `APP_URL` y `WHATSAPP_APP_SECRET` están cargados

```powershell
php artisan tinker --execute="dump(['app_url' => config('app.url'), 'has_whatsapp_app_secret' => filled(config('services.whatsapp.app_secret'))]);"
```

Debe mostrar:

- `app_url` con el dominio esperado;
- `has_whatsapp_app_secret` en `true`.

### Confirmar que existe una conexión WhatsApp lista para webhook

```powershell
php artisan tinker --execute="dump(App\Models\ChannelConnection::query()->where('channel', 'whatsapp')->get(['id','organization_id','provider_phone_number_id','webhook_status','provider_status'])->toArray());"
```

Revisar:

- `provider_phone_number_id` presente;
- `organization_id` correcto;
- `webhook_status` cambia a `verified` después del `GET` exitoso.

### Confirmar que el último intento de verificación quedó registrado

```powershell
php artisan tinker --execute="dump(App\Models\WebhookEvent::query()->latest('id')->first(['provider','event_type','method','status','organization_id','created_at'])?->toArray());"
```

Revisar:

- `provider = whatsapp`;
- `event_type = verification` para la prueba `GET`;
- `method = GET`;
- `status = 200` o `403` según el resultado.

### Confirmar recepción de webhook sin exponer payload completo

```powershell
php artisan tinker --execute="dump(App\Models\IncomingMessage::query()->latest('id')->first(['id','organization_id','provider','external_message_id','from_identifier','status','received_at'])?->toArray());"
```

Revisar:

- `provider = whatsapp`;
- `external_message_id` nuevo;
- `status` procesado o duplicado según el caso.

### Ejecutar la suite automatizada del webhook

```powershell
php artisan test tests/Feature/WebhookVerificationTest.php tests/Feature/WhatsAppIncomingWebhookTest.php
```

## Checklist de seguridad antes de producción

- `APP_URL` usa HTTPS público, sin túneles efímeros.
- `WHATSAPP_APP_SECRET` está cargado desde un gestor de secretos, no desde documentación compartida.
- `provider_verify_token` es aleatorio, largo y distinto entre staging y producción.
- `provider_phone_number_id` de producción no se mezcla con el de prueba.
- `APP_DEBUG=false` en el entorno expuesto a Meta.
- Los logs no imprimen tokens, verify token, access token ni app secret.
- El webhook responde `401` a firmas inválidas y `403` a verificaciones fallidas.
- Existe monitoreo básico sobre errores `401`, `403`, `5xx` y ausencia de eventos entrantes.
- El equipo verificó rotación de secretos antes de go-live.
- La prueba de `GET` y la de `POST` firmada pasaron en el entorno público final.

## Estado esperado en Benditio después del setup de prueba

- La URL `https://TU-DOMINIO-DE-PRUEBA/webhooks/whatsapp` responde a Meta.
- El `GET` de verificación devuelve el `hub.challenge`.
- El `POST` firmado con `X-Hub-Signature-256` se acepta.
- Los mensajes duplicados no crean un segundo `IncomingMessage` ni una segunda orden para el mismo `external_message_id`.
