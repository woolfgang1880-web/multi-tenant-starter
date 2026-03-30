# Contrato API (fuente de verdad)

Este documento enlaza la **especificación OpenAPI** del starter y fija expectativas para consumidores (p. ej. el futuro **starter-web**).

## Archivo oficial

| Recurso | Ubicación |
|--------|-----------|
| Especificación OpenAPI 3.0 | [`openapi/openapi.yaml`](openapi/openapi.yaml) |

Para endpoints **presentes en** `openapi.yaml`, los cambios de rutas, cuerpos, códigos o envelope deben mantener **implementación y spec alineados** en el mismo PR, sin divergencia.

**Política starter v1:** el YAML tiene **cobertura parcial intencional** (no lista todo el API real). Las rutas no documentadas en OpenAPI siguen **solo** en código y tests hasta que un producto derivado amplíe el contrato. Ver README raíz del monorepo y [`STARTER_SCOPE.md`](../../STARTER_SCOPE.md).

## Visualizar (Swagger UI)

Con la aplicación en marcha (`php artisan serve` u otro host):

1. Abrir en el navegador: **`/docs/api`** (vista Swagger UI embebida, sin paquetes extra).
2. La especificación se sirve en **`/docs/openapi.yaml`** (`Content-Type: application/yaml`).

Alternativa sin levantar Laravel: copiar `docs/openapi/openapi.yaml` en [editor.swagger.io](https://editor.swagger.io/) o cargar el archivo en Swagger UI estático.

## Envelope JSON

Todas las respuestas documentadas usan:

```json
{
  "code": "string",
  "message": "string",
  "data": null
}
```

- En éxito habitual, `code` es `OK` (salvo convenciones futuras documentadas).
- En error, `code` es un identificador estable (`AuthErrorCode`, `ApiErrorCode`, etc.).
- Validación: `code` = `VALIDATION_ERROR`, `data.errors` = objeto campo → array de mensajes.

## Autenticación y middleware (resumen)

| Área | Middleware / notas |
|------|-------------------|
| `POST .../auth/login`, `POST .../auth/refresh` | Públicos (rate limit propio). |
| `POST .../auth/logout` | `auth:sanctum`, `tenant.context` (sin `active.api.session`). |
| `GET .../auth/me` | `auth:sanctum`, `tenant.context`, `active.api.session`, **`commercially.operable`** (revalida `Tenant::allowsApiAccess()`; si falla → `403` `SUBSCRIPTION_EXPIRED`). |
| Usuarios y asignación de roles | + `commercially.operable` + `can:manage-users`. |
| Roles CRUD | + `commercially.operable` + `can:manage-roles`. |
| Empresa (`PATCH .../tenant/company`, `POST .../tenant/company/inactivate`, `POST .../tenant/company/reactivate`) | + `commercially.operable` + `can:manage-tenant-company`. Reglas de rol: ver **Edición de empresa (API tenant)**. |
| `POST .../users` | Si `tenants.subscription_status` es **trial** y ya hay ≥1 usuario miembro (misma regla que el listado), **403** `TRIAL_USER_LIMIT_REACHED` (además de **403** `FORBIDDEN` sin `manage-users`). |

Bearer: cabecera `Authorization: Bearer <access_token>` (token devuelto en login/refresh).

### Edición de empresa (API tenant) vs plataforma

**Rutas bajo `/api/v1/tenant/company` (mismo Gate y middleware que la tabla anterior):** el tenant activo es el de la sesión (login / `switch-tenant`).

- **Quién puede:** solo usuarios que tengan en el **tenant activo** el rol con slug **`admin`** (`manage-tenant-company`; config `authorization.abilities.manage_tenant_company` = `['admin']`).
- **Quién no puede (respuesta 403):**
  - usuarios con rol **`user`** en ese tenant;
  - usuarios **sin** rol asignado en ese tenant;
  - usuarios con **`is_platform_admin`** que **no** tengan rol **`admin`** en ese tenant (la bandera de plataforma **no** actúa como sustituto en estas rutas).

En denegación por autorización, el envelope suele llevar **`code`: `FORBIDDEN`** (salvo otro código documentado para el mismo caso).

**Separación tenant vs plataforma**

| Situación | Ruta a usar |
|-----------|-------------|
| Editar datos de empresa **como miembro del tenant** (p. ej. flujo del cliente) | `PATCH /api/v1/tenant/company` (y, si aplica, `POST .../tenant/company/inactivate` / `reactivate`) con token de sesión y rol **`admin`** en ese tenant. |
| Editar datos de empresa **como operador de plataforma** (soporte / backoffice) | `PATCH /api/v1/platform/tenants/{tenant_codigo}` con autorización **`manage-platform`** (no usar la ruta tenant anterior para este fin). |

**Ejemplos breves (comportamiento esperado)**

- **Permitido:** usuario con rol `admin` en el tenant `DEFAULT` → `PATCH /api/v1/tenant/company` con cuerpo válido → **200**, `code` = `OK` (salvo validación u otras reglas de negocio).
- **Denegado:** usuario con rol `user` en el mismo tenant → mismo `PATCH` → **403**, `code` = `FORBIDDEN`.

## Multi-tenant y autorización

- El tenant activo se obtiene del **usuario autenticado**; no hace falta enviar `tenant_id` en el cuerpo para delimitar datos.
- En **login** sí se envía `tenant_codigo` para ubicar la cuenta.
- La autorización actual usa **roles dinámicos** y **Gates** (`manage-users`, `manage-roles`, `manage-tenant-company`); la evolución a permisos finos está descrita en [`AUTHORIZATION.md`](AUTHORIZATION.md).

## Uso por el futuro starter-web

- El cliente debe generar tipos, clientes HTTP o mocks a partir de **`docs/openapi/openapi.yaml`** (o de su publicación estable en CI).
- **No** inventar endpoints, query params ni formas de `data` no documentadas.
- Cualquier necesidad nueva de API implica **cambio de contrato + backend** en el mismo flujo de trabajo.

## Referencias

- Autorización: [`AUTHORIZATION.md`](AUTHORIZATION.md)
- Tenancy: [`TENANCY.md`](TENANCY.md) (si existe en el repo)
