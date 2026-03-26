# Demo tenant PRUEBAS y vista Users (starter-web)

## Problema resuelto

La sesión guardada en el navegador debe incluir **`roles` devueltos por `GET /api/v1/auth/me`** para que `canViewUsers()` en el frontend pueda mostrar el menú y la ruta **Users**. Sin esos datos en la respuesta y en `localStorage`, la UI no mostraba gestión de usuarios aunque el backend tuviera permisos.

## Qué hacer en backend

1. Sembrar datos: `php artisan db:seed --class=DemoUserSeeder` (desde `starter-core`).
2. Ver `starter-core/docs/DEMO_USERS.md` para credenciales PRUEBAS.

## Qué hacer en frontend

1. Tras login o arranque con token, el cliente guarda `roles` (y `codigo_cliente` si aplica) según `auth/me`.
2. Entrar con **PRUEBAS · admin** en el login (relleno rápido) o manualmente:
   - Tenant: `PRUEBAS`
   - Usuario: `admin_pruebas`
   - Contraseña: `AdminPruebas123!`

## Validación manual

1. Iniciar sesión como `admin_pruebas` / tenant `PRUEBAS`.
2. Debe verse el ítem de menú **Users** (o equivalente según copy).
3. Abrir Users: listado debe incluir al menos `admin_pruebas`, `user_pruebas1` y `user_pruebas2`.
4. Cerrar sesión e iniciar como `user_pruebas1`: el menú Users no debe mostrarse (rol `user`).

## Reglas

- La autorización real sigue en el API; la UI solo refleja roles/permisos conocidos en sesión.
- No hay email en el modelo de usuario: la tabla usa `usuario` y `codigo_cliente`.
