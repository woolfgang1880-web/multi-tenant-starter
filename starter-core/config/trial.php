<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Periodo de prueba por defecto (empresas nuevas creadas desde Plataforma)
    |--------------------------------------------------------------------------
    |
    | Solo afecta a altas que rellenan trial en `PlatformTenantProvisioningService`.
    | No modifica tenants existentes ni seeds demo.
    |
    */
    'default_trial_days' => max(1, (int) env('TENANT_DEFAULT_TRIAL_DAYS', 14)),

];
