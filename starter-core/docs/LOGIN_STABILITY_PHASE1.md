# Estabilidad del login tras Fase 1 (multi-empresa)

## Causas habituales de fallo

1. **Migraciones no aplicadas en MySQL**  
   Error típico: `Table 'starter_core.user_tenants' doesn't exist`.  
   Otro error frecuente: **`Unknown column 'tenant_id' in 'field list'`** al insertar en `user_sessions` — la columna la añaden `2026_03_26_100000_*` y, si hiciera falta, la migración idempotente `2026_03_27_000000_ensure_user_sessions_tenant_id_column`.  
   **Acción:** desde `starter-core`, con el `.env` correcto: `php artisan migrate`.  
   Resumen E2E y tabla demo: `docs/LOGIN_E2E_AUDIT.md`.

2. **Sesión frontend inconsistente**  
   El login devolvía tokens pero `GET /api/v1/auth/me` fallaba (p. ej. 500); el SPA guardaba tokens sin usuario y el menú/authz quedaba roto.  
   **Corrección:** si `/auth/me` falla justo después del login, el cliente **revierte** tokens y usuario en `localStorage` y muestra el error en el formulario.

3. **Usuario sin fila en `user_tenants`**  
   Datos antiguos o inserciones fuera de Eloquent pueden dejar `users.tenant_id` correcto pero sin pivote.  
   **Corrección backend:** `User::belongsToTenantId()` admite también coincidencia por `users.tenant_id` (además del pivote), manteniendo el requisito de pivote para empresas que no son el tenant principal del usuario.

## Contrato API (sin cambio de forma obligatorio)

- **`POST /api/v1/auth/login`:** body `{ tenant_codigo, usuario, password }`; respuesta `data` con `access_token`, `refresh_token`, `expires_in`, `session_uuid`.
- **`GET /api/v1/auth/me`:** con `Authorization: Bearer …`; `data.user` (incl. `roles` del **tenant activo** de la sesión) y `data.tenant` (esa misma empresa).

El frontend (`starter-web`) ya lee `body.data` y persiste `user` + `roles` + `tenant` tras un `/me` exitoso.

## Cómo probar manualmente

1. API: `php artisan migrate` y `php artisan app:setup-demo`.
2. Tests core: `php artisan test tests/Feature/LoginEndToEndStabilityTest.php`
3. Front: `npm run dev`, usar atajos en login:
   - **PRUEBAS · admin** (`admin_pruebas`)
   - **multi · DEFAULT** / **multi · PRUEBA1** (`multi_demo`)
4. Tras entrar: `/dashboard` y, si el rol activo es admin, entrada a **Usuarios**.

## Archivos relevantes (corrección estabilización)

- `app/Models/User.php` — `belongsToTenantId()`
- `starter-web/src/api/client.js` — rollback si `/me` falla tras login
- `starter-web/src/components/LoginForm.jsx` — mensaje 5xx y atajos multi-empresa
- Tests: `tests/Feature/LoginEndToEndStabilityTest.php`, `starter-web/src/api/client.test.js`

## Antes de Fase 2

Con migraciones aplicadas y estos ajustes, el flujo **actual** (con `tenant_codigo`) queda alineado con `user_tenants` y `user_sessions.tenant_id` sin introducir login sin tenant.
