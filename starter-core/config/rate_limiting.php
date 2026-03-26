<?php

/**
 * Límites por riesgo (PASO 4). Los closures en AppServiceProvider leen estos valores en cada petición.
 * Override en tests con config(['rate_limiting.*' => ...]).
 */
return [

    'auth_login' => [
        'max_attempts' => (int) env('RATE_LIMIT_AUTH_LOGIN_MAX', 5),
        'decay_seconds' => (int) env('RATE_LIMIT_AUTH_LOGIN_DECAY', 60),
    ],

    'auth_refresh' => [
        'per_token_max_attempts' => (int) env('RATE_LIMIT_REFRESH_PER_TOKEN_MAX', 15),
        'per_ip_max_attempts' => (int) env('RATE_LIMIT_REFRESH_PER_IP_MAX', 40),
        'decay_seconds' => (int) env('RATE_LIMIT_REFRESH_DECAY', 60),
    ],

    'auth_logout' => [
        'max_attempts' => (int) env('RATE_LIMIT_LOGOUT_MAX', 30),
        'decay_seconds' => (int) env('RATE_LIMIT_LOGOUT_DECAY', 60),
    ],

    'auth_me' => [
        'max_attempts' => (int) env('RATE_LIMIT_ME_MAX', 180),
        'decay_seconds' => (int) env('RATE_LIMIT_ME_DECAY', 60),
    ],

    'auth_switch_tenant' => [
        'max_attempts' => (int) env('RATE_LIMIT_SWITCH_TENANT_MAX', 40),
        'decay_seconds' => (int) env('RATE_LIMIT_SWITCH_TENANT_DECAY', 60),
    ],

    'admin_users_store' => [
        'max_attempts' => (int) env('RATE_LIMIT_ADMIN_USER_CREATE_MAX', 25),
        'decay_seconds' => (int) env('RATE_LIMIT_ADMIN_DECAY', 60),
    ],

    'admin_user_roles' => [
        'max_attempts' => (int) env('RATE_LIMIT_ADMIN_USER_ROLES_MAX', 80),
        'decay_seconds' => (int) env('RATE_LIMIT_ADMIN_DECAY', 60),
    ],

    'admin_roles_store' => [
        'max_attempts' => (int) env('RATE_LIMIT_ADMIN_ROLE_CREATE_MAX', 25),
        'decay_seconds' => (int) env('RATE_LIMIT_ADMIN_DECAY', 60),
    ],

];
