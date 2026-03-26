# PASO 2 — Endurecimiento de Auth (Refresh Token)

## Resumen

Implementación de rotación segura de refresh tokens con detección de reutilización (token replay).

## Confirmaciones técnicas (cierre PASO 2)

### Almacenamiento del refresh token

- **En base de datos**: solo se persiste el **hash SHA-256** del secreto en `refresh_tokens.token_hash` (ver `AuthSessionService`: `hash('sha256', $plainRefresh)` al crear y `where('token_hash', $hash)` al buscar).
- **No se guarda el valor en texto plano** en tablas de la aplicación.
- El secreto en claro existe solo en memoria y en la **respuesta HTTP** al cliente (login/refresh), que debe ir por **HTTPS** en producción.

**Pendiente crítica inmediata por tokens sin hashear**: no aplica al estado actual del código: la persistencia ya es solo hash. Si en el futuro se añadiera otra columna o caché con el token en claro, sería una regresión crítica.

### `lockForUpdate()` y transacción

- `lockForUpdate()` se ejecuta **dentro de** `DB::transaction(...)` en `AuthSessionService::refresh()` (misma conexión y transacción activa de Laravel).
- Hay una lectura previa **fuera** de la transacción solo para fallos tempranos (`not_found` tras no encontrar fila, `expired` si la fecha ya pasó), sin mutación ni lock.
- La ruta que **modifica** estado (uso válido o detección de reuse) siempre reabre la fila con `lockForUpdate()` dentro del closure de la transacción.

### Atomicidad de la rotación

- La rotación completa en caso de éxito (borrado de access tokens Sanctum de la sesión, actualización de `user_sessions`, creación del nuevo refresh, `used_at` + `replaced_by_token_id` en el anterior) ocurre **en una sola transacción**; commit o rollback conjunto.
- La invalidación por reuse (`invalidateSessionOnReuse`) también corre **dentro** de esa misma transacción cuando se detecta token ya usado/revocado.

### Logs y tokens en claro

- `SecurityLogger` en eventos de refresh registra `user_id`, `session_uuid`, `ip`, `reason` / `severity`; **no** incluye refresh token ni access token en texto plano ni hash del refresh presentado.
- La clase documenta explícitamente que no se registran contraseñas ni tokens completos (`app/Support/Logging/SecurityLogger.php`).

## Cambios en modelo de datos

**Migración** `2026_03_24_000000_add_used_at_and_replaced_by_to_refresh_tokens.php`:

- `used_at` (timestamp nullable): cuándo el token fue consumido con éxito
- `replaced_by_token_id` (FK nullable): ID del nuevo token que reemplazó a este

## Flujo de rotación

1. **Uso válido** del refresh token:
   - Se marca `used_at = now()`
   - Se crea un nuevo refresh token
   - Se enlaza el viejo con el nuevo mediante `replaced_by_token_id`
   - Se eliminan access tokens anteriores y se emite uno nuevo
   - Log: `auth.refresh.success`

2. **Detección de reutilización** (token ya usado o revocado):
   - Se invalida la sesión completa (`user_session`: `is_active=false`, `invalidated_at`, `invalidation_reason='reuse_detected'`)
   - Se revocan todos los refresh tokens de esa sesión
   - Se eliminan todos los access tokens de esa sesión
   - Log: `auth.refresh.reuse_detected` (severity: high)
   - API responde 401 `REFRESH_INVALID` (sin distinguir motivo por seguridad)

3. **Concurrencia**: `lockForUpdate()` sobre la fila del refresh token evita race conditions; solo una petición puede consumir el token.

## Decisiones de seguridad y límites actuales

- **401 `REFRESH_INVALID` uniforme**: la API no distingue públicamente entre token inexistente, ya usado, revocado o (en algunos flujos) inválido por diseño, para no dar pistas a un atacante.
- **Token usado y token revocado**: ambos se tratan como posible **compromiso** si se intenta usarlos de nuevo; se dispara la misma rama de reuse (invalidación de sesión, revocación de refresh de esa sesión, borrado de access tokens de esa sesión, log `auth.refresh.reuse_detected`).
- **Reuse detection invalida toda la sesión**: no solo se rechaza la petición; se marca la `user_session` como invalidada y se limpian tokens asociados a esa sesión.
- **Pendientes futuros (no implementados en PASO 2)**:
  - Endurecer el almacenamiento del hash (p. ej. **pepper** de aplicación, o KDF más costoso que SHA-256 plano sobre un secreto de alta entropía) si la política de amenazas lo exige.
  - **Métricas y alertas** operativas sobre `auth.refresh.reuse_detected` (dashboards, umbrales, paging).
  - **Política de expiración más avanzada** (TTL por riesgo, rotación forzada, límites por dispositivo/IP).

## Archivos modificados

| Archivo | Cambio |
|---------|--------|
| `database/migrations/2026_03_24_000000_add_used_at_and_replaced_by_to_refresh_tokens.php` | Nueva migración |
| `app/Models/RefreshToken.php` | Campos `used_at`, `replaced_by_token_id`, relación `replacedBy()` |
| `app/Services/Auth/AuthSessionService.php` | Rotación con `used_at`, detección de reuse, `invalidateSessionOnReuse()` |
| `app/Support/Logging/SecurityLogger.php` | `refreshReuseDetected()` |
| `tests/Feature/SecurityCriticalTest.php` | Tests PASO 2 |
| `docs/PASO2_AUTH_HARDENING.md` | Documentación y cierre de revisión PASO 2 |

## API

Sin cambios respecto al contrato externo: 401 `REFRESH_INVALID` para token inválido, expirado o reutilizado (y otros códigos ya existentes para `REFRESH_EXPIRED` / `SESSION_INVALID` según `RefreshController`).

## Tests y cómo ejecutarlos

Suite completa:

```bash
cd starter-core
php artisan test
```

Solo endurecimiento / seguridad de refresh:

```bash
php artisan test --filter=SecurityCriticalTest
```

Flujo general de sesión (incluye rotación básica):

```bash
php artisan test --filter=AuthSessionFlowTest
```

| Test | Valida |
|------|--------|
| `test_refresh_token_rotates_correctly` | Cadena de rotación, tokens antiguos fallan |
| `test_refresh_token_cannot_be_reused` | Segundo uso devuelve 401 |
| `test_refresh_token_reuse_returns_invalid` | Access tokens invalidados tras reuse |
| `test_reusing_refresh_token_revokes_session` | Sesión completa revocada tras reuse |
| `test_revoked_session_cannot_refresh` | Refresh tokens de sesión invalidada fallan |
| `test_only_one_concurrent_refresh_succeeds` | Solo un refresh concurrente tiene éxito |
| `test_security_event_logged_on_token_reuse` | Log `auth.refresh.reuse_detected` |
