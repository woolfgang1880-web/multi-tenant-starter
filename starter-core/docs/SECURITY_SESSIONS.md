# Seguridad y sesiones (FASE 5)

## Contexto

No existía aún un flujo API de autenticación en el starter; esta fase añade **login / refresh / logout / me** con **Laravel Sanctum** (tokens de acceso) más **`user_sessions`** y **`refresh_tokens`**, alineado con la política de **una sesión activa lógica por usuario** y respuestas JSON uniformes.

## Política de sesión activa

- Cada **login** exitoso:
  - Marca sesiones previas del usuario como inactivas (`is_active = false`, `invalidation_reason = superseded_login`, `invalidated_at` rellenado).
  - Revoca refresh tokens previos (`revoked_at`).
  - **No borra** de inmediato los access tokens Sanctum de esas sesiones: el access sigue siendo aceptado por Sanctum hasta que caduque o se limpie, pero el middleware `active.api.session` responde **`SESSION_SUPERSEDED`** si el cliente sigue usándolo.
- Tras **logout**, se eliminan los access tokens de esa sesión (`session_uuid` = nombre del token Sanctum), se invalida la fila en `user_sessions` y se revocan los refresh ligados a esa sesión (`user_session_id`).

## Middleware `active.api.session`

Alias: `active.api.session` → `EnsureActiveApiSession`.

- Colocación recomendada: **`auth:sanctum`**, **`tenant.context`** (si aplica), **`active.api.session`**.
- Comprueba: token Sanctum de tipo **PersonalAccessToken** (no sesión web), fila en `user_sessions` coherente, activa, no expirada (`expires_at` de sesión), sin invalidación previa.
- Actualiza `last_seen_at` en cada petición que pasa.

**Rutas públicas de auth:** `POST /api/v1/auth/login`, `POST /api/v1/auth/refresh` (solo throttle).

**Logout:** `auth:sanctum` + `tenant.context` **sin** `active.api.session`, para poder cerrar sesión aunque la fila de sesión esté rara (siempre que el bearer sea un personal access token válido).

## Formato de respuesta API

Todas las respuestas relevantes usan:

```json
{ "code": "...", "message": "...", "data": null }
```

Códigos habituales: `INVALID_CREDENTIALS`, `ACCOUNT_INACTIVE`, `UNAUTHENTICATED`, `TOKEN_INVALID_OR_REVOKED`, `SESSION_EXPIRED`, `SESSION_INVALID`, `SESSION_SUPERSEDED`, `REFRESH_INVALID`, `REFRESH_EXPIRED`, `TOO_MANY_ATTEMPTS`, `FORBIDDEN`.

## Rate limiting

- `auth-login`: 5 intentos / minuto por clave derivada de IP + `tenant_codigo` + `usuario`.
- `auth-refresh`: 20 / minuto por IP.

Los 429 en API devuelven `TOO_MANY_ATTEMPTS` con el mismo envelope.

## Logs (`security` channel)

Archivo rotativo: `storage/logs/security.log` (canal `security` en `config/logging.php`).

Eventos (sin contraseñas ni tokens completos):

- `auth.login.success` / `auth.login.failed`
- `auth.login.throttled` (clave limitador solo como hash)
- `auth.refresh.success` / `auth.refresh.failed` (incl. throttled)
- `auth.logout`
- `auth.session.superseded`
- `auth.access.denied` (middleware de sesión)

## Sanctum: solo Bearer

En `config/sanctum.php`, `guard` está vacío para **no** autenticar la API vía sesión `web` antes del Bearer. Para SPA con cookies stateful, reintroducir `['web']` y el middleware stateful de Sanctum según la guía oficial.

## Tests y RequestGuard

El guard Sanctum cachea el usuario resuelto por petición. En tests HTTP encadenados, `tests/TestCase.php` llama a `forgetGuards()` tras cada `call()` para evitar falsos positivos. En **Octane**, seguir las recomendaciones de Laravel para estado de autenticación entre requests.

## Cómo probar manualmente

1. `php artisan migrate:fresh --seed`
2. Crear usuario (p. ej. con Tinker) en tenant `DEFAULT` o ampliar seeders.
3. `POST /api/v1/auth/login` con JSON `{ "tenant_codigo", "usuario", "password" }`
4. `GET /api/v1/auth/me` con header `Authorization: Bearer {access_token}`
5. Repetir login: el primer access debe dar `SESSION_SUPERSEDED` en `/me`
6. `POST /api/v1/auth/logout` con Bearer; luego `/me` → `TOKEN_INVALID_OR_REVOKED`
7. `POST /api/v1/auth/refresh` con `{ "refresh_token" }`; el refresh anterior debe fallar con `REFRESH_INVALID`
