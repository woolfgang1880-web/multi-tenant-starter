# Auditoría E2E del login (starter-core ↔ starter-web)

Documento corto: diagnóstico del fallo por esquema, corrección esperada y cómo validar el flujo completo.

## Problema detectado

- **Síntoma:** el login falla con SQL `Unknown column 'tenant_id' in 'field list'` al insertar en `user_sessions`.
- **Causa raíz:** el código (`UserSession` / `AuthSessionService`) escribe `tenant_id` en cada sesión activa, pero la base MySQL tenía la tabla `user_sessions` creada por la migración base **sin** esa columna, y **no se habían aplicado** las migraciones posteriores que la añaden (o el entorno quedó desincronizado).

## Esquema esperado

| Origen | Qué hace |
|--------|-----------|
| `0001_01_01_000007_create_user_sessions_table.php` | Crea `user_sessions` **sin** `tenant_id`. |
| `2026_03_26_100000_user_tenants_session_tenant_and_platform_flag.php` | Añade `user_tenants`, `users.is_platform_admin` y **`user_sessions.tenant_id`**. |
| `2026_03_27_000000_ensure_user_sessions_tenant_id_column.php` | **Idempotente:** si falta `tenant_id`, la agrega y rellena desde `users.tenant_id` en filas existentes. |

No es un bug de lógica de aplicación: es **desalineación código ↔ BD** hasta ejecutar migraciones.

## Corrección (mínima, sin hacks)

Desde `starter-core`, con el `.env` apuntando a la misma base que usa la API:

```bash
php artisan migrate
```

Comprobar en MySQL:

```sql
SHOW COLUMNS FROM user_sessions LIKE 'tenant_id';
```

Opcional: `php artisan migrate:status` y verificar que las migraciones anteriores consten como ejecutadas.

## Validación backend (API)

Con datos demo (`php artisan app:setup-demo` si hace falta):

| Caso | `tenant_codigo` | Usuario | Contraseña |
|------|-----------------|---------|--------------|
| Admin principal | `DEFAULT` | `admin_demo` | `Admin123!` |
| Admin otro tenant | `PRUEBAS` | `admin_pruebas` | `AdminPruebas123!` |
| Multi-empresa | `DEFAULT` | `multi_demo` | `MultiDemo123!` |
| Multi-empresa | `PRUEBA1` | `multi_demo` | `MultiDemo123!` |

Flujo: `POST /api/v1/auth/login` → 200, `code: OK`, tokens y `session_uuid` → `GET /api/v1/auth/me` con `Authorization: Bearer …` → `data.tenant.codigo` coincide con el tenant del login y `data.user.roles` reflejan el rol en ese tenant.

**Nota:** el código del tenant secundario es **`PRUEBA1`** (no confundir con **`PRUEBAS`**, que es otro tenant con `admin_pruebas`).

## Validación frontend (starter-web)

- **Base URL:** `VITE_API_BASE_URL` (o valor por defecto del cliente); el login usa `/api/v1/auth/login` y `/api/v1/auth/me` vía `src/api/client.js`.
- **Payload:** `tenant_codigo` (opcional para login global), `usuario`, `password`.
- **Tras login:** si `/me` falla, el cliente **revierte** tokens y usuario almacenados (no deja sesión a medias).
- **Usuarios en menú:** la entrada **Users** depende de roles del usuario en el **tenant activo** devuelto por `/me` (p. ej. `multi_demo` en `PRUEBA1` es rol `user`, sin pantalla de administración de usuarios según reglas actuales).

## Tests automatizados

- **Backend:** `php artisan test tests/Feature/LoginEndToEndStabilityTest.php` (login + `/me` + comprobación de fila `user_sessions` con `tenant_id`).
- **Frontend:** `npm test -- --run` en `starter-web` (incl. `client.test.js`: URL versionada y rollback si `/me` falla).

## Archivos de referencia

- Migraciones: `database/migrations/2026_03_26_100000_*.php`, `2026_03_27_000000_ensure_user_sessions_tenant_id_column.php`
- Estabilidad login (contexto amplio): `docs/LOGIN_STABILITY_PHASE1.md`
- Usuarios demo: `docs/DEMO_USERS.md`

## Estado funcional

Con **migraciones aplicadas** y **seed demo** al día, el login extremo a extremo queda operativo: sesión se persiste con `tenant_id`, `/me` alinea tenant y roles, y el SPA mantiene coherencia o hace rollback si `/me` falla.
