# Usuarios demo (starter-core)

Este starter incluye usuarios demo listos para login con el modelo multi-tenant actual.

## Estado base reproducible (recomendado)

Sin borrar datos existentes:

```bash
cd starter-core
php artisan app:setup-demo
```

Solo seeders (base ya migrada):

```bash
php artisan app:setup-demo --no-migrate
```

Detalle: [SETUP_DEMO.md](./SETUP_DEMO.md).

## Tenants demo

### DEFAULT

- `codigo`: `DEFAULT`
- `nombre`: `Empresa principal`
- `activo`: `true`

### PRUEBA1 (tenant base adicional)

- `codigo`: `PRUEBA1`
- `nombre`: `PRUEBA1`
- `activo`: `true`

### PRUEBAS (opcional / escenarios extra)

- `codigo`: `PRUEBAS`
- `nombre`: `PRUEBAS`
- `activo`: `true`

## Credenciales demo — tenant DEFAULT

- **Admin**
  - `tenant_codigo`: `DEFAULT`
  - `usuario`: `admin_demo`
  - `password`: `Admin123!`

- **Usuario base**
  - `tenant_codigo`: `DEFAULT`
  - `usuario`: `user_demo`
  - `password`: `User123!`

- **Gestor (rol admin)**
  - `tenant_codigo`: `DEFAULT`
  - `usuario`: `manager_demo`
  - `password`: `Manager123!`

## Usuario multi-empresa (demo)

Mismo login en **DEFAULT** y **PRUEBA1**; roles distintos por empresa (contexto según `tenant_codigo` del login).

- **Usuario:** `multi_demo`
- **Contraseña:** `MultiDemo123!`
- **DEFAULT:** rol `admin`
- **PRUEBA1:** rol `user`

## Credenciales demo — tenant PRUEBA1

- **Admin**
  - `tenant_codigo`: `PRUEBA1`
  - `usuario`: `admin_prueba1`
  - `password`: `AdminPrueba1!`

- **Usuario**
  - `tenant_codigo`: `PRUEBA1`
  - `usuario`: `user_prueba1`
  - `password`: `UserPrueba1!`

## Credenciales demo — tenant PRUEBAS

- **Administrador**
  - `tenant_codigo`: `PRUEBAS`
  - `usuario`: `admin_pruebas`
  - `password`: `AdminPruebas123!`

- **Usuarios normales**
  - `user_pruebas1` / `UserPruebas123!`
  - `user_pruebas2` / `UserPruebas123!`

## Roles asignados (resumen)

| Usuario         | Tenant   | Rol (slug) |
|-----------------|----------|------------|
| `admin_demo`    | DEFAULT  | `admin`    |
| `manager_demo`  | DEFAULT  | `admin`    |
| `user_demo`     | DEFAULT  | `user`     |
| `admin_prueba1` | PRUEBA1  | `admin`    |
| `user_prueba1`  | PRUEBA1  | `user`     |
| `admin_pruebas` | PRUEBAS  | `admin`    |
| `user_pruebas1` | PRUEBAS  | `user`     |
| `user_pruebas2` | PRUEBAS  | `user`     |
| `multi_demo`    | DEFAULT  | `admin`    |
| `multi_demo`    | PRUEBA1  | `user`     |

## Modelo de usuario (tabla `users`)

No hay columnas `email` ni `name`: solo `usuario`, `codigo_cliente`, `fecha_alta`, `activo`, etc.

**Unicidad:** el campo `usuario` es **único en toda la plataforma** (no solo dentro del tenant). Dos empresas no pueden tener el mismo login; ver `docs/MULTI_TENANT_EVOLUTION.md`.

## Trial (tenant PRUEBA1)

`TenantSeeder` (y `DemoUserSeeder` si se ejecuta solo) rellenan datos de trial en `PRUEBA1` cuando el tenant es nuevo o `trial_starts_at` sigue vacío. El **bloqueo por vencimiento** se implementará en una fase posterior; hoy son datos preparatorios.

## Cómo aplicar solo el seeder de demos

```bash
php artisan db:seed --class=DemoUserSeeder
```

O el seeder completo del proyecto:

```bash
php artisan db:seed
```

Los seeders son **idempotentes**: no eliminan datos arbitrarios; crean filas conocidas si faltan.

**Contraseñas:** en **`APP_ENV=local`**, por defecto (`config/demo.php` → `reset_demo_passwords_on_seed`) el `DemoUserSeeder` **vuelve a aplicar** las contraseñas en texto plano documentadas aquí en cada seed, para que el login demo siga funcionando aunque la BD ya tuviera usuarios creados antes. En **`APP_ENV=testing`** y producción esto está desactivado salvo que definas explícitamente `DEMO_RESET_DEMO_PASSWORDS=true` en `.env` (útil solo en entornos controlados).

## Cómo probar login y auth/me

### 1) Login

```bash
curl -X POST "http://127.0.0.1:8000/api/v1/auth/login" \
  -H "Content-Type: application/json" \
  -d "{\"tenant_codigo\":\"PRUEBA1\",\"usuario\":\"admin_prueba1\",\"password\":\"AdminPrueba1!\"}"
```

Debe responder `200` con `data.access_token` y `data.refresh_token`.

### 2) auth/me

Incluye `user.roles` para autorización UX en el frontend:

```bash
curl "http://127.0.0.1:8000/api/v1/auth/me" \
  -H "Authorization: Bearer <ACCESS_TOKEN>"
```
