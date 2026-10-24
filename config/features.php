<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Developer Toolkit
    |--------------------------------------------------------------------------
    |
    | Simulador de webhooks y generador/borrado de datos demo. Encendido por
    | defecto solo con APP_ENV=local; APP_DEBUG ya no lo habilita, para que un
    | debug olvidado en producción no exponga el reset de datos.
    |
    */
    'developer_toolkit_enabled' => (bool) env('DEVELOPER_TOOLKIT_ENABLED', env('APP_ENV') === 'local'),
];
