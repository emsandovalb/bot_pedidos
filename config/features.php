<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Legacy Lottery Routes
    |--------------------------------------------------------------------------
    |
    | When enabled, the legacy lottery-era routes remain registered for
    | migration and emergency debug work. Keep this disabled in normal use.
    |
    */
    'legacy_lottery_routes_enabled' => (bool) env('LEGACY_LOTTERY_ROUTES_ENABLED', false),
];
