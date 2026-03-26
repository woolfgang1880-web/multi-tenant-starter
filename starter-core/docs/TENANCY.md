# Multi-tenant (FASE 3) — contexto y evolución

## Estrategia actual

- Cada **usuario** tiene `tenant_id` (ver `docs/DATABASE.md`).
- El **tenant activo** se obtiene del usuario **autenticado** en el request actual (`$request->user()->tenant`).
- Una sola base de datos; sin subdominios, cabeceras ni multi-DB en esta fase.

### Por qué empezar por usuario autenticado

- Es la vía más simple y segura para API/SPA: el token o sesión ya identifica al usuario y, con él, su empresa.
- Evita ambigüedad (no hace falta inferir tenant desde URL hasta que el producto lo requiera).
- Encaja con login futuro sin rediseñar el núcleo: solo se añaden resolutores o una cadena de estrategias.

## Componentes

| Pieza | Rol |
|-------|-----|
| `TenantResolver` (contrato) | Define cómo se obtiene el `Tenant` a partir del request (extensible). |
| `AuthenticatedUserTenantResolver` | Usuario autenticado → tenant de **`user_sessions.tenant_id`** (sesión Sanctum); si no aplica, fallback a `user->tenant`. |
| `TenantContext` | Almacena el `Tenant` resuelto durante la petición. |
| `TenantManager` | API de aplicación: `resolveFromRequest`, `current()`, `id()`, `clear()`. |
| `ResolveTenantContext` | Middleware: resuelve al inicio y **limpia** al final (`finally`, apto para Octane). |
| Helpers `tenant_manager()`, `current_tenant()`, `current_tenant_id()` | Acceso cómodo desde código de aplicación. |

## Uso del middleware

Registrar el alias `tenant.context` y aplicarlo **después** de autenticar (p. ej. `auth:sanctum`), para que `$request->user()` exista:

```php
Route::middleware(['auth:sanctum', 'tenant.context'])->group(function () {
    // current_tenant() y tenant_manager()->id() disponibles dentro del ciclo del request
});
```

Rutas **sin** autenticación: el resolver devuelve `null`; no debe romper. El middleware puede usarse igual: el contexto quedará vacío.

## Ejemplo de uso en código

```php
$tenantId = current_tenant_id();
$tenant = current_tenant();

// O explícito:
$tenant = tenant_manager()->current();
```

En consultas sobre modelos con `tenant_id`, usar el scope local del trait (sin global scope automático aún):

```php
$id = tenant_manager()->id();
if ($id !== null) {
    Role::forTenant($id)->get();
}
```

## Cómo crecer después (sin implementarlo ahora)

1. **Nuevo resolutor** (`SubdomainTenantResolver`, `HeaderTenantResolver`, etc.) que implemente `TenantResolver`.
2. **Composición**: un resolver “cadena” que pruebe estrategias en orden y delegue en `AuthenticatedUserTenantResolver` como fallback.
3. **Registro**: enlazar el contrato `TenantResolver` a la implementación compuesta en `AppServiceProvider`.
4. El resto (`TenantContext`, `TenantManager`, middleware) puede permanecer igual.

## Aislamiento de datos (fases siguientes)

- **Ahora:** sin global scopes masivos; aislamiento explícito con `BelongsToTenant::scopeForTenant` y convención de filtrar por `tenant_manager()->id()` en repositorios/servicios o políticas.
- **Después:** opcionalmente un `addGlobalScope` en el trait o traits por módulo, o `TenantScope` dedicado, siempre alineado con el mismo `tenant_id` que expone el contexto.

Revisión formal de reglas, puntos auditados y tests: **`docs/PASO3_TENANT_AUTHORIZATION.md`** (PASO 3).
