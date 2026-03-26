# Autorización base (FASE 7)

## Estrategia

Se usan **Gates de Laravel** con middleware **`can:`** en rutas, sin duplicar `if` en controladores. Los Gates consultan **roles dinámicos** (tabla `roles`, pivote `user_roles`) mediante slugs configurables.

### Por qué Gates y no solo Policies

- Las rutas administrativas agrupan varias acciones (CRUD + asignación); un Gate por **capacidad** (`manage-users`, `manage-roles`) encaja bien con middleware de ruta.
- Las **Policies** siguen siendo adecuadas en fases futuras para reglas por modelo (`UserPolicy`, `RolePolicy`) si se desea granularidad por acción; los Gates actuales pueden delegar internamente en esas policies sin cambiar las rutas.

## Archivos clave

| Pieza | Ubicación |
|-------|-----------|
| Mapeo slugs → habilidad | `config/authorization.php` (`abilities.manage_users`, `abilities.manage_roles`) |
| Nombres de abilities (Gates) | `App\Support\Authorization\Ability` |
| Registro Gates | `AppServiceProvider::configureAuthorizationGates()` |
| Comprobación en modelo | `User::hasAnyRoleSlug()`, `rolesForTenant()` (roles del **tenant activo** de la sesión, vía `current_tenant_id()`, con fallback a `users.tenant_id`) |
| Rutas | `routes/api.php` — grupos con `can:manage-users` y `can:manage-roles` |

## Endpoints protegidos

- **`manage-users`**: todo `/api/v1/users` y `/api/v1/users/{id}/roles` (tras `auth:sanctum`, `tenant.context`, `active.api.session`).
- **`manage-roles`**: todo `/api/v1/roles`.

Usuarios con rol `user` (u otros no listados en config) reciben **403** con envelope estándar (`FORBIDDEN`, mensaje genérico).

## Multi-tenant

`rolesForTenant()` filtra por `roles.tenant_id` igual al tenant de contexto HTTP (sesión de login), no solo al `users.tenant_id`. La autorización **no sustituye** las consultas acotadas en servicios; refuerza el acceso a las rutas administrativas.

## Respuestas y logs

- **403 API**: `AuthErrorCode::FORBIDDEN` vía manejador de `AccessDeniedHttpException` en `bootstrap/app.php` (Laravel transforma antes el `AuthorizationException` de los Gates en esa excepción; el log solo se escribe si la causa previa es autorización).
- **Log**: `security` → `auth.authorization.denied` con `user_id`, `tenant_id`, `path` y un extracto del mensaje de la excepción (sin datos sensibles).

## Evolución a permisos finos

1. Añadir tablas `permissions` y `role_permissions` (como en `docs/DATABASE.md`).
2. Sustituir el cuerpo de los Gates por algo del estilo `$user->hasPermission('users.manage')`, resolviendo permisos vía roles.
3. Opcional: mover la lógica a **Policies** por recurso y llamarlas desde los Gates o desde el middleware personalizado.
4. Mantener `config/authorization.php` como **lista de permisos** en lugar de slugs de rol, o generar mapeos por entorno.

No se ha implementado `permissions` en esta fase para no bloquear ni adelantar migraciones innecesarias.

## Cómo probar

```bash
php artisan test --filter=AuthorizationApiTest
php artisan test --filter=UsersAndRolesApiTest
```

- Usuario solo con rol `user` → 403 en `/api/v1/users` y `/api/v1/roles`.
- Usuario con rol `admin` o `super_admin` (según config) → acceso permitido.
