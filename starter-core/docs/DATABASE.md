# Base de datos (FASE 2)

## Enfoque single database + `tenant_id`

Se usa **una sola base de datos** y columnas `tenant_id` donde aplica (`users`, `roles`). Así el modelo de datos es simple al inicio (un solo tenant sembrado) y puede escalar a SaaS multi-tenant **sin** cambiar a varias bases: basta con filtrar por tenant en consultas y políticas de autorización en fases posteriores. Alternativas futuras (schema por tenant, DB por tenant) son decisiones de infraestructura, no obligatorias con este esquema.

## Tablas

| Tabla | Propósito |
|-------|-----------|
| `tenants` | Empresa/cliente del sistema; `codigo` y `slug` únicos. |
| `users` | Usuarios con `tenant_id`; `usuario` único **por tenant**; `password_hash` (nunca texto plano); `codigo_cliente` opcional; `fecha_alta` y `activo`. |
| `roles` | Roles **dinámicos** por tenant (`tenant_id` + `slug` único por tenant), base RBAC; encajan con `permissions` / `role_permissions` futuras (ver abajo). |
| `user_roles` | Pivote N:N usuario ↔ rol; clave primaria compuesta `(user_id, role_id)`. Base para RBAC; los permisos efectivos del usuario se obtendrán vía roles → permisos cuando existan esas tablas. |
| `refresh_tokens` | Refresh tokens solo como `token_hash`; caducidad y revocación; soft deletes. |
| `user_sessions` | Sesiones de aplicación (UUID, IP, user agent, actividad); invalidación y soft deletes. |

## Por qué roles dinámicos

Los roles viven en tabla para poder **crear y ajustar** perfiles sin desplegar código nuevo (nombres, slugs, descripciones por tenant). La autorización fina (permisos) puede añadirse después sin invalidar este diseño.

## Estrategia RBAC y permisos futuros (sin tablas aún)

**Modelo actual:** usuario → muchos roles (`user_roles`) → (futuro) cada rol → muchos permisos. No se crean ahora `permissions` ni `role_permissions` para no adelantar implementación ni datos vacíos; el diseño actual **no bloquea** añadirlas en una migración posterior.

**Orientación para la migración futura (referencia, no implementada):**

- **`permissions`**: permisos atómicos (p. ej. acción por módulo o vista). Campos típicos: `id`, `tenant_id` nullable si hace falta permisos globales de producto, `slug` único en el ámbito elegido (convención recomendada: `modulo.recurso.accion` o `modulo.vista` para no cerrar puertas a permisos por módulo o por pantalla), `nombre`, `descripcion` nullable, timestamps. Índices por `tenant_id` y `slug` según reglas de unicidad.
- **`role_permissions`**: pivote N:N `role_id` + `permission_id`, PK compuesta o `id` según preferencia, `created_at`. **Un mismo `Role` existente** se relaciona aquí sin cambiar la tabla `roles`.

**Por qué no rehace la base:** `users`, `roles` y `user_roles` permanecen; solo se **añaden** tablas y relaciones Eloquent nuevas. La comprobación “¿puede el usuario X hacer Y?” pasará a unir `user_roles` → `roles` → `role_permissions` → `permissions` (o caché derivada).

**Compatibilidad con slugs de rol:** los `slug` actuales (`super_admin`, `admin`, `user`) son independientes de los `slug` de permisos; no chocan.

## Refresh tokens y sesiones desde el starter

- **`refresh_tokens`**: base para flujos OAuth2/JWT/Sanctum donde el refresh se almacena solo como hash y se puede revocar o purgar por expiración.
- **`user_sessions`**: auditoría e historial de sesiones, cierre remoto y base para **una sesión activa por usuario** aplicando la regla en la capa de aplicación (índices preparados para consultas por `user_id` e `is_active`).

## Tablas Laravel relacionadas

- `sessions`: driver de sesión web de Laravel (no confundir con `user_sessions`).
- `password_reset_tokens`: preparada con `user_id` como clave; el broker de reset por defecto de Laravel asume `email`: al implementar login/recuperación habrá que **adaptar** el repositorio de tokens o el flujo.

## Serialización

Los modelos ocultan `password_hash` y el hash de refresh donde aplica; no exponer datos sensibles en respuestas JSON en fases siguientes.

## Contexto de tenant en runtime

Resolución del tenant activo, middleware y evolución (subdominio, cabeceras, etc.): **`docs/TENANCY.md`**.

## Autenticación API y sesiones

Política de sesión única, refresh, logout, middleware y logs: **`docs/SECURITY_SESSIONS.md`**.

## Administración de usuarios y roles (API)

CRUD de usuarios/roles y asignación: **`docs/USERS_ROLES.md`**.

## Autorización (Gates y roles)

Capacidades `manage-users` / `manage-roles` y evolución a permisos: **`docs/AUTHORIZATION.md`**.

## Contrato OpenAPI (consumo frontend)

Especificación formal, Swagger UI y reglas para el futuro starter-web: **`docs/API_CONTRACT.md`**.
