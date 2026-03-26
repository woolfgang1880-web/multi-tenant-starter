# PASO 4 — Rate limiting por riesgo

## Objetivo

Límites de petición **diferenciados** por superficie de ataque (auth vs administración), no solo un throttle global de API.

## Estrategia

- **Laravel `RateLimiter` nombrado** registrado en `AppServiceProvider::configureRateLimiting()`.
- Middleware `throttle:<nombre>` en rutas concretas (`routes/api.php`).
- Umbrales y ventanas en `config/rate_limiting.php`, sobreescribibles con variables `RATE_LIMIT_*` y con `config([...])` en tests.
- Respuesta **429** uniforme vía `bootstrap/app.php` → `ApiResponse` con código `TOO_MANY_ATTEMPTS` (ya existente).

## Endpoints limitados

| Ruta | Limiter | Clave (`by`) | Comportamiento |
|------|---------|--------------|----------------|
| `POST /api/v1/auth/login` | `auth-login` | `sha1(ip \| tenant_codigo \| usuario)` | Fuerza bruta por IP + identificador de cuenta (tenant + usuario). |
| `POST /api/v1/auth/refresh` | `auth-refresh` | **Dos** límites: (1) `sha256(refresh_token):ip`, (2) `ip` | Abuso por reutilización del mismo cuerpo de token + techo por IP. |
| `POST /api/v1/auth/logout` | `auth-logout` | `tenant_id : user_id` | Tras `auth:sanctum`. |
| `GET /api/v1/auth/me` | `auth-me` | `tenant_id : user_id` | Tras sesión activa. |
| `POST /api/v1/users` | `admin-users-store` | `tenant_id : user_id` | Creación masiva de usuarios. |
| `POST/PUT …/users/{id}/roles` | `admin-user-roles` | `tenant_id : user_id` | Attach y sync comparten contador. |
| `POST /api/v1/roles` | `admin-roles-store` | `tenant_id : user_id` | Creación de roles. |

Rutas **sin** throttle dedicado en este paso: listados/show/update/deactivate de usuarios y roles (siguen sin límite específico; se puede añadir `admin-read` en el futuro).

## Límites por defecto (ventana `decay_seconds`)

Definidos en `config/rate_limiting.php` (override con `.env`):

| Clave config | Env (opcional) | Default |
|--------------|----------------|---------|
| `auth_login.max_attempts` | `RATE_LIMIT_AUTH_LOGIN_MAX` | 5 / 60 s |
| `auth_refresh.per_token_max_attempts` | `RATE_LIMIT_REFRESH_PER_TOKEN_MAX` | 15 / 60 s |
| `auth_refresh.per_ip_max_attempts` | `RATE_LIMIT_REFRESH_PER_IP_MAX` | 40 / 60 s |
| `auth_logout.max_attempts` | `RATE_LIMIT_LOGOUT_MAX` | 30 / 60 s |
| `auth_me.max_attempts` | `RATE_LIMIT_ME_MAX` | 180 / 60 s |
| `admin_users_store.max_attempts` | `RATE_LIMIT_ADMIN_USER_CREATE_MAX` | 25 / 60 s |
| `admin_user_roles.max_attempts` | `RATE_LIMIT_ADMIN_USER_ROLES_MAX` | 80 / 60 s |
| `admin_roles_store.max_attempts` | `RATE_LIMIT_ADMIN_ROLE_CREATE_MAX` | 25 / 60 s |

## Tests

Archivo: `tests/Feature/RateLimitingTest.php`

| Test | Qué valida |
|------|------------|
| `test_login_throttled_after_max_attempts` | Tras N intentos (config bajo), login devuelve 429. |
| `test_refresh_throttled_after_abuse_same_token_body` | Mismo `refresh_token` inválido repetido agota cupo **per-token**. |
| `test_admin_create_user_throttled_per_actor` | `POST /users` 429 tras exceso por actor. |
| `test_login_quota_isolated_per_tenant_and_usuario` | Tenant/usuario distinto no hereda el bloqueo de otro par (misma IP en tests). |
| `test_admin_create_user_quota_isolated_per_tenant_actor` | Admin en tenant B puede crear aunque admin en tenant A esté limitado. |
| `test_auth_me_throttled_after_excess` | `GET auth/me` 429 tras exceso. |
| `test_admin_sync_roles_throttled_per_actor` | `PUT …/roles` 429 tras exceso (mismo limiter que attach). |

### Ventana temporal y reset

- Los tests **no esperan** el tiempo real de decay: bajan `max_attempts` vía `config()` y comprueban el 429 inmediato.
- El reset obedece al driver de caché (`array` en PHPUnit) y al `decay_seconds` del limiter; en producción usar **Redis** u otro store compartido si hay varias instancias de PHP.

## Riesgos y pendientes

- **Login**: el contador cuenta **todas** las peticiones al endpoint (éxito o fallo), no solo credenciales inválidas; reduce la superficie de fuerza bruta con la misma clave.
- **Refresh**: con token distinto en cada intento, solo aplica el límite **per_ip** (el per-token es por hash del cuerpo).
- **Multi-instancia**: con `CACHE_STORE=array` o por-request, cada worker tiene su propio contador; en horizontal scaling hace falta caché compartido.
- **Endpoints de lectura/actualización** genéricos sin throttle dedicado: evaluar `admin-users-read`, `admin-roles-update`, etc., si hay abuso.
- **OpenAPI**: si se documentan límites, mantenerlos alineados con este archivo.

## Cómo ejecutar

```bash
cd starter-core
php artisan test --filter=RateLimitingTest
php artisan test
```
