<?php

return [
    'live_queue_poll_interval_ms' => (int) env('OPERATIONS_LIVE_QUEUE_POLL_INTERVAL_MS', 8000),
    'focus_mode_default' => filter_var(env('OPERATIONS_FOCUS_MODE_DEFAULT', false), FILTER_VALIDATE_BOOL),
];
