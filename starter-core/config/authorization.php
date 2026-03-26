<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Habilidades → slugs de rol (tenant)
    |--------------------------------------------------------------------------
    |
    | Los gates usan estos listados para decidir acceso. Los slugs deben existir
    | en la tabla `roles` del tenant (sembrados o creados por API).
    | Más adelante se puede sustituir la comprobación por permisos granulares.
    |
    */

    'abilities' => [
        'manage_users' => ['super_admin', 'admin'],
        'manage_roles' => ['super_admin', 'admin'],
    ],

];
