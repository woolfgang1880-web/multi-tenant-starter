<?php

return [
    'enabled' => (bool) env('METRICS_ENABLED', true),
    'prefix' => env('METRICS_PREFIX', 'metrics'),
    'counter_ttl_seconds' => (int) env('METRICS_COUNTER_TTL_SECONDS', 7 * 24 * 60 * 60),
    'store' => env('METRICS_STORE'),
];

