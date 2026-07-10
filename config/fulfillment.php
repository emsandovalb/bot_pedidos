<?php

return [
    'decision_version' => env('FULFILLMENT_DECISION_VERSION', 'v1'),
    'defaults' => [
        'priority_score' => (int) env('FULFILLMENT_DEFAULT_PRIORITY_SCORE', 0),
        'priority_level' => env('FULFILLMENT_DEFAULT_PRIORITY_LEVEL', 'normal'),
        'sla_minutes' => (int) env('FULFILLMENT_DEFAULT_SLA_MINUTES', 0),
        'delivery_method' => env('FULFILLMENT_DEFAULT_DELIVERY_METHOD', 'unknown'),
        'payment_method' => env('FULFILLMENT_DEFAULT_PAYMENT_METHOD', 'unknown'),
    ],
    'priority' => [
        'base_score' => (int) env('FULFILLMENT_PRIORITY_BASE_SCORE', 40),
        'weights' => [
            'today' => (int) env('FULFILLMENT_PRIORITY_WEIGHT_TODAY', 30),
            'urgent_phrase' => (int) env('FULFILLMENT_PRIORITY_WEIGHT_URGENT_PHRASE', 40),
            'vip' => (int) env('FULFILLMENT_PRIORITY_WEIGHT_VIP', 15),
            'delivery' => (int) env('FULFILLMENT_PRIORITY_WEIGHT_DELIVERY', 10),
            'pickup' => (int) env('FULFILLMENT_PRIORITY_WEIGHT_PICKUP', 0),
            'tomorrow' => (int) env('FULFILLMENT_PRIORITY_WEIGHT_TOMORROW', 0),
            'express' => (int) env('FULFILLMENT_PRIORITY_WEIGHT_EXPRESS', 20),
            'duplicate' => (int) env('FULFILLMENT_PRIORITY_WEIGHT_DUPLICATE', 5),
        ],
        'confidence' => [
            'enabled' => filter_var(env('FULFILLMENT_PRIORITY_CONFIDENCE_ENABLED', false), FILTER_VALIDATE_BOOL),
            'weight' => (int) env('FULFILLMENT_PRIORITY_CONFIDENCE_WEIGHT', 20),
        ],
        'vip_min_orders' => (int) env('FULFILLMENT_PRIORITY_VIP_MIN_ORDERS', 20),
        'thresholds' => [
            'low_max' => (int) env('FULFILLMENT_PRIORITY_THRESHOLD_LOW_MAX', 30),
            'normal_max' => (int) env('FULFILLMENT_PRIORITY_THRESHOLD_NORMAL_MAX', 60),
            'high_max' => (int) env('FULFILLMENT_PRIORITY_THRESHOLD_HIGH_MAX', 85),
        ],
    ],
    'priority_scores' => [
        'normal' => (int) env('FULFILLMENT_PRIORITY_SCORE_NORMAL', 40),
        'high' => (int) env('FULFILLMENT_PRIORITY_SCORE_HIGH', 70),
        'urgent' => (int) env('FULFILLMENT_PRIORITY_SCORE_URGENT', 95),
    ],
    'commitment' => [
        'default_times' => [
            'morning' => env('FULFILLMENT_COMMITMENT_TIME_MORNING', '11:00'),
            'afternoon' => env('FULFILLMENT_COMMITMENT_TIME_AFTERNOON', '16:00'),
            'evening' => env('FULFILLMENT_COMMITMENT_TIME_EVENING', '19:00'),
            'anytime' => env('FULFILLMENT_COMMITMENT_TIME_ANYTIME', '17:00'),
        ],
    ],
    'risk' => [
        'high_under_minutes' => (int) env('FULFILLMENT_RISK_HIGH_UNDER_MINUTES', 60),
        'medium_today_under_minutes' => (int) env('FULFILLMENT_RISK_MEDIUM_TODAY_UNDER_MINUTES', 180),
    ],
    'timezone' => env('FULFILLMENT_TIMEZONE'),
    'phrase_maps' => [
        'date' => [
            'today' => [
                'hoy',
                'para hoy',
                'lo ocupo hoy',
                'lo necesito hoy',
            ],
            'tomorrow' => [
                'manana',
                'para manana',
            ],
            'day_after_tomorrow' => [
                'pasado manana',
            ],
            'weekdays' => [
                'monday' => [
                    'el lunes',
                    'este lunes',
                ],
                'tuesday' => [
                    'el martes',
                    'este martes',
                ],
                'wednesday' => [
                    'el miercoles',
                    'este miercoles',
                ],
                'thursday' => [
                    'el jueves',
                    'este jueves',
                ],
                'friday' => [
                    'el viernes',
                    'este viernes',
                ],
                'saturday' => [
                    'el sabado',
                    'este sabado',
                ],
                'sunday' => [
                    'el domingo',
                    'este domingo',
                ],
            ],
        ],
        'time_windows' => [
            'morning' => [
                'temprano',
                'en la manana',
                'por la manana',
                'a primera hora',
            ],
            'afternoon' => [
                'en la tarde',
                'por la tarde',
            ],
            'evening' => [
                'en la noche',
                'por la noche',
            ],
            'before_noon' => [
                'antes del mediodia',
                'antes de las 12',
                'antes de medio dia',
            ],
            'after_work' => [
                'despues del trabajo',
                'cuando salga del trabajo',
                'al salir del trabajo',
            ],
            'anytime' => [
                'a cualquier hora',
                'cuando pueda',
                'sin hora especifica',
            ],
        ],
        'delivery_methods' => [
            'pickup' => [
                'yo paso',
                'paso por el',
                'paso por ellos',
                'lo recojo',
                'voy a recoger',
                'retiro en tienda',
                'recojo en sucursal',
            ],
            'delivery' => [
                'mandemelo',
                'enviemelo',
                'me lo envia',
                'entrega',
                'a domicilio',
                'llevemelo',
                'me lo llevan',
            ],
            'express' => [
                'express',
                'urgente con envio',
                'envio inmediato',
                'lo antes posible con entrega',
            ],
            'third_party' => [
                'lo recoge otra persona',
                'pasa mi hermano',
                'pasa un mensajero',
                'recoge un tercero',
            ],
        ],
        'payment_methods' => [
            'cash' => [
                'efectivo',
                'pago en efectivo',
                'contra entrega en efectivo',
            ],
            'sinpe' => [
                'sinpe',
                'sinpe movil',
                'pago por sinpe',
            ],
            'transfer' => [
                'transferencia',
                'deposito',
                'transferencia bancaria',
            ],
            'card' => [
                'tarjeta',
                'pago con tarjeta',
            ],
            'credit' => [
                'credito',
                'fiado',
                'a credito',
            ],
        ],
        'priority' => [
            'urgent' => [
                'urgente',
                'lo necesito ya',
                'para ya',
                'lo antes posible',
                'cuanto antes',
                'inmediatamente',
                'de emergencia',
            ],
            'high' => [
                'para hoy',
                'hoy mismo',
                'antes de las',
                'llega la cuadrilla',
                'lo necesito temprano',
                'no puede esperar',
            ],
        ],
    ],
];
