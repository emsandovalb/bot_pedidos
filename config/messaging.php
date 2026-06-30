<?php

return [
    'default' => env('MESSAGING_DEFAULT', 'telegram'),

    'notifications_sending_enabled' => (bool) env('NOTIFICATIONS_SENDING_ENABLED', false),
    'telegram_notifications_enabled' => (bool) env('TELEGRAM_NOTIFICATIONS_ENABLED', false),
    'whatsapp_notifications_enabled' => (bool) env('WHATSAPP_NOTIFICATIONS_ENABLED', false),

    'providers' => [
        'telegram',
        'whatsapp',
        'instagram',
    ],
];
