<?php

return [
    'default' => env('MESSAGING_DEFAULT', 'telegram'),

    // Cuando es true, el webhook entrante solo persiste el mensaje y encola su
    // procesamiento (parser + pedido) en un job, devolviendo 200 de inmediato.
    // El developer toolkit lo fuerza a false para poder ver el resultado al momento.
    'inbound_queue' => (bool) env('MESSAGING_INBOUND_QUEUE', true),

    'notifications_sending_enabled' => (bool) env('NOTIFICATIONS_SENDING_ENABLED', false),
    'telegram_notifications_enabled' => (bool) env('TELEGRAM_NOTIFICATIONS_ENABLED', false),
    'whatsapp_notifications_enabled' => (bool) env('WHATSAPP_NOTIFICATIONS_ENABLED', false),

    'providers' => [
        'telegram',
        'whatsapp',
        'instagram',
    ],
];
