<?php

namespace App\Support\Auth;

final class AuthErrorCode
{
    public const UNAUTHENTICATED = 'UNAUTHENTICATED';

    public const TOKEN_INVALID_OR_REVOKED = 'TOKEN_INVALID_OR_REVOKED';

    public const SESSION_EXPIRED = 'SESSION_EXPIRED';

    public const SESSION_INVALID = 'SESSION_INVALID';

    public const SESSION_SUPERSEDED = 'SESSION_SUPERSEDED';

    public const REFRESH_INVALID = 'REFRESH_INVALID';

    public const REFRESH_EXPIRED = 'REFRESH_EXPIRED';

    public const INVALID_CREDENTIALS = 'INVALID_CREDENTIALS';

    /** Login global: hay que elegir empresa (respuesta 200, no error). */
    public const TENANT_SELECTION_REQUIRED = 'TENANT_SELECTION_REQUIRED';

    /** Token de selección de tenant inválido o expirado. */
    public const SELECTION_TOKEN_INVALID = 'SELECTION_TOKEN_INVALID';

    public const ACCOUNT_INACTIVE = 'ACCOUNT_INACTIVE';

    public const TOO_MANY_ATTEMPTS = 'TOO_MANY_ATTEMPTS';

    public const FORBIDDEN = 'FORBIDDEN';

    /** Código de empresa inexistente o inactiva (p. ej. cambio de tenant en sesión). */
    public const TENANT_NOT_FOUND = 'TENANT_NOT_FOUND';
}
