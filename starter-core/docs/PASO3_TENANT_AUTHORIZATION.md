# PASO 3 — Endurecimiento de autorización multi-tenant

## Objetivo

Garantizar que ningún usuario autenticado pueda leer, modificar o relacionar recursos fuera de su `tenant_id`, incluso manipulando IDs en la URL o payloads (IDOR y relaciones cruzadas).

## Reglas de aislamiento

1. **Tenant activo**: Tras `auth:sanctum`, el middleware `tenant.context` resuelve el tenant del usuario autenticado (`current_tenant_id()`). Los controladores usan `Controller::tenantId()` para obtener el ID obligatorio.
2. **Consultas**: Toda lectura/escritura de usuarios y roles pasa por servicios que filtran explícitamente:
   - `UserService`: `where('tenant_id', $tenantId)` en listados y `findForTenantOrFail`.
   - `RoleService`: `Role::forTenant($tenantId)` (trait `BelongsToTenant`).
3. **Route model binding**: No se usa binding implícito de `User`/`Role` por ID; los IDs son enteros en ruta y se resuelven siempre con `findForTenantOrFail` / equivalente.
4. **Validación de relaciones**: `StoreUserRequest`, `UpdateUserRequest`, `AttachUserRolesRequest`, `SyncUserRolesRequest` y `UpdateRoleRequest` usan `Rule::unique` / `Rule::exists` con `where('tenant_id', current_tenant_id())`. `UserRoleAssignmentService::assertRolesBelongToTenant` refuerza que todos los `role_ids` existan en el tenant antes de `sync`/`attach`.
5. **Creación**: `UserService::create` y `RoleService::create` fijan `tenant_id` desde el parámetro del servicio (origen: `tenantId()` del controlador), no desde el body del cliente.
6. **Respuestas**: `ModelNotFoundException` en API se traduce a **404** con código `NOT_FOUND` (“Recurso no encontrado”), sin filtrar existencia cross-tenant.
7. **Listados**: Solo se acepta `per_page` validado; parámetros extra (p. ej. `tenant_id` en query) son **ignorados** por la capa de aplicación — el listado siempre usa el tenant del contexto.

## Puntos revisados (auditoría PASO 3)

| Área | Estado |
|------|--------|
| `routes/api.php` | Rutas admin con `tenant.context` + abilities; IDs con `whereNumber('id')`. |
| `UserController` / `RoleController` / `DeactivateUserController` / `UserRolesController` | Delegan en servicios con `tenantId()`. |
| `UserService` | Paginación y CRUD acotados por `tenant_id`. |
| `RoleService` | Igual con `forTenant`. |
| `UserRoleAssignmentService` | Usuario vía `findForTenantOrFail`; roles vía `assertRolesBelongToTenant`. |
| Form requests de usuarios/roles | Unicidad y `exists` acotados al tenant actual. |
| `User` (modelo) | No usa aún el trait `BelongsToTenant`; el aislamiento depende de **servicios** y requests — riesgo si en el futuro se añaden consultas directas al modelo sin filtro. |
| `Role` (modelo) | Usa `BelongsToTenant` y `scopeForTenant`. |

## Tests agregados

Archivo: `tests/Feature/TenantIsolationApiTest.php`

| Test | Cobertura |
|------|-----------|
| `test_cannot_show_user_from_other_tenant_returns_404` | GET usuario ajeno |
| `test_cannot_show_role_from_other_tenant_returns_404` | GET rol ajeno |
| `test_list_users_never_includes_other_tenant_despite_query_params` | Listado + query manipulada (`tenant_id`, `page`) |
| `test_list_roles_never_includes_other_tenant_despite_query_params` | Igual para roles |
| `test_cannot_attach_role_from_other_tenant_returns_422` | POST roles con `role_id` de otro tenant |
| `test_cannot_sync_role_from_other_tenant_returns_422` | PUT sync con mezcla inválida |
| `test_cannot_update_user_from_other_tenant_returns_404` | PUT usuario ajeno |
| `test_cannot_update_role_from_other_tenant_returns_404` | PUT rol ajeno |
| `test_cannot_deactivate_user_from_other_tenant_returns_404` | PATCH deactivate ajeno |
| `test_cannot_assign_roles_to_user_from_other_tenant_returns_404` | PUT roles sobre usuario ajeno |

Tests relacionados en otras suites: `SecurityCriticalTest` (IDOR y escalación), `UsersAndRolesApiTest::test_cannot_access_user_from_other_tenant`.

## Cómo ejecutar

```bash
cd starter-core
php artisan test --filter=TenantIsolationApiTest
```

Suite completa:

```bash
php artisan test
```

## Límites actuales y riesgos pendientes

- **Sin global scope en `User`**: Cualquier código nuevo que haga `User::find($id)` o `User::query()->whereKey($id)` sin `tenant_id` puede filtrar cross-tenant. Convención: usar siempre `UserService` o añadir `where('tenant_id', current_tenant_id())`.
- **Sin endpoint de borrado físico** de usuarios/roles en la API actual; si se añade, debe seguir el mismo patrón `findForTenantOrFail`.
- **Parámetros de listado**: Hoy solo `per_page`. Si se añaden filtros (`search`, `sort`), deben aplicarse **después** del `where('tenant_id', …)` para no reintroducir fugas.
- **Gates (`can:MANAGE_*`)**: Complementan pero no sustituyen el filtrado por tenant; la autorización de “puede administrar” no implica acceso a datos de otro tenant.

No se modificó `starter-web` ni el contrato JSON de respuestas exitosas.
