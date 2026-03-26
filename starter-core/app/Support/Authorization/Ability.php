<?php

namespace App\Support\Authorization;

/**
 * Nombres de abilities registrados como Gates (uso en middleware `can:`).
 */
final class Ability
{
    public const MANAGE_USERS = 'manage-users';

    public const MANAGE_ROLES = 'manage-roles';

    /**
     * Super admin global (users.is_platform_admin).
     * Permite acciones de plataforma sin depender del tenant activo.
     */
    public const MANAGE_PLATFORM = 'manage-platform';
}
