<?php

return [
    'default' => env('MESSAGING_DEFAULT', 'telegram'),

    'providers' => [
        'telegram',
        'whatsapp',
    ],
];
