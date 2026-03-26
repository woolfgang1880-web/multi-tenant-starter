# Usuarios y roles dinámicos (FASE 6)

## Visión general

Módulo **API-first** para administrar usuarios y roles dentro del **tenant actual** (resuelto por `tenant.context` tras autenticación). Sin permisos granulares aún: cualquier usuario autenticado con sesión activa puede usar estos endpoints (en fases posteriores conviene políticas por rol).

## Multi-tenant

- El `tenant_id` de los registros **no** se envía en el cuerpo para crear usuarios: siempre se usa `current_tenant_id()`.
- Consultas y validaciones (`unique`, `exists`) están acotadas al tenant.
- Un recurso de otro tenant produce **404** (`NOT_FOUND`) sin filtrar por mensajes que revelen existencia.

## CRUD de usuarios

| Método | Ruta | Descripción |
|--------|------|-------------|
| GET | `/api/v1/users` | Listado paginado (`per_page` opcional, 1–100). Incluye roles resumidos. |
| POST | `/api/v1/users` | Alta: `usuario`, `password` + `password_confirmation`, opcionales `codigo_cliente`, `fecha_alta`, `activo`. |
| GET | `/api/v1/users/{id}` | Detalle (sin `password_hash`). |
| PUT | `/api/v1/users/{id}` | Actualización parcial/total: `usuario`, `password`+confirmación, `codigo_cliente`, `fecha_alta`, `activo`. |
| PATCH | `/api/v1/users/{id}/deactivate` | Inactivación lógica (`activo = false`) + revocación de tokens/sesiones/refresh del usuario. |

No hay borrado físico de usuarios en esta fase.

## Roles dinámicos

Los roles viven en tabla `roles` (por tenant). No hay slugs fijos en código.

| Método | Ruta | Descripción |
|--------|------|-------------|
| GET | `/api/v1/roles` | Listado paginado (`per_page` opcional). |
| POST | `/api/v1/roles` | `nombre`, `slug` (solo `a-z`, `0-9`, `_`), `descripcion` opcional. |
| GET | `/api/v1/roles/{id}` | Detalle. |
| PUT | `/api/v1/roles/{id}` | Actualización de nombre, slug y descripción. |

`slug` es único **por tenant**.

## Asignación de roles

| Método | Ruta | Comportamiento |
|--------|------|----------------|
| PUT | `/api/v1/users/{id}/roles` | **Sincroniza** el conjunto: cuerpo `{ "role_ids": [1,2] }`. `role_ids` puede ser `[]` para quitar todos. Usa regla **`present` + `array`** (no `required`, porque en Laravel `required` rechaza arrays vacíos). |
| POST | `/api/v1/users/{id}/roles` | **Añade** roles sin quitar los existentes; `role_ids` debe tener al menos un id. Sin duplicar filas en pivote. |

Los `role_ids` deben existir en `roles` del mismo tenant (`exists` en FormRequest).

## Seguridad y middleware

Rutas bajo: `auth:sanctum`, `tenant.context`, `active.api.session`.

- Validación con Form Requests en `App\Http\Requests\Api\V1\...`.
- Lógica en `UserService`, `RoleService`, `UserRoleAssignmentService`.
- Inactivación: `UserAccessRevoker` revoca access Sanctum, marca sesiones y revoca refresh tokens.

## Logs (canal `security`)

Eventos `admin.*`: creación/actualización/inactivación de usuario, sincronización/anexo de roles, creación/actualización de rol. Sin datos sensibles.

## Permisos futuros

La tabla `roles` y la pivote `user_roles` siguen el modelo RBAC descrito en `docs/DATABASE.md`. Las políticas de autorización (quién puede crear usuarios, editar roles, etc.) se pueden añadir sin cambiar el contrato de estos endpoints.

## Ejemplos rápidos

**Crear usuario** (con Bearer del tenant):

```http
POST /api/v1/users
Content-Type: application/json

{
  "usuario": "operador1",
  "password": "secreto123",
  "password_confirmation": "secreto123"
}
```

**Sincronizar roles**:

```http
PUT /api/v1/users/5/roles
Content-Type: application/json

{ "role_ids": [1, 3] }
```

**Inactivar**:

```http
PATCH /api/v1/users/5/deactivate
```
