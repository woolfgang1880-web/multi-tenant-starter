# Plataforma: empresas y visibilidad del admin

## Qué se detectó

- `POST /api/v1/platform/tenants` y `POST .../admins` funcionaban: la empresa y el usuario admin se creaban con `tenant_id`, membresía `user_tenants` y rol `admin` en `user_roles`.
- El listado `GET /api/v1/platform/tenants` **no** devolvía ningún dato del usuario administrador, así que en la UI de Plataforma no quedaba claro **quién** era el admin de cada empresa tras crearlo.

## Cómo quedó resuelto

### Backend

- En `PlatformTenantProvisioningService::listTenantsForPlatform()`, cada ítem incluye **`initial_admin`** (o `null`):
  - Se toma el **primer usuario con rol `admin`** en ese tenant en orden de **`users.id` ascendente** (en la práctica suele coincidir con el admin inicial creado desde Plataforma).
  - Campos expuestos: `id`, `usuario`, `codigo_cliente`, `activo` (sin contraseña ni datos sensibles).

### Frontend (Plataforma)

- Tabla **Empresas existentes**: nueva columna **Admin (rol)**.
  - Si hay admin: muestra **usuario** en monoespaciado, **código cliente** si existe, y **(inactivo)** si el usuario está desactivado.
  - Si no hay ningún usuario con rol `admin` en ese tenant: texto **Sin admin**.
- Tras **crear admin inicial** con éxito, se llama a **`refreshTenants()`** para que el listado se actualice y se vea el admin sin pulsar “Actualizar listado”.
- Textos del hero y del card aclaran que la columna refiere al rol **administrador** (no a super admin de plataforma).

## Cómo probar desde Plataforma

1. Inicia sesión con un usuario **`is_platform_admin`**.
2. Abre **Plataforma** (`#/platform`).
3. Crea una empresa nueva o elige una sin admin en la columna **Admin (rol)** (debe decir **Sin admin**).
4. En **Crear admin inicial**, elige esa empresa, completa usuario/contraseña y envía.
5. Comprueba que la misma fila pasa a mostrar el **usuario** (y código cliente si lo indicaste) **sin recargar manualmente** el listado (opcionalmente pulsa “Actualizar listado” para forzar otra petición).

## Tests

- **Backend:** `php artisan test tests/Feature/PlatformTenantsListInitialAdminTest.php`
- **Frontend:** `npm run test:run -- src/pages/PlatformAdminPage.test.jsx` (en `starter-web`)

## Pendiente (no implementado)

- **Edición** del admin inicial o gestión amplia de usuarios por tenant desde Plataforma (fuera de alcance; seguiría usando flujos normales de usuarios dentro del tenant o nuevos endpoints dedicados).
- Un botón “Ver detalle” solo aportaría lo mismo que la columna; si hace falta **editar** usuario/código cliente, conviene un paso de producto aparte con permisos y auditoría.
