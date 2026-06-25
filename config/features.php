<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Legacy Routes
    |--------------------------------------------------------------------------
    |
    | When enabled, the legacy routes remain registered for migration and
    | emergency debug work. Keep this disabled in normal use.
    |
    */
    'legacy_lottery_routes_enabled' => (bool) env('LEGACY_LOTTERY_ROUTES_ENABLED', false),
];
