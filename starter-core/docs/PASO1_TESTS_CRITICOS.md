# PASO 1 — Tests críticos backend (Laravel API-first)

## Archivos de tests

| Archivo | Cobertura |
|---------|-----------|
| `tests/Feature/AuthSessionFlowTest.php` | Auth: login, refresh, logout, auth/me, sesión inválida/expirada/superseded |
| `tests/Feature/UsersAndRolesApiTest.php` | Usuarios CRUD, roles, multi-tenant, validación password |
| `tests/Feature/AuthorizationApiTest.php` | 403 para usuario sin permisos |
| `tests/Feature/SecurityCriticalTest.php` | Seguridad: refresh no reutilizable, IDOR, roles, usuario inactivo, logs |

## Cobertura por requisito

### 1. AUTH
| Caso | Test | Archivo |
|------|------|---------|
| Login correcto | `test_login_me_logout_and_me_denied` | AuthSessionFlowTest |
| Login incorrecto | `test_invalid_credentials_same_shape` | AuthSessionFlowTest |
| Refresh válido | `test_refresh_rotates_tokens_and_old_refresh_fails` | AuthSessionFlowTest |
| Refresh inválido | (mismo test: reusar refresh viejo falla) | AuthSessionFlowTest |
| Logout | `test_login_me_logout_and_me_denied` | AuthSessionFlowTest |
| auth/me con token válido | `test_login_me_logout_and_me_denied` | AuthSessionFlowTest |
| auth/me sin token | `test_auth_me_without_token_returns_401` | AuthSessionFlowTest |

### 2. SESIÓN
| Caso | Test | Archivo |
|------|------|---------|
| Sesión inválida | `test_auth_me_with_invalid_token_returns_401`, `test_session_invalid_when_session_row_missing` | AuthSessionFlowTest |
| Sesión expirada | `test_session_expired_returns_401` | AuthSessionFlowTest |
| Sesión reemplazada | `test_second_login_supersedes_first_access_token` | AuthSessionFlowTest |

### 3. USUARIOS
| Caso | Test | Archivo |
|------|------|---------|
| Crear usuario | `test_full_user_and_role_flow` | UsersAndRolesApiTest |
| No crear sin password | `test_cannot_create_user_without_password` | UsersAndRolesApiTest |
| No crear con password corto | `test_cannot_create_user_with_password_too_short` | UsersAndRolesApiTest |
| Actualizar usuario | `test_full_user_and_role_flow` | UsersAndRolesApiTest |
| Inactivar usuario | `test_full_user_and_role_flow` (patch deactivate) | UsersAndRolesApiTest |

### 4. ROLES
| Caso | Test | Archivo |
|------|------|---------|
| Crear rol | `test_full_user_and_role_flow` | UsersAndRolesApiTest |
| Asignar rol | `test_full_user_and_role_flow` (POST/PUT roles) | UsersAndRolesApiTest |
| Usuario sin permiso → 403 | `test_plain_user_cannot_manage_users_or_roles` | AuthorizationApiTest |

### 5. MULTI-TENANT
| Caso | Test | Archivo |
|------|------|---------|
| No acceder datos de otro tenant | `test_cannot_access_user_from_other_tenant` | UsersAndRolesApiTest |

### 6. SEGURIDAD CRÍTICA (SecurityCriticalTest)
| Caso | Test | Valida |
|------|------|--------|
| Refresh token no reutilizable | `test_refresh_token_is_not_reusable` | Segundo uso del mismo refresh devuelve 401 REFRESH_INVALID |
| Refresh reuse invalida acceso anterior | `test_refresh_token_reuse_returns_invalid` | El access token previo deja de funcionar; el nuevo sigue activo |
| Doble refresh concurrente | `test_rapid_double_refresh_second_fails` | Solo un refresh exitoso; el segundo falla con REFRESH_INVALID |
| IDOR update cross-tenant | `test_idor_cannot_update_user_from_other_tenant` | 404 al intentar actualizar usuario de otro tenant por ID |
| IDOR deactivate cross-tenant | `test_idor_cannot_deactivate_user_from_other_tenant` | 404 al desactivar usuario de otro tenant |
| IDOR assign roles cross-tenant | `test_idor_cannot_assign_roles_to_user_from_other_tenant` | 404 al asignar roles a usuario de otro tenant |
| Escalación de privilegios (roles) | `test_basic_user_cannot_assign_roles_to_self_or_others` | 403 al intentar autoasignarse rol admin |
| Escalación (crear roles) | `test_basic_user_cannot_create_roles` | 403 al crear roles sin permiso |
| Usuario duplicado (mismo tenant) | `test_duplicate_usuario_in_same_tenant_returns_422` | 422 con error en `usuario` al duplicar username |
| Usuario inactivo no puede login | `test_inactive_user_cannot_login` | 403 ACCOUNT_INACTIVE |
| Log login fallido | `test_security_log_on_login_failed` | Log `auth.login.failed` con `bad_credentials` |
| Log refresh reutilizado | `test_security_log_on_refresh_token_reuse` | Log `auth.refresh.failed` con `revoked` |

## Límites actuales / pendiente para Paso 2

- **Token inválido vs reutilizado**: La API devuelve el mismo código `REFRESH_INVALID` para token no encontrado, expirado o reutilizado. El cliente no puede distinguirlos. El log interno sí distingue (`not_found`, `expired`, `revoked`), pero la respuesta HTTP no.
- **Revocación de sesión por replay**: Cuando se detecta reutilización de refresh token, solo se rechaza la petición (401). No se revoca la sesión completa. Si un atacante reutilizó el token, la sesión legítima sigue activa. La revocación proactiva de toda la sesión ante reuse queda para Paso 2.
- **Tests de logs**: Leen el archivo físico del canal `security` (`storage/logs/security-YYYY-MM-DD.log`). Dependen del driver `daily` en `config/logging.php`. No usan `Log::fake()` ni assertions sobre el canal.
- **Detección robusta de reuse y rotación**: Detección fuerte de replay (p.ej. bloom filters, ventanas temporales) y rotación más estricta de tokens quedan para Paso 2.

## Cómo ejecutar

```bash
cd starter-core
php artisan test
```

Filtrar por suite:
```bash
php artisan test --filter=AuthSessionFlowTest
php artisan test --filter=UsersAndRolesApiTest
php artisan test --filter=AuthorizationApiTest
php artisan test --filter=SecurityCriticalTest
```
