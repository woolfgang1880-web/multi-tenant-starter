<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Fiscal / onboarding (Plataforma)
    |--------------------------------------------------------------------------
    |
    | La unicidad global de RFC en alta de tenant ya no se aplica en CreateTenantRequest
    | (varias empresas pueden compartir RFC). Esta clave se mantiene por compatibilidad
    | con despliegues que aún la lean en documentación u otras capas.
    */
    'allow_duplicate_rfc' => (bool) env('ALLOW_DUPLICATE_RFC', env('APP_ENV') === 'local'),
];

