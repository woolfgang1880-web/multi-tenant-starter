# Usuario global y membresía multi-empresa

Documento maestro: diseño, flujo objetivo, fases, riesgos y estado de implementación. Complementa [MULTI_TENANT_EVOLUTION.md](./MULTI_TENANT_EVOLUTION.md) (trial, unicidad de `usuario`, etc.).

---

## 1. Análisis del modelo anterior a esta fase

| Pieza | Comportamiento |
|-------|----------------|
| **users** | Una fila por identidad; `tenant_id` como “tenant principal” (hogar). Tras migraciones previas, `usuario` es **único global**. |
| **tenants** | Empresa = tenant; sin entidad separada. |
| **roles** | Por tenant (`roles.tenant_id`); `user_roles` enlaza usuario ↔ rol. |
| **Login** | `tenant_codigo` + `usuario` + `password`. El usuario debía tener `users.tenant_id` = tenant del login. |
| **Contexto HTTP** | `AuthenticatedUserTenantResolver` tomaba `user->tenant` (siempre el FK `users.tenant_id`). |
| **auth/me** | Devolvía `tenant` y `roles` alineados con `users.tenant_id`, no con la empresa elegida en el login. |
| **Seeders** | Un usuario demo distinto por tenant (excepto evolución posterior). |

**Problema:** un mismo login global no podía pertenecer a varias empresas con roles distintos por empresa sin que el contexto efectivo siguiera siendo solo `users.tenant_id`.

---

## 2. Diseño pragmático recomendado

1. **Identidad:** un `usuario` único en toda la plataforma (ya aplicado en fase previa).
2. **Pertenencia:** tabla pivote **`user_tenants`** (`user_id`, `tenant_id`) con unicidad por par. `users.tenant_id` se mantiene como **tenant principal** (compatibilidad, listados, migración gradual).
3. **Contexto activo:** columna **`user_sessions.tenant_id`**: la empresa en la que el usuario inició sesión en **esa** sesión. El resolver de tenant usa la sesión Sanctum (nombre del token = `session_uuid`) para leer `UserSession.tenant_id`; si no hay token de sesión, hace **fallback** a `user->tenant`.
4. **Roles por empresa:** sin cambiar el esquema de `roles`: los roles siguen siendo por tenant; un usuario multi-empresa tiene varias filas en `user_roles` apuntando a roles de distintos `tenant_id`. Las comprobaciones (`hasRoleSlug`, gates) usan **`current_tenant_id()`** (sesión), no solo `users.tenant_id`.
5. **Super admin global:** columna reservada **`users.is_platform_admin`** (boolean, default `false`). **Sin** rutas ni login específico en esta fase (evitar hacks). La política de uso se define en fase 2.

**Flujo objetivo (completo, varias fases):**

1. Login con `usuario` + `password` (sin `tenant_codigo` obligatorio).
2. Backend devuelve empresas accesibles (`user_tenants` + metadatos).
3. Si hay una sola empresa, fija contexto y emite tokens como hoy.
4. Si hay varias, el cliente elige empresa; el backend fija `user_sessions.tenant_id` (o endpoint `switch-tenant`).
5. Toda la API usa el tenant activo de la sesión.

---

## 3. Cambios de modelo (fase 1 implementada)

| Elemento | Detalle |
|----------|---------|
| **`user_tenants`** | N:N usuario ↔ tenant; backfill desde `users.tenant_id` para filas existentes. |
| **`user_sessions.tenant_id`** | FK a `tenants`; backfill desde `users.tenant_id` en sesiones existentes. |
| **`users.is_platform_admin`** | Reservado; sin lógica de autorización aún. |
| **Login** | Busca usuario por `usuario` global, valida contraseña y **`belongsToTenantId(tenant del login)`**. Misma respuesta 401 si no es miembro (sin filtrar motivo). |
| **`UserObserver`** | Al crear usuario, asegura fila en `user_tenants` para `users.tenant_id`. |
| **`DemoUserSeeder`** | Usuario **`multi_demo`**: miembro de DEFAULT y PRUEBA1; admin en DEFAULT, `user` en PRUEBA1. |
| **`UserRoleAssignmentService::sync`** | Conserva roles de **otros** tenants al sincronizar uno (no borra roles ajenos al tenant actual). |
| **`UserService` listado / find** | Incluye usuarios con membresía en el tenant, no solo `users.tenant_id`. |

---

## 4. Compatibilidad temporal (post fase 2)

| Sigue vigente (convivencia) | Deprecado / sustituido como camino principal |
|----------------------------|-----------------------------------------------|
| `POST /api/v1/auth/login` con **`tenant_codigo`** + `usuario` + `password` (mismo comportamiento que fase 1) | El flujo **recomendado** es login **sin** `tenant_codigo`: una empresa → tokens; varias → `code: TENANT_SELECTION_REQUIRED` + `POST .../auth/login/select-tenant` |
| Clientes que solo envían credenciales y **no** manejan selección | Deben seguir enviando `tenant_codigo` hasta migrar |
| **`users.tenant_id`** como hogar y datos legados | Sigue siendo necesario para backfill y UX; la pertenencia efectiva es `user_tenants` + sesión |
| Respuesta 200 de login con solo `data` de tokens cuando `code === OK` | Sin cambio para ese caso; nuevo subcaso 200 con `code === TENANT_SELECTION_REQUIRED` |

**Convivencia:** un mismo endpoint `POST /auth/login` decide por presencia de `tenant_codigo` (no vacío → login con tenant explícito; vacío u omitido → login global).

---

## 5. Roadmap por fases

| Fase | Contenido | Estado |
|------|-----------|--------|
| **Previo** | `usuario` único global; trial en `tenants`; validaciones | Hecho |
| **1 (esta)** | `user_tenants`, `user_sessions.tenant_id`, resolver + login por membresía, roles/`me` por contexto, sync de roles multi-tenant, seeder `multi_demo`, tests | **Hecho** |
| **2** | Login global (`usuario` + `password`); lista de empresas + `selection_token`; `POST /auth/login/select-tenant`; OpenAPI + tests (`GlobalLoginPhase2Test`, contrato OpenAPI); `users.is_platform_admin` como super admin global + rutas platform para crear tenants y admin inicial | **Hecho** (login global + selección + platform admin) |
| **3** | `POST /auth/switch-tenant` con sesión válida; actualiza `user_sessions.tenant_id`; `GET /auth/me` incluye `accessible_tenants`; auditoría `auth.tenant.switch`; selector en header starter-web | **Hecho** |
| **4** | E2E browser opcionales (login + switch) | Pendiente (UI login fase 2 + switch fase 3 ya en header) |
| **5** | Enforcement trial / bloqueo si aplica | Ver MULTI_TENANT_EVOLUTION |

---

## 6. Riesgos

- **Datos:** usuarios creados antes de `user_tenants` quedan cubiertos por la migración; los creados después vía observer. Rutas que inserten usuarios sin pasar por Eloquent deben sincronizar el pivote manualmente.
- **Sync de roles:** operaciones admin deben seguir limitadas al `current_tenant_id()`; el `sync` ya no elimina roles de otros tenants.
- **Sesiones antiguas:** si alguna fila tuviera `user_sessions.tenant_id` nulo, el resolver cae a `user->tenant` (degradación segura).

---

## 7. Qué se implementó (fase 1) — referencia de archivos

- Migración: `database/migrations/2026_03_26_100000_user_tenants_session_tenant_and_platform_flag.php`
- Modelos: `User` (relaciones, `belongsToTenantId`, `tenantForActiveContext`, `rolesForTenant` con contexto), `UserSession` (`tenant_id`)
- Observer: `app/Observers/UserObserver.php` + registro en `AppServiceProvider`
- Auth: `AuthSessionService` (login por usuario global + membresía), `AuthenticatedUserTenantResolver`
- API: `MeController` (roles y `tenant` del contexto activo)
- Servicios: `UserService`, `UserRoleAssignmentService`
- Factory: `UserFactory::configure()` para pivote en tests
- Seeders: `DemoUserSeeder`
- Tests: `tests/Feature/MultiTenantMembershipTest.php`; ajuste de conteo en `SetupDemoCommandTest`

Además en Fase 2: super admin global (`users.is_platform_admin`) y endpoints de plataforma para crear tenants y el admin inicial (`PlatformAdminTenancyTest`).

**Fase 2 (login global):** `AuthSessionService::loginGlobal`, `completeLoginSelection`; `LoginController`, `LoginSelectTenantController`, `LoginSelectTenantRequest`; `AuthErrorCode::TENANT_SELECTION_REQUIRED`, `SELECTION_TOKEN_INVALID`; `config/auth-session.php` → `login_selection_ttl_seconds`; rutas en `routes/api.php`; tests `tests/Feature/GlobalLoginPhase2Test.php` y contrato en `tests/Feature/OpenApiContractTest.php`; OpenAPI en `docs/openapi/openapi.yaml`.

**Fase 3 (switch tenant en sesión):** `AuthSessionService::switchSessionTenant`, `accessibleActiveTenants()` público; `SwitchTenantController`, `SwitchTenantRequest`; `SecurityLogger::tenantSwitched`, `tenantSwitchDenied`; métricas `auth.tenant_switch.success` / `auth.tenant_switch.denied`; throttle `auth-switch-tenant`; `MeController` añade `data.accessible_tenants`; `AuthErrorCode::TENANT_NOT_FOUND`; tests `tests/Feature/SwitchTenantPhase3Test.php` y contrato switch en `OpenApiContractTest`; starter-web: `switchSessionTenant`, `syncStoredUserFromMe`, selector en `DashboardHeader.jsx`.

---

## 8. Cómo probar

Estabilidad del login clásico + diagnóstico: [LOGIN_STABILITY_PHASE1.md](./LOGIN_STABILITY_PHASE1.md).

```bash
cd starter-core
php artisan migrate
php artisan app:setup-demo
php artisan test tests/Feature/MultiTenantMembershipTest.php
php artisan test tests/Feature/LoginEndToEndStabilityTest.php
php artisan test tests/Feature/GlobalLoginPhase2Test.php
php artisan test tests/Feature/SwitchTenantPhase3Test.php
php artisan test

# UI (starter-web) — switch tenant sin relogin
cd ../starter-web
npm test -- --run src/App.tenant-switch-ui.test.jsx
```

**Manual API (fase 3 — cambio de empresa con sesión):**

1. Login (cualquier modo) como `multi_demo` en `DEFAULT` o `PRUEBA1`.
2. `GET /api/v1/auth/me` → revisar `data.accessible_tenants` (≥ 2 empresas).
3. `POST /api/v1/auth/switch-tenant` con header `Authorization: Bearer <access_token>` y body `{ "tenant_codigo": "PRUEBA1" }` (o `DEFAULT`).
4. Sin nuevo login, `GET /api/v1/auth/me` debe mostrar `data.tenant` y `data.user.roles` alineados al nuevo contexto.
5. Intentar `tenant_codigo` de empresa sin membresía (p. ej. `PRUEBAS`) → **403** `FORBIDDEN`; código inexistente → **404** `TENANT_NOT_FOUND`.

**Manual starter-web:** con usuario multi-empresa, en el header aparece el desplegable **Empresa**; al cambiar, se llama a switch + refresco de `/me` y el menú (p. ej. Usuarios) sigue las reglas del rol en el tenant activo.

**Manual API (super admin global — plataforma):**

1. Login como `admin_demo` (password: `Admin123!`) (o cualquier usuario con `users.is_platform_admin = true`). En dev, `php artisan app:setup-demo` deja `admin_demo` marcado como super admin global.
2. `POST /api/v1/platform/tenants` con `{ "nombre": "...", "codigo": "...", "activo": true }` → crea la empresa.
3. `POST /api/v1/platform/tenants/{tenant_codigo}/admins` con:
   - `admin_usuario`
   - `admin_password` + `admin_password_confirmation`
   - `admin_codigo_cliente` (opcional)

4. Validación: haz login normal en el tenant recién creado con el usuario admin creado; en `/api/v1/auth/me` deberías ver rol `admin` en ese tenant.

**Manual starter-web (plataforma UI mínima):**

1. Login como `admin_demo` (is_platform_admin=true; password: `Admin123!`).
2. En el sidebar aparece la sección **Plataforma** (no depende del tenant activo normal).
3. Usa los formularios:
   - “Crear tenant”: nombre, código y activo.
   - “Crear admin inicial”: tenant código + usuario + contraseña (y opcional `admin_codigo_cliente`).
4. Verifica el éxito con los mensajes de la UI y luego valida vía API `/api/v1/auth/me` dentro del tenant creado.

**Manual API (fase 2):**

1. Login global: `POST /api/v1/auth/login` con `{ "usuario": "admin_demo", "password": "Admin123!" }` → `code: OK` y tokens (una sola empresa).
2. Multi-empresa: mismo endpoint con `{ "usuario": "multi_demo", "password": "MultiDemo123!" }` → `code: TENANT_SELECTION_REQUIRED` y `data.tenants` + `data.selection_token`.
3. Completar: `POST /api/v1/auth/login/select-tenant` con `{ "selection_token": "<token>", "tenant_codigo": "PRUEBA1" }` → tokens; luego `GET /api/v1/auth/me` debe reflejar ese tenant y roles.

**Manual starter-web:** dejar “Tenant código” vacío, usuario `multi_demo` y contraseña `MultiDemo123!` (o botón “global · multi_demo”); elegir empresa y “Continuar en esta empresa”.

**Contrato:** `docs/openapi/openapi.yaml` (Swagger en la app: ruta de documentación existente del proyecto).

---

## 9. Pendientes (tras fase 3)

- Enforcement **trial** / bloqueo por suscripción: ver [TRIAL_SUBSCRIPTION.md](./TRIAL_SUBSCRIPTION.md) (fase 1 implementada en `AuthSessionService`).
- E2E browser opcionales (login global + switch tenant).
- **Optimistic UI** o invalidación de caché de datos de página al cambiar tenant (hoy el usuario debe saber que el contexto cambió; listas ya cargadas pueden ser del tenant anterior hasta recargar navegación).
