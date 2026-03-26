<?php

return [

    'access_ttl_minutes' => (int) env('AUTH_ACCESS_TTL_MINUTES', 60),

    'refresh_ttl_days' => (int) env('AUTH_REFRESH_TTL_DAYS', 14),

    /** Segundos de validez del token opaco devuelto cuando hay varias empresas (Fase 2). */
    'login_selection_ttl_seconds' => (int) env('AUTH_LOGIN_SELECTION_TTL_SECONDS', 600),

];
