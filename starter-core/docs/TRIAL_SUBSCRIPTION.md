# Trial y estado de suscripción (empresa / tenant)

Este documento fija el **diseño pragmático** de la fase 1: columnas ya existentes en `tenants`, reglas de acceso API, respuestas HTTP y próximos pasos (sin lógica comercial compleja).

## Modelo de datos (ya en BD)

| Campo | Uso |
|--------|-----|
| `trial_starts_at` | Inicio del periodo de prueba (nullable). |
| `trial_ends_at` | Fin del periodo de prueba (nullable). Si está en el pasado y el estado es `trial`, el acceso se bloquea. |
| `subscription_status` | `trial`, `active`, `expired`, `suspended` o **null** (ver reglas). |

Constantes en `App\Models\Tenant`: `SUBSCRIPTION_TRIAL`, `SUBSCRIPTION_ACTIVE`, `SUBSCRIPTION_EXPIRED`, `SUBSCRIPTION_SUSPENDED`.

## Reglas de acceso API (`Tenant::allowsApiAccess()`)

1. **`activo = false`** → sin acceso (la query de login ya filtra `activo = true`; el chequeo refuerza refresh y datos inconsistentes).

2. **`subscription_status` null o vacío** → **acceso permitido** (compatibilidad: empresas existentes sin módulo de facturación / demo).

3. **`active`** → acceso permitido.

4. **`suspended` / `expired`** → acceso **bloqueado**.

5. **`trial`** → acceso permitido si:
   - `trial_ends_at` es **null** (trial abierto en desarrollo), o
   - `now() <= trial_ends_at` (fin inclusive respecto al reloj del servidor).

Estados desconocidos se tratan como permitidos (evita bloqueos accidentales); conviene alinear datos por migración si aparece basura.

## Dónde se aplica el enforcement (fase 1)

| Punto | Comportamiento |
|--------|----------------|
| Login con `tenant_codigo` | Tras validar credenciales y membresía: si no hay acceso → **403** `SUBSCRIPTION_EXPIRED`. |
| Login global + lista de empresas | Solo aparecen tenants con `allowsApiAccess()`. Si ninguna queda → 401 como “sin acceso”. |
| Selección de empresa (`login/select-tenant`) | Igual que login. |
| `POST /auth/switch-tenant` | Destino debe permitir acceso → **403** `SUBSCRIPTION_EXPIRED` si no. |
| `POST /auth/refresh` | Si el tenant de la sesión ya no permite acceso: sesión invalidada (`invalidation_reason = subscription_blocked`), **403** `SUBSCRIPTION_EXPIRED`. |

**No** se añade todavía middleware en cada petición autenticada: el access token tiene TTL corto; el refresh corta sesiones largas cuando el trial expira. Una fase posterior puede añadir chequeo en `EnsureActiveApiSession` para cortar al instante.

## Respuesta HTTP al bloquear

- **Código de error estable:** `SUBSCRIPTION_EXPIRED` (`App\Support\Auth\AuthErrorCode::SUBSCRIPTION_EXPIRED`).
- **HTTP:** **403** en login, selección, switch y refresh bloqueados.
- **Mensaje:** texto claro en español (periodo de prueba finalizado o acceso no disponible).

## Perfil (`GET /auth/me`)

En `data.tenant` se exponen además:

- `subscription_status`
- `trial_starts_at`, `trial_ends_at` (ISO 8601)

Así el front puede mostrar estado sin deducir solo por errores.

## Empresas creadas desde Plataforma

`PlatformTenantProvisioningService::createTenant()` rellena por defecto:

- `trial_starts_at` = ahora  
- `trial_ends_at` = ahora + `config('trial.default_trial_days')` (env `TENANT_DEFAULT_TRIAL_DAYS`, default **14**)  
- `subscription_status` = `trial`

El listado `GET /api/v1/platform/tenants` incluye estos campos en cada ítem.

## Roadmap sugerido (siguientes fases)

1. **Transición automática** `trial` → `expired` al pasar `trial_ends_at` (job o observer; idempotente).
2. **Middleware** post-`auth:sanctum` que valide `allowsApiAccess()` en cada request (revocar sesión opcional).
3. **Renovación / `active`**: flujo manual o integración de pago (fuera de alcance actual).
4. **starter-web:** banner “trial termina el …” y pantalla dedicada cuando `SUBSCRIPTION_EXPIRED` (**implementado**, ver abajo).

## Flujo en UI (starter-web, fase 3)

Secuencia de experiencia cuando el tenant está en **trial**:

1. **Trial activo** — El usuario ve el banner informativo (`TenantTrialStatusBanner`) con mensaje de prueba activa hasta la fecha.
2. **Warning** — Si quedan **7 días o menos**, el banner pasa a estado advertencia y puede mostrar texto aclaratorio sobre el bloqueo al vencer.
3. **Expirado en backend** — Si el periodo de prueba ya no permite acceso (`Tenant::allowsApiAccess()` falso), la API responde **403** con código estable **`SUBSCRIPTION_EXPIRED`** (login, selección de empresa, `switch-tenant`, `refresh`, etc.).
4. **Pantalla bloqueada** — El front **limpia la sesión local** cuando aplica y **navega a** `#/subscription-expired` (`SubscriptionExpiredPage`): mensaje claro de periodo finalizado, texto para contactar y enlace a iniciar sesión. **No** se muestra el layout del panel (sidebar/menú completo); el marco visual sigue alineado con la vista de login (marca Ohtli).

**Rutas públicas relacionadas:** `#/login` (acceso), `#/subscription-expired` (acceso bloqueado por suscripción/trial).

**Redirección automática** cuando la API devuelve `SUBSCRIPTION_EXPIRED`:

| Momento | Comportamiento |
|--------|----------------|
| **Login** o **select-tenant** | Tras el error, redirección a `#/subscription-expired` (sin quedar en el formulario con un toast genérico como única pista). |
| **Refresh** (`POST /auth/refresh`) | Misma redirección; no se debe mostrar solo el aviso genérico de “sesión expirada” si el motivo es suscripción. |
| **Bootstrap** (`GET /auth/me` con token guardado) | Redirección a la pantalla bloqueada; no se fuerza `#/login` con mensaje de sesión inválida para este código. |
| **Cualquier `apiFetch` autenticado** con 403 `SUBSCRIPTION_EXPIRED` | Limpieza de sesión y redirección a `#/subscription-expired`. |

## Cómo probar

1. Migraciones aplicadas y tests: `php artisan test tests/Unit/TenantSubscriptionAccessTest.php tests/Feature/TrialSubscriptionEnforcementTest.php`
2. Manual: crear tenant con `subscription_status = trial` y `trial_ends_at` en el pasado → login debe responder **403** con `code: SUBSCRIPTION_EXPIRED`.
3. Tras login válido, actualizar el tenant en BD a trial vencido y llamar **refresh** → **403** y sesión invalidada.

## Cómo probar en UI (starter-web)

1. Inicia sesión con un tenant que tenga `subscription_status = trial` (demo).
   - Debes ver un banner arriba del contenido con:
     - `Tu prueba gratuita está activa hasta el ...` si está lejos del vencimiento.
     - `Tu prueba gratuita vence en ...` si queda poco.

2. En **Plataforma**, abre el listado de empresas:
   - Para cada tenant verás:
     - `Suscripción`
     - `Trial inicia`
     - `Trial termina`

3. (Opcional para debug) Si quieres que el warning se vea más rápido:
   - baja temporalmente `TENANT_DEFAULT_TRIAL_DAYS` en `starter-core/.env` (ej. `3`),
   - vuelve a crear un tenant desde **Plataforma**.

4. **Pantalla de acceso bloqueado (`#/subscription-expired`)**  
   - Con la API en marcha, fuerza un **403** `SUBSCRIPTION_EXPIRED` (por ejemplo tenant en trial con `trial_ends_at` vencido e intento de login con ese tenant, o sesión válida y luego `trial_ends_at` en el pasado + **refresh**).  
   - Debes ver la página dedicada (sin sidebar), con “Tu periodo de prueba ha finalizado” y el texto de contacto, y el botón “Volver al inicio de sesión” que lleva a `#/login`.  
   - También puedes abrir manualmente `http://localhost:5173/#/subscription-expired` (o el puerto de Vite) sin sesión para revisar el copy y el layout.

5. **Tests automáticos (starter-web):** `npm run test:run` — incluyen redirección y limpieza ante `SUBSCRIPTION_EXPIRED` en `api/client` y el flujo desde `LoginForm`.

## Fase 4 — Acciones post-bloqueo (sin pagos)

Cuando el usuario ya **no puede entrar** (`SUBSCRIPTION_EXPIRED`), la app ofrece:

1. **Volver al inicio de sesión** — `#/login`.
2. **Solicitar activación** — `POST /api/v1/subscription/request-activation` (público, con rate limit). Registra una fila en `subscription_activation_requests` (IP, user agent, datos opcionales `tenant_codigo`, `contact_email`, `message`). Devuelve **201** con `data.received: true`. No hay pasarela de pago.
3. **Contactar soporte** (opcional) — si en el front defines `VITE_SUPPORT_EMAIL` en `.env`, se muestra un enlace `mailto:` en la pantalla `#/subscription-expired`.

**Plataforma (super admin):** en el listado de empresas, si el tenant está en **`trial`**, puede **Activar** (`subscription_status` → `active`) o **Suspender** (`→ suspended`) vía `PATCH /api/v1/platform/tenants/{tenant_codigo}/subscription` con cuerpo `{ "subscription_status": "active"|"suspended" }`. Solo aplica desde estado **trial**; otros estados devuelven **422**.

### Cómo probar (fase 4)

- **API solicitud:** `curl -X POST http://127.0.0.1:8000/api/v1/subscription/request-activation -H "Content-Type: application/json" -d "{}"` → esperar **201** y fila en BD.
- **API plataforma:** login como `is_platform_admin`, `PATCH` a un tenant en trial con `subscription_status: active`.
- **UI:** pantalla `#/subscription-expired` — botones “Solicitar activación” y “Volver…”; en Plataforma, fila trial con “Activar” / “Suspender”.
- **Tests:** `php artisan test tests/Feature/SubscriptionActivationRequestTest.php tests/Feature/PlatformTenantSubscriptionUpdateTest.php` y `npm run test:run` en `starter-web`.
