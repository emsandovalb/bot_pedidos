<?php

/*
| Oria (pago de servicios): un negocio se conecta con su cuenta de Oria y, si Oria le da el plan Plus, ve "Pagar
| servicios". Oria decide el plan; aquí solo se guarda el último conocido. La llave sale de
| `php artisan oria:partner-key bot_pedidos` en Oria, y la dirección de regreso (route('settings.oria.callback'))
| tiene que estar registrada allá en PARTNER_BOT_PEDIDOS_RETURN_URLS.
*/

return [
    // p. ej. https://api.oria.cr/api
    'api_url' => env('ORIA_API_URL'),
    'partner_key' => env('ORIA_PARTNER_KEY'),
    'timeout_seconds' => (int) env('ORIA_TIMEOUT_SECONDS', 5),
    // Cada cuánto se le vuelve a preguntar a Oria por el plan.
    'plan_ttl_minutes' => (int) env('ORIA_PLAN_TTL_MINUTES', 10),
];
