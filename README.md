# BotPedidos

BotPedidos es una plataforma de captura automática de pedidos por chat, con Telegram como primer canal y WhatsApp como canal futuro.

## Qué hace

- Recibe mensajes entrantes desde bots.
- Guarda el mensaje original sin perder contexto.
- Ayuda a revisar y confirmar pedidos antes del despacho.
- Organiza cierres diarios por sucursal.

## Flujo operativo

1. El cliente envía un mensaje por chat.
2. El sistema guarda el mensaje entrante.
3. El parser intenta detectar productos, cantidades y notas.
4. Se crea un pedido pendiente de revisión.
5. El equipo administrativo revisa, edita y confirma el pedido.
6. El pedido avanza a preparación y despacho.
7. Al final del día se genera el cierre diario o la exportación correspondiente.

## Canal Telegram

### Variables de entorno

- `TELEGRAM_ENABLED=true`
- `TELEGRAM_BOT_TOKEN=...`
- `TELEGRAM_DEFAULT_BRANCH_ID=...`
- `TELEGRAM_VERIFY_SSL=true`

### Procesamiento local

El poller de Telegram puede ejecutarse en modo continuo para pruebas locales:

```bash
php artisan telegram:poll --loop --sleep=3
```

Si solo quieres procesar una pasada:

```bash
php artisan telegram:poll
```

## Instalación local

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

- La primera versión está enfocada en Telegram.
- WhatsApp se incorporará más adelante sin cambiar el flujo central de pedidos.
- Los mensajes entrantes nunca deben descartarse silenciosamente.
