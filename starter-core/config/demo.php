<?php

/**
 * Demo / desarrollo local.
 *
 * En APP_ENV=local, por defecto se vuelven a aplicar las contraseñas en texto plano
 * del DemoUserSeeder al ejecutar seed (útil si la BD ya tenía usuarios demo con otro hash).
 * En testing/production desactivado salvo DEMO_RESET_DEMO_PASSWORDS=true.
 */
$explicit = env('DEMO_RESET_DEMO_PASSWORDS');

return [
    'reset_demo_passwords_on_seed' => $explicit !== null && $explicit !== ''
        ? filter_var($explicit, FILTER_VALIDATE_BOOLEAN)
        : env('APP_ENV') === 'local',
];
