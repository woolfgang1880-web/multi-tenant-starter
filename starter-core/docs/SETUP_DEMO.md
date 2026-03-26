# Comando `app:setup-demo` (estado base reproducible)

## Objetivo

Dejar el proyecto en un **estado demo consistente** sin borrar datos:

- Aplica **solo migraciones pendientes** (`php artisan migrate --force`), equivalente a crear tablas/columnas nuevas cuando faltan.
- Ejecuta **seeders idempotentes** (`TenantSeeder` → `RoleSeeder` → `DemoUserSeeder`).

**No** ejecuta `migrate:fresh`, `migrate:reset` ni elimina filas por defecto.

## Uso

Desde la raíz de `starter-core`:

```bash
php artisan app:setup-demo
```

- Primero corre migraciones pendientes (sin `fresh`).
- Después `db:seed` con `DatabaseSeeder`.

### Solo seeders (sin migraciones)

Útil si la base ya está al día y solo quieres **asegurar** tenants, roles y usuarios demo:

```bash
php artisan app:setup-demo --no-migrate
```

Equivalente directo:

```bash
php artisan db:seed --force
```

## Comportamiento idempotente (importante)

| Componente | Comportamiento |
|------------|----------------|
| **TenantSeeder** | `firstOrCreate` por `codigo` (`DEFAULT`, `PRUEBA1`, `PRUEBAS`). No sobrescribe nombre/slug de tenants ya existentes. **PRUEBA1:** datos de trial demo solo si el tenant es nuevo o `trial_starts_at` sigue en `null`. |
| **RoleSeeder** | Por cada tenant anterior, roles `super_admin`, `admin`, `user` con `firstOrCreate` por `(tenant_id, slug)`. |
| **DemoUserSeeder** | Usuarios demo con `firstOrCreate` por `usuario` (único global); **las contraseñas demo solo se fijan al crear el usuario**. Incluye `multi_demo` en DEFAULT y PRUEBA1 (membresía N:N). Roles: `syncWithoutDetaching`. |

Si necesitas **restaurar contraseñas demo** en usuarios ya creados, hazlo manualmente (por API de usuarios o actualizando el hash en base) o elimina esas filas de `users` y vuelve a ejecutar el seeder.

## Entornos

- En **producción**, migraciones y seed suelen requerir `--force`; el comando ya lo pasa.
- Revisa políticas internas antes de sembrar datos demo en entornos compartidos.

## Referencias

- Credenciales: [DEMO_USERS.md](./DEMO_USERS.md).
- Evolución multi-tenant: [MULTI_TENANT_EVOLUTION.md](./MULTI_TENANT_EVOLUTION.md).
- Usuario global / membresías: [GLOBAL_USER_MEMBERSHIP.md](./GLOBAL_USER_MEMBERSHIP.md).
