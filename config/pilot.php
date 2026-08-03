<?php

return [
    'enabled' => (bool) env('PILOT_ENABLED', true),
    'database_path' => env('PILOT_DB_PATH', database_path('pilot.sqlite')),
    'application_url' => env('PILOT_APP_URL', env('APP_URL', 'http://127.0.0.1:8010')),
    'application_port' => (int) env('PILOT_APP_PORT', 8010),
    'scenario' => env('PILOT_SCENARIO', 'small-hardware-store'),
    'expected_browser' => env('PILOT_EXPECTED_BROWSER', 'chromium'),
    'artifact_paths' => [
        'root' => env('PILOT_ARTIFACT_ROOT', base_path('artifacts/pilot')),
        'latest' => env('PILOT_ARTIFACT_LATEST', base_path('artifacts/pilot/latest')),
        'runs' => env('PILOT_ARTIFACT_RUNS', base_path('artifacts/pilot/runs')),
    ],
    'readiness_thresholds' => [
        'maximum_allowed_console_errors' => (int) env('PILOT_MAX_CONSOLE_ERRORS', 0),
        'maximum_allowed_http_500_responses' => (int) env('PILOT_MAX_HTTP_500_RESPONSES', 0),
    ],
    'expected_channels' => ['whatsapp', 'telegram'],
    'expected_order_counts' => [
        'minimum_orders' => (int) env('PILOT_MIN_ORDERS', 1),
        'minimum_customers' => (int) env('PILOT_MIN_CUSTOMERS', 1),
    ],
    'organization_name' => env('PILOT_ORGANIZATION_NAME', 'Benditio Pilot Hardware Store'),
    'owner_email' => env('PILOT_OWNER_EMAIL', 'owner@local.test'),
    'owner_name' => env('PILOT_OWNER_NAME', 'Pilot Owner'),
    'demo_products' => [
        'bolsas_de_jardin' => [
            'name' => 'Bolsas de jardin',
            'sku' => 'PILOT-JARDIN',
            'unit_label' => 'bolsa',
            'aliases' => ['bolsa jardin', 'bolsas jardin', 'jardin'],
        ],
        'tubos_pvc' => [
            'name' => 'Tubos PVC',
            'sku' => 'PILOT-PVC',
            'unit_label' => 'tubo',
            'aliases' => ['pvc', 'tubo pvc', 'tubos pvc'],
        ],
        'sacos_cemento' => [
            'name' => 'Sacos de cemento',
            'sku' => 'PILOT-CEMENTO',
            'unit_label' => 'saco',
            'aliases' => ['cemento', 'saco cemento', 'cemento gris'],
        ],
        'pintura_blanca' => [
            'name' => 'Pintura blanca',
            'sku' => 'PILOT-PINTURA',
            'unit_label' => 'galon',
            'aliases' => ['pintura', 'pintura blanca', 'galon pintura'],
        ],
        'clavos' => [
            'name' => 'Clavos 2in',
            'sku' => 'PILOT-CLAVOS',
            'unit_label' => 'caja',
            'aliases' => ['clavos', 'caja de clavos', 'clavos 2in'],
        ],
    ],
];
