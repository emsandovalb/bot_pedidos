<?php

return [
    'defaults' => [
        'priority_score' => (int) env('FULFILLMENT_DEFAULT_PRIORITY_SCORE', 0),
        'priority_level' => env('FULFILLMENT_DEFAULT_PRIORITY_LEVEL', 'normal'),
        'sla_minutes' => (int) env('FULFILLMENT_DEFAULT_SLA_MINUTES', 0),
        'delivery_method' => env('FULFILLMENT_DEFAULT_DELIVERY_METHOD', 'unknown'),
        'payment_method' => env('FULFILLMENT_DEFAULT_PAYMENT_METHOD', 'unknown'),
    ],
];
