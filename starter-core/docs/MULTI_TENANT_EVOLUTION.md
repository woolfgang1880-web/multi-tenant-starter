# Evolución multi-tenant: análisis, diseño y roadmap

> **Membresía multi-empresa y contexto por sesión (fase 1 nueva):** ver [GLOBAL_USER_MEMBERSHIP.md](./GLOBAL_USER_MEMBERSHIP.md) (tabla `user_tenants`, `user_sessions.tenant_id`, login por membresía, roadmap fases 2+).

## 1. Modelo actual (revisión)

| Pieza | Estado actual |
|-------|----------------|
| **Tenant** | Tabla `tenants`: `codigo` único, `nombre`, `slug` único, `activo`. Equivale a **empresa** en este starter; no hay entidad separada “Empresa” vs “Tenant”. |
| **User** | `tenant_id` principal + **único global `usuario`**; pivote **`user_tenants`** para N empresas; login sigue con `tenant_codigo` pero valida **membresía**; contexto activo en **`user_sessions.tenant_id`**. Ver [GLOBAL_USER_MEMBERSHIP.md](./GLOBAL_USER_MEMBERSHIP.md). |
| **Roles** | Por tenant (`roles.tenant_id`); slugs `super_admin`, `admin`, `user` en `RoleSeeder` por tenant. **No** existe hoy un rol de plataforma fuera de tenant. |
| **Auth** | `AuthSessionService::login` resuelve tenant por código, luego usuario dentro del tenant. Sin “super admin global” en el flujo actual. |
| **Seeders** | `TenantSeeder` (DEFAULT + PRUEBA1), `RoleSeeder`, `DemoUserSeeder` (usuarios por tenant con nombres distintos). Comando `app:setup-demo` para estado reproducible. |
| **Permisos** | Gates/abilities por rol en Laravel; API de usuarios exige capacidad `manage-users` donde aplique. |

**Super administrador global:** no modelado como tal; `super_admin` existe como **rol dentro de cada tenant**, no como identidad sin tenant.

---

## 2. Propuesta de diseño (objetivos A–F)

### A. Super administrador global

- **Opción recomendada (fases 2+):** tabla `platform_users` o flag `users.is_platform_admin` + `tenant_id` nullable solo para cuentas de sistema, **o** usuario en un tenant “sistema” reservado (`codigo` = `PLATFORM`) con roles especiales.
- **API:** rutas bajo prefijo `api/v1/platform/...` con guard/middleware distinto, sin mezclar con `tenant.context` de clientes.
- **No** reutilizar login multi-tenant actual para operaciones de plataforma sin diseño explícito (evitar hacks).

### B. Empresa vs tenant

- Mantener **una sola entidad `Tenant`** como empresa; renombrar en UI/docs si hace falta. Separar solo si aparecen requisitos fuertes (facturación multi-sede, etc.).

### C. Registro self-service (fase 4)

- Endpoint público `POST /api/v1/register` (o similar) que en transacción: crea `Tenant`, usuario admin inicial, roles base, asigna trial 30 días.
- Rate limiting estricto y validación de email si se añade.

### D. Trial y bloqueo

- **Fase 1:** columnas en `tenants` (`trial_starts_at`, `trial_ends_at`, `subscription_status`) — datos listos, **sin** bloqueo automático aún.
- **Fase 3:** middleware `EnsureTenantSubscriptionActive` (o chequeo en `AuthSessionService` + respuestas 403 coherentes) cuando `subscription_status` = `expired` o fecha pasada.

### E. Usuario único global

- **Fase 1 implementada:** restricción DB `UNIQUE(usuario)` y validación `Rule::unique('users','usuario')` sin filtrar por tenant.
- Login sigue siendo `tenant_codigo + usuario`; si el mismo `usuario` no puede existir dos veces, no hay ambigüedad de identidad global para el nombre de login.

### F. Empresa demo PRUEBA1

- Garantizada por `TenantSeeder` + `DemoUserSeeder`; trial de ejemplo en tenant PRUEBA1 vía seeder (`subscription_status = trial`, ventana ~30 días).

---

## 3. Secuencia de implementación recomendada

| Fase | Contenido | Riesgo |
|------|-----------|--------|
| **1** | Migración: unicidad global `usuario`; columnas trial en `tenants`; validaciones; seeders; tests; documentación. | Bajo |
| **2** | Super admin: modelo/guard/rutas platform; crear tenant y admin inicial desde API interna. | Medio |
| **3** | Enforcement trial: middleware o chequeos en login/API; códigos de error estables. | Medio |
| **4** | Registro público + flujo email opcional. | Medio-alto |
| **5** | starter-web: pantallas mínimas (registro, mensaje trial). | Bajo si API estable |

---

## 4. Fase 1 — Qué se implementó (resumen acumulado)

- Migración `2026_03_25_000001_tenant_trial_and_users_usuario_global_unique.php`
  - `tenants`: `trial_starts_at`, `trial_ends_at`, `subscription_status` (nullable).
  - `users`: sustituye `UNIQUE(tenant_id, usuario)` por `UNIQUE(usuario)`.
- Migración `2026_03_26_100000_user_tenants_session_tenant_and_platform_flag.php`
  - `user_tenants`, `user_sessions.tenant_id`, `users.is_platform_admin` (reservado).
  - Login, resolver de tenant, `auth/me`, sync de roles y seeders alineados — ver [GLOBAL_USER_MEMBERSHIP.md](./GLOBAL_USER_MEMBERSHIP.md).
- Modelo `Tenant`: constantes de estado, `fillable`, `casts` para fechas.
- `StoreUserRequest` / `UpdateUserRequest`: unicidad global de `usuario`.
- `DemoUserSeeder`: trial PRUEBA1 + usuario demo multi-empresa `multi_demo`.
- Tests: `GlobalUsuarioUniquenessTest`, `MultiTenantMembershipTest`, etc.

**Comportamiento API:** login sigue exigiendo `tenant_codigo` (compatibilidad); no se bloquea por trial hasta fase 3.

---

## 5. Riesgos

- **Bases existentes** con el mismo `usuario` en dos tenants: la migración **fallará** hasta resolver duplicados manualmente.
- **Producto:** usuarios que esperaban el mismo login en dos empresas deberán usar nombres distintos o unificar identidad (email único en fases futuras).

---

## 6. Cómo probar la fase 1

```bash
cd starter-core
php artisan migrate
php artisan app:setup-demo
php artisan test tests/Feature/GlobalUsuarioUniquenessTest.php
```

Verificar en BD que `users` tiene índice único sobre `usuario` y que `tenants` tiene las columnas nuevas.

---

## 7. Pendientes (fase 2+)

- Modelar super admin y rutas de plataforma.
- Bloqueo por trial vencido y pruebas de contrato OpenAPI.
- Registro self-service y ajustes de frontend.
