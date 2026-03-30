# Changelog

Registro de releases del **monorepo** (starter-core + starter-web). Formato inspirado en [Keep a Changelog](https://keepachangelog.com/).

## [1.0.0] — starter v1 (congelado)

Fecha de referencia: snapshot etiquetado como **starter v1** (plantilla reusable).

### Incluido

- Monorepo Laravel 11 (`starter-core`) + React + Vite (`starter-web`).
- Autenticación con access/refresh, sesiones, login global y selección de tenant.
- Multi-tenant: contexto HTTP, membresía usuario–tenant, aislamiento en API.
- Usuarios y roles dinámicos por tenant; asignación de roles (sync/attach).
- Autorización por Gates y abilities en `config/authorization.php`.
- **Empresas / tenant**: datos editables; flujo plataforma para operadores globales (`is_platform_admin`).
- Middleware comercial (`commercially.operable`) y reglas de trial/suscripción según implementación.
- **Admin inicial**: `POST /api/v1/platform/tenants/{tenant_codigo}/admins` + UI en plataforma; servicio de aprovisionamiento.
- Demo: `php artisan app:setup-demo`, seeders y documentación de usuarios demo.
- Documentación extensa en `starter-core/docs/`, `docs/README.md` y notas del front.
- Tests Feature en backend (y tests de front donde existan); tests de contrato OpenAPI para lo **documentado** en el YAML.

### Endurecido (hardening)

- Edición de empresa vía API tenant (`PATCH /api/v1/tenant/company` y rutas hermanas) restringida a rol **`admin`** en config; rol **`user`** y sin rol → 403.
- Eliminación del bypass de **`is_platform_admin`** en el Gate `manage-tenant-company` para la API tenant (la plataforma usa rutas `platform/*`).
- Tests de autorización asociados a empresa (tenant).

### Fuera de alcance de la plantilla

- Productos verticales (CRM, facturación, etc.) como módulos terminados.
- Permisos finos por acción (más allá de roles + abilities actuales).
- OpenAPI que cubra el 100% del API real (ver política **parcial intencional** en README y `API_CONTRACT.md`).

### Deuda técnica menor abierta

- Completar OpenAPI para rutas no cubiertas **o** mantener política de cobertura parcial y ampliar por producto derivado.
- Algunos documentos históricos (`PASO*.md`) pueden solaparse; el alcance oficial de v1 está en [`STARTER_SCOPE.md`](STARTER_SCOPE.md).
- `starter-core/README.md` puede seguir siendo el README por defecto de Laravel; el README raíz del monorepo es la entrada principal.

---

## [Unreleased]

Cambios posteriores a v1.0.0 en forks o líneas de producto: documentar en el repositorio del producto derivado.
