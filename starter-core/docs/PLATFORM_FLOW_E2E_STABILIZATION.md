# Plataforma (super admin global) — flujo end-to-end estabilizado

Este documento resume la auditoría del flujo de plataforma en **starter-core ↔ starter-web** para asegurar que funcione extremo a extremo en entorno de desarrollo, antes de agregar nuevas reglas de negocio.

## Objetivo
1. Iniciar sesión como **super admin global**.
2. Ver sección **Plataforma** en `starter-web`.
3. Crear una **empresa/tenant**.
4. Crear el **admin inicial** de esa empresa.
5. Hacer login con el **admin creado**.
6. Validar que la UI/menú del tenant funciona (roles, acceso a `Users`).

## Usuario demo y tenant de desarrollo
- Super admin global: `admin_demo`
- Tenant para login del super admin: `DEFAULT`
- Contraseña demo: `Admin123!`
- Nota: el super admin global vive en `users.is_platform_admin = true` (no depende del tenant activo).

## Casos clave validados
A continuación se validó el comportamiento real en backend con tests E2E y la UI con tests de visibilidad/feedback.

### A) SUPER ADMIN
- Login en `/api/v1/auth/login` en `DEFAULT`.
- `GET /api/v1/auth/me` ahora expone `data.user.is_platform_admin = true`.
- En `starter-web`, el menú **Plataforma** se muestra usando ese flag.
- El super admin puede llamar a los endpoints de plataforma.

### A.1) LISTADO DE EMPRESAS (UX)
- Endpoint: `GET /api/v1/platform/tenants`
- La UI de **Plataforma** carga y muestra el listado (código, nombre, estado) para:
  - validar visualmente qué empresas existen
  - evitar capturar el código “a ciegas” al crear el admin inicial

### B) CREACIÓN DE TENANT
- Endpoint: `POST /api/v1/platform/tenants`
- Se valida que el `codigo` sea único (caso duplicado devuelve `422` con `code: VALIDATION_ERROR`).

### C) CREACIÓN DE ADMIN INICIAL
- Endpoint: `POST /api/v1/platform/tenants/{tenant_codigo}/admins`
- El admin inicial queda con:
  - membresía al tenant creada
  - rol `admin` en ese tenant
  - `is_platform_admin = false` (no es global)

### D) LOGIN DEL ADMIN CREADO
- Login con `tenant_codigo` recién creado y `usuario` del admin inicial.
- `GET /api/v1/auth/me` responde con:
  - `data.tenant.codigo` = tenant creado
  - `data.user.is_platform_admin = false`
  - `data.user.roles` contiene `admin` del tenant activo

### E) USERS (dentro del tenant)
- Como el admin inicial tiene rol `admin`, la UI debe permitir `Users`.
- El menú **Plataforma** debe permanecer oculto (porque `is_platform_admin = false`).

## Problemas encontrados (y corrección aplicada)

1. **Backend /auth/me no devolvía `is_platform_admin`**
   - Impacto: `starter-web` no podía saber si debía mostrar **Plataforma**.
   - Corrección: `starter-core/app/Http/Controllers/Api/V1/Auth/MeController.php` ahora incluye `data.user.is_platform_admin`.

2. **Persistencia frontend faltante**
   - Impacto: aunque el backend se corrigiera, el flag no se guardaba en `localStorage`.
   - Corrección: `starter-web/src/api/client.js` ahora persiste `is_platform_admin` dentro de `syncStoredUserFromMe`.

3. **Seed demo del super admin global**
   - Asegurado: `starter-core/database/seeders/DemoUserSeeder.php` marca `admin_demo` con `is_platform_admin = true` (y fuerza el flag si ya existía el usuario).

## Qué se corrigió (resumen técnico)
- Backend: `/api/v1/auth/me` incluye `is_platform_admin`
- Frontend: `syncStoredUserFromMe` persiste `is_platform_admin`
- Tests:
  - E2E backend del flujo completo
  - Tests de UI (visibilidad de menú Plataforma y creación con feedback)

## Cómo probar end-to-end (manual, recomendado)

### 1) Preparar backend
1. En `starter-core`:
   ```bash
   php artisan app:setup-demo
   ```

### 2) Entrar como super admin en starter-web
1. En `starter-web`, abre la pantalla de login.
2. En el formulario:
   - `Código de empresa`: `DEFAULT`
   - `Usuario`: `admin_demo`
   - `Contraseña`: `Admin123!`
3. Valida que aparezca el menú **Plataforma**.
4. Entra a **Plataforma** y confirma que ves “Empresas existentes” (usa “Actualizar listado”).

### 3) Crear empresa/tenant
En la sección **Plataforma**:
1. Formulario “Crear empresa”:
   - `Nombre`: “Tenant X”
   - `Código`: usa un valor único (ej. `TENANTX`)
   - `Activo`: Sí
2. Ejecuta “Crear empresa”.
3. Confirma que aparece en el listado de “Empresas existentes”.

### 4) Crear admin inicial
En el formulario “Crear admin inicial”:
1. `Código de empresa`: selecciona `TENANTX` del desplegable
2. `Admin usuario`: `admin_tenantx`
3. `Admin contraseña`: una contraseña válida (mínimo 8 chars; ej. `Admin1234!`)
4. Clic en “Crear admin inicial”.

### 4.1) Validaciones UX esperadas
- Si intentas crear una empresa con `Código` repetido, debe mostrar un error claro de duplicado.
- Si intentas crear un admin con `Admin usuario` repetido, debe mostrar un error claro de duplicado.

### 5) Validar login del admin creado
1. Cierra sesión / limpia tokens (o abre una sesión nueva).
2. Regresa al login:
   - `Código de empresa`: `TENANTX`
   - `Usuario`: `admin_tenantx`
   - `Contraseña`: la misma que definiste
3. En el menú:
   - Debe verse **Users**
   - No debe verse **Plataforma**

## Tests que cubren el flujo (automáticos)
- Backend (E2E): `starter-core/tests/Feature/PlatformAdminTenancyTest.php`
- Frontend (UI mínima): tests existentes en `starter-web/src/components/layout` y `starter-web/src/pages`.

## Qué quedó pendiente
- (Opcional) ampliar el E2E browser con más casos (multi-empresa, errores de validación repetidos, etc.).

## E2E browser (Playwright) — sin mocks
- Documentación: `starter-web/docs/E2E_PLATFORM.md`
- Test: `starter-web/e2e/platform-flow.spec.js`

