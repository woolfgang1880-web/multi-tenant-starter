# PASO 8 — Auditoría y observabilidad de seguridad

## Objetivo

Mejorar trazabilidad de eventos sensibles con un formato homogéneo y sin exponer secretos, manteniendo bajo riesgo y sin cambiar el contrato HTTP de la API.

## Cambios aplicados

### 1) Formato consistente de eventos

Se normalizó el contexto en ambos loggers del canal `security`:

- `app/Support/Logging/SecurityLogger.php`
- `app/Support/Logging/AdminAuditLogger.php`

Campos mínimos comunes (cuando aplica):

- `type`
- `severity`
- `actor_user_id`
- `tenant_id`
- `target_type`
- `target_id`
- `session_id`
- `ip`
- `user_agent`
- `metadata`

Además, por compatibilidad operativa, se conservan campos históricos en varios eventos (`user_id`, `session_uuid`, `reason`, etc.).

### 2) Cobertura de eventos sensibles

Eventos presentes y revisados:

- Auth:
  - `auth.login.success`
  - `auth.login.failed`
  - `auth.logout`
  - `auth.refresh.success`
  - `auth.refresh.failed`
  - `auth.refresh.reuse_detected`
  - (adicionales ya existentes) `auth.login.throttled`, `auth.access.denied`, `auth.session.superseded`, `auth.authorization.denied`
- Admin/auditoría:
  - `admin.user.created`
  - `admin.user.deactivated`
  - `admin.user.roles.attached`
  - `admin.user.roles.synced`
  - (también existentes) `admin.user.updated`, `admin.role.created`, `admin.role.updated`

### 3) Señales de red (ip/user_agent)

- En Auth, `AuthSessionService` ahora pasa `user_agent` a:
  - `SecurityLogger::loginFailed(...)`
  - `SecurityLogger::refreshFailed(...)`
  - `SecurityLogger::refreshReuseDetected(...)`

## No exposición de secretos

Verificado en diseño y tests:

- No se registran tokens raw, contraseñas ni secretos.
- En `auth.login.throttled` se guarda solo hash de clave (`limiter_key_suffix`).
- En `refresh` se usa contexto mínimo (`reason`, ids y metadatos operativos).
- `metadata` está pensada para datos mínimos y seguros (no payloads completos).

## Tests agregados/ajustados

### `tests/Feature/SecurityCriticalTest.php`

- `test_security_log_on_login_failed`:
  - valida `type`, `severity`, `metadata.reason`, `ip`
  - valida ausencia de contraseña en claro
- `test_security_event_logged_on_token_reuse`:
  - valida `type`, `severity`, `session_id`
  - valida ausencia del refresh token raw

### `tests/Feature/UsersAndRolesApiTest.php`

- `test_admin_audit_events_have_minimum_consistent_structure`:
  - dispara `admin.user.created`, `admin.user.roles.synced`, `admin.user.deactivated`
  - valida `type`, `severity`, `target_type` y metadata mínima

## Límites actuales / pendientes

- **Canal único** (`security`): útil para starter, pero en crecimiento conviene separar `security` vs `admin_audit` o enrutar por `type`.
- **Correlación distribuida**: no se adjunta `request_id`/`trace_id`; recomendable si se integra observabilidad distribuida.
- **Esquema fuerte de eventos**: hoy es convención de código + tests de contenido; no hay validador de schema para logs.
- **Sin SIEM externo**: intencional en este paso (fuera de alcance).

## Cómo probar

```bash
cd starter-core
php artisan test tests/Feature/SecurityCriticalTest.php tests/Feature/UsersAndRolesApiTest.php
php artisan test
```

